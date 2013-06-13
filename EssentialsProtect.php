<?php

/*
__PocketMine Plugin__
name=EssentialsProtect
description=EssentialsProtect
version=1.0.1
author=KsyMC
class=EssentialsProtect
apiversion=9
*/

/*
Small Changelog
===============

1.0:
- Release

*/

class EssentialsProtect implements Plugin{
	private $api, $path, $lang, $config;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
	}
	
	public function init(){
		$this->api->event("server.close", array($this, "handler"));
		$this->api->addHandler("player.block.break", array($this, "permissionsCheck"), 7);
		$this->api->addHandler("player.block.place", array($this, "permissionsCheck"), 7);
		$this->api->addHandler("player.block.touch", array($this, "permissionsCheck"), 7);
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
	}
	
	public function handler(&$data, $event){
		switch($event){
			case "server.close":
				break;
		}
	}
	
	public function permissionsCheck($data, $event){
		if(!$this->api->ban->isOp($data["player"]->username)){
			switch($event){
				case "player.block.touch":
					$items = BlockAPI::fromString($this->config["blacklist"]["usage"], true);
					$type = "item";
					break;
				case "player.block.break":
					$items = BlockAPI::fromString($this->config["blacklist"]["break"], true);
					$type = "target";
					break;
				case "player.block.place":
					$items = BlockAPI::fromString($this->config["blacklist"]["placement"], true);
					$type = "item";
					break;
			}
			foreach($items as $item){
				if($data[$type]->getID() === $item->getID() and $data[$type]->getMetadata() === $item->getMetadata()){
					return false;
				}
			}
		}
	}
	
	public function getMessage($msg, $params = array("%1", "%2", "%3", "%4")){
		$msgs = array_merge($this->lang->get("Default"), $this->lang->get("Protect"));
		if(!isset($msgs[$msg])){
			return $this->getMessage("noMessages", array($msg));
		}
		return str_replace(array("%1", "%2", "%3", "%4"), array($params[0], $params[1], $params[2], $params[3]), $msgs[$msg])."\n";
	}
}