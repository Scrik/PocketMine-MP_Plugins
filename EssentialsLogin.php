<?php

/*
__PocketMine Plugin__
name=EssentialsLogin
description=EssentialsLogin
version=0.0.1
author=KsyMC
class=EssentialsLogin
apiversion=8
*/

/*
Small Changelog
===============

1.0:
- Release

*/

class EssentialsLogin implements Plugin{
	private $api;
	private $config;
	private $password;
	private $status;
	private $registered;
	private $lang;
	private $forget;
	
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
	}
	
	public function init(){
		$this->api->event("server.close", array($this, "handler"));
		$this->api->addHandler("player.join", array($this, "handler"), 5);
		$this->api->addHandler("player.chat", array($this, "permissionsCheck"), 6);
		$this->api->addHandler("player.move", array($this, "permissionsCheck"), 6);
		$this->api->addHandler("player.interact", array($this, "permissionsCheck"), 6);
		$this->api->addHandler("player.block.touch", array($this, "permissionsCheck"), 6);
		$this->api->addHandler("console.command", array($this, "permissionsCheck"), 6);
		$this->api->addHandler("player.block.activate", array($this, "permissionsCheck"), 6);
		
		$this->api->console->register("register", "<password> <password>", array($this, "commandHandler"));
		$this->api->console->register("login", "<password>", array($this, "commandHandler"));
		$this->api->console->register("logout", "", array($this, "commandHandler"));
		$this->api->console->register("changepassword", "<oldpassword> <newpassword>", array($this, "commandHandler"));
		$this->api->console->register("unregister", "<password> [player]", array($this, "commandHandler"));
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
			case "player.join":
				$this->newPlayer($data);
				break;
		}
	}
	
	public function permissionsCheck($data, $event){
		switch($event){
			case "player.chat":
			case "player.block.touch":
			case "player.block.activate":
				$player = $data["player"];
				break;
			case "console.command":
				if(!($data["issuer"] instanceof Player)){
					return;
				}
				$player = $data["issuer"];
				break;
			case "player.move":
				$player = $data->player;
				break;
			case "player.interact":
				$player = $data["entity"]->player;
				break;
		}
		if($this->getPlayerStatus($player) === "logout"){
			if($event === "player.move"){
				if(!$this->config["login"]["allow-non-loggedIn"]["move"] and
				($data->player->lastCorrect->x !== $data->player->entity->x or
				$data->player->lastCorrect->y !== $data->player->entity->y or
				$data->player->lastCorrect->z !== $data->player->entity->z)){
					return false;
				}
			}elseif($event === "player.chat"){
				if(!$this->config["login"]["allow-non-loggedIn"]["chat"]){
					return false;
				}
			}elseif($event === "console.command"){
				if(in_array($data["cmd"], $this->config["login"]["allow-non-loggedIn"]["commands"])){
					return true;
				}
				return false;
			}else{
				return false;
			}
		}
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
					$output .= $this->getMessage("Error.WrongPassword");
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
					$output .= $this->getMessage("Error.InvalidPassword", array($this->forget[$issuer->iusername], $this->config["login"]["kick-on-wrong-password"]["count"]));
					if($this->config["login"]["kick-on-wrong-password"]["enable"]){
						if($this->forget[$issuer->iusername] >= $this->config["login"]["kick-on-wrong-password"]["count"]){
							$this->api->ban->kick($issuer->username, $this->getMessage("Error.InvalidPassword"));
							break;
						}
					}
					$this->forget[$issuer->iusername] += 1;
					break;
				}
				$output .= $this->getMessage("login");
				$this->setPlayerStatus($issuer, "login");
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
				$realpassword = $this->getPlayerPassword($issuer);
				if(!$this->comparePassword($oldpassword, $realpassword)){
					$output .= $this->getMessage("enterPasswordAgain");
				}
				$this->setPlayerPassword($issuer, $newpassword);
				$output .= $this->getMessage("changepassword");
				break;
			case "unregister":
				if(!($issuer instanceof Player)){
					if($params[0] == "" or $params[1] == ""){
						$output .= "Usage: /unregister <password> [player]\n";
						break;
					}
					$issuer = $this->api->player->get($params[1]);
					if($issuer === false){
						$output .= $this->getMessage("playerNotFound");
						break;
					}
					$params[0] = $this->getPlayerPassword($issuer);
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
					$output .= $this->getMessage("Error.InvalidPassword");
					break;
				}
				$this->setPlayerPassword($issuer, false, true);
				$this->setPlayerStatus($issuer, "logout");
				$output .= $this->getMessage("unregister");
				break;
		}
		return $output;
	}
	
	public function newPlayer(Player $player){
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
	
	public function getMessage($msg, $params = array("", "", "", "")){
		$msgs = array_merge($this->lang->get("Default"), $this->lang->get("Login"));
		if(!isset($msgs[$msg])){
			return $this->getMessage("noMessages", array($msg));
		}
		return str_replace(array("%1", "%2", "%3", "%4"), array($params[0], $params[1], $params[2], $params[3]), $msgs[$msg])."\n";
	}
}