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
else {
  define('PORT', 25565);
}


require 'MinecraftQuery.php';

function getCurrentVersion($plugin_version, $versions) {
  foreach ($versions as $version) {
    if (preg_match('/(\d+\.\d+(\.\d+)?(-\S*)?(_\S*)?)/', $version['file_name'], $match)) {
      if ($match[1] == $plugin_version) {
        return $version;
      }
    }
    elseif (strpos($plugin_version, $version['file_name']) !== false || strpos($version['file_name'], $plugin_version) !== false) {
      return $version;
    }
  }
  return NULL;
}

function getLastVersion($software, $versions) {
  preg_match('/((\d+\.\d+\.\d+)-R\d\.\d)/', $software, $match);
  $cb_version = 'CB '.trim($match[1]);
  $mc_version = trim($match[2]);
  for ($i=count($versions)-1; $i>=0; $i--) {
    $version = $versions[$i];
    $version['bukkit_version'] = trim($version['bukkit_version']);
    if ($cb_version == $version['bukkit_version'] || $mc_version == $version['bukkit_version']) {
      return $version;
    }
  }
  return false;
}

$query = new MinecraftQuery(HOSTNAME, PORT);

$rules = $query->getRules();

// connect
$m = new Mongo();
// select a database
$db = $m->bukkit;

// select a collection (analogous to a relational database's table)
$server_mods = $db->server_mods;

$plugin_info = $server_mods->findOne(array('name' => 'CraftBukkit'));

if (empty($plugin_info) || empty($plugin_info['versions']) || ($plugin_info['last_update']+86400 < time())) {
  if (empty($plugin_info)) {
    $plugin_info = array(
      'name' => 'CraftBukkit',
      'url' => 'http://dl.bukkit.org/downloads/craftbukkit/view/latest-rb/',
      'last_update' => time(),
      'versions' => array(),
    );
  }
  elseif (!empty($plugin_info['versions'])) {
    $added_versions = array();
    foreach ($plugin_info['versions'] as $version) {
      $added_versions[] = $version['file_name'];
    }
  }

  if (!empty($plugin_info['url'])) {
    echo "Updating Version History of CraftBukkit...";
    $html = file_get_contents($plugin_info['url']);
    if (preg_match('/<dt>\s*Upstream Artifacts:\s*<\/dt>\s*<dd>\s*<ul>(.*?)<\/ul>\s*<\/dd>/s', $html, $match)) {
      if (preg_match('/<li>.*: <a href="(.*)">((.*)\(Build(.*)\))<\/a><\/li>/m', $match[1], $available_file)) {
        $version = array(
          'type' => 'R', //R, B, A
          'url'  => 'http://dl.bukkit.org'.trim($available_file[1]),
          'file_name' => trim($available_file[2]),
          'bukkit_version' => 'CB '. trim($available_file[3]),
          'bukkit_build' => trim($available_file[4]),
          'time' => time(),
        );
        if (isset($added_versions) && !in_array($version['file_name'], $added_versions) || !isset($added_versions)) {
          $plugin_info['versions'][] = $version;
        }
      }
    }
    $server_mods->save($plugin_info);
    echo "done\n";
  }
}

if (!empty($plugin_info['versions'])) {
  preg_match('/((\d+\.\d+\.\d+)-R\d\.\d)/', $rules['software'], $match);
  $cb_version = 'CB '.trim($match[1]);
  $last_version = array_pop($plugin_info['versions']);
  if ($last_version['bukkit_version'] != $cb_version) {
    echo "Bukkit Update available!\n";
    echo "\tCurrent Version ".$cb_version."\n";
    echo "\tLatest Version ".$last_version['file_name']."\n";
    echo "\tURI: ".$last_version['url']."\n";
  }
}

foreach($rules['plugins'] as $plugin) {
  $plugin_info = $server_mods->findOne(array('name' => $plugin['name']));

  if (!empty($plugin_info) && !empty($plugin_info['subModuleOf'])) {
    $plugin_info = $server_mods->findOne(array('name' => $plugin_info['subModuleOf']));
  }


  if (empty($plugin_info) || empty($plugin_info['versions']) || ($plugin_info['last_update']+86400 < time())) {
    if (empty($plugin_info)) {
      $plugin_info = array(
        'name' => $plugin['name'],
        'url' => "http://dev.bukkit.org/server-mods/".$plugin['name'],
        'last_update' => time(),
        'versions' => array(),
      );
    }
    elseif (!empty($plugin_info['versions'])) {
      $added_versions = array();
      foreach ($plugin_info['versions'] as $version) {
        $added_versions[] = $version['file_name'];
      }
    }

    if (!empty($plugin_info['url'])) {
      echo "Updating Version History of ".$plugin['name']."...";
      $html = file_get_contents($plugin_info['url']);

      if (preg_match_all("/<dt>Recent files<\/dt>\s*<dd><ul>(.*)<\/ul><\/dd>/m", $html, $matches)) {
        $count = preg_match_all('/<li><span.*?>(.*?)<\/span>.*?<a href="(.*?)">(.*?)<\/a> for (.*?)<span.*?data-epoch="(.*?)".*?>(.*?)<\/span><\/li>/m', $matches[1][0], $available_files, PREG_SET_ORDER);

        $new_versions = array();
        foreach($available_files as $files) {
          $version = array(
            'type' => trim($files[1]), //R, B, A
            'url'  => 'http://dev.bukkit.org'.trim($files[2]),
            'file_name' => trim($files[3]),
            'bukkit_version' => trim($files[4]),
            'time' => trim($files[5]),
            'time_string' => trim($files[6]),
          );
          if (isset($added_versions) && !in_array($version['file_name'], $added_versions) || !isset($added_versions)) {
            array_unshift($new_versions, $version);
          }
        }
        $plugin_info['versions'] += $new_versions;
      }
      else {
        unset($plugin_info['url']);
      }
      $server_mods->save($plugin_info);
      echo "done\n";
    }

  }

  $current_version = NULL;
  if (preg_match('/(.*?) \[(.*?)\]/', $plugin['version'], $match)) {
    $plugin_version = $match[1];
    $plugin_bukkit_version = $match[2];
  }
  else {
    $plugin_version = $plugin['version'];
    $plugin_bukkit_version = NULL;
  }

  $current_version = getCurrentVersion($plugin_version, $plugin_info['versions']);
  if (empty($current_version) && preg_match('/(\d+\.\d+(\.\d+)?)/', $plugin_version, $match)) {
    $plugin_version = $match[1];
    $current_version = getCurrentVersion($plugin_version, $plugin_info['versions']);
  }

  if (empty($plugin_info['versions'])) {
    echo "No version infos for ".$plugin['name']."\n";
    echo "\tCurrent Version ".$plugin['version']."\n";
  }
  elseif (empty($current_version)) {
    echo "No current version found for ".$plugin['name']."\n";
    echo "\tCurrent Version ".$plugin['version']."\n";
    if ($last_version = getLastVersion($rules['software'], $plugin_info['versions']))
      echo "\tLatest version ".$last_version['file_name']." from ".$last_version['time_string']."\n";
  }
  else {
    $last_version = getLastVersion($rules['software'], $plugin_info['versions']);

    if ($last_version && $last_version['file_name'] != $current_version['file_name'] && $current_version['time_string'] != $last_version['time_string']) {
      echo "Update for ".$plugin['name']."\n";
      echo "\tCurrent Version ".$plugin['version']." from ".$current_version['time_string']."\n";
      echo "\tLatest Version ".$last_version['file_name']." from ".$last_version['time_string']."\n";
      echo "\tURI: ".$last_version['url']."\n";
    }
    else {
      $last_version = array_pop($plugin_info['versions']);
      if ($last_version['file_name'] != $current_version['file_name'] && $current_version['time_string'] != $last_version['time_string']) {
        echo "Non-Equal CB Version Update for ".$plugin['name']."\n";
        echo "\tCurrent Version ".$plugin['version']." from ".$current_version['time_string']."\n";
        echo "\tLatest Version ".$last_version['file_name']." from ".$last_version['time_string']." for ".$last_version['bukkit_version']."\n";
        echo "\tURI: ".$last_version['url']."\n";
      }
    }
  }
}