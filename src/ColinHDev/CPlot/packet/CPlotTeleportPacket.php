<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\packet;

use ColinHDev\CPlot\plots\Plot;
use ColinHDev\CPlot\provider\DataProvider;
use matze\cloudbridge\network\packets\DataPacket;
use matze\cloudbridge\network\packets\types\PlayerTransferPacket;
use pocketmine\player\Player;
use pocketmine\Server;
use SOFe\AwaitGenerator\Await;

/**
 * This packet is used to transfer a player to a plot in a specific plot world on another plot server.
 */
class CPlotTeleportPacket extends PlayerTransferPacket {

    // The name of the plot server that the player is being transferred to.
    public string $plotServerName;
    // The name of the world the plot is on.
    public string $plotWorldName;
    // The X coordinate of the plot.
    public int $plotX;
    // The Z coordinate of the plot.
    public int $plotZ;
    // Whether the player should be teleported to the center of the plot.
    public bool $teleportToPlotCenter;
    // Whether the player should be teleported to the set spawn of the plot.
    public bool $checkPlotSpawnFlag;
    // Whether or not the plot should be automatically claimed by the player after the transfer.
    public bool $claimPlotAfterTransfer;

    /**
     * @param string $playerName            The name of the player that is being transferred.
     * @param string $plotServerName        The name of the plot server that the player is being transferred to.
     * @param string $plotWorldName         The name of the world the plot is on.
     * @param int $plotX                    The X coordinate of the plot.
     * @param int $plotZ                    The Z coordinate of the plot.
     * @param bool $teleportToPlotCenter    Whether the player should be teleported to the center of the plot.
     * @param bool $checkPlotSpawnFlag      Whether the player should be teleported to the set spawn of the plot.
     * @param bool $claimPlotAfterTransfer  Whether or not the plot should be automatically claimed by the player after the transfer.
     * @return self                         Returns a new instance of this packet with the given values as properties.
     */
    public static function create(string $playerName, string $plotServerName, string $plotWorldName, int $plotX, int $plotZ, bool $teleportToPlotCenter, bool $checkPlotSpawnFlag, bool $claimPlotAfterTransfer) : self {
        $packet = new self;
        $packet->playerName = $playerName;
        $packet->plotServerName = $plotServerName;
        $packet->plotWorldName = $plotWorldName;
        $packet->plotX = $plotX;
        $packet->plotZ = $plotZ;
        $packet->teleportToPlotCenter = $teleportToPlotCenter;
        $packet->checkPlotSpawnFlag = $checkPlotSpawnFlag;
        $packet->claimPlotAfterTransfer = $claimPlotAfterTransfer;
        return $packet;
    }

    /**
     * @phpstan-param CPlotTeleportPacket $packet
     */
    public function handle(DataPacket $packet) : void {
        Await::f2c(
            static function() use($packet) : \Generator {
                /** @phpstan-var Plot|null $plot */
                $plot = yield from DataProvider::getInstance()->awaitPlot($packet->plotWorldName, $packet->plotX, $packet->plotZ);
                $server = Server::getInstance();
                $player = $server->getPlayerExact($packet->playerName);
                if (!$player instanceof Player) {
                    return;
                }
                $plot->teleportTo($player, $packet->teleportToPlotCenter, $packet->checkPlotSpawnFlag);
            }
        );
    }
}