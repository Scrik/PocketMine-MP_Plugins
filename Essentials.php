<?php

/*
__PocketMine Plugin__
name=Essentials
description=Essentials
version=0.0.1
author=KsyMC
class=Essentials
apiversion=8
*/

/*
Small Changelog
===============

1.0:
- Release

*/

class Essentials implements Plugin{
	private $api, $lang;
	private static $cmds = array(
		"home",
		"sethome",
		"delhome",
		"mute",
		"back",
		"heal",
		"tree",
		"clearinventory",
		"setspawn",
		"burn",
		"kickall",
		"killall",
		"login",
		"logout",
		"register",
		"unregister",
		"changepassword",
	);
	
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
	}
	
	public function init(){
		$this->api->event("server.close", array($this, "handler"));
		$this->api->addHandler("player.join", array($this, "handler"), 5);
		$this->api->addHandler("player.quit", array($this, "handler"), 5);
		$this->api->addHandler("player.chat", array($this, "handler"), 5);
		$this->api->addHandler("player.death", array($this, "handler"), 5);
		$this->api->addHandler("player.teleport", array($this, "handler"), 5);
		$this->api->addHandler("player.spawn", array($this, "initPlayer"), 5);
		$this->api->addHandler("player.block.break", array($this, "handler"), 5);
		$this->api->addHandler("console.command", array($this, "permissionsCheck"), 5);
		$this->api->addHandler("groupmanager.permission.check", array($this, "permissionsCheck"), 5);
		
		$this->api->console->register("home", "", array($this, "defaultCommands"));
		$this->api->console->register("sethome", "", array($this, "defaultCommands"));
		$this->api->console->register("delhome", "", array($this, "defaultCommands"));
		$this->api->console->register("mute", "<player>", array($this, "defaultCommands"));
		$this->api->console->register("back", "", array($this, "defaultCommands"));
		$this->api->console->register("heal", "[player]", array($this, "defaultCommands"));
		$this->api->console->register("tree", "<tree|brich|redwood>", array($this, "defaultCommands"));
		$this->api->console->register("clearinventory", "[player] [item]", array($this, "defaultCommands"));
		$this->api->console->register("setspawn", "", array($this, "defaultCommands"));
		$this->api->console->register("burn", "<player> <seconds>", array($this, "defaultCommands"));
		$this->api->console->register("kickall", "[reason]", array($this, "defaultCommands"));
		$this->api->console->register("killall", "[reason]", array($this, "defaultCommands"));
		$this->readConfig();
	}
	
	public function __destruct(){
	}
	
	public function readConfig(){
		if(is_dir("./plugins/Essentials/userdata/") === false){
			mkdir("./plugins/Essentials/userdata/", 0777, true);
		}
		$this->path = $this->api->plugin->createConfig($this, array(
			"login" => array(
				"allow-non-loggedIn" => array(
					"chat" => false,
					"commands" => array(),
					"move" => true,
				),
				"kick-on-wrong-password" => array(
					"enable" => false,
					"count" => 5,
				),
			),
			"chat-format" => "<{DISPLAYNAME}> {MESSAGE}",
			"blacklist" => array(
				"placement" => '',
				"usage" => '',
				"break" => '',
			),
			"kits" => array(),
			"newbies" => array(
				"kit" => "",
				"message" => "Welcome {DISPLAYNAME} to the server!",
			),
			"creative-item" => array(
				"op" => array(),
				"default" => array(),
			),
			"player-commands" => array(),
		));
		if(!file_exists($this->path."messages.yml")){
			console("[ERROR] \"messages.yml\" file not found!");
		}else{
			$this->lang = new Config($this->path."messages.yml", CONFIG_YAML);
		}
		$this->config = $this->api->plugin->readYAML($this->path."config.yml");
	}
	
	public function permissionsCheck($data, $event){
		switch($event){
			case "groupmanager.permission.check":
				if(in_array(substr($data["permission"], 11), $this->config["player-commands"])){
					return true;
				}
				return false;
			case "console.command":
				if(!($data["issuer"] instanceof Player) or $this->api->ban->isOp($data["issuer"]->iusername)){
					return true;
				}
				if($data["cmd"] === "heal" or $data["cmd"] === "clearinventory"){
					if($this->api->dhandle("groupmanager.permission.check", array("issuer" => $data["issuer"], "permission" => isset($data["parameters"][0]) ? "essentials.".$data["cmd"].".other" : "essentials.".$data["cmd"])) !== false){
						return true;
					}
					return false;
				}elseif($data["cmd"] === "unregister"){
					if($this->api->dhandle("groupmanager.permission.check", array("issuer" => $data["issuer"], "permission" => isset($data["parameters"][1]) ? "essentials.".$data["cmd"].".other" : "essentials.".$data["cmd"])) !== false){
						return true;
					}
					return false;
				}elseif(in_array($data["cmd"], self::$cmds)){// All essentials commands
					if($this->api->dhandle("groupmanager.permission.check", array("issuer" => $data["issuer"], "permission" => "essentials.".$data["cmd"])) !== false){
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
					$spawn = $data->level->getSpawn();
					$this->data[$data->iusername] = new Config(DATA_PATH."/plugins/Essentials/userdata/".$data->iusername.".yml", CONFIG_YAML, array(
						"ipAddress" => $data->ip,
						"mute" => false,
						"newbie" => true,
					));
				break;
			case "player.quit":
				if($this->data[$data->iusername] instanceof Config){
					$this->data[$data->iusername]->save();
				}
				break;
			case "player.chat":
				$data = array("player" => $data["player"], "message" => str_replace(array("{DISPLAYNAME}", "{MESSAGE}", "{WORLDNAME}"), array($data["player"]->username, $data["message"], $data["player"]->level->getName()), $this->config["chat-format"]));
				if($this->api->handle("essentials.".$event, $data) !== false){
					$this->api->chat->broadcast($data["message"]);
				}
				return false;
			case "player.death":
				$data["player"]->sendChat("Use the /back command to return to your death point.");
				break;
			case "player.teleport":
				$this->data[$data["player"]->iusername]->set("lastlocation", array(
					"world" => $data["player"]->level->getName(),
					"x" => $data["player"]->entity->x,
					"y" => $data["player"]->entity->y,
					"z" => $data["player"]->entity->z,
				));
				break;
			case "player.block.break":
				if($data["target"]->getID() === SIGN_POST or $data["target"]->getID() === WALL_SIGN){
					$t = $this->api->tile->get($data["target"]);
					$this->api->tile->remove($t->id);
				}
				break;
		}
	}
	
	public function initPlayer($data, $event){
		if($this->data[$data->iusername]->get("newbie")){
			switch($data->gamemode){
				case SURVIVAL:
					if(!array_key_exists($this->config["newbies"]["kit"], $this->config["kits"])){
						break;
					}
					$kits = $this->config["kits"][$this->config["newbies"]["kit"]];
					foreach($kits as $kit){
						$kit = explode(" ", $kit);
						$item = BlockAPI::fromString(array_shift($kit));
						$count = $kit[0];
						$data->addItem($item->getID(), $item->getMetadata(), $count);
					}
					break;
				case CREATIVE:
					break;
			}
			$data->sendChat(str_replace(array("{DISPLAYNAME}", "{WORLDNAME}", "{GROUP}"), array($data->username, $data->level->getName(), ""), $this->config["newbies"]["message"]));
			$this->data[$data->iusername]->set("newbie", false);
		}
		if($data->gamemode === CREATIVE){
			$type = $this->api->ban->isOp($data->iusername) ? "op" : "default";
			$creative = $this->config["creative-item"][$type];
			foreach($creative as $item){
				$item = explode(" ", $item);
				$data->setSlot($item[0], BlockAPI::fromString($item[1]));
			}
		}
	}
	
	public function defaultCommands($cmd, $params, $issuer, $alias){
		$output = "";
		if(!($issuer instanceof Player)){
			break;
		}
		switch($cmd){
			case "home":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				if($this->data[$issuer->iusername]->exists("home")){
					$home = $this->data[$issuer->iusername]->get("home");
					$name = $issuer->iusername;
					if($home["world"] !== $issuer->level->getName()){
						$this->api->player->teleport($name, "w:".$home["world"]);
					}
					$this->api->player->tppos($name, $home["x"], $home["y"], $home["z"]);
				}else{
					$output .= "You do not have a home.\n";
				}
				break;
			case "sethome":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				$this->data[$issuer->iusername]->set("home", array(
					"world" => $issuer->level->getName(),
					"x" => $issuer->entity->x,
					"y" => $issuer->entity->y,
					"z" => $issuer->entity->z,
				));
				$output .= "Your home has been saved.\n";
				break;
			case "delhome":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				$spawn = $issuer->level->getSpawn();
				$this->data[$issuer->iusername]->remove("home");
				$output .= "Your home has been deleted.\n";
				break;
			case "back":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				if($this->data[$issuer->iusername]->exists("lastlocation")){
					$backpos = $this->data[$issuer->iusername]->get("lastlocation");
					$name = $issuer->iusername;
					if($backpos["world"] !== $issuer->level->getName()){
						$this->api->player->teleport($name, "w:".$backpos["world"]);
					}
					$this->api->player->tppos($name, $backpos["x"], $backpos["y"], $backpos["z"]);
					$output .= "Returning to previous location.\n";
				}
				break;
			case "tree":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				switch(strtolower($params[0])){
					case "redwood":
						$meta = 1;
						break;
					case "brich":
						$meta = 2;
						break;
					case "tree":
						$meta = 0;
						break;
					default:
						$output .= "Usage: /$cmd <tree|brich|redwood>\n";
						break 2;
				}
				TreeObject::growTree($issuer->level, new Vector3 (((int)$issuer->entity->x), ((int)$issuer->entity->y), ((int)$issuer->entity->z)), new Random(), $meta);
				$output .= $this->getMessage("treeSpawned");
				break;
			case "setspawn":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				$pos = new Vector3(((int)$issuer->entity->x + 0.5), ((int)$issuer->entity->y), ((int)$issuer->entity->z + 0.5));
				$output .= "Spawn location set.\n";
				$issuer->level->setSpawn($pos);
				break;
			case "mute":
				if($params[0] == ""){
					$output .= "Usage: /$cmd <player>\n";
					break;
				}
				$target = $this->api->player->get($params[0]);
				if($target === false){
					$output .= $this->getMessage("playerNotFound");
					break;
				}
				if($this->data[$target->iusername]->get("mute") === false){
					$output .= "Player ".$target->username." muted.\n";
					$target->sendChat($this->getMessage("playerMuted"));
					$this->data[$target->iusername]->set("mute", true);
				}else{
					$output .= "Player ".$target->username." unmuted.\n";
					$target->sendChat($this->getMessage("playerUnmuted"));
					$this->data[$target->iusername]->set("mute", false);
				}
				break;
			case "heal":
				if(!($issuer instanceof Player) and $params[0] == ""){
					$output .= "Usage: /$cmd <player>\n";
					break;
				}
				if($params[0] != ""){
					$player = $this->api->player->get($params[0]);
					if($player === false){
						$output .= $this->getMessage("playerNotFound");
						break;
					}
				}
				$this->api->entity->heal($player->eid, 20);
				break;
			case "clearinventory":
				if(!($issuer instanceof Player) and $params[0] == ""){
					$output .= "Usage: /$cmd <player> [item]\n";
					break;
				}
				$player = $issuer;
				if($params[0] != ""){
					$player = $this->api->player->get($params[0]);
					if($player === false){
						$output .= $this->getMessage("playerNotFound");
						break;
					}
				}
				if($player->gamemode === CREATIVE){
					$output .= "Player is in creative mode.\n";
					break;
				}
				$item = false;
				if($params[1] != ""){
					$item = BlockAPI::fromString($params[1]);
				}
				foreach($player->inventory as $slot => $data){
					if($item !== false and $item->getID() !== $data->getID()){
						continue;
					}
					$player->setSlot($slot, BlockAPI::getItem(AIR, 0, 0));
				}
				break;
			case "burn":
				if($params[0] == "" or $params[1] == ""){
					$output .= "Usage: /$cmd <player> <seconds>\n";
					break;
				}
				$player = $this->api->player->get($params[0]);
				$player->entity->fire = (int)$params[1];
				break;
			case "kickall":
				if($params[0] == ""){
					$output .= "Usage: /$cmd [reason]\n";
					break;
				}
				foreach($this->api->player->online() as $username){
					$this->api->ban->kick($username, $reason = $params[0]);
				}
				break;
			case "killall":
				if($params[0] == ""){
					$output .= "Usage: /$cmd [reason]\n";
					break;
				}
				foreach($this->api->player->online() as $username){
					$target = $this->api->player->get($username);
					$this->api->entity->harm($target->eid, 3000, $reason = $params[0]);
				}
				break;
		}
		return $output;
	}
	
	public function getMessage($msg, $params = array("", "", "", "")){
		$msgs = array_merge($this->lang->get("Default"), $this->lang->get("Essentials"));
		if(!isset($msgs[$msg])){
			return $this->getMessage("noMessages", array($msg));
		}
		return str_replace(array("%1", "%2", "%3", "%4"), array($params[0], $params[1], $params[2], $params[3]), $msgs[$msg])."\n";
	}
}