<?php


class BuildPhar
{
  private $_sourceDirectory = null;
  private $_stubFile        = null;
  private $_outputDirectory = null;
  private $_pharFileName    = null;

  /**
   * @param $_sourceDirectory       // This is the directory where your project is stored.
   * @param $stubFile               // Name the entry point for your phar file. This file have to be within the source
   *                                   directory. 
   * @param string $_outputDirectory  // Directory where the phar file will be placed.
   * @param string $pharFileName    // Name of your final *.phar file.
   */
  public function __construct($_sourceDirectory, $stubFile, $_outputDirectory = null, $pharFileName = 'myPhar.phar') {

    if ((file_exists($_sourceDirectory) === false) || (is_dir($_sourceDirectory) === false)) {
      throw new Exception('No valid source directory given.');
    }
    $this->_sourceDirectory = $_sourceDirectory;

    // if (file_exists($this->_sourceDirectory.'/'.$stubFile) === false) {
    //   throw new Exception('Your given stub file doesn\'t exists.');
    // }

    $this->_stubFile = $stubFile;

    if(empty($pharFileName) === true) {
      throw new Exception('Your given output name for your phar-file is empty.');
    }
    $this->_pharFileName = $pharFileName;

    if ((empty($_outputDirectory) === true) || (file_exists($_outputDirectory) === false) || (is_dir($_outputDirectory) === false)) {

      if ($_outputDirectory !== null) {
        trigger_error ( 'Your output directory is invalid. We set the fallback to: "'.dirname(__FILE__).'".', E_USER_WARNING);
      }

      $this->_outputDirectory = dirname(__FILE__);
    } else {
      $this->_outputDirectory = $_outputDirectory;
    }

    $this->prepareBuildDirectory();
    $this->buildPhar();
  }

  private function prepareBuildDirectory() {
    if (preg_match('/.phar$/', $this->_pharFileName) == FALSE) {
      $this->_pharFileName .= '.phar';
    }

    if (file_exists($this->_pharFileName) === true) {
      unlink($this->_pharFileName);
    }
  }

  private function buildPhar() {
    $phar = new Phar($this->_outputDirectory.'/'.$this->_pharFileName);
    $phar->buildFromDirectory($this->_sourceDirectory);
    $phar->setStub($this->_stubFile);
  }
}

function createPhar($sourceDir, $outputDir, $pharFileName, $version = "1.0.0"){
  $stubFile = sprintf(<<<'STUB'
  <?php
  // Version: %s
  Phar::mapPhar();
  __HALT_COMPILER();
  ?>
  STUB
  , $version);
  $builder = new BuildPhar(
    $sourceDir,
    $stubFile,
    $outputDir . '/phars',
    $pharFileName
  );
}
function createPharZip($sourceDir, $outputDir, $pharFileName, $version = "1.0.0"){
    // Create a zip file with the phar file inside
    $zip = new ZipArchive();
    $zipFileName = $outputDir . '/zips' . '/' . basename($pharFileName, '.phar') . '.zip';
    if ($zip->open($zipFileName, ZipArchive::CREATE) === TRUE) {
      $zip->addFile($outputDir . '/phars' . '/' . $pharFileName, $pharFileName);
      $zip->close();
    } else {
      throw new Exception('Could not create zip file.');
    }
}
function createPharAndZip($sourceDir, $outputDir, $pharFileName, $version = "1.0.0"){
  try {
    $stubFile = sprintf(<<<'STUB'
    <?php
    // Version: %s
    Phar::mapPhar();
    __HALT_COMPILER();
    ?>
    STUB
    , $version);
    $builder = new BuildPhar(
      $sourceDir,
      $stubFile,
      $outputDir,
      $pharFileName
    );


    // Create a zip file with the phar file inside
    $zip = new ZipArchive();
    $zipFileName = $outputDir . '/' . basename($pharFileName, '.phar') . '.zip';
    if ($zip->open($zipFileName, ZipArchive::CREATE) === TRUE) {
      $zip->addFile($outputDir . '/' . $pharFileName, $pharFileName);
      $zip->close();
    } else {
      throw new Exception('Could not create zip file.');
    }


  } catch (\Throwable $th) {
    echo $th->getMessage();
  }
}


function buildFramewark(array $builds, string $buildDir, string $version = "1.0.0"){
  foreach ($builds as $sourceDir => $fileName) {
    createPhar($sourceDir, $buildDir, $fileName.".phar", $version);
    createPharZip($sourceDir, $buildDir, $fileName.".phar", $version);
  }
}

$version = "1.0.0";
$do_version = null;

$file_version = $do_version ?? '';
if(!empty($file_version)) $file_version = '-'.$file_version;

$buildDir = '../../releases';

$builderArr = [
  dirname(__FILE__).'/vendor/DafCore' => 'DafCore'.$file_version,
  dirname(__FILE__).'/vendor/DafDb' => 'DafDb'.$file_version,
  dirname(__FILE__).'/vendor/DafGlobals' => 'DafGlobals'.$file_version,
  dirname(__FILE__).'/vendor/Firebase' => 'Firebase'.$file_version,
];

buildFramewark($builderArr, $buildDir, $version);
echo "Build completed!";