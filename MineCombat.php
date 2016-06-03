namespace onebone\minecombat;

use onebone\minecombat\grenade\BaseGrenade;
use onebone\minecombat\gun\BaseGun;
use onebone\minecombat\gun\Bazooka;
use onebone\minecombat\gun\FlameThrower;
use pocketmine\Player;use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
/* secrete 수정 본 */
use pocketmine\scheduler\AsyncTask;

use onebone\minecombat\gun\Pistol;
use onebone\minecombat\grenade\FragmentationGrenade;
use onebone\minecombat\task\GameStartTask;
use onebone\minecombat\task\GameEndTask;
use onebone\minecombat\task\TeleportTask;
use onebone\minecombat\task\PopupTask;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\level\Explosion;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\entity\Entity;
use pocketmine\event\player\PlayerItemHeldEvent;


class MineCombat extends PluginBase implements Listener{
	const STAT_GAME_END = 0;
	const STAT_GAME_PREPARE = 1;
	const STAT_GAME_IN_PROGRESS = 2;
	
	const PLAYER_DEAD = 0;
	const PLAYER_ALIVE = 1;
	
	const TEAM_RED = 0;
	const TEAM_BLUE = 1;
	
	const 수류탄 = Item::SLIMEBALL;
	const 기본총 = Item::MELON_STEM;
	const 돌격총 = Item::REDSTONE;
	const 썬더스틱 = Item::STICK;
	const 회복 = Item::STICK;

	private $rank, $players, $score, $status, $spawnPos = null, $nextLevel = null, $level, $killDeath;
	
	private static $obj;

	/**
	 * @return MineCombat
	 */
	public static function getInstance(){
		return self::$obj;
	}
	
	public function prepareGame(){
		$this->status = self::STAT_GAME_PREPARE;
		
		$this->getServer()->getScheduler()->scheduleDelayedTask(new GameStartTask($this), $this->getConfig()->get("prepare-time") * 20);
		
		$this->getServer()->broadcastMessage(TextFormat::AQUA."============BLACK squad===========");
		$this->getServer()->broadcastMessage(TextFormat::AQUA." 휴식 시간 입니다 자신의 전적을 보실려면. ");
		$this->getServer()->broadcastMessage(TextFormat::AQUA."    /전적' 을 채팅창에  쳐주세요.      ");
		
		$pos = $this->getConfig()->get("spawn-pos");
		
		if($pos === []) return;
		$randKey = array_rand($pos);
		
		$randPos = $pos[$randKey];
		
		if(($level = $this->getServer()->getLevelByName($randPos["blue"][3])) instanceof Level){
			$this->spawnPos = [new Position($randPos["red"][0], $randPos["red"][1], $randPos["red"][2], $level), new Position($randPos["blue"][0], $randPos["blue"][1], $randPos["blue"][2], $level)];
			$this->nextLevel = $randKey;
		}else{
			$this->getLogger()->critical("Invalid level name was given.");
			$this->getServer()->shutdown();
		}
	}
	public function startGame(){
		if(count($this->getServer()->getOnlinePlayers()) < 1){ ///// TODO: CHANGE HERE ON RELEASE
			$this->getServer()->broadcastMessage(TextFormat::YELLOW."============BLACK squad===========");
			$this->getServer()->broadcastMessage(TextFormat::YELLOW."플레이어가 게임을 시작하기에 부족합니다 ..");
			$this->getServer()->getScheduler()->scheduleDelayedTask(new GameStartTask($this), $this->getConfig()->get("prepare-time") * 20);
			return;
		}
		
		$blue = $red = 0;
		
		$this->status = self::STAT_GAME_IN_PROGRESS;
		
		$online = $this->getServer()->getOnlinePlayers();
		shuffle($online);
		foreach($online as $player){
			if($blue < $red){
				$this->players[$player->getName()][2] = self::TEAM_BLUE;
				
				if(isset($this->level[$player->getName()])){
					$level = floor(($this->level[$player->getName()] / 100));
					$gun = $this->getClassColor($this->players[$player->getName()][0]->getClass()).$this->players[$player->getName()][0]->getName();
					$player->setNameTag("Lv.".$level.TextFormat::BLUE."GUN:".$gun.TextFormat::BLUE."<".$player->getName().">");
				}else{
					$player->setNameTag(TextFormat::BLUE.$player->getName());
				}
				$player->sendMessage(TextFormat::GREEN."============BLACK squad===========");
				$player->sendMessage(TextFormat::GREEN." 당신은 ".TextFormat::BLUE."블루팀".TextFormat::GREEN." 입니다 적군 을 죽여 나오는  ");
				$player->sendMessage(TextFormat::GREEN."    점수가 적군보다 많아야 승리 합니다    ");
				$player->sendMessage(TextFormat::GREEN."      블루팀은 이름표가 파란색이고         ");
				$player->sendMessage(TextFormat::GREEN."     레드팀은 이름표가 빨간색 입니다       ");
				$player->sendMessage(TextFormat::GREEN."더자세한 설명은 /설명 을 쳐주시기 바람니다 ");
				if(isset($this->players[$player->getName()][0])){
					$this->players[$player->getName()][0]->setColor([40, 45, 208]);
				}
				
				++$blue;
			}else{
				$this->players[$player->getName()][2] = self::TEAM_RED;
				
				if(isset($this->level[$player->getName()])){
					$level = floor(($this->level[$player->getName()] / 100));
					$gun = $this->getClassColor($this->players[$player->getName()][0]->getClass()).$this->players[$player->getName()][0]->getName();
					$player->setNameTag("Lv.".$level.TextFormat::RED."GUN:".$gun.TextFormat::RED."<".$player->getName().">");
				}else{
					$player->setNameTag(TextFormat::RED.$player->getName());
				}
				
				$player->sendMessage(TextFormat::GREEN."============BLACK squad===========");
				$player->sendMessage(TextFormat::GREEN." 당신은".TextFormat::RED."레드팀".TextFormat::GREEN." 입니다 적군 을 죽여 나오는  ");
				$player->sendMessage(TextFormat::GREEN."    점수가 적군보다 많아야 승리 합니다    ");
				$player->sendMessage(TextFormat::GREEN."      블루팀은 이름표가 파란색이고         ");
				$player->sendMessage(TextFormat::GREEN."     레드팀은 이름표가 빨간색 입니다       ");
				$player->sendMessage(TextFormat::GREEN."더자세한 설명은 /설명 을 쳐주시기 바람니다 ");
				
				if(isset($this->players[$player->getName()][0])){
					$this->players[$player->getName()][0]->setColor([247, 2, 9]);
				}
				
				++$red;
			}
			$this->killDeath[0][$player->getName()] = 0;
			$this->killDeath[1][$player->getName()] = 1;
			
			$this->teleportToSpawn($player);
			$player->setHealth(20);
			
			if(!$player->getInventory()->contains(Item::get(self::수류탄))){
				$player->getInventory()->addItem(Item::get(self::수류탄, 0, 2));
			}
			if(!$player->getInventory()->contains(Item::get(self::기본총))){
				$player->getInventory()->addItem(Item::get(self::기본총));
			}
			if(!$player->getInventory()->contains(Item::get(self::돌격총))){
				$player->getInventory()->addItem(Item::get(self::돌격총));
			}
			if(!$player->getInventory()->contains(Item::get(self::썬더스틱))){
				$player->getInventory()->addItem(Item::get(self::썬더스틱, 0, 5));
			}
			if(!$player->getInventory()->contains(Item::get(self::회복))){
				$player->getInventory()->addItem(Item::get(self::회복));
			}

			if(isset($this->players[$player->getName()][0])){
				$this->players[$player->getName()][0]->setAmmo($this->players[$player->getName()][0]->getDefaultAmmo());
			}
			$this->players[$player->getName()][3] = time();
		}
		$this->score = [0, 0];
		
		$this->getServer()->getScheduler()->scheduleDelayedTask(new GameEndTask($this), $this->getConfig()->get("game-time") * 20);
		
		$this->getServer()->broadcastMessage(TextFormat::GREEN."  게임이 정상적으로 시작 되었습니다.");
		$this->getServer()->broadcastMessage(TextFormat::GREEN."============BLACK squad===========");

	}
	
	public function endGame(){
		$this->status = self::STAT_GAME_END;
		
		$winner = TextFormat::YELLOW."무승부 입니다 두팀다 수고하셨습니다".TextFormat::RESET;
		if($this->score[self::TEAM_RED] > $this->score[self::TEAM_BLUE]){
			$winner = TextFormat::RED."레드팀 승리 입니다 축하드립니다!";
		}elseif($this->score[self::TEAM_BLUE] > $this->score[self::TEAM_RED]){
			$winner = TextFormat::BLUE."블루팀 승리 입니다 축하드립니다!";
		}
		$this->getServer()->broadcastMessage(TextFormat::GREEN."============BLACK squad===========");
		$this->getServer()->broadcastMessage(TextFormat::GREEN."게임 결과는".$winner);
		$this->getServer()->broadcastMessage(TextFormat::GREEN."============BLACK squad===========");
		
		foreach($this->getServer()->getOnlinePlayers() as $player){
			$player->setNameTag($player->getName());
		}
		
		
		$this->prepareGame();
	}
	
	public function isEnemy($player1, $player2){
		if(isset($this->players[$player1]) and isset($this->players[$player2])){
			return ($this->players[$player1][2] !== $this->players[$player2][2]);
		}
		return false;
	}

	/**
	 * @param Player|string $player
	 *
	 * @return BaseGun|null
	 */
	public function getGunByPlayer($player){
		if($player instanceof Player){
			$player = $player->getName();
		}
		
		if(isset($this->players[$player][0])){
			return $this->players[$player][0];
		}
		return null;
	}
	
	public function broadcastPopup($message){
		foreach($this->getServer()->getOnlinePlayers() as $player){
			$player->sendTip($message);
		}
	}
	
	public function getPlayersCountOnTeam($team){
		$ret = 0;
		foreach($this->players as $stats){
			if($stats[2] === $team){
				$ret++;
			}
		}
		return $ret;
	}
	
	public function teleportToSpawn(Player $player){
		if($this->spawnPos === null) return;
		$team = $this->players[$player->getName()][2];
		switch($team){
			case self::TEAM_BLUE:
			$player->teleport($this->spawnPos[1]);
			break;
			default: // RED team or not decided
			$player->teleport($this->spawnPos[0]);
			break;
		}
	}
	
	public function showPopup(){
		if($this->status === self::STAT_GAME_IN_PROGRESS){

			foreach($this->getServer()->getOnlinePlayers() as $player){
				if(!isset($this->players[$player->getName()])) continue;
				if($this->players[$player->getName()][2] === self::TEAM_RED){
					$level = floor(($this->level[$player->getName()] / 100));
					$popup = TextFormat::BLACK."                 <=[|+|]=>\n"."\n"."\n"."\n"."\n"."\n".TextFormat::BLACK."[BLACK SQUAD]".TextFormat::RED." 팀:레드팀".TextFormat::GOLD." [LV.". $level ." ]\n".TextFormat::WHITE."점수> "."레드팀:".TextFormat::RED.($this->score[self::TEAM_RED]).TextFormat::WHITE." / "."블루팀:".TextFormat::BLUE.($this->score[self::TEAM_BLUE].TextFormat::WHITE." / RP : ".TextFormat::GREEN.$this->level[$player->getName()]);
				}else{
					$level = floor(($this->level[$player->getName()] / 100));
					$popup = TextFormat::BLACK."                 <=[|+|]=>\n"."\n"."\n"."\n"."\n"."\n".TextFormat::BLACK."[BLACK SQUAD]".TextFormat::BLUE." 팀:블루팀".TextFormat::GOLD." [ LV.". $level ." ]\n".TextFormat::WHITE."점수> "."블루팀:".TextFormat::BLUE.($this->score[self::TEAM_BLUE]).TextFormat::WHITE." / "."레드팀:".TextFormat::RED.($this->score[self::TEAM_RED].TextFormat::WHITE." / RP : ".TextFormat::GREEN.$this->level[$player->getName()]);
				}
				$ammo = "";
				$gun = "";
				if(isset($this->players[$player->getName()][0])){
					$ammo = $this->players[$player->getName()][0]->getLeftAmmo();
					if($ammo <= 0){
						$ammo = TextFormat::RED.$ammo;
					}
					$gun = $this->getClassColor($this->players[$player->getName()][0]->getClass()).$this->players[$player->getName()][0]->getName();
				}
				$popup .= "\n장착 무기 : ".$gun.", 총알: ".$ammo;
				$player->sendPopup($popup);
			}
		}else{
			foreach($this->getServer()->getOnlinePlayers() as $player){
				$levelStr = "";
				if($this->nextLevel !== null){
					$levelStr = "    \n다음 전투 지역  => ".TextFormat::AQUA.$this->nextLevel;
				}
				$player->sendPopup(TextFormat::GREEN."\n                [공지사항 및 게임 방법]"."\n 1.총은 2가지 기능의 총이 있습니다 <돌격총>"."레드스톤을 들고 움직이면 총알이 자동으로발사 됨니다 "."\n<기본총> 기본총은 화면을 1초 이상 눌러 주면 발사됨니다 "."\n<수류탄>은 들고 1초간 꾹누르면 자신이 있던자리가 2초뒤 즉사 데미지로 터집니다"."\n<썬더스틱>으로 번개가 칠 블럭을 누르면 스틱1개를 소모하고 그곳에 번개가 칩니다"."\n승리 조건: 적팀을 죽여 가장 높은점수르 얻은 팀이 우승합니다."."\n전적 확인은 /전적 을 채팅 창에 쳐주시면 됨니다."."/n업대이트 버젼 black squad 1.0.1 수정 소스는 깃허브에 공유".$levelStr);
			}
		}
	}

	public static function getClassColor($class){
		switch($class){
			default: $color = TextFormat::GOLD;break;
		}
		return $color;
	}
	
	public function submitAsyncTask(AsyncTask $task){
		$this->getServer()->getScheduler()->scheduleAsyncTask($task);
	}
	
	public function onEnable(){
		self::$obj = $this;
		
		if(!file_exists($this->getDataFolder())){
			mkdir($this->getDataFolder());
		}
		if(!is_file($this->getDataFolder()."rank.dat")){
			file_put_contents($this->getDataFolder()."rank.dat", serialize([]));
		}
		$this->rank = unserialize(file_get_contents($this->getDataFolder()."rank.dat"));
		
		if(!is_file($this->getDataFolder()."level.dat")){
			file_put_contents($this->getDataFolder()."level.dat", serialize([]));
		}
		$this->level = unserialize(file_get_contents($this->getDataFolder()."level.dat"));
		
		if(!is_file($this->getDataFolder()."kill_death.dat")){
			file_put_contents($this->getDataFolder()."kill_death.dat", serialize([]));
		}
		$this->killDeath = unserialize(file_get_contents($this->getDataFolder()."kill_death.dat"));
		
		$this->players = [];
		
		$this->saveDefaultConfig();
		
		$spawnPos = $this->getConfig()->get("spawn-pos");
		
		foreach($spawnPos as $key => $data){
			if(!isset($data["blue"]) or !isset($data["red"])){
				unset($spawnPos[$key]);
			}
		}
		if($spawnPos !== [] and $spawnPos !== null){ // TODO: Fix here
			$this->prepareGame();
		}else{
			$this->getLogger()->warning("게임을 시작하기 위해서는 전투지역 설정을 하여야 합니다.");
			return;
		}
		
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new PopupTask($this), 10);
	}
	
	public function onDisable(){
		file_put_contents($this->getDataFolder()."rank.dat", serialize($this->rank));
		file_put_contents($this->getDataFolder()."level.dat", serialize($this->level));
		file_put_contents($this->getDataFolder()."kill_death.dat", serialize($this->killDeath));
	}
	
	public function onCommand(CommandSender $sender, Command $command, $label, array $params){
		switch($command->getName()){
			case "전적":
			if(!$sender instanceof Player){
				$sender->sendMessage(TextFormat::RED."============BLACK squad===========");
				$sender->sendMessage(TextFormat::RED."   게임을 한후에 이 커맨드를 쳐주세요.");
				return true;
			}
			
			$data = $this->killDeath[0];
			
			arsort($data);
			
			$cnt = 0;
			$send = "당신의 전적 : ".TextFormat::YELLOW.$this->killDeath[0][$sender->getName()].TextFormat::WHITE."죽인수/".TextFormat::YELLOW.$this->killDeath[1][$sender->getName()].TextFormat::WHITE."죽은수\n--------------------\n";
			foreach($data as $player => $datam){
				$send .= TextFormat::GREEN.$player.TextFormat::WHITE." ".TextFormat::YELLOW.$datam.TextFormat::WHITE."죽인수/".TextFormat::YELLOW.$this->killDeath[1][$player].TextFormat::WHITE."죽은수\n";
				if($cnt >= 10){
					break;
				}
				++$cnt;
			}
			$sender->sendMessage($send);
			return true;
			case "spawnpos":
			$sub = strtolower(array_shift($params));
			switch($sub){
				case "blue":
				case "b":
				if(!$sender instanceof Player){
					$sender->sendMessage(TextFormat::RED."Please run this command in-game.");
					return true;
				}
				
				$name = array_shift($params);
				if(trim($name) === ""){
					$sender->sendMessage(TextFormat::RED."Usage: /spawnpos blue <name>");
					return true;
				}
				
				$config = $this->getConfig()->get("spawn-pos");
				if(isset($config[$name]["blue"])){
					$sender->sendMessage(TextFormat::RED."$name already exists.");
					return true;
				}
				$loc = [
					$sender->getX(), $sender->getY(), $sender->getZ(), $sender->getLevel()->getFolderName()
				];
				$config[$name]["blue"] = $loc;
				$this->getConfig()->set("spawn-pos", $config);
				$this->getConfig()->save();
				$sender->sendMessage("[MineCombat] Spawn position of BLUE team set.");
				return true;
				case "r":
				case "red":
				if(!$sender instanceof Player){
					$sender->sendMessage(TextFormat::RED."Please run this command in-game.");
					return true;
				}
				
				$name = array_shift($params);
				if(trim($name) === ""){
					$sender->sendMessage(TextFormat::RED."Usage: /spawnpos red <name>");
					return true;
				}
				
				$config = $this->getConfig()->get("spawn-pos");
				if(isset($config[$name]["red"])){
					$sender->sendMessage(TextFormat::RED."$name already exists.");
					return true;
				}
				
				$loc = [
					$sender->getX(), $sender->getY(), $sender->getZ(), $sender->getLevel()->getFolderName()
				];
				$config[$name]["red"] = $loc;
				$this->getConfig()->set("spawn-pos", $config);
				$this->getConfig()->save();
				$sender->sendMessage("[MineCombat] Spawn position of RED team set.");
				return true;
				case "remove":
				$name = array_shift($params);
				if(trim($name) === ""){
					$sender->sendMessage(TextFormat::RED."Usage: /spawnpos blue <name>");
					return true;
				}
				
				$config = $this->getConfig()->get("spawn-pos");
				$config[$name] = null;
				unset($config[$name]);
				
				$this->getConfig()->set("spawn-pos", $config);
				$this->getConfig()->save();
				return true;
				case "list":
				$list = implode(", ", array_keys($this->getConfig()->get("spawn-pos")));
				$sender->sendMessage("Positions list: \n".$list);
				return true;
				default:
				$sender->sendMessage("Usage: ".$command->getUsage());
			}
			return true;
			case "momap":
			$name = array_shift($params);
			
			if(trim($name) === ""){
				$sender->sendMessage(TextFormat::RED."Usage: ".$command->getUsage());
				return true;
			}
			
			if($this->status === self::STAT_GAME_IN_PROGRESS){
				$sender->sendMessage(TextFormat::RED."Game is already in progress. Select map after the game is ended.");
				return true;
			}
			
			$pos = $this->getConfig()->get("spawn-pos");
			if(!isset($pos[$name])){
				$sender->sendMessage("Map ".TextFormat::RED.$name.TextFormat::WHITE." exist!");
				return true;
			}else{
				$selectedPos = $pos[$name];
				if(($level = $this->getServer()->getLevelByName($selectedPos["blue"][3])) instanceof Level){
					$this->spawnPos = [new Position($selectedPos["red"][0], $selectedPos["red"][1], $selectedPos["red"][2], $level), new Position($selectedPos["blue"][0], $selectedPos["blue"][1], $selectedPos["blue"][2], $level)];
					$this->nextLevel = $name;
					$sender->sendMessage("Map was selected to ".TextFormat::AQUA.$name);
				}else{
					$this->getLogger()->critical("Invalid level name was given.");
					$this->getServer()->shutdown();
				}
			}
			return true;
		}

		return true;
	}
	public function onPlayer(PlayerMoveEvent $event){
		if($this->status === self::STAT_GAME_IN_PROGRESS){
			$player = $event->getPlayer();
			$item = $player->getInventory()->getItemInHand();
			if($item->getId() === self::돌격총){
				$this->players[$player->getName()][0]->shoot();
			}
			
		}
		
	}
	public function onInteract(PlayerInteractEvent $event){
		if($this->status === self::STAT_GAME_IN_PROGRESS){
			$player = $event->getPlayer();
			$item = $player->getInventory()->getItemInHand();
			if($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_AIR){
				if($item->getId() === self::기본총){
					$this->players[$player->getName()][0]->shoot();
				}
				if($item->getId() === self::수류탄){
					$this->players[$player->getName()][1]->lob($event->getTouchVector());
					$player->getInventory()->removeItem(Item::get(self::수류탄, 0, 1));
					$this->getServer()->broadcastMessage(TextFormat::GREEN."수류탄이 2초뒤 터집니다");
				}
			}	
		}
	}


	
	public function onLoginEvent(PlayerLoginEvent $event){
		$player = $event->getPlayer();
		
		if(!isset($this->level[$player->getName()])){
			$this->level[$player->getName()] = 0;
		}
		if(!isset($this->killDeath[0][$player->getName()])){
			$this->killDeath[0][$player->getName()] = 0;
			$this->killDeath[1][$player->getName()] = 0;
		}
		
		$this->players[$player->getName()] = [
			new Pistol($this, $player, array(175, 175, 175)),
			new FragmentationGrenade($this, $player),
			-1,
			time()
		];
	}
	
	public function onJoinEvent(PlayerJoinEvent $event){
		$player = $event->getPlayer();
		
			if(!$player->getInventory()->contains(Item::get(self::수류탄))){
				$player->getInventory()->addItem(Item::get(self::수류탄, 0, 2));
			}
			if(!$player->getInventory()->contains(Item::get(self::기본총))){
				$player->getInventory()->addItem(Item::get(self::기본총));
			}
			if(!$player->getInventory()->contains(Item::get(self::돌격총))){
				$player->getInventory()->addItem(Item::get(self::돌격총));
			}
			if(!$player->getInventory()->contains(Item::get(self::썬더스틱))){
				$player->getInventory()->addItem(Item::get(self::썬더스틱, 0, 5));
			}
			if(!$player->getInventory()->contains(Item::get(self::회복))){
				$player->getInventory()->addItem(Item::get(self::회복));
			}
		
		if($this->status === self::STAT_GAME_IN_PROGRESS){
			$redTeam = $this->getPlayersCountOnTeam(self::TEAM_RED);
			$blueTeam = $this->getPlayersCountOnTeam(self::TEAM_BLUE);
			if($redTeam > $blueTeam){
				$team = self::TEAM_BLUE;
				
				$level = floor(($this->level[$player->getName()] / 10000));
				$player->setNameTag("Lv.".$level.TextFormat::BLUE.$player->getName());
				
				$this->players[$player->getName()][0]->setColor([40, 45, 208]);
			}else{
				$team = self::TEAM_RED;
				
				$level = floor(($this->level[$player->getName()] / 10000));
				$player->setNameTag("Lv.".$level.TextFormat::RED.$player->getName());
				
				$this->players[$player->getName()][0]->setColor([247, 2, 9]);
			}
			$this->players[$player->getName()][2] = $team;
			
			$this->teleportToSpawn($player);
			$player->sendMessage("[MineCombat] You are ".($team === self::TEAM_RED ? TextFormat::RED."RED" : TextFormat::BLUE."BLUE").TextFormat::WHITE." team. Kill as much as enemies and get more scores.");
		}else{
			$player->sendMessage("[MineCombat] It is preparation time. Please wait for a while to start the match.");
		}
	}
	public function onQuitEvent(PlayerQuitEvent $event){
		$player = $event->getPlayer();
		
		if($player->loggedIn and isset($this->players[$player->getName()])){
			unset($this->players[$player->getName()]);
		}
	}
	
	public function onDeath(PlayerDeathEvent $event){
		$player = $event->getEntity();

		if(isset(FlameThrower::$tasks[$player->getName()])){
			FlameThrower::$tasks[$player->getName()]->getHandler()->cancel();
		}

		if($this->status === self::STAT_GAME_IN_PROGRESS){
			$items = $event->getDrops();
			foreach($items as $key => $item){
				if($item->getId() !== self::기본총){
					unset($items[$key]);
				}
			}
			$event->setDrops($items);
			$cause = $player->getLastDamageCause();

			if($cause !== null && $cause->getCause() == EntityDamageEvent::CAUSE_FALL){
				if($this->players[$player->getName()][2] === self::TEAM_BLUE){
					$playerColor = TextFormat::BLUE;
					$damagerColor = TextFormat::RED;
					$this->score[self::TEAM_RED]++;
				}else{
					$playerColor = TextFormat::RED;
					$damagerColor = TextFormat::BLUE;
					$this->score[self::TEAM_BLUE]++;
				}
				$firstKill = "";
				if($this->score[self::TEAM_BLUE] + $this->score[self::TEAM_RED] <= 1){
					$firstKill = TextFormat::YELLO."처음 으로 사망 하였습니다\n".TextFormat::WHITE;
				}
				$this->broadcastPopup($firstKill.$playerColor.$player->getName().$damagerColor." 굿 ! 완벽했어요!");
			}

			if(!($cause instanceof EntityDamageByEntityEvent)){
				return;
			}

			if($cause !== null and $cause->getCause() === 15){
				$damager = $cause->getDamager();
				if($damager instanceof Player){
					if($this->players[$damager->getName()][2] === self::TEAM_BLUE){
						$damagerColor = TextFormat::BLUE;
						$playerColor = TextFormat::RED;
						$this->score[self::TEAM_BLUE]++;
					}else{
						$damagerColor = TextFormat::RED;
						$playerColor = TextFormat::BLUE;
						$this->score[self::TEAM_RED]++;
					}
					$firstKill = "";
					if($this->score[self::TEAM_BLUE] + $this->score[self::TEAM_RED] <= 1){
						$firstKill = TextFormat::YELLOW."<=BLACK squad=>\n처음 으로 사망 하였습니다 \n".TextFormat::WHITE;
					}
					$this->broadcastPopup("<=BLACK squad=>\n".$firstKill.$damagerColor.$damager->getName().TextFormat::WHITE."님이".$playerColor.$player->getName()."님을 총으로 사살 하였습니다");
					
					++$this->killDeath[0][$damager->getName()];
					++$this->killDeath[1][$player->getName()];
					
					$this->level[$damager->getName()] += ($damager->getHealth() * 5);
					$level = floor(($this->level[$damager->getName()] / 100));
					$damager->setNameTag("Lv.".$level.$damagerColor.$damager->getName());
				}
			}elseif($cause !== null and $cause->getCause() === 16){
				$damager = $cause->getDamager();
				if($damager instanceof Player){
					if($this->players[$damager->getName()][2] === self::TEAM_BLUE){
						$damagerColor = TextFormat::BLUE;
						$playerColor = TextFormat::RED;
						$this->score[self::TEAM_BLUE]++;
					}else{
						$damagerColor = TextFormat::RED;
						$playerColor = TextFormat::BLUE;
						$this->score[self::TEAM_RED]++;
					}
					$firstKill = "";
					if($this->score[self::TEAM_BLUE] + $this->score[self::TEAM_RED] <= 1){
						$firstKill = TextFormat::YELLOW."처음 으로 사망 하였습니다 \n".TextFormat::WHITE;
					}
					$this->broadcastPopup("<=BLACK squad=>\n".$firstKill.$damagerColor.$damager->getName().TextFormat::WHITE."님이 수류탄으로  ".$playerColor.$player->getName()."님을 폭파 시켰습니다.");
					
					++$this->killDeath[0][$damager->getName()];
					++$this->killDeath[1][$player->getName()];
					
					$this->level[$damager->getName()] += ($damager->getHealth() * 5);
					$level = floor(($this->level[$damager->getName()] / 100));
					$damager->setNameTag("Lv.".$level.$damagerColor.$damager->getName());
				}
			}
			$event->setDeathMessage("");
		}
	}
	
	public function onRespawn(PlayerRespawnEvent $event){
		$player = $event->getPlayer();
		
		$this->getServer()->getScheduler()->scheduleDelayedTask(new TeleportTask($this, $player->getName()), 5);
		
			if(!$player->getInventory()->contains(Item::get(self::수류탄))){
				$player->getInventory()->addItem(Item::get(self::수류탄, 0, 2));
			}
			if(!$player->getInventory()->contains(Item::get(self::기본총))){
				$player->getInventory()->addItem(Item::get(self::기본총));
			}
			if(!$player->getInventory()->contains(Item::get(self::돌격총))){
				$player->getInventory()->addItem(Item::get(self::돌격총));
			}
			if(!$player->getInventory()->contains(Item::get(self::썬더스틱))){
				$player->getInventory()->addItem(Item::get(self::썬더스틱, 0, 5));
			}
			if(!$player->getInventory()->contains(Item::get(self::회복))){
				$player->getInventory()->addItem(Item::get(self::회복));
			}
		
		$this->players[$player->getName()][3] = time();
		if(isset($this->players[$player->getName()][0])){
			$this->players[$player->getName()][0]->setAmmo($this->players[$player->getName()][0]->getDefaultAmmo());
		}
	}
	
	public function onDamage(EntityDamageEvent $event){
		$player = $event->getEntity();
		if($player instanceof Player){
			if($this->status !== self::STAT_GAME_IN_PROGRESS){
				$event->setCancelled();
				return;
			}
			if((time() - $this->players[$player->getName()][3]) < 3){
				$event->setCancelled();
				return;
			}
			if($event instanceof EntityDamageByEntityEvent){
				$damager = $event->getDamager();
				$event->setKnockBack(0.2);
				if($damager instanceof Player){
					if(!$this->isEnemy($player->getName(), $damager->getName())){
						$event->setCancelled();
					}
				}
			}
		}
	}
	
	public function onDropItem(PlayerDropItemEvent $event){
		$item = $event->getItem();
		
		if($item->getId() === self::수류탄){
			$event->setCancelled();
		}
		if($item->getId() === self::썬더스틱){
			$event->setCancelled();
		}
		if($item->getId() === self::회복){
			$event->setCancelled();
		}
		if($item->getId() === self::돌격총){
			$event->setCancelled();
		}elseif($item->getId() === self::기본총){
			$event->setCancelled();
		}
	}
	
	public function onPickup(InventoryPickupItemEvent $event){
		$player = $event->getInventory()->getHolder();
		
		if($player instanceof Player){
			if($event->getItem()->getItem()->getId() === self::기본총){
				$this->players[$player->getName()][0]->addAmmo(30);
				if($player->getInventory()->contains(Item::get(self::기본총))){
					$event->getItem()->kill();
					$event->setCancelled();
				}
			}else{
				$event->getItem()->kill();
			}
		}
	}

	public function giveGun($player, BaseGun $gun){
		switch($this->players[$player][2]){
			case self::TEAM_RED: $color = [247, 2, 9]; break;
			case self::TEAM_BLUE: $color = [40, 45, 208]; break;
				break;
			default: return false;
		}

		$gun->setColor($color);
		$this->players[$player][0] = $gun;
		return true;
	}

	public function giveGrenade($playerName, BaseGrenade $grenade){
		$this->players[$playerName][1] = $grenade;
	}

	public function decreaseXP($playerName, $amount){
		if($this->level[$playerName] >= $amount) {
			$this->level[$playerName] -= $amount;
			return true;
		}else{
			return false;
		}
	}

	public function getTeam($playerName){
		return $this->players[$playerName][2];
	}

	public function getStatus(){
		return $this->status;
	}
}
