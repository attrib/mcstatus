Minecraft Status
================

Query your Minecraft Server with MinecraftQuery and check if all plugins of your bukkit server are up-to-date.

MinecraftQuery
--------------

This is a PHP Version of Dinnerbones mcstatus - https://github.com/Dinnerbone/mcstatus

Protocol Definition: http://dinnerbone.com/blog/2011/10/14/minecraft-19-has-rcon-and-query/

Class to query a Minecraft server with "enable_query=true" in server.properties.

Usage:

<?php
require 'MinecraftQuery.php';

$query = new MinecraftQuery('localhost', 25565);

$status = $query->getStatus(); // get the Status
$rules = $query->getRules(); // get the Full Info
?>

Status:
* Message of the Day (motd)
* Game Type (gametype)
* Main World Name (map)
* Number Players Online (numplayers)
* Maximum Players (maxplayers)
* Server Address (hostport)
* Server Port (hostname)

Full Info:
* Message of the Day (motd)
* Game ID (game_id)
* Game Type (gametype)
* Main World Name (map)
* Number Players Online (numplayers)
* Players Online (players)
* Maximum Players (maxplayers)
* Server Address (hostport)
* Server Port (hostname)
* Installed Plugins (plugins)
* Bukkit Version (software)
* Minecraft Version (version)

checkPlugins.php
----------------

Check if new versions of installed plugins are available.

Usage: php checkPlugins.php -h=hostname [-p=port]

CLI-PHP Script which parses bukkit.org website once a day for new Bukkit and Module updates.
Therefore MongoDB and Mongo PHP Driver required.
