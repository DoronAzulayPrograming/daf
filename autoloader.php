<?php 
spl_autoload_register('autoload');

function autoload($name): void {

    $isPhpBaseType = match($name){
        'int' => true,
        'bool' => true,
        'array' => true,
        'string' => true,
        'callable' => true,
        default => false
    };
    if($isPhpBaseType) return;


    if(str_starts_with($name, basename(dirname(__DIR__,1))))
    {
        $filePath = "$name.php";
        if(load($filePath)) return;
    }

    $pharClassPath = resolvePharClassPath($name);
    if($pharClassPath !== null && file_exists($pharClassPath) && load($pharClassPath)) return;
    
    $filePath = "vendor\\$name.php";
    if(load($filePath)) return;
}

function load(string $filename): bool{
    if(is_win_os() === false){
        $filename = str_replace("\\", "/", $filename);
    }

    // if(!file_exists($filename)){
    //     return false;
    // }
    
    require_once $filename;
    return true;
}

function is_win_os(){
    if (strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN') {
        return true;
    }
    return false;
}

function resolvePharClassPath(string $name): string | null {
    $parts = explode("\\", trim($name, "\\"));

    if (count($parts) < 2) return null;

    $pharBase = array_shift($parts);
    $relativePath = implode("/", $parts);

    return "phar://" . __DIR__ . "/" . $pharBase . ".phar/" . $relativePath . ".php";
}

function VAR_DUMP_DEBUG($value){
    echo "<pre>";
    var_dump($value);
    echo "</pre>";
}
?>