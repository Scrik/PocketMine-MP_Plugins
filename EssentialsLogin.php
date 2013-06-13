<?php

/*
__PocketMine Plugin__
name=EssentialsLogin
description=EssentialsLogin
version=1.1 dev
author=KsyMC
class=EssentialsLogin
apiversion=9
*/

/*
Small Changelog
===============

1.0:
- Release

*/

class EssentialsLogin implements Plugin{
	private $api, $config, $password, $status, $registered, $lang, $forget, $data;
	
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
	}
	
	public function init(){
		$this->api->event("server.close", array($this, "handler"));
		$this->api->addHandler("player.join", array($this, "newPlayer"), 5);
		$this->api->addHandler("player.spawn", array($this, "resetPlayer"), 5);
		$this->api->addHandler("player.chat", array($this, "permissionsCheck"), 6);
		$this->api->addHandler("player.move", array($this, "permissionsCheck"), 6);
		$this->api->addHandler("player.interact", array($this, "permissionsCheck"), 6);
		$this->api->addHandler("player.block.touch", array($this, "permissionsCheck"), 6);
		$this->api->addHandler("console.command", array($this, "permissionsCheck"), 6);
		$this->api->addHandler("entity.health.change", array($this, "permissionsCheck"), 6);
		
		$this->api->console->register("register", "<password> <password>", array($this, "commandHandler"));
		$this->api->console->register("login", "<password>", array($this, "commandHandler"));
		$this->api->console->register("logout", "", array($this, "commandHandler"));
		$this->api->console->register("changepassword", "<oldpassword> <newpassword>", array($this, "commandHandler"));
		$this->api->console->register("unregister", "<password>", array($this, "commandHandler"));
		$this->readConfig();
	}
	
	public function __destruct(){
	}
	
	public function readConfig(){
		$this->path = DATA_PATH."/plugins/Essentials/";
		$this->config = $this->api->plugin->readYAML($this->path."config.yml");
		if(file_exists($this->path."messages.yml")){
			$this->lang = new Config($this->path."messages.yml", CONFIG_YAML);
		}
		if(file_exists($this->path."Logindata.dat")){
			$data = unserialize(file_get_contents($this->path."Logindata.dat"));
			if(is_array($data)){
				$this->password = $data["password"];
				$this->registered = $data["registered"];
			}
		}
	}
	
	public function handler(&$data, $event){
		switch($event){
			case "server.close":
				file_put_contents("./plugins/Essentials/Logindata.dat", serialize(array("password" => $this->password, "registered" => $this->registered)));
				break;
		}
	}
	
	public function permissionsCheck($player, $event, $data = false){
		if(!($player instanceof Player)){
			$data = $player;
			switch($event){
				case "console.command":
					if(!($data["issuer"] instanceof Player)) return;
					$player = $data["issuer"];
					break;
				case "player.move":
					$player = $data->player;
					break;
				case "player.chat":
				case "player.block.touch":
					$player = $data["player"];
					break;
				case "entity.health.change":
					if(!($data["entity"]->player instanceof Player)) return;
				case "player.interact":
					$player = $data["entity"]->player;
					break;
			}
		}
		if($this->getPlayerStatus($player) === "logout"){
			switch($event){
				case "player.move":
					if($this->config["login"]["allow-non-loggedIn"]["move"]){
						break;
					}
					if($player->lastCorrect->x !== $player->entity->x or $player->lastCorrect->y !== $player->entity->y or $player->lastCorrect->z !== $player->entity->z){
						if($this->data[$player->iusername]["count"] <= 0){
							$this->data[$player->iusername]["count"] = 100;
							$username = $player->username;
							$pos = $player->level->getSpawn();
							$this->api->player->tppos($username, $pos->x, $pos->y, $pos->z);
						}else{
							$this->data[$player->iusername]["count"] -= 1;		
						}
					}
					break;
				case "console.command":
					if(in_array($data["cmd"], $this->config["login"]["allow-non-loggedIn"]["commands"])){
						return true;
					}
					return false;
				case "player.chat":
					if(!$this->config["login"]["allow-non-loggedIn"]["chat"]){
						return false;
					}
					break;
				case "entity.health.change":
					if(!$this->config["login"]["allow-non-loggedIn"]["damage"]){
						return false;
					}
					break;
				default:
					return false;
			}
		}
	}
	
	public function resetPlayer(Player $player, $event = false){
		$this->data[$player->iusername] = array(
			"spawnpos" => array(
				$player->entity->x,
				$player->entity->y,
				$player->entity->z,
			),
			"count" => 100,
		);
		foreach($player->inventory as $slot => $item){
			$this->data[$player->iusername]["inventory"][$slot] = $player->getSlot($slot);
			$player->setSlot($slot, BlockAPI::getItem(AIR, 0, 0));
		}
		$username = $player->username;
		$pos = $player->level->getSpawn();
		$this->api->player->tppos($username, $pos->x, $pos->y, $pos->z);
	}
	
	public function returnPlayer(Player $player){
		foreach($this->data[$player->iusername]["inventory"] as $slot => $item){
			$player->setSlot($slot, $item);
		}
		$pos = $this->data[$player->iusername]["spawnpos"];
		$this->api->player->tppos($player, $pos[0], $pos[1], $pos[2]);
	}
	
	public function newPlayer(Player $player, $event = false){
		if(!isset($this->password[$player->iusername])){
			$this->registered[$player->iusername] = false;
		}
		$this->setPlayerStatus($player, "logout");
		$this->forget[$player->iusername] = 0;
	}
	
	public function getPlayerPassword(Player $player){
		if($this->registered[$player->iusername] === true){
			return $this->password[$player->iusername];
		}
		return false;
	}
	
	public function setPlayerPassword(Player $player, $password, $remove = false){
		if($remove === false){
			$this->password[$player->iusername] = hash("sha256", $password);
			$this->registered[$player->iusername] = true;
		}else{
			unset($this->password[$player->iusername]);
			$this->registered[$player->iusername] = false;
		}
	}
	
	public function isPlayerRegistered(Player $player){
		return $this->registered[$player->iusername];
	}
	
	public function setPlayerStatus(Player $player, $status){
		$this->status[$player->iusername] = $status;
	}
	
	public function getPlayerStatus(Player $player){
		return $this->status[$player->iusername];
	}
	
	public function comparePassword($password, $hash){
		if(hash("sha256", $password) === $hash){
			return true;
		}
		return false;
	}
	
	public function commandHandler($cmd, $params, $issuer, $alias){
		$output = "";
		switch($cmd){
			case "register":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				if($params[0] == "" or $params[1] == ""){
					$output .= "Usage: /register <password> <password>\n";
					break;
				}
				if($params[0] !== $params[1]){
					$output .= $this->getMessage("enterPasswordAgain");
					break;
				}
				$password = $params[0];
				if(strlen($password) < 4 or strlen($password) > 15){
					$output .= $this->getMessage("passwordIncorrect");
					break;
				}
				if($this->isPlayerRegistered($issuer) === true){
					$output .= $this->getMessage("alreadyRegistered");
					break;
				}
				$this->setPlayerPassword($issuer, $password);
				$output .= $this->getMessage("register");
				break;
			case "login":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				if($params[0] == ""){
					$output .= "Usage: /login <password>\n";
					break;
				}
				if($this->getPlayerStatus($issuer) === "login"){
					$output .= $this->getMessage("alreadyLogged");
					break;
				}
				$password = $params[0];
				if($this->isPlayerRegistered($issuer) === false){
					$output .= $this->getMessage("notRegistered");
					break;
				}
				$realpassword = $this->getPlayerPassword($issuer);
				if(!$this->comparePassword($password, $realpassword)){
					$output .= $this->getMessage("notPasswordMatch", array($this->forget[$issuer->iusername], $this->config["login"]["kick-on-wrong-password"]["count"]));
					if($this->config["login"]["kick-on-wrong-password"]["enable"]){
						if($this->forget[$issuer->iusername] >= $this->config["login"]["kick-on-wrong-password"]["count"]){
							$this->api->ban->kick($issuer->username, $this->getMessage("notPasswordMatch"));
							break;
						}
					}
					$this->forget[$issuer->iusername] += 1;
					break;
				}
				$output .= $this->getMessage("login");
				$this->setPlayerStatus($issuer, "login");
				
				$this->returnPlayer($issuer);
				$this->api->dhandle("essentials.player.login", $issuer);
				break;
			case "logout":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				if($this->getPlayerStatus($issuer) === "logout"){
					$output .= $this->getMessage("notLogged");
					break;
				}
				$this->setPlayerStatus($issuer, "logout");
				$output .= $this->getMessage("logout");
				
				$this->resetPlayer($issuer);
				$this->api->dhandle("essentials.player.logout", $issuer);
				break;
			case "changepassword":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				if($params[0] == "" or $params[1] == ""){
					$output .= "Usage: /changepassword <oldpassword> <newpassword>\n";
					break;
				}
				$oldpassword = $params[0];
				$newpassword = $params[1];
				if($this->isPlayerRegistered($issuer) === false){
					$output .= $this->getMessage("notRegistered");
					break;
				}
				if($this->getPlayerStatus($issuer) === "logout"){
					$output .= $this->getMessage("notLogged");
					break;
				}
				$realpassword = $this->getPlayerPassword($issuer);
				if(!$this->comparePassword($oldpassword, $realpassword)){
					$output .= $this->getMessage("enterPasswordAgain");
				}
				$this->setPlayerPassword($issuer, $newpassword);
				$output .= $this->getMessage("changepassword");
				break;
			case "unregister":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				if($params[0] == ""){
					$output .= "Usage: /unregister <password>\n";
					break;
				}
				$password = $params[0];
				if($this->isPlayerRegistered($issuer) === false){
					$output .= $this->getMessage("notRegistered");
					break;
				}
				$realpassword = $this->getPlayerPassword($issuer);
				if(!$this->comparePassword($password, $realpassword)){
					$output .= $this->getMessage("notPasswordMatch", array($this->forget[$issuer->iusername], $this->config["login"]["kick-on-wrong-password"]["count"]));
					break;
				}
				$this->setPlayerPassword($issuer, false, true);
				$output .= $this->getMessage("unregister");
				
				$this->resetPlayer($issuer);
				$this->setPlayerStatus($issuer, "logout");
				break;
		}
		return $output;
	}
	
	public function getMessage($msg, $params = array("%1", "%2", "%3", "%4")){
		$msgs = array_merge($this->lang->get("Default"), $this->lang->get("Login"));
		if(!isset($msgs[$msg])){
			return $this->getMessage("noMessages", array($msg));
		}
		return str_replace(array("%1", "%2", "%3", "%4"), array($params[0], $params[1], $params[2], $params[3]), $msgs[$msg])."\n";
	}
}