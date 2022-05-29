<?php
declare(strict_types=1);

namespace PunchBot;

use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;
use ReflectionProperty;
use function explode;

class PunchBotEntity extends Human{

	/** @var Position */
	protected Position $spawnVector;

	protected int $hitTick = 60;

	protected int $comeBackTick = 1200;

	protected float $follow_range_sq;

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->follow_range_sq = $this->getScale() + 8;

		[$x, $y, $z] = explode(":", $nbt->getString("spawnLocation"));
		$world = $nbt->getString("spawnLevel");
		$this->spawnVector = new Position(floatval($x), floatval($y), floatval($z), Server::getInstance()->getWorldManager()->getWorldByName($world));
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setIntArray("spawnLocation", [
			$this->spawnVector->getFloorX(),
			$this->spawnVector->getFloorY(),
			$this->spawnVector->getFloorZ()
		]);
		$nbt->setString("spawnLevel", $this->getWorld()->getFolderName());
		return $nbt;
	}

	public function attack(EntityDamageEvent $source) : void{
		$source->setBaseDamage(0);
		$ref = new ReflectionProperty(EntityDamageEvent::class, "modifiers");
		$ref->setAccessible(true);
		$ref->setValue($source, []);
		parent::attack($source);
		if($source instanceof EntityDamageByEntityEvent){
			$d = $source->getDamager();
			if($d instanceof Player){
				if($d->hasPermission(DefaultPermissions::ROOT_OPERATOR)){
					if($d->isSneaking())
						$this->kill();
				}
			}
		}
	}

	public function onUpdate(int $currentTick = 1) : bool{
		if($this->isClosed() || !$this->isAlive()){
			return false;
		}
		$updated = parent::onUpdate($currentTick);
		--$this->hitTick;
		if(count($res = $this->getPlayerInRadius(10)) > 0){
			$player = $res[0];
			$nextPosition = $player->getPosition();
			$this->follow($nextPosition);
			if($this->hitTick < 1){
				$this->hitTick = 20;
				$player->attack(new EntityDamageByEntityEvent($this, $player, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 0.5));
				$this->broadcastAnimation(new ArmSwingAnimation($this));
			}
		}else{
			--$this->comeBackTick;
			if($this->comeBackTick < 1){
				$this->teleport($this->spawnVector);
			}
		}
		if($this->spawnVector->distance($this->getPosition()) >= 20){
			$this->teleport($this->spawnVector);
		}
		return $updated;
	}

	/**
	 * @param int $radius
	 *
	 * @return Player[]
	 */
	public function getPlayerInRadius(int $radius) : array{
		$arr = [];
		foreach($this->getWorld()->getPlayers() as $player){
			if($this->getPosition()->distance($player->getPosition()) <= $radius)
				$arr[] = $player;
		}
		return $arr;
	}

	public function follow(Vector3 $target) : void{

		$x = $target->x - $this->getPosition()->getX();
		// $y = $target->y - $this->y;
		$z = $target->z - $this->getPosition()->getZ();
		$xz_sq = $x * $x + $z * $z;
		$xz_modulus = sqrt($xz_sq);
		if($xz_sq < $this->follow_range_sq){
			$this->motion->x = 0;
			$this->motion->z = 0;
		}else{
			$speed_factor = $this->getSpeed();
			$this->motion->x = $speed_factor * ($x / $xz_modulus);
			$this->motion->z = $speed_factor * ($z / $xz_modulus);
		}
		$this->lookAt($target);
		$this->move($this->motion->x, $this->motion->y, $this->motion->z);

	}

	public function getSpeed() : float{
		return 1.0;
	}
}