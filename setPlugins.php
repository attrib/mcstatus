<?php

require 'BukkitPlugins.php';

$options = getopt('n:u::p::');

if (empty($options) || empty($options['n']) || (empty($options['u']) && empty($options['p']))) {
  echo "Usage:\n\t-n\tPlugin Name\n\t-u\tURL\n\t-p\tParent Name\n";
  exit();
}

$plugin_name = $options['n'];

$bukktikPlugins = new BukkitPlugins();

$plugin = $bukktikPlugins->getPlugin($plugin_name);

if (!empty($options['u'])) {
  $plugin->setUrl($options['u']);
}
if (!empty($options['p'])) {
  $plugin_parent = $bukktikPlugins->getPlugin($options['p']);
  $plugin->setParent($plugin_parent);
}
