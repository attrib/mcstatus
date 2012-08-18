<?php

class BukkitPlugins {

  /** @var Mongo */
  private $mongo;
  /** @var MongoDB */
  private $db;
  /** @var MongoCollection */
  private $serverMods;

  private $plugins;

  public function __construct() {
    // connect
    $this->mongo = new Mongo();
    // select a database
    $this->db = $this->mongo->bukkit;
    // select a collection (analogous to a relational database's table)
    $this->serverMods = $this->db->server_mods;

    $this->plugins = array();
  }

  private function savePlugin($name) {
    if ($this->plugins[$name]->isDirty()) {
      $this->serverMods->save($this->plugins[$name]->toArray());
      $this->plugins[$name]->saved();
    }
  }

  public function __destruct() {
    foreach ($this->plugins as $name => $plugin) {
      $this->savePlugin($name);
    }
  }

  /**
   * @param $name
   * @return BukkitPlugin
   */
  public function getPlugin($name) {
    if (!isset($this->plugins[$name])) {
      $plugin_info = $this->serverMods->findOne(array('name' => $name));
      if (empty($plugin_info)) {
        $plugin_info = array('name' => $name);
        switch($name) {
          case 'CraftBukkit':
            $plugin_info['url'] = 'http://dl.bukkit.org/downloads/craftbukkit/view/latest-rb/';
            break;
          default:
            $plugin_info['url'] = "http://dev.bukkit.org/server-mods/".$name;
            break;
        }
      }
      else {
        if (!empty($plugin_info['parent'])) {
          $plugin_info['parent'] = $this->getPlugin($plugin_info['parent']);
        }
      }
      $this->plugins[$name] = new BukkitPlugin($plugin_info);
      $this->savePlugin($name);
    }
    return $this->plugins[$name];
  }



}

class BukkitPlugin {

  private $_id;
  private $name;
  private $url;
  private $versions;
  private $lastUpdate;
  private $dirty;
  /** @var BukkitPlugin  */
  private $parent;

  const UPDATE_INTERVAL = 86400;

  public function BukkitPlugin($plugin_info) {
    $this->name       = $plugin_info['name'];
    $this->_id        = isset($plugin_info['_id']) ? $plugin_info['_id'] : NULL;
    $this->url        = isset($plugin_info['url']) ? $plugin_info['url'] : FALSE;
    $this->lastUpdate = isset($plugin_info['last_update']) ? $plugin_info['last_update'] : 0;
    $this->versions   = isset($plugin_info['versions']) ? $plugin_info['versions'] : array();
    $this->parent     = isset($plugin_info['parent']) ? $plugin_info['parent'] : FALSE;
    $this->dirty      = array();

    if ((empty($this->versions) || ($this->lastUpdate+self::UPDATE_INTERVAL < time()))) {
      $this->update();
    }
  }

  public function toArray() {
    $return = array(
      'name' => $this->name,
      'url'  => $this->url,
      'last_update' => $this->lastUpdate,
      'versions'    => $this->versions,
    );
    if (isset($this->_id)) {
      $return['_id'] = $this->_id;
    }
    if ($this->parent) {
      $return['parent'] = $this->parent->getName();
    }
    return $return;
  }

  public function update($force = FALSE) {
    if ($force || (empty($this->versions) || ($this->lastUpdate+self::UPDATE_INTERVAL < time()))) {
      if ($this->parent) {
        $this->parent->update($force);
        return;
      }
      $added_versions = array();
      foreach ($this->versions as $version) {
        $added_versions[] = $version['file_name'];
      }
      if (!empty($this->url)) {
        $html = file_get_contents($this->url);

        if (strpos($this->url, 'http://dev.bukkit.org') !== FALSE) {
          $new_version = $this->parseDevBukkit($html);
        }
        elseif (strpos($this->url, 'http://dl.bukkit.org') !== FALSE) {
          $new_version = $this->parseDlBukkit($html);
        }

        if (!empty($new_version)) {
          foreach($new_version as $version) {
            if (!in_array($version['file_name'], $added_versions)) {
              $this->versions[] = $version;
            }
          }
          $this->dirty[] = 'versions';
        }
        else {
          $this->url = FALSE;
          $this->dirty[] = 'url';
        }
        $this->lastUpdate = time();
        $this->dirty[] = 'time';
      }
    }
  }

  private function parseDevBukkit($html) {
    $new_versions = array();
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
        );
        array_unshift($new_versions, $version);
      }
    }
    return $new_versions;
  }

  private function parseDlBukkit($html) {
    $new_versions = array();
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
        $new_versions[] = $version;
      }
    }
    return $new_versions;
  }


  public function getCurrentVersion($plugin_version) {
    $versions = $this->getVersions();
    for ($i=count($versions)-1; $i>=0; $i--) {
      $version = $versions[$i];
      if (preg_match('/(\d+\.\d+(\.\d+)?(-\S*)?(_\S*)?)/', $version['file_name'], $match)) {
        if ($match[1] == $plugin_version) {
          return $version;
        }
      }
      elseif (strpos($plugin_version, $version['file_name']) !== FALSE || strpos($version['file_name'], $plugin_version) !== FALSE) {
        return $version;
      }
    }
    return FALSE;
  }

  public function getLastVersion($software) {
    preg_match('/((\d+\.\d+\.\d+)-R\d\.\d)/', $software, $match);
    $cb_version = 'CB '.trim($match[1]);
    $mc_version = trim($match[2]);
    $versions = $this->getVersions();
    for ($i=count($versions)-1; $i>=0; $i--) {
      $version = $versions[$i];
      $version['bukkit_version'] = trim($version['bukkit_version']);
      if ($cb_version >= $version['bukkit_version'] || $mc_version >= $version['bukkit_version']) {
        return $version;
      }
    }
    return FALSE;
  }

  public function getVersions() {
    if ($this->parent) {
      return $this->parent->getVersions();
    }
    return $this->versions;
  }

  public function getLastUpdate() {
    if ($this->parent) {
      return $this->parent->getLastUpdate();
    }
    return $this->lastUpdate;
  }

  public function getName() {
    return $this->name;
  }

  public function getUrl() {
    if ($this->parent) {
      return $this->parent->getUrl();
    }
    return $this->url;
  }

  public function isDirty() {
    return !empty($this->dirty);
  }

  /**
   * @param \BukkitPlugin $parent
   */
  public function setParent($parent) {
    $this->parent = $parent;
    $this->dirty[] = 'parent';
  }

  public function setUrl($url) {
    $this->url = $url;
    $this->dirty[] = 'url';
  }

  public function saved() {
    $this->dirty = array();
  }

}
