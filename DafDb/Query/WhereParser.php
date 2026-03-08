<?php
namespace DafDb\Query;

use DafGlobals\Dates\DateOnly;

/**
 * WhereParser
 *
 * Parses a PHP lambda/closure like:
 *   fn($x) => $x->Price > 500 && str_contains($x->Category, "smart")
 *
 * Into:
 *   [
 *     'sql'    => '((`t0`.`Price` > :p0) AND (`t0`.`Category` LIKE :p1))',
 *     'params' => [':p0' => 500, ':p1' => '%smart%']
 *   ]
 *
 * Notes:
 * - Keeps your current user syntax (no Eq()/And()).
 * - Correct precedence + parentheses via shunting-yard (RPN).
 * - Supports:
 *   - $x->Field
 *   - literals: "a", 'a', 123, 12.3, true/false/null
 *   - operators: === == != <> < > <= >= && || !
 *   - parentheses
 *   - functions: str_contains/str_starts_with/str_ends_with (col, value)
 *   - captured static vars: $needle, $obj->Prop, $arr['key'] (as in your current helpers)
 */
final class WhereParser
{
    /**
     * @param callable $predicate  The Where(fn($x)=>...) closure
     * @param string|null $tableAlias  If provided, columns compile to `alias`.`Col`, else `Col`
     * @return array{query:string, params:array<string,mixed>}
     */
    public static function Parse(callable $predicate, ?string $tableAlias = null, array $externals = []): array
    {
        $ref = new \ReflectionFunction($predicate);

        $paramName  = $ref->getParameters()[0]->getName();
        $staticVars = $ref->getStaticVariables();

        // externals override / extend
        $vars = array_merge($staticVars, $externals);

        $expr   = self::extractLambdaExpr($ref);
        $tokens = self::tokenizeExpr($expr, $paramName);
        $rpn    = self::toRpn($tokens);

        return self::compileRpnToSql($rpn, $vars, $tableAlias);
    }


    // ---------------- Extraction ----------------

    private static function extractLambdaExpr(\ReflectionFunction $ref): string
    {
        $start  = $ref->getStartLine();
        $end    = $ref->getEndLine();
        $lines  = array_slice(file($ref->getFileName()), $start - 1, ($end - $start + 1));
        $markup = implode("", $lines);

        // 1) Classic closure: function (...) { return EXPR; }
        if (preg_match('/return\s+(.*)\s*;\s*}/s', $markup, $m)) {
            return trim($m[1]);
        }

        // 2) Arrow fn: fn(...) => EXPR
        $pos = strpos($markup, '=>');
        if ($pos === false) {
            throw new \RuntimeException("Cannot find '=>' for arrow function.");
        }

        $i = $pos + 2; // after =>
        // skip whitespace
        $len = strlen($markup);
        while ($i < $len && ctype_space($markup[$i])) $i++;

        $expr = self::readExprUntilTerminator($markup, $i);
        return trim($expr);
    }

    /**
     * Reads expression from $start until it hits a terminator at depth 0.
     * Terminators: ')', ',', ';', '}'.
     * Correctly skips terminators inside strings and nested ()/[]/{}.
     */
    private static function readExprUntilTerminator(string $s, int $start): string
    {
        $len = strlen($s);
        $depthParen = 0;
        $depthBrack = 0;
        $depthBrace = 0;

        $inStr = false;
        $strCh = '';
        $escape = false;

        $out = '';

        for ($i = $start; $i < $len; $i++) {
            $ch = $s[$i];

            if ($inStr) {
                $out .= $ch;
                if ($escape) { $escape = false; continue; }
                if ($ch === '\\') { $escape = true; continue; }
                if ($ch === $strCh) { $inStr = false; $strCh = ''; }
                continue;
            }

            if ($ch === '"' || $ch === "'") {
                $inStr = true;
                $strCh = $ch;
                $out .= $ch;
                continue;
            }

            // nesting
            if ($ch === '(') { $depthParen++; $out .= $ch; continue; }
            if ($ch === ')') {
                // If we are at depth 0, this ')' belongs to the outer call (e.g. Where(...))
                if ($depthParen === 0 && $depthBrack === 0 && $depthBrace === 0) break;
                $depthParen--;
                $out .= $ch;
                continue;
            }

            if ($ch === '[') { $depthBrack++; $out .= $ch; continue; }
            if ($ch === ']') { $depthBrack--; $out .= $ch; continue; }

            if ($ch === '{') { $depthBrace++; $out .= $ch; continue; }
            if ($ch === '}') {
                // terminator at depth 0
                if ($depthParen === 0 && $depthBrack === 0 && $depthBrace === 0) break;
                $depthBrace--;
                $out .= $ch;
                continue;
            }

            // terminators at depth 0
            if (($ch === ',' || $ch === ';') && $depthParen === 0 && $depthBrack === 0 && $depthBrace === 0) {
                break;
            }

            $out .= $ch;
        }

        return $out;
    }


    // ---------------- Tokenize ----------------

    /**
     * Tokenizes a controlled subset of expressions.
     * @return string[]
     */
    private static function tokenizeExpr(string $expr, string $paramName): array
    {
        $p = preg_quote($paramName, '~');

        // Use a template and inject $p via sprintf so we avoid "$$p" interpolation bugs
        $tpl = <<<'REGEX'
    ~(\s+)                                               # 1 whitespace
    |(\$%s->\w+)                                         # 2 $param->Field (column)
    |(===|==|!=|<>|<=|>=|&&|\|\||<|>|!)                   # 3 operators
    |(::)                                                # 4 static access
    |(\()|(\))|(,)                                       # 5/6/7 parens, comma
    |(\[(?:\s*(?:"(?:\\\\.|[^"\\\\])*"|'(?:\\\\.|[^'\\\\])*'|\d+\.\d+|\d+|true|false|null)\s*(?:,\s*(?:"(?:\\\\.|[^"\\\\])*"|'(?:\\\\.|[^'\\\\])*'|\d+\.\d+|\d+|true|false|null)\s*)*)?\])  # 8 array literal
    |("(?:\\\\.|[^"\\\\])*")                             # 9 double-quoted string
    |('(?:\\\\.|[^'\\\\])*')                             # 10 single-quoted string
    |(\btrue\b|\bfalse\b|\bnull\b)                        # 11 keywords
    |(\d+\.\d+|\d+)                                      # 12 numbers
    |(\$[A-Za-z_]\w*(?:->\w+\(\)|->\w+|\[[^\]]+\])?)      # 13 captured: $var, $obj->x, $obj->M(), $arr["k"]
    |([A-Za-z_]\w*)                                      # 14 identifiers
    ~x
    REGEX;

        $pattern = sprintf($tpl, $p);

        $ok = preg_match_all($pattern, $expr, $m, PREG_SET_ORDER);
        if ($ok === false) {
            throw new \RuntimeException("Where tokenize failed: " . preg_last_error_msg());
        }

        $out = [];
        foreach ($m as $t) {
            if (!empty($t[1])) continue; // whitespace

            // group 2: $param->Field => COL:Field
            if (!empty($t[2])) {
                $field = explode('->', $t[2], 2)[1];
                $out[] = 'COL:' . $field;
                continue;
            }

            // pick first non-empty group among [3..13]
            for ($i = 3; $i < 15; $i++) {
                if (isset($t[$i]) && $t[$i] !== '' && $t[$i] !== null) {
                    $out[] = $t[$i];
                    break;
                }
            }
        }

        return $out;
    }


    // ---------------- RPN (shunting-yard) ----------------

    /**
     * @param string[] $tokens
     * @return string[]
     */
    private static function toRpn(array $tokens): array
    {
        $prec = [
            '!'  => 4,
            '==='=>3,'=='=>3,'!='=>3,'<>'=>3,'<'=>3,'>'=>3,'<='=>3,'>='=>3,
            '&&' => 2,
            '||' => 1,
        ];
        $rightAssoc = ['!'=>true];

        $out = [];
        $stack = [];
        $funcStack = [];

        for ($i=0; $i<count($tokens); $i++) {
            $tok = $tokens[$i];

            if ($tok === '(') {
                $prev  = $tokens[$i-1] ?? null;
                $prev2 = $tokens[$i-2] ?? null; // maybe ::
                $prev3 = $tokens[$i-3] ?? null; // maybe Class

                // --------- Disallow captured object method calls WITH ARGS ----------
                // Example: $obj->Foo($u->Username)
                // Token stream looks like: "$obj->Foo", "(", "COL:Username", ")"
                // We do NOT translate this to SQL (not safe / not deterministic).
                if ($prev && str_starts_with($prev, '$') && str_contains($prev, '->') && !str_ends_with($prev, '()')) {
                    throw new \RuntimeException(
                        "Where cannot translate method calls with arguments: {$prev}(...). " .
                        "Only zero-arg captured calls are supported like \$obj->GetDate()."
                    );
                }

                // --------- Static call: Class :: Method ( ... ) ----------
                if ($prev && $prev2 === '::' && $prev3
                    && preg_match('/^[A-Za-z_]\w*$/', $prev3)
                    && preg_match('/^[A-Za-z_]\w*$/', $prev)
                ) {
                    $funcStack[] = "STATIC:{$prev3}::{$prev}";

                    // remove "Class", "::", "Method" if they were already emitted as value tokens
                    while (!empty($out) && (end($out) === $prev || end($out) === '::' || end($out) === $prev3)) {
                        array_pop($out);
                    }

                    $stack[] = $tok;
                    continue;
                }

                // --------- Normal function: ident( ... ) ----------
                if ($prev && preg_match('/^[A-Za-z_]\w*$/', $prev)) {
                    $funcStack[] = $prev;

                    // IMPORTANT: remove function name token from output if present
                    if (!empty($out) && end($out) === $prev) {
                        array_pop($out);
                    }
                }

                $stack[] = $tok;
                continue;
            }


            if ($tok === ')') {
                while ($stack && end($stack) !== '(') $out[] = array_pop($stack);
                if (!$stack) throw new \RuntimeException("Mismatched ')'");
                array_pop($stack); // pop '('

                if ($funcStack) {
                    $out[] = 'CALL:' . array_pop($funcStack);
                }
                continue;
            }

            if ($tok === ',') {
                while ($stack && end($stack) !== '(') $out[] = array_pop($stack);
                continue;
            }

            if (isset($prec[$tok])) {
                while ($stack) {
                    $top = end($stack);
                    if (!isset($prec[$top])) break;

                    $pTop = $prec[$top];
                    $pTok = $prec[$tok];

                    if (($rightAssoc[$tok] ?? false) ? ($pTok < $pTop) : ($pTok <= $pTop)) {
                        $out[] = array_pop($stack);
                    } else break;
                }
                $stack[] = $tok;
                continue;
            }

            // value token
            $out[] = $tok;
        }

        while ($stack) {
            $op = array_pop($stack);
            if ($op === '(') throw new \RuntimeException("Mismatched '('");
            $out[] = $op;
        }

        return $out;
    }

    // ---------------- Compile ----------------

    /**
     * @param string[] $rpn
     * @param array<string,mixed> $staticVars
     * @return array{sql:string, params:array<string,mixed>}
     */
    private static function compileRpnToSql(array $rpn, array $staticVars, ?string $alias): array
    {
        $stack = [];
        $params = [];
        $pIndex = 0;

        $colSql = function(string $field) use ($alias) {
            return $alias ? "`{$alias}`.`{$field}`" : "`{$field}`";
        };

        foreach ($rpn as $tok) {

            // $x->Field (column)
            if (str_starts_with($tok, 'COL:')) {
                $field = substr($tok, 4);
                $stack[] = ['kind'=>'expr', 'sql'=>$colSql($field)];
                continue;
            }


            // function call marker
            if (str_starts_with($tok, 'CALL:')) {

                // ---------- STATIC call: CALL:STATIC:DateOnly::FromString ----------
                if (str_starts_with($tok, 'CALL:STATIC:')) {
                    $sig = substr($tok, strlen('CALL:STATIC:')); // e.g. "DateOnly::FromString"

                    // One-arg static calls only (for now)
                    $arg1 = array_pop($stack);
                    if (!$arg1) throw new \RuntimeException("Invalid static call in Where().");

                    if (($arg1['kind'] ?? '') !== 'val') {
                        throw new \RuntimeException("Static call {$sig} requires a literal/captured value argument.");
                    }

                    $ph = $arg1['sql'];
                    $raw = $params[$ph];

                    // whitelist only what you want
                    if ($sig === 'DateOnly::FromString') {
                        // Use your real class namespace if needed:
                        // $d = \DafGlobals\Dates\DateOnly::FromString((string)$raw);
                        $d = DateOnly::FromString((string)$raw);

                        // normalize to DB binding value (string)
                        $params[$ph] = (string)$d;

                        // return same placeholder as a value
                        $stack[] = ['kind'=>'val', 'sql'=>$ph];
                        continue;
                    }

                    throw new \RuntimeException("Unsupported static call in Where(): {$sig}");
                }

                // ---------- existing normal call ----------
                $fname = strtolower(substr($tok, 5));

                $arg2 = array_pop($stack);
                $arg1 = array_pop($stack);

                if (!$arg1 || !$arg2) throw new \RuntimeException("Invalid function call in Where().");

                if (!in_array($fname, ['str_contains','str_starts_with','str_ends_with','in_array'], true)) {
                    throw new \RuntimeException("Unsupported function in Where(): {$fname}");
                }

                // in_array(col, [..])  =>  col IN (:p1,:p2,...)
                if ($fname === 'in_array') {
                    if (($arg1['kind'] ?? '') !== 'expr') {
                        throw new \RuntimeException("First arg of in_array must be a column, like in_array(\$u->Id, ...)");
                    }
                    if (($arg2['kind'] ?? '') !== 'val') {
                        throw new \RuntimeException("Second arg of in_array must be an array literal or captured array.");
                    }

                    $phArr = $arg2['sql'];
                    $arrVal = $params[$phArr] ?? null;

                    if (!is_array($arrVal)) {
                        throw new \RuntimeException("Second arg of in_array must resolve to an array.");
                    }

                    // remove the array placeholder (we will expand it)
                    unset($params[$phArr]);

                    if (count($arrVal) === 0) {
                        $stack[] = ['kind'=>'expr', 'sql'=>'(0=1)']; // empty IN => always false
                        continue;
                    }

                    $placeholders = [];
                    foreach ($arrVal as $v) {
                        $ph = ':p' . $pIndex++;
                        $params[$ph] = $v;
                        $placeholders[] = $ph;
                    }

                    $stack[] = ['kind'=>'expr', 'sql'=>'(' . $arg1['sql'] . ' IN (' . implode(',', $placeholders) . '))'];
                    continue;
                }


                if (($arg2['kind'] ?? '') !== 'val') {
                    throw new \RuntimeException("Second arg of {$fname} must be a literal/captured value.");
                }

                $ph = $arg2['sql'];
                $val = (string)$params[$ph];

                $params[$ph] = match ($fname) {
                    'str_starts_with' => $val . '%',
                    'str_ends_with'   => '%' . $val,
                    'str_contains'    => '%' . $val . '%',
                };

                $stack[] = ['kind'=>'expr', 'sql'=>'(' . $arg1['sql'] . ' LIKE ' . $ph . ')'];
                continue;
            }

            // operators
            if (in_array($tok, ['===','==','!=','<>','<','>','<=','>=','&&','||','!'], true)) {
                if ($tok === '!') {
                    $a = array_pop($stack);
                    if (!$a) throw new \RuntimeException("Invalid unary '!' usage in Where().");
                    $stack[] = ['kind'=>'expr', 'sql'=>'(NOT ' . $a['sql'] . ')'];
                    continue;
                }

                $b = array_pop($stack);
                $a = array_pop($stack);
                if (!$a || !$b) {
                    throw new \RuntimeException(
                        "Invalid binary operator usage in Where(). Op={$tok}. RPN=" . json_encode($rpn)
                    );
                }


                $op = match ($tok) {
                    '===', '==' => '=',
                    '&&'        => 'AND',
                    '||'        => 'OR',
                    default     => $tok
                };

                $logical = ($op === 'AND' || $op === 'OR');

                // ---------- NULL handling: = NULL => IS NULL, != NULL => IS NOT NULL ----------
                if (!$logical && ($op === '=' || $op === '!=' || $op === '<>')) {
                    $aIsNull = self::isNullVal($a, $params);
                    $bIsNull = self::isNullVal($b, $params);

                    if ($aIsNull || $bIsNull) {
                        $exprSide = $aIsNull ? $b : $a;

                        // remove the bound null placeholder
                        $nullSide = $aIsNull ? $a : $b;
                        unset($params[$nullSide['sql']]);

                        $isNot = ($op === '!=' || $op === '<>');
                        $stack[] = ['kind'=>'expr', 'sql'=>'(' . $exprSide['sql'] . ($isNot ? ' IS NOT NULL' : ' IS NULL') . ')'];
                        continue;
                    }
                }

                // ---------- attach comparison meta (for BETWEEN optimization) ----------
                $cmpOps = ['<','>','<=','>='];
                if (!$logical && in_array($op, $cmpOps, true)) {

                    // normalize "val OP col" to "col REVOP val"
                    $left = $a;
                    $right = $b;
                    $op2 = $op;

                    if (($left['kind'] ?? '') === 'val' && ($right['kind'] ?? '') === 'expr') {
                        $left = $b;
                        $right = $a;
                        $op2 = self::reverseCmpOp($op);
                    }

                    $sqlExpr = '(' . $left['sql'] . ' ' . $op2 . ' ' . $right['sql'] . ')';

                    $meta = null;
                    if (($left['kind'] ?? '') === 'expr' && ($right['kind'] ?? '') === 'val') {
                        $meta = [
                            'type' => 'cmp',
                            'col'  => $left['sql'],
                            'op'   => $op2,
                            'ph'   => $right['sql'],
                        ];
                    }

                    $stack[] = ['kind'=>'expr', 'sql'=>$sqlExpr, 'meta'=>$meta];
                    continue;
                }

                // ---------- BETWEEN optimization: (col >= a) AND (col <= b) ----------
                if ($op === 'AND') {
                    $m1 = $a['meta'] ?? null;
                    $m2 = $b['meta'] ?? null;

                    if (($m1['type'] ?? null) === 'cmp' && ($m2['type'] ?? null) === 'cmp' && $m1['col'] === $m2['col']) {
                        $low = null; $high = null;

                        if ($m1['op'] === '>=' ) $low = $m1;
                        if ($m1['op'] === '<=' ) $high = $m1;
                        if ($m2['op'] === '>=' ) $low = $low ?? $m2;
                        if ($m2['op'] === '<=' ) $high = $high ?? $m2;

                        if ($low && $high && $low['op'] === '>=' && $high['op'] === '<=') {
                            $stack[] = [
                                'kind'=>'expr',
                                'sql'=>'(' . $m1['col'] . ' BETWEEN ' . $low['ph'] . ' AND ' . $high['ph'] . ')'
                            ];
                            continue;
                        }
                    }
                }

                // fallback normal
                $stack[] = ['kind'=>'expr', 'sql'=>'(' . $a['sql'] . ' ' . $op . ' ' . $b['sql'] . ')'];
                continue;
            }

            // literal / captured var / keyword / number
            $val = self::resolveValueToken($tok, $staticVars);

            $ph = ':p' . $pIndex++;
            $params[$ph] = $val;
            $stack[] = ['kind'=>'val', 'sql'=>$ph];
        }

        if (count($stack) !== 1) {
            throw new \RuntimeException("Where parse failed: expression stack not balanced.");
        }

        return ['query' => $stack[0]['sql'], 'params' => $params];
    }

    /**
     * Resolves a token into a PHP value.
     * Supports:
     *  - "text", 'text'
     *  - numbers
     *  - true/false/null
     *  - $captured, $obj->Prop, $arr['key']
     */
    private static function resolveValueToken(string $tok, array $staticVars): mixed
    {
        $l = strtolower($tok);
        if ($l === 'true') return true;
        if ($l === 'false') return false;
        if ($l === 'null') return null;

        // array literal: [1,2,"a"]
        if ($tok !== '' && $tok[0] === '[' && str_ends_with($tok, ']')) {
            return self::parseArrayLiteral($tok, $staticVars);
        }

        // strings
        if ($tok !== '' && ($tok[0] === '"' || $tok[0] === "'")) {
            $q = $tok[0];
            $s = substr($tok, 1, -1);
            $s = str_replace('\\'.$q, $q, $s);
            $s = str_replace('\\\\', '\\', $s);
            return $s;
        }

        // numbers
        if (is_numeric($tok)) {
            return str_contains($tok, '.') ? (float)$tok : (int)$tok;
        }

        // $obj->Method()
        if ($tok[0] === '$' && str_contains($tok, '->') && str_ends_with($tok, '()')) {
            $tok2 = substr($tok, 0, -2); // remove ()
            $obj = self::getObjectAccess($tok2);
            if ($obj && isset($staticVars[$obj['object']])) {
                $target = $staticVars[$obj['object']];
                $m = $obj['field'];
                if (!is_object($target) || !method_exists($target, $m)) {
                    throw new \RuntimeException("Method {$m} not found on captured object \${$obj['object']}");
                }
                return $target->$m();
            }
        }

        // captured vars: $x, $obj->a, $arr['k']
        if ($tok !== '' && $tok[0] === '$') {

            $obj = self::getObjectAccess($tok);
            if ($obj && isset($staticVars[$obj['object']])) {
                return $staticVars[$obj['object']]->{$obj['field']};
            }

            $arr = self::getArrayAccess($tok);
            if ($arr && isset($staticVars[$arr['object']])) {
                return $staticVars[$arr['object']][$arr['field']] ?? null;
            }

            $name = substr($tok, 1);
            if (array_key_exists($name, $staticVars)) return $staticVars[$name];
        }

        throw new \RuntimeException("Unsupported token in Where(): {$tok}");
    }

    private static function getObjectAccess(string $token): ?array
    {
        if (!str_contains($token, '->')) return null;
        if ($token[0] !== '$') return null;

        $arr = explode('->', $token, 2);
        return ['object' => substr($arr[0], 1), 'field' => $arr[1]];
    }

    private static function getArrayAccess(string $token): ?array
    {
        if (!str_contains($token, '[')) return null;
        if ($token[0] !== '$') return null;

        $arr = explode('[', $token, 2);
        $obj = substr($arr[0], 1);

        $key = rtrim($arr[1], ']');
        $key = trim($key);
        $key = trim($key, "\"'");

        return ['object' => $obj, 'field' => $key];
    }

    private static function reverseCmpOp(string $op): string
    {
        return match ($op) {
            '<'  => '>',
            '>'  => '<',
            '<=' => '>=',
            '>=' => '<=',
            default => $op,
        };
    }

    private static function parseArrayLiteral(string $tok, array $staticVars): array
    {
        // tok is like: [1, "a", true]
        $inner = trim(substr($tok, 1, -1));
        if ($inner === '') return [];

        $items = [];
        $cur = '';
        $inStr = false;
        $strCh = '';
        $escape = false;

        for ($i=0; $i<strlen($inner); $i++) {
            $ch = $inner[$i];

            if ($inStr) {
                $cur .= $ch;
                if ($escape) { $escape = false; continue; }
                if ($ch === '\\') { $escape = true; continue; }
                if ($ch === $strCh) { $inStr = false; $strCh = ''; }
                continue;
            }

            if ($ch === '"' || $ch === "'") {
                $inStr = true;
                $strCh = $ch;
                $cur .= $ch;
                continue;
            }

            if ($ch === ',') {
                $items[] = trim($cur);
                $cur = '';
                continue;
            }

            $cur .= $ch;
        }

        if (trim($cur) !== '') $items[] = trim($cur);

        // resolve each element using existing value resolver
        $out = [];
        foreach ($items as $it) {
            $out[] = self::resolveValueToken($it, $staticVars);
        }
        return $out;
    }

    private static function isNullVal(array $node, array $params): bool
    {
        return ($node['kind'] ?? '') === 'val'
            && isset($node['sql'])
            && array_key_exists($node['sql'], $params)
            && $params[$node['sql']] === null;
    }

}