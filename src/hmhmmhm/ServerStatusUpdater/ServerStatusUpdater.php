<?php

namespace hmhmmhm\ServerStatusUpdater;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\scheduler\PluginTask;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\event\Listener;

class ServerStatusUpdater extends PluginBase implements Listener {
	private $chatlist = [ ];
	private $externalIp = "0.0.0.0";
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		$default = [ 
				"statusLocate" => $this->getDataFolder () . "status.json",
				"updateFrequency" => "5" 
		];
		$defaultConfig = (new Config ( $this->getDataFolder () . "locate.json", Config::JSON, $default ))->getAll ();
		
		$this->getServer ()->getScheduler ()->scheduleAsyncTask ( new GetExternalIPAsyncTask ( "ServerStatusUpdater" ) );
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new StatusUpdateTask ( $this ), $defaultConfig ["updateFrequency"] * 20 );
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		
		$this->getLogger ()->info ( "Activated" );
	}
	public function onChat(PlayerChatEvent $event) {
		$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new PlayerChatCollector ( $this, $event ), 1 );
	}
	public function addChat($string) {
		array_push ( $this->chatlist, $string );
		if (count ( $this->chatlist ) > 9)
			array_shift ( $this->chatlist );
	}
	public function reportExternalIp($ip) {
		$this->externalIp = $ip;
	}
	public function update() {
		$status = [ ];
		$status ["motd"] = $this->getServer ()->getMotd ();
		$status ["gamemode"] = explode ( "%gameMode.", $this->getServer ()->getGamemodeString ( $this->getServer ()->getGamemode () ) ) [1];
		$status ["maxplayer"] = $this->getServer ()->getMaxPlayers ();
		$status ["nowplayer"] = count ( $this->getServer ()->getOnlinePlayers () );
		$status ["version"] = $this->getServer ()->getVersion ();
		$status ["whitelist"] = count ( $this->getServer ()->getWhitelisted ()->getAll () ) == 0 ? false : true;
		$status ["default-level-name"] = $this->getServer ()->getDefaultLevel ()->getName ();
		$status ["tps"] = $this->getServer ()->getTicksPerSecond ();
		$status ["ip"] = $this->externalIp;
		$status ["port"] = $this->getServer ()->getPort ();
		$status ["in-game-time"] = $this->getMinecraftTime ( $this->getServer ()->getDefaultLevel ()->getTime () );
		$status ["server-engine"] = $this->getServer ()->getName () . " " . $this->getServer ()->getPocketMineVersion ();
		
		$players = [ ];
		foreach ( $this->getServer ()->getOnlinePlayers () as $player )
			if ($player->isOnline ())
				$players [] = $player->getName ();
		
		$status ["player-list"] = $players;
		$status ["chat-list"] = $this->chatlist;
		
		$defaultConfig = (new Config ( $this->getDataFolder () . "locate.json", Config::JSON, [ ] ))->getAll ();
		$statusConfig = new Config ( $defaultConfig ["statusLocate"], Config::JSON );
		
		$statusConfig->setAll ( $status );
		$statusConfig->save ( true );
	}
	public function getMinecraftTime($tick) {
		$totalhour = ($tick / 1000) + 6;
		$totalday = floor ( $totalhour / 24 );
		
		$nowhour = $totalhour - $totalday * 24;
		$nowmin = ($nowhour - floor ( $nowhour )) * 60;
		$nowsec = ($nowmin - floor ( $nowmin )) * 60;
		
		$hour = ( int ) $nowhour;
		$min = ( int ) $nowmin;
		$sec = ( int ) $nowsec;
		
		$meridiem = "";
		if ($hour <= 12) {
			$meridiem = "AM";
		} else {
			$hour -= 12;
			$meridiem = "PM";
		}
		
		return "$meridiem:$hour:$min:$sec";
	}
}
class PlayerChatCollector extends PluginTask {
	private $event;
	public function __construct(Plugin $owner, PlayerChatEvent $event) {
		parent::__construct ( $owner );
		$this->event = $event;
	}
	public function onRun($currentTick) {
		if (! $this->event->isCancelled ()) {
			$chat = Server::getInstance ()->getLanguage ()->translateString ( $this->event->getFormat (), [ 
					$this->event->getPlayer ()->getDisplayName (),
					$this->event->getMessage () 
			] );
			if ($chat != null)
				$this->getOwner ()->addChat ( $chat );
		}
	}
}
class StatusUpdateTask extends PluginTask {
	public function onRun($currentTick) {
		$this->getOwner ()->update ();
	}
}

?>