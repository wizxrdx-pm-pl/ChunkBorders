<?php
declare(strict_types=1);

namespace twisted\chunkborders;

use pocketmine\block\Block;
use pocketmine\level\Position;
use pocketmine\level\SimpleChunkManager;
use pocketmine\math\Vector3;
use pocketmine\nbt\NetworkLittleEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\types\StructureEditorData;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use twisted\chunkborders\command\ChunkBordersCommand;

class ChunkBorders extends PluginBase{

	/** @var NetworkLittleEndianNBTStream */
	private $networkStream;

	/**
	 * @var Position[]
	 * @phpstan-var array<int, Position>
	 */
	private $viewers = [];

	public function onLoad() : void{
		$this->networkStream = new NetworkLittleEndianNBTStream();
	}

	public function onEnable() : void{
		$this->getServer()->getCommandMap()->register("chunkborders", new ChunkBordersCommand($this, "chunkborders", "Show or hide chunk borders", ["showchunks"]));
		$this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
	}

	/**
	 * Returns whether or not a player is currently viewing chunk borders.
	 *
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function isViewingChunkBorders(Player $player) : bool{
		return isset($this->viewers[$player->getId()]);
	}

	/**
	 * Set whether or not a player can see chunk borders. If $viewing is false and
	 *  the player is currently viewing chunk borders, they will immediately dissapear.
	 *
	 * @param Player $player
	 * @param bool   $viewing
	 */
	public function setViewingChunkBorders(Player $player, bool $viewing = true) : void{
		if(!$viewing){
			$this->removeChunkBorderFrom($player);

			return;
		}

		$this->updateChunkBordersFor($player);
	}

	/**
	 * Removes the current chunk border from the provided Player.
	 *
	 * @param Player $player
	 */
	public function removeChunkBorderFrom(Player $player) : void{
		$tilePos = $this->viewers[$player->getId()] ?? null;
		if($tilePos !== null){
			$this->sendFakeBlock($player, $tilePos, Block::get(Block::STRUCTURE_BLOCK));
			$tilePos->getLevel()->sendBlocks([$player], [$tilePos]);
		}
		unset($this->viewers[$player->getId()]);
	}

	/**
	 * Send a fake block to a specific player
	 *  rather than the whole server.
	 *
	 * @param Player  $player
	 * @param Vector3 $position
	 * @param Block   $block
	 */
	private function sendFakeBlock(Player $player, Vector3 $position, Block $block) : void{
		$pk = new UpdateBlockPacket();
		$pk->x = $position->getX();
		$pk->y = $position->getY();
		$pk->z = $position->getZ();
		$pk->blockRuntimeId = $block->getRuntimeId();
		$pk->flags = UpdateBlockPacket::FLAG_NETWORK;
		$player->sendDataPacket($pk);
	}

	/**
	 * Attempts to remove the Player"s current chunk border and then
	 *  creates a new chunk border in their current chunk.
	 *
	 * @param Player $player
	 */
	public function updateChunkBordersFor(Player $player) : void{
		$this->removeChunkBorderFrom($player);

		$chunk = $player->getLevel()->getChunkAtPosition($player);
		if($chunk !== null){
			$position = new Position($chunk->getX() * 16, 0, $chunk->getZ() * 16, $player->getLevel());
			$originalBlock = $player->getLevel()->getBlock($position);

			$this->sendFakeBlock($player, $position, Block::get(Block::STRUCTURE_BLOCK));
			$this->sendStructureBlockTile([$player], $position);
			$this->sendBlockWhilstKeepingTile($position, $originalBlock);

			$this->viewers[$player->getId()] = $position;
		}
	}

	/**
	 * Sends the structure block tile to the player with
	 *  a size of 16x16x256 starting from $position.
	 *
	 * @param Player[] $players
	 * @param Vector3  $position
	 */
	private function sendStructureBlockTile(array $players, Vector3 $position) : void{
		$nbt = new CompoundTag();
		$nbt->setString("id", "StructureBlock");

		$nbt->setInt("data", StructureEditorData::TYPE_EXPORT);
		$nbt->setString("dataField", "");
		$nbt->setByte("ignoreEntities", 0);
		$nbt->setByte("includePlayers", 1);
		$nbt->setFloat("integrity", 1.0);
		$nbt->setByte("isMovable", 0);
		$nbt->setByte("isPowered", 1);
		$nbt->setByte("mirror", 0);
		$nbt->setByte("removeBlocks", 0);
		$nbt->setByte("rotation", 0);
		$nbt->setLong("seed", 0);
		$nbt->setByte("showBoundingBox", 1);
		$nbt->setString("structureName", "Chunk Border");

		$nbt->setInt("x", $position->getX());
		$nbt->setInt("y", $position->getX());
		$nbt->setInt("z", $position->getX());

		$nbt->setInt("xStructureOffset", 0);
		$nbt->setInt("yStructureOffset", 0);
		$nbt->setInt("zStructureOffset", 0);

		$nbt->setInt("xStructureSize", 16);
		$nbt->setInt("yStructureSize", 256);
		$nbt->setInt("zStructureSize", 16);

		$pk = new BlockActorDataPacket();
		$pk->x = $position->getX();
		$pk->y = $position->getY();
		$pk->z = $position->getZ();
		$pk->namedtag = $this->networkStream->write($nbt);

		foreach($players as $player){
			if($player instanceof Player){
				$player->sendDataPacket($pk);
			}
		}
	}

	/**
	 * Use a chunk manager to replace a block at a certain
	 *  position whilst not overriding the tile already there.
	 *
	 * @param Position $position
	 * @param Block    $block
	 */
	private function sendBlockWhilstKeepingTile(Position $position, Block $block) : void{
		$level = $position->getLevel();
		if($level === null){
			return;
		}

		$chunk = $level->getChunkAtPosition($position);
		if($chunk === null){
			return;
		}
		$chunkX = $chunk->getX();
		$chunkZ = $chunk->getZ();

		$chunkManager = new SimpleChunkManager($level->getSeed());
		$chunkManager->setChunk($chunkX, $chunkZ, $chunk);
		$chunkManager->setBlockIdAt($block->getX(), $block->getY(), $block->getZ(), $block->getId());
		$chunkManager->setBlockDataAt($block->getX(), $block->getY(), $block->getZ(), $block->getDamage());

		$level->setChunk($chunkX, $chunkZ, $chunkManager->getChunk($chunkX, $chunkZ), false);
	}
}