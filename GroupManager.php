<?php

/*
__PocketMine Plugin__
name=GroupManager
description=GroupManager
version=1.1 dev
author=KsyMC
class=GroupManager
apiversion=8
*/

/*
Small Changelog
===============

1.0:
- Release

*/

class GroupManager implements Plugin{
	private $api;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
	}
	
	public function init(){
		$this->api->event("server.close", array($this, "handler"));
		$this->api->addHandler("player.join", array($this, "handler"), 5);
		$this->api->addHandler("player.block.touch", array($this, "handler"), 6);
		$this->api->addHandler("essentials.player.chat", array($this, "handler"), 6);
		$this->api->addHandler("groupmanager.permission.check", array($this, "permissionsCheck"), 5);
		
		$this->api->console->register("manuadd", "<player> <group> [world]", array($this, "defaultCommands"));
		$this->api->console->register("manudel", "<player> [world]", array($this, "defaultCommands"));
		$this->api->console->register("manwhois", "<player> [world]", array($this, "defaultCommands"));
		$this->api->console->register("mangadd", "<group> [world]", array($this, "defaultCommands"));
		$this->api->console->register("mangdel", "<group> [world]", array($this, "defaultCommands"));
		$this->api->console->register("listgroups", "", array($this, "defaultCommands"));
		$this->api->console->register("mansave", "", array($this, "defaultCommands"));
		$this->api->console->register("manload", "", array($this, "defaultCommands"));
		$this->readConfig();
	}
	
	public function __destruct(){
	}
	
	public function readConfig(){
		foreach(scandir("./worlds/") as $world){
			if($this->api->level->levelExists($world)){
				if(is_dir("./plugins/GroupManager/worlds/$world") === false){
					mkdir("./plugins/GroupManager/worlds/$world", 0777, true);
				}
				$userdata = new Config(DATA_PATH."/plugins/GroupManager/worlds/$world/users.yml", CONFIG_YAML, array("users" => array()));
				$groupdata = new Config(DATA_PATH."/plugins/GroupManager/worlds/$world/groups.yml", CONFIG_YAML, array("groups" => array()));
			}
		}
	}
	
	public function permissionsCheck($data, $event){
		if($this->api->ban->isOp($data["issuer"]->username)){	
			return true;
		}
		return true;
	}
	
	public function handler(&$data, $event){
		switch($event){
			case "server.close":
				break;
			case "player.join":
				break;
			case "player.block.touch":
				break;
			case "essentials.player.chat":
				$data["message"] = str_replace($data["player"]->username, "prefix ".$data["player"]->username." suffix", $data["message"]);
				break;
		}
	}
	
	public function defaultCommands($cmd, $params, $issuer, $alias){
		$output = "";
		switch($cmd){
			case "manuadd":
				if($params[0] == "" or $params[1] == ""){
					$output .= "Usage: /manuadd <player> <group> [world]\n";
					break;
				}
				break;
			case "manudel":
				if($params[0] == ""){
					$output .= "Usage: /manudel <player> [world]\n";
					break;
				}
				break;
			case "manwhois":
				if($params[0] == ""){
					$output .= "Usage: /manwhois <player> [world]\n";
					break;
				}
			case "mangadd":
				if($params[0] == ""){
					$output .= "Usage: /mangadd <group> [world]\n";
					break;
				}
				break;
			case "mangdel":
				if($params[0] == ""){
					$output .= "Usage: /mangdel <group> [world]\n";
					break;
				}
				break;
			case "listgroups":
				break;
			case "mansave":
				break;
			case "manload":
				break;
		}
		return $output;
	}
	
	public function getPlayerPermissions(Player $player){
		return $player;
	}
}