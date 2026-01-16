<?php
namespace DafCore;


class CParser {
    const CharNone = "\0";
    static array $abc_chars = [
        'a' => 1,'b' => 1,'c' => 1,'d' => 1,'e' => 1,'f' => 1,'g' => 1,'h' => 1,'i' => 1,'j' => 1,'k' => 1,'l' => 1,'m' => 1,'n' => 1,'o' => 1,'p' => 1,'q' => 1,'r' => 1,'s' => 1,'t' => 1,'u' => 1,'v' => 1,'w' => 1,'x' => 1,'y' => 1,'z' => 1,
        'A' => 1,'B' => 1,'C' => 1,'D' => 1,'E' => 1,'F' => 1,'G' => 1,'H' => 1,'I' => 1,'J' => 1,'K' => 1,'L' => 1,'M' => 1,'N' => 1,'O' => 1,'P' => 1,'Q' => 1,'R' => 1,'S' => 1,'T' => 1,'U' => 1,'V' => 1,'W' => 1,'X' => 1,'Y' => 1,'Z' => 1,
    ];
    static array $numbers_chars = [
        '0' => 1,'1' => 1,'2' => 1,'3' => 1,'4' => 1,'5' => 1,'6' => 1,'7' => 1,'8' => 1,'9'
    ];

    public static int $TextLength;
    public static string $Text;
    public static int $CurrentPos;
    public static string $CurrentChar;

    static function MakeComponents(string $text) : array {
        self::$Text = $text;
        self::$TextLength = strlen(self::$Text);
        self::$CurrentPos = -1;
        self::$CurrentChar = self::CharNone;
        self::Advance();

        $result = [];
        $string_state = ['isOn' => false, 'char' => self::CharNone];
        while (self::$CurrentChar != self::CharNone) {
            $isString = $string_state['isOn'];
            if(self::$CurrentChar === "`" || self::$CurrentChar === "'" || self::$CurrentChar === '"'){
                $char = $string_state['char']; 
                if($char === self::CharNone || self::$CurrentChar === $char){
                    $string_state['isOn'] = !$string_state['isOn'];
                    $string_state['char'] = self::$CurrentChar; 
                }
                self::Advance();
                continue;
            }

            if(!$isString && self::$CurrentChar === "<"){
                self::Advance();
                if(ctype_upper(self::$CurrentChar)){
                    $res = self::ParseComponent();
                    if($res !== null){
                        $result[] = $res;
                    }
                }         
            }
            self::Advance();
        }

        return $result;
    }

    private static function ParseComponent() : mixed {

        $markupStartPos = self::$CurrentPos - 1;
        $name = "";
        while (isset(self::$abc_chars[self::$CurrentChar]) || isset(self::$numbers_chars[self::$CurrentChar]) || self::$CurrentChar === "\\") {
           $name .= self::$CurrentChar;
           self::Advance();
        }
        self::SkipSpace();

        $props_str = '';
        if(isset(self::$abc_chars[self::$CurrentChar])){
            // read props from this markup  <Alert Msg="You are <not> authorized...!!!" />
            while (self::$CurrentChar != ">" && self::$CurrentChar != "/") {
                if(self::$CurrentChar === '"'){
                    $props_str .= '"'.self::ParseString(). '"';
                }
                else $props_str .= self::$CurrentChar;
                self::Advance();
            }
        }
        $props = [];
        if(!empty($props_str)){
            $props = self::getPropsFromString($props_str);
        }

        if(self::$CurrentChar == '/'){
            self::Advance();
            self::SkipSpace();
            if(self::$CurrentChar == '>'){
                self::Advance();
                $markupEndPos = self::$CurrentPos;
                $markup = substr(self::$Text, $markupStartPos, $markupEndPos - $markupStartPos);
                //echo htmlspecialchars($markup) . "<br>";
                //return new Component($name, $props, '', $markup);
                return ['start' => $markupStartPos, 'end' => $markupEndPos, 'component' => new Component($name, $props, '', $markup)];
            }
        } else if(self::$CurrentChar == '>'){
            self::Advance();
            $startContent = self::$CurrentPos;

            $name_len = strlen($name);
            $closeTag = 1;
            while(self::$CurrentChar !== CParser::CharNone && $closeTag > 0){
                if(self::$CurrentChar === '<'){

                    if(self::PeekWord($name_len+1) === "<".$name){
                        $closeTag++;
                    }
                    if(self::PeekWord($name_len+2) === "</".$name){
                        $closeTag--;
                        if($closeTag === 0) break;
                    }
                }
                self::Advance();
            }
            $endContent = self::$CurrentPos;
            self::SkipSpace();
            self::Advance();
            self::Advance($name_len+2);

            $markupContent = substr(self::$Text, $startContent, $endContent - $startContent);
            //echo htmlspecialchars($markupContent) ."<br>";

            $markupEndPos = self::$CurrentPos;
            $markup = substr(self::$Text, $markupStartPos, $markupEndPos - $markupStartPos);

            //echo htmlspecialchars($markup) ."<br>";
            return ['start' => $markupStartPos, 'end' => $markupEndPos, 'component' => new Component($name, $props, $markupContent, $markup)];
        }

        return null;
    }


   static private function formatValue($value)
   {
       if(is_string($value)){
   
           if(is_numeric($value)){
               // Check if the numeric string is an integer or float
               if(floor($value) != $value){
                   // It's a float, so return as float
                   return floatval($value);
               } else {
                   // It's an integer, so return as int
                   return intval($value);
               }
           } elseif(strtolower($value) === 'true') {
               // It's a boolean true, so return as bool
               return true;
           } elseif(strtolower($value) === 'false') {
               // It's a boolean false, so return as bool
               return false;
           }

           // Remove quotes from start and end of string
           $value = trim($value, '\'"');
       }
       // It's not a string, or it's a string that doesn't represent a number or boolean, so return as is
       return $value;
   }
    private static function getPropsFromString($props_str) : array {
        preg_match_all('/\s*(.*?=".*?")/', $props_str, $matches);

        $props = [];
        foreach($matches[0] as $value){
            preg_match('/\s*(.*?)="(.*?)"/', $value, $propMatches);
            $props[$propMatches[1]] = self::formatValue($propMatches[2]);
        }
        return $props;
    }
    // make string from text like this "You are <not> authorized...!!!"
    // need to read from the first '"' to the last '"' 
    private static function ParseString() : string {
        $str = "";
        self::Advance();
        while (self::$CurrentChar != self::CharNone) {
            if(self::$CurrentChar === '"'){
                break;
            }
            $str .= self::$CurrentChar;
            self::Advance();
        }
        return $str;
    }



    private static function SkipSpace()
    {
        while (self::$CurrentChar === " ") {
            self::Advance();
        }
    }
    private static function Advance(int $steps = 1)
    {
       for ($i = 0; $i < $steps; $i++) {
            self::$CurrentPos++;
            if (self::$CurrentPos < self::$TextLength) {
                self::$CurrentChar = self::$Text[self::$CurrentPos];
            } else {
                self::$CurrentChar = CParser::CharNone;
            }
       }
    }

    private static function Reverse(int $steps = 1)
    {
        for ($i = 0; $i < $steps; $i++) {
            self::$CurrentPos--;
            if (self::$CurrentPos >= 0) {
                self::$CurrentChar = self::$Text[self::$CurrentPos];
            } else {
                self::$CurrentChar = CParser::CharNone;
            }
        }
    }

    private static function Peek(int $steps = 1) : string {
        $pos = self::$CurrentPos + $steps;
        if($pos < 0 || $pos >= self::$TextLength){
            return CParser::CharNone;
        }
        return self::$Text[$pos];
    }
    private static function PeekWord(int $steps = 1) : string {
        $start = self::$CurrentPos;
        $end = self::$CurrentPos + $steps;

        if($start < 0 || $end >= self::$TextLength){
            return "";
        }

        $word = "";
        for ($i = $start; $i < $end; $i++) {
            $word .= self::$Text[$i];
        }
        return $word;
    }
}