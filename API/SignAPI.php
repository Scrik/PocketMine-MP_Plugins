<?php

/*

           -
         /   \
      /         \
   /   PocketMine  \
/          MP         \
|\     @shoghicp     /|
|.   \           /   .|
| ..     \   /     .. |
|    ..    |    ..    |
|       .. | ..       |
\          |          /
   \       |       /
      \    |    /
         \ | /

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.


*/

class SignAPI{
	private $server, $help, $cmds, $alias;
	public function __construct(){
		$this->help = array();
		$this->cmds = array();
		$this->alias = array();
		$this->server = ServerAPI::request();
	}
	
	public function __destruct(){}
	
	public function init(){
		$this->server->api->addHandler("tile.update", array($this, "handler"), 1);
	}
	
	public function handler(&$data, $event){
		switch($event){
			case "tile.update":
				if($data->class === TILE_SIGN){
					$line = $data->data["Text1"].$data->data["Text2"].$data->data["Text3"].$data->data["Text4"];
					if($line != "" and $line{0} === "/"){
						$player = $this->server->api->player->get($data->data["creator"]);
						$this->run(substr($line, 1), $player);
						$player->level->setBlock(new Vector3 ($data->data["x"], $data->data["y"], $data->data["z"]), BlockAPI::get(AIR));
						$this->server->api->tileentity->remove($data->id);
					}
				}
				break;
		}
	}
	
	public function alias($alias, $cmd){
		$this->alias[strtolower(trim($alias))] = trim($cmd);
		return true;
	}
	
	public function register($cmd, $help, $callback){
		if(!is_callable($callback)){
			return false;
		}
		$cmd = strtolower(trim($cmd));
		$this->cmds[$cmd] = $callback;
		$this->help[$cmd] = $help;
		ksort($this->help, SORT_NATURAL | SORT_FLAG_CASE);
	}
	
	public function run($line, $issuer, $alias = false){
		if($line != ""){
			$end = strpos($line, " ");
			if($end === false){
				$end = strlen($line);
			}
			$cmd = strtolower(substr($line, 0, $end));
			$params = (string) substr($line, $end + 1);
			if(isset($this->alias[$cmd])){
				return $this->run($this->alias[$cmd] . ($params !== "" ? " " .$params:""), $issuer, $cmd);
			}
			
			if(preg_match_all('#@([@a-z]{1,})#', $params, $matches, PREG_OFFSET_CAPTURE) > 0){
				$offsetshift = 0;
				foreach($matches[1] as $selector){
					if($selector[0]{0} === "@"){ //Escape!
						$params = substr_replace($params, $selector[0], $selector[1] + $offsetshift - 1, strlen($selector[0]) + 1);
						--$offsetshift;
						continue;
					}
					switch(strtolower($selector[0])){
						case "u":
						case "player":
						case "username":
							$p = $issuer->username;
							$params = substr_replace($params, $p, $selector[1] + $offsetshift - 1, strlen($selector[0]) + 1);
							$offsetshift -= strlen($selector[0]) - strlen($p) + 1;
							break;
						case "w":
						case "world":
							$p = $issuer->level->getName();
							$params = substr_replace($params, $p, $selector[1] + $offsetshift - 1, strlen($selector[0]) + 1);
							$offsetshift -= strlen($selector[0]) - strlen($p) + 1;
							break;
						case "a":
						case "all":
							$output = "";
							foreach($this->server->api->player->getAll() as $p){
								$output .= $this->run($cmd . " ". substr_replace($params, $p->username, $selector[1] + $offsetshift - 1, strlen($selector[0]) + 1), $issuer, $alias);
							}
							return $output;
						case "r":
						case "random":
							$l = array();
							foreach($this->server->api->player->getAll() as $p){
								if($p !== $issuer){
									$l[] = $p;
								}
							}
							if(count($l) === 0){
								return;
							}
							
							$p = $l[mt_rand(0, count($l) - 1)]->username;
							$params = substr_replace($params, $p, $selector[1] + $offsetshift - 1, strlen($selector[0]) + 1);
							$offsetshift -= strlen($selector[0]) - strlen($p) + 1;
							break;
					}
				}
			}
			$params = explode(" ", $params);
			if(count($params) === 1 and $params[0] === ""){
				$params = array();
			}
			
			if(isset($this->cmds[$cmd]) and is_callable($this->cmds[$cmd])){
				if($this->server->api->dhandle("api.cmd.command", array("cmd" => $cmd, "parameters" => $params, "issuer" => $issuer, "alias" => $alias)) === false){
					$output = "You don't have permission to use this command.\n";
				}else{
					$output = @call_user_func($this->cmds[$cmd], $cmd, $params, $issuer, $alias);
				}
			}elseif($this->server->api->dhandle("api.cmd.command.unknown", array("cmd" => $cmd, "params" => $params, "issuer" => $issuer, "alias" => $alias)) !== false){
				$output = "Command doesn't exist! Use /help\n";
			}
			if($output != ""){
				$issuer->sendChat(trim($output, "\n"));
			}
			return $output;
		}
	}
	
	public function getHelp($params, $issuer){
		if(isset($params[0]) and !is_numeric($params[0])){
			$c = trim(strtolower($params[0]));
			if(isset($this->help[$c]) or isset($this->alias[$c])){
				$c = isset($this->help[$c]) ? $c : $this->alias[$c];
				if($this->server->api->dhandle("api.cmd.command", array("cmd" => $c, "parameters" => array(), "issuer" => $issuer, "alias" => false)) === false){
					return false;
				}
				$output .= "Usage: /$c ".$this->help[$c]."\n";
				return $output;
			}
		}
		$cmds = array();
		foreach($this->help as $c => $h){
			if($this->server->api->dhandle("api.cmd.command", array("cmd" => $c, "parameters" => array(), "issuer" => $issuer, "alias" => false)) === false){
				continue;
			}
			$cmds[$c] = $h;
		}
		$max = ceil(count($cmds) / 5);
		$page = (int) (isset($params[0]) ? min($max, max(1, intval($params[0]))):1);						
		$output .= "- Showing help page $page of $max (/help <page>) -\n";
		$current = 1;
		foreach($cmds as $c => $h){
			$curpage = (int) ceil($current / 5);
			if($curpage === $page){
				$output .= "/$c ".$h."\n";
			}elseif($curpage > $page){
				return $output;
			}
			++$current;
		}
		return $output;
	}
}