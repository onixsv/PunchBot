<?php
declare(strict_types=1);

namespace PunchBot;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Human;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\world\World;
use function implode;

class PunchBot extends PluginBase{

	protected function onEnable() : void{
		EntityFactory::getInstance()->register(PunchBotEntity::class, function(World $world, CompoundTag $nbt) : PunchBotEntity{
			return new PunchBotEntity(EntityDataHelper::parseLocation($nbt, $world), Human::parseSkinNBT($nbt), $nbt);
		}, ["PunchBot"]);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if($sender instanceof Player){
			$loc = $sender->getLocation();
			$entity = new PunchBotEntity($sender->getLocation(), $sender->getSkin(), CompoundTag::create()->setString("spawnLocation", implode(":", [$loc->getX(), $loc->getY(), $loc->getZ(), $loc->getWorld()->getFolderName()]))->setString("spawnLevel", $loc->getWorld()->getFolderName()));
			$entity->setNameTag("펀치봇");
			$entity->setNameTagVisible(true);
			$entity->spawnToAll();
		}
		return true;
	}
}