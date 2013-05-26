<?php

/*
__PocketMine Plugin__
name=Essentials
description=Essentials
version=0.0.1
author=KsyMC
class=Essentials
apiversion=7
*/

/*
Small Changelog
===============

1.0:
- Release

*/

class Essentials implements Plugin{
	private $api, $sign;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
	}
	
	public function __destruct(){}
	
	public function init(){
		if(is_dir("./plugins/Essentials/userdata/") === false){
			mkdir("./plugins/Essentials/userdata/", 0777, true);
		}
		$this->createConfig();
		
		$this->api->event("server.close", array($this, "handler"));
		$this->api->addHandler("player.join", array($this, "handler"), 5);
		$this->api->addHandler("player.quit", array($this, "handler"), 5);
		$this->api->addHandler("player.flying", array($this, "handler"), 5);
		$this->api->addHandler("player.move", array($this, "handler"), 5);
		$this->api->addHandler("player.death", array($this, "handler"), 5);
		$this->api->addHandler("player.teleport", array($this, "handler"), 5);
		$this->api->addHandler("player.block.place", array($this, "handler"), 5);
		$this->api->addHandler("player.block.break", array($this, "handler"), 5);
		$this->api->addHandler("player.block.place.spawn", array($this, "handler"), 5);
		$this->api->addHandler("player.block.break.spawn", array($this, "handler"), 5);
		
		$this->api->sign->register("help", "[page|command name]", array($this, "defaultCommands"));
		$this->api->sign->register("say", "<message ...>", array($this, "defaultCommands"));
		$this->api->sign->register("home", "", array($this, "defaultCommands"));
		$this->api->sign->register("sethome", "", array($this, "defaultCommands"));
		$this->api->sign->register("delhome", "", array($this, "defaultCommands"));
		$this->api->sign->register("mute", "<player>", array($this, "defaultCommands"));
		$this->api->sign->register("back", "", array($this, "defaultCommands"));
		$this->api->sign->register("tree", "<tree|brich|redwood>", array($this, "defaultCommands"));
		$this->api->sign->register("clear", "", array($this, "defaultCommands"));
		$this->api->sign->register("stop", "", array($this, "defaultCommands"));
		$this->api->sign->register("status", "", array($this, "defaultCommands"));
		$this->api->sign->register("invisible", "<on | off>", array($this, "defaultCommands"));
		$this->api->sign->register("difficulty", "<0|1|2>", array($this, "defaultCommands"));
		$this->api->sign->register("defaultgamemode", "<mode>", array($this, "defaultCommands"));
		$this->api->sign->register("seed", "[world]", array($this, "defaultCommands"));
		$this->api->sign->register("save-all", "", array($this, "defaultCommands"));
		$this->api->sign->register("save-on", "", array($this, "defaultCommands"));
		$this->api->sign->register("save-off", "", array($this, "defaultCommands"));
		$this->api->sign->register("list", "", array($this, "defaultCommands"));
		$this->api->sign->register("kill", "<player>", array($this, "defaultCommands"));
		$this->api->sign->register("gamemode", "<mode> [player]", array($this, "defaultCommands"));
		$this->api->sign->register("tp", "[target player] <destination player|w:world> OR /tp [target player] <x> <y> <z>", array($this, "defaultCommands"));
		$this->api->sign->register("spawnpoint", "[player] [x] [y] [z]", array($this, "defaultCommands"));
		$this->api->sign->register("spawn", "", array($this, "defaultCommands"));
		$this->api->sign->register("lag", "", array($this, "defaultCommands"));
		$this->api->sign->register("time", "<check|set|add> [time]", array($this, "defaultCommands"));
		$this->api->sign->register("banip", "<add|remove|list|reload> [IP|player]", array($this, "defaultCommands"));
		$this->api->sign->register("ban", "<add|remove|list|reload> [username]", array($this, "defaultCommands"));
		$this->api->sign->register("kick", "<player> [reason ...]", array($this, "defaultCommands"));
		$this->api->sign->register("whitelist", "<on|off|list|add|remove|reload> [username]", array($this, "defaultCommands"));
		$this->api->sign->register("op", "<player>", array($this, "defaultCommands"));
		$this->api->sign->register("deop", "<player>", array($this, "defaultCommands"));
		$this->api->sign->register("sudo", "<player>", array($this, "defaultCommands"));
		$this->api->sign->register("give", "<player> <item[:damage]> [amount]", array($this, "defaultCommands"));
		$this->api->sign->register("tell", "<player> <private message ...>", array($this, "defaultCommands"));
		$this->api->sign->register("me", "<action ...>", array($this, "defaultCommands"));
	}
	
	public function handler(&$data, $event){
		switch($event){
			case "player.join":
					$spawn = $data->level->getSpawn();
					$this->data[$data->__get("iusername")] = new Config(DATA_PATH."/plugins/Essentials/userdata/".$data->__get("iusername").".yml", CONFIG_YAML, array(
						"ipAddress" => $data->ip,
						"home" => array(),
						"lastlocation" => array(),
						"mute" => false,
						"newbie" => true,
					));
				break;
			case "player.quit":
				if($this->data[$data->__get("iusername")] instanceof Config){
					$this->data[$data->__get("iusername")]->save();
				}
				break;
			case "player.move":
				$player = $this->api->player->getByEID($data->eid);
				if($player->__get("lastMovement") < 10){
					$this->initPlayer($player);
				}
				break;
			case "player.flying":
				if($this->api->ban->isOp($data->__get("iusername")) === true){
					return true;
				}
				break;
			case "player.teleport":
				$this->data[$data["player"]->__get("iusername")]->set("lastlocation", array(
					"world" => $data["player"]->level->getName(),
					"x" => $data["player"]->entity->x,
					"y" => $data["player"]->entity->y,
					"z" => $data["player"]->entity->z,
				));
				break;
			case "player.block.place":
				if($data["player"]->gamemode === SURVIVAL and $data["item"]->getID() === SIGN){
					if($this->api->getProperty("item-enforcement") === true){
						$data["player"]->addItem(SIGN, 0, 1);
					}else{
						$this->api->entity->drop(new Position($data["player"]->entity->x, $data["player"]->entity->y, $data["player"]->entity->z, $data["player"]->level), BlockAPI::getItem(SIGN, 0, 1));
					}
				}
				break;
			case "player.block.break":
				if($data["target"]->getID() === SIGN_POST or $data["target"]->getID() === WALL_SIGN){
					$t = $this->api->tileentity->get($data["target"]);
					foreach($t as $ts){
						if($ts->class === TILE_SIGN){
							$this->api->tileentity->remove($ts->id);
						}
					}
				}
				break;
			case "player.block.place.spawn":
				if($data["item"]->getID() === SIGN){
					return true;
				}
				break;
			case "player.block.break.spawn":
				if($data["target"]->getID() === SIGN_POST or $data["target"]->getID() === WALL_SIGN){
					return true;
				}
				break;
		}
	}
	
	public function initPlayer($player){
		if($this->data[$player->__get("iusername")]->get("newbie") === true){ //Newbie
			$this->data[$player->__get("iusername")]->set("newbie", false);
			$player->sendChat($this->config["newbies"]["message"]);
			if($player->gamemode === SURVIVAL){
				if($this->api->getProperty("item-enforcement") === true){
					$player->addItem(SIGN, 0, 2);
				}else{
					$this->api->entity->drop(new Position($player->entity->x, $player->entity->y, $player->entity->z, $player->level), BlockAPI::getItem(SIGN, 0, 2));
				}
			}
		}
		if($player->gamemode === CREATIVE){
			foreach($player->inventory as $slot => $item){
				if($this->api->ban->isOp($player->__get("iusername"))){
					$player->setSlot($slot, BlockAPI::fromString($this->config["creative-item-op"][$slot]));
				}else{
					$player->setSlot($slot, BlockAPI::fromString($this->config["creative-item"][$slot]));
				}
			}
		}elseif($player->gamemode === SURVIVAL){
			if(array_key_exists($this->config["newbies"]["kit"], $this->config["kits"])){
				foreach($this->config["kits"][$this->config["newbies"]["kit"]] as $kit){
					$kit = explode(" ", $kit);
					$i = BlockAPI::fromString(array_shift($kit));
					if(!$player->hasItem($i->getID(), $i->getMetadata())){
						if($this->api->getProperty("item-enforcement") === true){
							$player->addItem($i->getID(), $i->getMetadata(), (int)$kit[0]);
						}else{
							$this->api->entity->drop(new Position($player->entity->x, $player->entity->y, $player->entity->z, $player->level), BlockAPI::getItem($i->getID(), $i->getMetadata(), (int)$kit[0]));
						}
					}
				}
			}else{
				console("[Essentials] Can not find the ".$this->config["newbies"]["kit"].".");
			}
		}
	}
	
	public function getGM($name){
		$gm["users"] = new Config(DATA_PATH."/plugins/GroupManager/worlds/".$this->api->getProperty("level-name")."/users.yml", CONFIG_YAML);
		$gm["groups"] = new Config(DATA_PATH."/plugins/GroupManager/worlds/".$this->api->getProperty("level-name")."/groups.yml", CONFIG_YAML);
		foreach($gm["groups"]->get("groups") as $groupname => $group){
			if($group["default"] === true){
				$defaultgroup = $groupname;
				break;
			}
		}
		if(isset($gm["users"]->get("users")[$name])){
			$gm["users"] = $gm["users"]->get("users")[$name];
			$gm["groups"] = $gm["groups"]->get("groups")[$gm["users"]["group"]];
		}else{
			$gm["users"] = array(
				"group" => $defaultgroup,
				"permissions" => array(),
			);
			$gm["groups"] = $gm["groups"]->get("groups")[$defaultgroup];
		}
		return $gm;
	}
	
	public function defaultCommands($cmd, $params, $issuer, $alias){
		$output = "";
		switch($cmd){
			case "?":
			case "help":
				$output = $this->api->sign->getHelp($params, $issuer);
				break;
			case "say":
				$s = implode(" ", $params);
				if(trim($s) == ""){
					$output .= "Usage: /say <message ...>\n";
					break;
				}
				if($this->data[$issuer->__get("iusername")]->get("mute") === true){
					$output .= "You are muted.";
				}else{
					$gm = $this->getGM($issuer->__get("username"));
					$this->api->chat->broadcast(str_replace(array("{DISPLAYNAME}", "{MESSAGE}", "{WORLDNAME}", "{GROUP}"), array($gm["groups"]["info"]["prefix"].$issuer->__get("username").$gm["groups"]["info"]["suffix"], $s, $issuer->level->getName(), $gm["users"]["group"]), $this->config["chat-format"]));
				}
				break;
			case "home":
				$home = $this->data[$issuer->__get("iusername")]->get("home");
				if($home !== array()){
					$name = $issuer->__get("iusername");
					if($home["world"] !== $issuer->level->getName()){
						$this->api->player->teleport($name, "w:".$home["world"]);
					}
					$this->api->player->tppos($name, $home["x"], $home["y"], $home["z"]);
					$output .= "teleported to your home.";
				}else{
					$output .= "You do not have a home.";
				}
				break;
			case "sethome":
				$this->data[$issuer->__get("iusername")]->set("home", array(
					"world" => $issuer->level->getName(),
					"x" => $issuer->entity->x,
					"y" => $issuer->entity->y,
					"z" => $issuer->entity->z,
				));
				$output .= "Your home has been saved.";
				break;
			case "delhome":
				$spawn = $issuer->level->getSpawn();
				$this->data[$issuer->__get(iusername)]->set("home", array());
				$output .= "Your home has been deleted.";
				break;
			case "mute":
				if($params[0] == ""){
					$output .= "Usage: /mute <player>";
					break;
				}
				$target = $this->api->player->get($params[0]);
				if($target !== false){
					if($this->data[$target->__get("iusername")]->get("mute") === false){
						$output .= $target->__get("username")." has been muted.";
						$target->sendChat("Your mute has been turned on.");
						$this->data[$target->__get("iusername")]->set("mute", true);
					}else{
						$output .= $target->__get("username")." has been unmuted.";
						$target->sendChat("Your mute has been turned off.");
						$this->data[$target->__get("iusername")]->set("mute", false);
					}
				}else{
					$output .= "Player \"".$params[0]."\" does not exist.";
				}
				break;
			case "back":
				$backpos = $this->data[$issuer->__get("iusername")]->get("lastlocation");
				if($backpos !== array()){
					$name = $issuer->__get("iusername");
					if($backpos["world"] !== $issuer->level->getName()){
						$this->api->player->teleport($name, "w:".$backpos["world"]);
					}
					$this->api->player->tppos($name, $backpos["x"], $backpos["y"], $backpos["z"]);
				}
				break;
			case "tree":
				switch(strtolower($params[0])){
					case "redwood":
						$meta = 1;
						$output .= "Redwood tree spawned.";
						break;
					case "brich":
						$meta = 2;
						$output .= "Brich tree spawned.";
						break;
					case "tree":
						$meta = 0;
						$output .= "Tree spawned.";
						break;
					default:
						$output .= "Usage: /tree <tree|brich|redwood>";
						break 2;
				}
				TreeObject::growTree($issuer->level, new Vector3 ((int)$issuer->entity->x, (int)$issuer->entity->y, (int)$issuer->entity->z), $meta);
				break;
			case "clear":
			case "stop":
			case "status":
			case "invisible":
			case "difficulty":
			case "defaultgamemode":
				$output = $this->api->console->defaultCommands($cmd, $params, $issuer, false);
				break;
			case "seed":
			case "save-all":
			case "save-on":
			case "save-off":
				$output = $this->api->level->commandHandler($cmd, $params, $issuer, false);
				break;
			case "list":
			case "kill":
			case "gamemode":
			case "tp":
			case "spawnpoint":
			case "spawn":
			case "lag":
				$output = $this->api->player->commandHandler($cmd, $params, $issuer, false);
				break;
			case "time":
				$output = $this->api->time->commandHandler($cmd, $params, $issuer, false);
				break;
			case "banip":
			case "ban":
			case "kick":
			case "whitelist":
			case "op":
			case "deop":
			case "sudo":
				$output = $this->api->ban->commandHandler($cmd, $params, $issuer, false);
				break;
			case "give":
				$output = $this->api->block->commandHandler($cmd, $params, $issuer, false);
				break;
			case "tell":
			case "me":
				$output = $this->api->chat->commandHandler($cmd, $params, $issuer, false);
				break;
		}
		return $output;
	}
	
	public function createConfig(){
		$this->path = $this->api->plugin->createConfig($this, array(
			"chat-format" => "<{DISPLAYNAME}> {MESSAGE}",
			"login-after-commands" => array(
			),
			"login-after-move" => true,
			"blacklist" => array(
				"placement" => '8,9,10,11,46,95',
				"usage" => 327,
				"break" => 7,
			),
			"kits" => array(
				"tools" => array(
				),
			),
			"newbies" => array(
				"kit" => "tools",
				"message" => "Use /help <page|command>",
			),
			"blocklog-displays" => 5,
			"creative-item" => array(
				COBBLESTONE.":0",
				STONE_BRICKS.":0",
				STONE_BRICKS.":1",
				STONE_BRICKS.":2",
				MOSS_STONE.":0",
				WOODEN_PLANKS.":0",
				BRICKS.":0",
				STONE.":0",
				DIRT.":0",
				GRASS.":0",
				CLAY_BLOCK.":0",
				SANDSTONE.":0",
				SANDSTONE.":1",
				SANDSTONE.":2",
				SAND.":0",
				GRAVEL.":0",
				TRUNK.":0",
				TRUNK.":1",
				TRUNK.":2",
				NETHER_BRICKS.":0",
				NETHERRACK.":0",
				COBBLESTONE_STAIRS.":0",
				WOODEN_STAIRS.":0",
				BRICK_STAIRS.":0",
				SANDSTONE_STAIRS.":0",
				STONE_BRICK_STAIRS.":0",
				NETHER_BRICKS_STAIRS.":0",
				QUARTZ_STAIRS.":0",
				SLAB.":0",
				SLAB.":1",
				SLAB.":2",
				SLAB.":3",
				SLAB.":4",
				SLAB.":5",
				QUARTZ_BLOCK.":0",
				QUARTZ_BLOCK.":1",
				QUARTZ_BLOCK.":2",
				COAL_ORE.":0",
				IRON_ORE.":0",
				GOLD_ORE.":0",
				DIAMOND_ORE.":0",
				LAPIS_ORE.":0",
				REDSTONE_ORE.":0",
				GOLD_BLOCK.":0",
				IRON_BLOCK.":0",
				DIAMOND_BLOCK.":0",
				LAPIS_BLOCK.":0",
				OBSIDIAN.":0",
				SNOW_BLOCK.":0",
				GLASS.":0",
				GLOWSTONE_BLOCK.":0",
				NETHER_REACTOR.":0",
				WOOL.":0",
				WOOL.":7",
				WOOL.":6",
				WOOL.":5",
				WOOL.":4",
				WOOL.":3",
				WOOL.":2",
				WOOL.":1",
				WOOL.":15",
				WOOL.":14",
				WOOL.":13",
				WOOL.":12",
				WOOL.":11",
				WOOL.":10",
				WOOL.":9",
				WOOL.":8",
				LADDER.":0",
				TORCH.":0",
				GLASS_PANE.":0",
				WOODEN_DOOR.":0",
				TRAPDOOR.":0",
				FENCE.":0",
				FENCE_GATE.":0",
				BED.":0",
				BOOKSHELF.":0",
				PAINTING.":0",
				WORKBENCH.":0",
				STONECUTTER.":0",
				CHEST.":0",
				FURNACE.":0",
				TNT.":0",
				DANDELION.":0",
				CYAN_FLOWER.":0",
				BROWN_MUSHROOM.":0",
				RED_MUSHROOM.":0",
				CACTUS.":0",
				MELON_BLOCK.":0",
				SUGARCANE.":0",
				SAPLING.":0",
				SAPLING.":1",
				SAPLING.":2",
				LEAVES.":0",
				LEAVES.":1",
				LEAVES.":2",
				SEEDS.":0",
				MELON_SEEDS.":0",
				DYE.":15",
				IRON_HOE.":0",
				IRON_SWORD.":0",
				STICK.":0",
				SIGN.":0",
			),
			"creative-item-op" => array(
				COBBLESTONE.":0",
				STONE_BRICKS.":0",
				STONE_BRICKS.":1",
				STONE_BRICKS.":2",
				MOSS_STONE.":0",
				WOODEN_PLANKS.":0",
				BRICKS.":0",
				STONE.":0",
				DIRT.":0",
				GRASS.":0",
				CLAY_BLOCK.":0",
				SANDSTONE.":0",
				SANDSTONE.":1",
				SANDSTONE.":2",
				SAND.":0",
				GRAVEL.":0",
				TRUNK.":0",
				TRUNK.":1",
				TRUNK.":2",
				NETHER_BRICKS.":0",
				NETHERRACK.":0",
				COBBLESTONE_STAIRS.":0",
				WOODEN_STAIRS.":0",
				BRICK_STAIRS.":0",
				SANDSTONE_STAIRS.":0",
				STONE_BRICK_STAIRS.":0",
				NETHER_BRICKS_STAIRS.":0",
				QUARTZ_STAIRS.":0",
				SLAB.":0",
				SLAB.":1",
				SLAB.":2",
				SLAB.":3",
				SLAB.":4",
				SLAB.":5",
				QUARTZ_BLOCK.":0",
				QUARTZ_BLOCK.":1",
				QUARTZ_BLOCK.":2",
				COAL_ORE.":0",
				IRON_ORE.":0",
				GOLD_ORE.":0",
				DIAMOND_ORE.":0",
				LAPIS_ORE.":0",
				REDSTONE_ORE.":0",
				GOLD_BLOCK.":0",
				IRON_BLOCK.":0",
				DIAMOND_BLOCK.":0",
				LAPIS_BLOCK.":0",
				OBSIDIAN.":0",
				SNOW_BLOCK.":0",
				GLASS.":0",
				GLOWSTONE_BLOCK.":0",
				NETHER_REACTOR.":0",
				WOOL.":0",
				WOOL.":7",
				WOOL.":6",
				WOOL.":5",
				WOOL.":4",
				WOOL.":3",
				WOOL.":2",
				WOOL.":1",
				WOOL.":15",
				WOOL.":14",
				WOOL.":13",
				WOOL.":12",
				WOOL.":11",
				WOOL.":10",
				WOOL.":9",
				WOOL.":8",
				LADDER.":0",
				TORCH.":0",
				GLASS_PANE.":0",
				WOODEN_DOOR.":0",
				TRAPDOOR.":0",
				FENCE.":0",
				FENCE_GATE.":0",
				BED.":0",
				BOOKSHELF.":0",
				PAINTING.":0",
				WORKBENCH.":0",
				STONECUTTER.":0",
				CHEST.":0",
				FURNACE.":0",
				TNT.":0",
				DANDELION.":0",
				CYAN_FLOWER.":0",
				BROWN_MUSHROOM.":0",
				RED_MUSHROOM.":0",
				CACTUS.":0",
				MELON_BLOCK.":0",
				SUGARCANE.":0",
				SAPLING.":0",
				SAPLING.":1",
				SAPLING.":2",
				LEAVES.":0",
				LEAVES.":1",
				LEAVES.":2",
				SEEDS.":0",
				MELON_SEEDS.":0",
				DYE.":15",
				IRON_HOE.":0",
				IRON_SWORD.":0",
				STICK.":0",
				SIGN.":0",
			),
		));
		$this->reloadConfig();
	}
	
	public function reloadConfig(){
		$this->config = $this->api->plugin->readYAML($this->path."config.yml");
	}
}