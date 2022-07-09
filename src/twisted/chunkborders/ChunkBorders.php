<?php
declare(strict_types=1);

namespace twisted\chunkborders;

use pocketmine\block\Block;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\UnknownBlock;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use pocketmine\world\SimpleChunkManager;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\types\StructureEditorData;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\world\World;
use twisted\chunkborders\command\ChunkBordersCommand;

class ChunkBorders extends PluginBase{

	/**
	 * @var Position[]
	 * @phpstan-var array<int, Position>
	 */
	private array $viewers = [];

	public function onEnable() : void{
		# UpdateNotifier::checkUpdate($this->getDescription()->getName(), $this->getDescription()->getVersion());

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
			$this->sendFakeBlock($player, $tilePos, new UnknownBlock(new BlockIdentifier(BlockLegacyIds::STRUCTURE_BLOCK, 0), BlockBreakInfo::indestructible()));
            $pks = $player->getWorld()->createBlockUpdatePackets([$tilePos]);
            foreach ($pks as $pk) {
			$player->getNetworkSession()->sendDataPacket($pk);
            }
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
        $blockPos = BlockPosition::fromVector3($position);
        $blockRuntimeId = RuntimeBlockMapping::getInstance()->toRuntimeId($block->getFullId());
		$pk = UpdateBlockPacket::create($blockPos, $blockRuntimeId, UpdateBlockPacket::FLAG_NETWORK, UpdateBlockPacket::DATA_LAYER_NORMAL);
		$player->getNetworkSession()->sendDataPacket($pk);
	}

	/**
	 * Attempts to remove the Player"s current chunk border and then
	 *  creates a new chunk border in their current chunk.
	 *
	 * @param Player $player
	 */
	public function updateChunkBordersFor(Player $player) : void{
		$this->removeChunkBorderFrom($player);

        $chunk = $player->getWorld()->getOrLoadChunkAtPosition($player->getPosition());
        $chunkX = $player->getPosition()->getFloorX() >> Chunk::COORD_BIT_SIZE;
        $chunkZ = $player->getPosition()->getFloorZ() >> Chunk::COORD_BIT_SIZE;

		if($chunk !== null){
			$position = new Position($chunkX * 16, 0, $chunkZ * 16, $player->getWorld());
			$originalBlock = $player->getWorld()->getBlock($position);

			$this->sendFakeBlock($player, $position, new UnknownBlock(new BlockIdentifier(BlockLegacyIds::STRUCTURE_BLOCK, 0), BlockBreakInfo::indestructible()));
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
        $nbt->setInt("x", $position->getFloorX());
        $nbt->setInt("y", $position->getFloorX());
        $nbt->setInt("z", $position->getFloorX());
        $nbt->setByte("isMovable", 0);

        $nbt->setByte("isPowered", 1);
		$nbt->setInt("data", StructureEditorData::TYPE_EXPORT);

        $nbt->setInt("xStructureOffset", 0);
        $nbt->setInt("yStructureOffset", 0);
        $nbt->setInt("zStructureOffset", 0);

        $nbt->setInt("xStructureSize", 16);
        $nbt->setInt("yStructureSize", 256);
        $nbt->setInt("zStructureSize", 16);

		$nbt->setString("structureName", "Chunk Border");
		$nbt->setString("dataField", "");

		$nbt->setByte("ignoreEntities", 0);
		$nbt->setByte("includePlayers", 1);
		$nbt->setByte("removeBlocks", 0);
		$nbt->setByte("showBoundingBox", 1);
		$nbt->setByte("rotation", 0);
		$nbt->setByte("mirror", 0);
        $nbt->setByte("animationMode", 0);
        $nbt->setFloat("animationSeconds", 0);
		$nbt->setFloat("integrity", 1.0);

		$nbt->setLong("seed", 0);

        $blockPos = BlockPosition::fromVector3($position);
        $cacheableNbt = new CacheableNbt($nbt);
		$pk = BlockActorDataPacket::create($blockPos, $cacheableNbt);

		foreach($players as $player){
			if($player instanceof Player){
				$player->getNetworkSession()->sendDataPacket($pk);
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
		$level = $position->getWorld();

		$chunk = $level->getOrLoadChunkAtPosition($position);
		if($chunk === null){
			return;
		}
        $chunkX = $position->getFloorX() >> 4;
        $chunkZ = $position->getFloorZ() >> 4;

		$chunkManager = new SimpleChunkManager(World::Y_MIN, World::Y_MAX);
		$chunkManager->setChunk($chunkX, $chunkZ, $chunk);
		$chunkManager->setBlockAt($block->getPosition()->getFloorX(), $block->getPosition()->getFloorY(), $block->getPosition()->getFloorZ(), $block);

        if (($chunk = $chunkManager->getChunk($chunkX, $chunkZ)) !== null) {
		    $level->setChunk($chunkX, $chunkZ, $chunk);
        }
	}
}