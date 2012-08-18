<?php

require 'BukkitPlugins.php';
$DOWNLOAD_PATH = "dl/";

$options = getopt('n:u::p::d::');

if (empty($options) || empty($options['n']) || (empty($options['u']) && empty($options['p']) && !isset($options['d']))) {
  echo "Usage:\n\t-n\tPlugin Name\n\t-u\tURL\n\t-p\tParent Name\n\t-d\tDownload\n";
  exit();
}

$plugin_name = $options['n'];
if ($plugin_name == "CB") {
  $plugin_name = "CraftBukkit";
}

$bukktikPlugins = new BukkitPlugins();

$plugin = $bukktikPlugins->getPlugin($plugin_name);

if (!empty($options['u'])) {
  $plugin->setUrl($options['u']);
}
if (!empty($options['p'])) {
  $plugin_parent = $bukktikPlugins->getPlugin($options['p']);
  $plugin->setParent($plugin_parent);
}
if (isset($options['d'])) {
  $versions = array_slice($plugin->getVersions(), -5);
  echo "Choose Download for ".$plugin_name.": \n";
  foreach($versions as $key => $version) {
    echo $key.". - ".$version['type']." ".$version['file_name']."\t".$version['bukkit_version']."\t".date("Y-m-d", $version['time'])."\n";
  }
  echo "x - to exit\n";
  $valid_input = false;
  while($valid_input == false) {
    $value = trim(fgets(STDIN));
    if (!empty($value) && (strtolower($value) != 'x' || empty($versions[$value]))) {
      $valid_input = true;
    }
    else {
      echo "Invalid input.\n";
    }
  }
  if (strtolower($value) == 'x') {
    exit;
  }
  if (!empty($versions[$value])) {
    $version = $versions[$value];
    $downloadLink = FALSE;
    if (strpos($version['url'], "http://dev.bukkit.org") !== FALSE) {
      $html = file_get_contents($version['url']);
      if(preg_match('/<a href="(http:\/\/dev.bukkit.org\/media.*)">Download/', $html, $match)) {
        $downloadLink = $match[1];
      }
    }
    else {
      $downloadLink = $version['url'];
    }
    if (!empty($downloadLink)) {
      $extension = substr($downloadLink, strrpos($downloadLink, '.')+1);
      $file_name = $DOWNLOAD_PATH.$plugin_name.".".$extension;
      $fdownload = fopen($downloadLink, 'r');
      $fhdd = fopen($file_name, 'w');
      echo "Downloading File: [#";
      while(!feof($fdownload)) {
        $buffer = fread($fdownload, 4096);
        fwrite($fhdd, $buffer);
        echo "#";
      }
      echo "#]\n";
      fclose($fdownload);
      fclose($fhdd);
      if ($extension != "jar") {
        $zip = new ZipArchive();
        $res = $zip->open($file_name);
        if ($res === TRUE) {
          $target = $DOWNLOAD_PATH.$plugin_name;
          rrmdir($target);
          mkdir($target);
          $zip->extractTo($target);
          $zip->close();
          unlink($file_name);
          echo "File Unpacked to ".$target."\n";
        }
        else {
          echo "Failed unpacking with error code ".$res."\n";
        }
      }
    }
  }
}

function rrmdir($dir) {
  if (is_dir($dir)) {
    $objects = scandir($dir);
    foreach ($objects as $object) {
      if ($object != "." && $object != "..") {
        if (filetype($dir."/".$object) == "dir") rrmdir($dir."/".$object); else unlink($dir."/".$object);
      }
    }
    reset($objects);
    rmdir($dir);
  }
}
