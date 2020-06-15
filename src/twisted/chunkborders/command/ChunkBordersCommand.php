<?php
declare(strict_types=1);

namespace twisted\chunkborders\command;

use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use twisted\chunkborders\ChunkBorders;

class ChunkBordersCommand extends BaseCommand{

	public function __construct(ChunkBorders $plugin, string $name, string $description = "", array $aliases = []){
		parent::__construct($plugin, $name, $description, $aliases);
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void{
		if(!$sender instanceof Player){
			$sender->sendMessage(TextFormat::RED . "Use command in game.");

			return;
		}
		/** @var ChunkBorders $plugin */
		$plugin = $this->getPlugin();

		$plugin->setViewingChunkBorders($sender, !$plugin->isViewingChunkBorders($sender));

		$isViewing = $plugin->isViewingChunkBorders($sender);
		$sender->sendMessage(($isViewing ? TextFormat::GREEN : TextFormat::RED) . "You are " . ($isViewing ? "now" : "no longer") . " viewing chunk borders");
	}

	protected function prepare() : void{
		// NOOP
	}
}