<?php
namespace DafGlobals\IO;

final class Path
{
    public static function Combine(string ...$parts): string
    {
        $clean = [];

        foreach ($parts as $part) {
            if ($part === '') continue;
            $clean[] = trim($part, "\\/");
        }

        return implode(DIRECTORY_SEPARATOR, $clean);
    }

    public static function ResolveRelative(string $base, string $relative): string
    {
        $path = self::Combine($base, $relative);

        $segments = explode(DIRECTORY_SEPARATOR, $path);
        $stack = [];

        foreach ($segments as $seg) {
            if ($seg === '' || $seg === '.') continue;

            if ($seg === '..') {
                array_pop($stack);
                continue;
            }

            $stack[] = $seg;
        }

        $isWindows = preg_match('/^[A-Za-z]:$/', $stack[0] ?? '');

        $prefix = $isWindows
            ? array_shift($stack) . DIRECTORY_SEPARATOR
            : DIRECTORY_SEPARATOR;

        return $prefix . implode(DIRECTORY_SEPARATOR, $stack);
    }
}

