<?php

/*
__PocketMine Plugin__
name=EssentialsProtect
description=EssentialsProtect
version=0.0.1
author=KsyMC
class=EssentialsProtect
apiversion=7
*/

/*
Small Changelog
===============

1.0:
- Release

*/

class EssentialsProtect implements Plugin{
	private $api, $config, $protect, $data;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
	}
	
	public function __destruct(){}
	
	public function init(){
		foreach($this->api->plugin->getList() as $p){
			if($p["name"] === "Essentials"){
				$found = true;
				break;
			}
		}
		if(!isset($found)){
			console("[ERROR] Can not find Essentials plugin");
			console("Stopping the server");
			$this->api->console->defaultCommands("stop", array(), "plugin", false);
		}
		$this->config = $this->api->plugin->readYAML("./plugins/Essentials/config.yml");
		$this->data = new Config(DATA_PATH."/plugins/Essentials/Protectdata.yml", CONFIG_YAML);
		foreach($this->data->getAll() as $tile){
			if(!isset($tile["id"])){
				break;
			}
			$this->add($this->api, $tile["id"], $tile["x"], $tile["y"], $tile["z"], $this->api->level->get($tile["world"]), $tile);
		}
		
		$this->api->event("server.close", array($this, "handler"));
		$this->api->addHandler("player.join", array($this, "handler"), 5);
		$this->api->addHandler("player.flying", array($this, "handler"), 7);
		$this->api->addHandler("player.block.break", array($this, "handler"), 7);
		$this->api->addHandler("player.block.place", array($this, "handler"), 7);
		$this->api->addHandler("player.block.touch", array($this, "handler"), 7);
		$this->api->addHandler("player.block.activate", array($this, "handler"), 7);
		
		$this->api->sign->register("blundo", "<player>", array($this, "defaultCommands"));
	}
	
	public function handler(&$data, $event){
		switch($event){
			case "server.close":
				foreach($this->getAll() as $tile){
					$tiles[] = $tile->data;
				}
				$this->data->setAll($tiles);
				$this->data->save();
				break;
			case "player.block.place":
				if($data["item"]->getID() === CHEST){
					$protect = $this->addChest($this->api, $data["block"]->x, $data["block"]->y, $data["block"]->z, $data["player"]->level);
					$protect->data["owner"] = $data["player"]->__get("iusername");
					$protect->data["protected"] = false;
					break;
				}
				if($this->api->ban->isOp($data["player"]->__get("iusername")) === false){
					$items = BlockAPI::fromString($this->config["blacklist"]["placement"], true);
					foreach($items as $item){
						if($data["item"]->getID() === $item->getID() and $data["item"]->getMetadata() === $item->getMetadata()){
							return false;
						}
					}
				}
				break;
			case "player.block.break":
				if($data["target"]->getID() === CHEST){
					$t = $this->get(new Position($data["target"]->x, $data["target"]->y, $data["target"]->z, $data["player"]->level));
					if($t !== false){
						$ret = $t->onBreak($output, $data["player"]);
						if($output != ""){
							$data["player"]->sendChat($output);
						}
						if($ret === true){
							$this->remove(new Position($t->x, $t->y, $t->z, $t->level));
						}
						return $ret;
					}
					break;
				}
				if($this->api->ban->isOp($data["player"]->__get("iusername")) === false){
					$items = BlockAPI::fromString($this->config["blacklist"]["break"], true);
					foreach($items as $item){
						if($data["target"]->getID() === $item->getID() and $data["target"]->getMetadata() === $item->getMetadata()){
							return false;
						}
					}
				}
				break;
			case "player.block.activate":
				if($data["target"]->getID() === CHEST){
					$output = "";
					$t = $this->get(new Position($data["target"]->x, $data["target"]->y, $data["target"]->z, $data["player"]->level));
					if($data["item"]->getID() === STICK and ($this->api->ban->isOp($data["player"]->__get("iusername")) or $t->data["owner"] === $data["player"]->__get("iusername"))){
						$t->protectChange($output);
						if($output != ""){
							$data["player"]->sendChat($output);
						}
						return false;
					}
					if($t !== false){
						$open = $t->onOpen($output, $data["player"]);
						if($output != ""){
							$data["player"]->sendChat($output);
						}
						return $open;
					}
				}elseif($data["target"]->getID() === WOOD_DOOR_BLOCK){
					return false;
				}
				break;
		}
	}
	
	public function add($api, $class, $x, $y, $z, Level $level, $data){
		$protect = new Protect ($api, $class, $x, $y, $z, $level, $data);
		$this->protect[] = $protect;
		return $protect;
	}
	
	public function addChest($api, $x, $y, $z, Level $level){
		return $this->add($api, "Chest", $x, $y, $z, $level, array(
			"id" => "Chest",
			"x" => $x,
			"y" => $y,
			"z" => $z,
			"world" => $level->getName(),
		));
	}
	
	public function get(Position $pos){
		foreach($this->protect as $t){
			if($pos->level->getName() === $t->level->getName() and $pos->x === $t->x and $pos->y === $t->y and $pos->z === $t->z){
				return $t;
			}
		}
		return false;
	}
	
	public function getAll($level = false){
		if($level instanceof Level){
			foreach($this->protect as $t){
				if($level->getName() === $t->level->getName()){
					return $protect[] = $t;
				}
			}
			return $protect;
		}
		return $this->protect;
	}
	
	public function remove(Position $pos){
		foreach($this->protect as $key => $t){
			if($pos->level->getName() === $t->level->getName() and $pos->x === $t->x and $pos->y === $t->y and $pos->z === $t->z){
				$this->protect[$key] = null;
				unset($this->protect[$key]);
				break;
			}
		}
	}
	
	public function defaultCommands($cmd, $params, $issuer, $alias){
		$output = "";
		switch($cmd){
			case "blundo":
				break;
		}
		return $output;
	}
}

class Protect{
	public $api, $x, $y, $z, $data, $class, $level;
	public function __construct(ServerAPI $api, $class, $x, $y, $z, $level, $data){
		$this->api = $api;
		$this->level = $level;
		$this->x = $x;
		$this->y = $y;
		$this->z = $z;
		$this->class = $class;
		$this->data = $data;
	}
	
	public function __destruct(){}
	
	public function onOpen(&$output, $player){
		if($this->check($output, $player->__get("iusername"))){
		}else{
			return false;
		}
		return true;
	}
	
	public function onBreak(&$output, $player){
		if($this->data["protected"] === true){
			return true;
		}
		if($this->api->ban->isOp($player->__get("iusername")) or $this->check($output, $player->__get("iusername"))){
			return true;
		}else{
			return false;
		}
	}
	
	public function check(&$output, $target){
		if($this->data["protected"] === true){
			if($this->data["owner"] !== $target){
				$owner = $this->api->player->get($this->data["owner"]);
				$output = "You are not the owner of the Chest. Owner : $owner";
				return false;
			}else{
				$output = "My chest!";
			}
		}else{
			$output = "This is a public chest.";
		}
		return true;
	}
	
	public function protectChange(&$output){
		if($this->data["protected"] === false){
			$owner = $this->api->player->get($this->data["owner"]);
			$output = "The chest can only open the $owner.";
			$this->data["protected"] = true;
		}else{
			$output = "This is now public chest.";
			$this->data["protected"] = false;
		}
	}
}