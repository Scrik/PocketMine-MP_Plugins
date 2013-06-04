<?php

/*
__PocketMine Plugin__
name=GroupManager
description=GroupManager
version=0.0.1
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
	private $api, $worlds, $players;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
	}
	
	public function __destruct(){}
	
	public function init(){
		foreach(scandir("./worlds/") as $world){
			if($this->api->level->levelExists($world)){
				if(is_dir("./worlds/$world") === false) mkdir("./plugins/GroupManager/worlds/$world", 0777, true);
				$this->worlds[$world] = array(
					"users" => new Config(DATA_PATH."/plugins/GroupManager/worlds/$world/users.yml", CONFIG_YAML, array("users" => array())),
					"groups" => new Config(DATA_PATH."/plugins/GroupManager/worlds/$world/groups.yml", CONFIG_YAML, array("groups" => array())),
				);
			}
		}
		$this->reloadConfig();
		
		$this->api->event("server.close", array($this, "handler"));
		$this->api->addHandler("console.command", array($this, "permissionsCheck"), 5);
		
		$this->api->addHandler("player.join", array($this, "handler"), 5);
		$this->api->addHandler("player.block.touch", array($this, "handler"), 5);
		$this->api->addHandler("player.block.place.spawn", array($this, "handler"), 5);
		$this->api->addHandler("player.block.break.spawn", array($this, "handler"), 5);
		
		$this->api->console->register("manuadd", "<player> <group>", array($this, "defaultCommands"));
		$this->api->console->register("manudel", "<player>", array($this, "defaultCommands"));
		$this->api->console->register("manwhois", "<player>", array($this, "defaultCommands"));
		$this->api->console->register("mangadd", "<group>", array($this, "defaultCommands"));
		$this->api->console->register("mangdel", "<group>", array($this, "defaultCommands"));
		$this->api->console->register("listgroups", "", array($this, "defaultCommands"));
		$this->api->console->register("mansave", "", array($this, "defaultCommands"));
		$this->api->console->register("manload", "", array($this, "defaultCommands"));
	}
	
	public function permissionsCheck($data, $event){
		switch($event){
			case "console.command":
				if($data["issuer"] instanceof Player){
					if(in_array($data["cmd"], $this->players[$data["issuer"]->iusername]["groupdata"]["permissions"]) or in_array($data["cmd"], $this->players[$data["issuer"]->iusername]["userdata"]["permissions"])){
						if($data["cmd"] === "chat"){
							$prefix = $this->players[$data["issuer"]->iusername]["groupdata"]["info"]["prefix"];
							$suffix = $this->players[$data["issuer"]->iusername]["groupdata"]["info"]["suffix"];
							$data["parameters"][] = array($prefix, $suffix);
						}
						return true;
					}
					return false;
				}
				break;
		}
	}
	
	public function handler(&$data, $event){
		switch($event){
			case "player.join":
				$users = $this->worlds[$data->level->getName()]["users"]->get("users");
				$groups = $this->worlds[$data->level->getName()]["groups"]->get("groups");
				$defaultgroup = $this->worlds[$data->level->getName()]["defaultgroup"];
				$this->players[$data->iusername] = array(
					"groupdata" => isset($users[$data->username]) ? $groups[$users[$data->username]["group"]] : $defaultgroup,
					"userdata" => isset($users[$data->username]) ? $users[$data->username] : array("group" => "Default", "permissions" => $defaultgroup["permissions"]),
				);
				break;
			case "server.close":
				$this->save();
				break;
			case "player.block.touch":
				if(($data["type"] === "place" and $data["item"]->getID() !== SIGN) or ($data["type"] === "break" and $data["target"]->getID() !== SIGN_POST and $data["target"]->getID() !== WALL_SIGN)){
					if($this->players[$data["player"]->iusername]["groupdata"]["info"]["build"] === false){
						$data["player"]->sendChat("You don't have permission");
						return false;
					}
				}
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
				if($this->changeGroup($params[0], $params[1], $params[2])){
					$this->api->chat->broadcast("$player has been moved to the $group group.");
				}else{
					
				}
				break;
			case "manudel":
				if($params[0] == ""){
					$output .= "Usage: /manudel <player>\n";
					break;
				}
				$player = $params[0];
				$users = $this->users->get("users");
				if(!isset($users[$player])){
					$output .= "Player \"$player\" does not exist.\n";
					break;
				}
				unset($users[$player]);
				$this->users->set("users", $users);
				$output .= "$player has been removed\n";
				break;
			case "manwhois":
				if($params[0] == ""){
					$output .= "Usage: /manwhois <player>\n";
					break;
				}
				$player = $params[0];
				$users = $this->users->get("users");
				if(!isset($users[$player])){
					$output .= "Player \"$player\" does not exist.\n";
					break;
				}
				$group = $users[$player]["group"];
				$output .= "$player belong to the $group group.\n";
				break;
			case "mangadd":
				if($params[0] == ""){
					$output .= "Usage: /mangadd <group>\n";
					break;
				}
				$group = $params[0];
				$groups = $this->groups->get("groups");
				if(isset($groups[$group])){
					$output .= "$group already exists.\n";
					break;
				}
				$groups[$group] = array(
					"default" => false,
					"permissions" => array(),
					"info" => array(
						"prefix" => "[$group] ",
						"build" => true,
						"suffix" => " [Jejo]",
					),
				);
				$this->groups->set("groups", $groups);
				$output .= "Has been added to the $group group.\n";
				break;
			case "mangdel":
				if($params[0] == ""){
					$output .= "Usage: /mangadd <group>\n";
					break;
				}
				$group = $params[0];
				$groups = $this->groups->get("groups");
				if(!array_key_exists($group, $groups)){
					$output .= "Group \"$group\" does not exist.\n";
					break;
				}
				unset($groups[$group]);
				$this->groups->set("groups", $groups);
				$output .= "$group has been removed\n";
				break;
			case "listgroups":
				$groups = $this->groups->get("groups");
				$output .= "Groups list : ";
				foreach($groups as $name => $group){
					$output .= "$name, ";
				}
				$output .= "\n";
				break;
			case "mansave":
				$this->save();
				break;
			case "manload":
				$this->reloadConfig();
				break;
		}
		return $output;
	}
	
	public function changeGroup($player, $group, $world = false){
		if($player instanceof Player){
			$player = $plaer->iusername;
		}
		if($world === false) $worlds = $this->api->level->getDefault()->getName();
		$groups = $this->worlds[$world]["groups"]->get("groups");
		if(!isset($groups[$group])){
			return false;
		}
		$users = $this->worlds[$world]["users"]->get("users");
		if(!isset($users[$player])){
			$users[$player] = array("group" => "", "permissions" => array());
			$this->worlds[$world]["users"]->set("users", $users);
		}
		$users[$player]["group"] = $group;
		$this->users->set("users", $users);
		$this->players[$player] = array(
			"groupdata" => $groups[$group],
			"userdata" => $users[$player],
		);
		return true;
	}
	
	public function changeUser($player, $user){
		$this->players[$player->iusername];
	}
	
	public function save(){
		foreach($this->worlds as $world){
			$this->worlds[$world]["users"]->save();
			$this->worlds[$world]["groups"]->save();
		}
	}
		
	public function reloadConfig(){
		foreach($this->worlds as $world => $data){
			$this->worlds[$world]["users"]->load(DATA_PATH."/plugins/GroupManager/worlds/$world/users.yml", CONFIG_YAML);
			$this->worlds[$world]["groups"]->load(DATA_PATH."/plugins/GroupManager/worlds/$world/groups.yml", CONFIG_YAML);
			foreach($this->worlds[$world]["groups"]->get("groups") as $groupname => $groupdata){
				if($groupdata["default"] === true){
					$this->worlds[$world]["defaultgroup"] = $groupdata;
					console("[INFO] $world of the default group : ".$groupname);
					$found = true;
					break;
				}
			}
			if(!isset($found)){
				console("\x1b[31;1m[ERROR] $world of the default group does not exist.");
			}
		}
	}
}