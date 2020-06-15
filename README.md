# ChunkBorders
Makes use of structure blocks to create chunk borders in PocketMine-MP

## How does it work?
ChunkBorders makes use of structure blocks in order to display the borders of a player's current chunk like on Java edition. The plugin sends a structure block tile with a size 16x16x256 to display the borders.

## Can I change the color of the borders?
Unfortunately, you cannot change the color of the border. Minecraft creates a green line on the Y axis coming out of the tile, and a red and blue line on the X/Z axis coming out of the tile as well. The other lines not connected to the tile are all white.