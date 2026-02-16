<?php 
spl_autoload_register('autoload');

function autoload($name){
    global $pharFiles;

    $filePath = "$name.php";
    if(load($filePath)) return;

    $filePath = "vendor\\$name.php";
    if(load($filePath)) return;
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
?>