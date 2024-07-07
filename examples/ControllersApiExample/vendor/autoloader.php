<?php 
spl_autoload_register('autoload');

$pharFiles = [];
$dependencies = getDependencies();
foreach($dependencies as $key => $value){
    if($value === "^")
        $pharFiles[] = $key;
    else
        $pharFiles[] = $key."-".$value;
}
function autoload($name){
    global $pharFiles;

    $filePath = "$name.php";
    if(load($filePath)) return;

    $filePath = "vendor\\$name.php";
    if(load($filePath)) return;

    foreach($pharFiles as $pharFile){
        $filePath = "phar://".__DIR__."/$pharFile.phar/$name.php";
        if(load($filePath)) return;

        $name = str_replace($pharFile."\\", "", $name);
        $filePath = "phar://".__DIR__."/$pharFile.phar/$name.php";
        if(load($filePath)) return;
    }
}

function load(string $filename){
    if(is_win_os() === false){
        $filename = str_replace("\\", "/", $filename);
    }

    if(!file_exists($filename)){
        return false;
    }
    
    require_once $filename;
    return true;
}

function is_win_os(){
    if (strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN') {
        return true;
    }
    return false;
}

function getDependencies() {
    if(!file_exists('proj.json')) return [];
    
    $json = file_get_contents('proj.json');
    $data = json_decode($json, true);
    return $data['Dependencies'];
}