<?php

$options = getopt('h:p::');

if (empty($options) || empty($options['h'])) {
  echo "Hostname is required. Use -h=hostname. For Port use -p=port.\n";
  exit;
}
else {
  define('HOSTNAME', $options['h']);
}

if (!empty($options['p'])) {
  define('PORT', $options['p']);
}
elseif (!defined('PORT')) {
  define('PORT', 25565);
}

require 'MinecraftQuery.php';
require 'BukkitPlugins.php';

$query = new MinecraftQuery(HOSTNAME, PORT);
$rules = $query->getRules();

$plugins = new BukkitPlugins();

$craftBukkit = $plugins->getPlugin('CraftBukkit');

$versions = $craftBukkit->getVersions();
if (!empty($versions)) {
  preg_match('/((\d+\.\d+\.\d+)-R\d\.\d)/', $rules['software'], $match);
  $cb_version = 'CB '.trim($match[1]);
  $last_version = array_pop($versions);
  if ($last_version['bukkit_version'] != $cb_version) {
    echo "Bukkit Update available!\n";
    echo "\tCurrent Version ".$cb_version."\n";
    echo "\tLatest Version ".$last_version['file_name']."\n";
    echo "\tURI: ".$last_version['url']."\n";
  }
}

foreach($rules['plugins'] as $plugin_data) {

  $plugin_name = $plugin_data['name'];

  $plugin = $plugins->getPlugin($plugin_name);

  $current_version = NULL;
  if (preg_match('/(.*?) \[(.*?)\]/', $plugin_data['version'], $match)) {
    $plugin_version = $match[1];
    $plugin_bukkit_version = $match[2];
  }
  else {
    $plugin_version = $plugin_data['version'];
    $plugin_bukkit_version = NULL;
  }

  $current_version = $plugin->getCurrentVersion($plugin_version);
  if (empty($current_version) && preg_match('/(\d+\.\d+(\.\d+)?)/', $plugin_version, $match)) {
    $plugin_version = $match[1];
    $current_version = $plugin->getCurrentVersion($plugin_version);
  }

  $versions = $plugin->getVersions();
  if (empty($versions)) {
    echo "No version infos for ".$plugin_name."\n";
    echo "\tCurrent Version ".$plugin_data['version']."\n";
  }
  elseif (empty($current_version)) {
    echo "No current version found for ".$plugin_name."\n";
    echo "\tCurrent Version ".$plugin_data['version']."\n";
    if ($last_version = $plugin->getLastVersion($rules['software']))
      echo "\tLatest version ".$last_version['file_name']." from ".date("Y-m-d", $last_version['time'])."\n";
  }
  else {
    $last_version = $plugin->getLastVersion($rules['software']);

    if ($last_version && $last_version['file_name'] != $current_version['file_name']) {
      echo "Update for ".$plugin_name."\n";
      echo "\tCurrent Version ".$plugin_data['version']." from ".date("Y-m-d", $current_version['time'])."\n";
      echo "\tLatest Version ".$last_version['file_name']." from ".date("Y-m-d", $last_version['time'])."\n";
      echo "\tURI: ".$last_version['url']."\n";
    }
    else {
      $last_version = array_pop($versions);
      if ($last_version['file_name'] != $current_version['file_name']) {
        echo "Non-Equal CB Version Update for ".$plugin_name."\n";
        echo "\tCurrent Version ".$plugin_data['version']." from ".date("Y-m-d", $current_version['time'])."\n";
        echo "\tLatest Version ".$last_version['file_name']." from ".date("Y-m-d", $last_version['time'])." for ".$last_version['bukkit_version']."\n";
        echo "\tURI: ".$last_version['url']."\n";
      }
    }
  }
}
