<?php
declare(strict_types=1);

namespace twisted\chunkborders;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\scheduler\ClosureTask;

class EventListener implements Listener{

	/** @var ChunkBorders */
	private $plugin;

	public function __construct(ChunkBorders $plugin){
		$this->plugin = $plugin;
	}

	/**
	 * @param PlayerMoveEvent $event
	 *
	 * @priority HIGHEST
	 * @ignoreCancelled true
	 */
	public function onPlayerMove(PlayerMoveEvent $event) : void{
		$player = $event->getPlayer();
		if(!$this->plugin->isViewingChunkBorders($player)){
			return;
		}

		$fromPos = $player->getLevel()->getChunkAtPosition($event->getFrom());
		$toPos = $player->getLevel()->getChunkAtPosition($event->getTo());
		if($fromPos->getX() !== $toPos->getX() || $fromPos->getZ() !== $toPos->getZ()){
			$this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function(int $currentTick) use ($player) : void{
				$this->plugin->updateChunkBordersFor($player);
			}), 1);
		}
	}
}