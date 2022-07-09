<?php
declare(strict_types=1);

namespace twisted\chunkborders;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\format\Chunk;

class EventListener implements Listener{

	private ChunkBorders $plugin;

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

        $fromPosX = $event->getFrom()->getFloorX() >> Chunk::COORD_BIT_SIZE;
        $fromPosZ = $event->getFrom()->getFloorZ() >> Chunk::COORD_BIT_SIZE;
        $toPosX = $event->getTo()->getFloorX() >> Chunk::COORD_BIT_SIZE;
        $toPosZ = $event->getTo()->getFloorZ() >> Chunk::COORD_BIT_SIZE;

		if($fromPosX !== $toPosX || $fromPosZ !== $toPosZ){
			$this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player) : void{
				$this->plugin->updateChunkBordersFor($player);
			}), 1);
		}
	}
}