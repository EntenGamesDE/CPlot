<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\packet;

use ColinHDev\CPlot\commands\PlotCommand;
use ColinHDev\CPlot\commands\subcommands\ClaimSubcommand;
use ColinHDev\CPlot\plots\Plot;
use ColinHDev\CPlot\plots\TeleportDestination;
use ColinHDev\CPlot\provider\DataProvider;
use matze\cloudbridge\network\packets\DataPacket;
use matze\cloudbridge\network\packets\types\PlayerTransferPacket;
use pocketmine\player\Player;
use pocketmine\Server;
use SOFe\AwaitGenerator\Await;

/**
 * This packet is used to transfer a player to a plot in a specific plot world on another plot server.
 */
class PlayerTransferToPlotPacket extends PlayerTransferPacket {

    // The name of the plot server that the player is being transferred to.
    public string $plotServerName;
    // The name of the world the plot is on.
    public string $plotWorldName;
    // The X coordinate of the plot.
    public int $plotX;
    // The Z coordinate of the plot.
    public int $plotZ;
    // The destination where the player should be teleported to. A list of destinations can be found in {@see PlotTeleportDestination}.
    public int $teleportDestination;
    // Whether or not the plot should be automatically claimed by the player after the transfer.
    public bool $claimPlotAfterTransfer;

    /**
     * @param string $playerName            The name of the player that is being transferred.
     * @param string $plotServerName        The name of the plot server that the player is being transferred to.
     * @param string $plotWorldName         The name of the world the plot is on.
     * @param int $plotX                    The X coordinate of the plot.
     * @param int $plotZ                    The Z coordinate of the plot.
     * @param int $teleportDestination      The destination where the player should be teleported to. A list of destinations can be found in {@see PlotTeleportDestination}.
     * @phpstan-param TeleportDestination::* $teleportDestination
     * @param bool $claimPlotAfterTransfer  Whether or not the plot should be automatically claimed by the player after the transfer.
     * @return self                         Returns a new instance of this packet with the given values as properties.
     */
    public static function create(string $playerName, string $plotServerName, string $plotWorldName, int $plotX, int $plotZ, int $teleportDestination, bool $claimPlotAfterTransfer) : self {
        $packet = new self;
        $packet->playerName = $playerName;
        $packet->plotServerName = $plotServerName;
        $packet->plotWorldName = $plotWorldName;
        $packet->plotX = $plotX;
        $packet->plotZ = $plotZ;
        $packet->teleportDestination = $teleportDestination;
        $packet->claimPlotAfterTransfer = $claimPlotAfterTransfer;
        return $packet;
    }

    /**
     * @phpstan-param PlayerTransferToPlotPacket $packet
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
                $plot->teleportTo($player, $packet->teleportDestination);
                if ($packet->claimPlotAfterTransfer) {
                    $plotCommand = $server->getCommandMap()->getCommand("plot");
                    assert($plotCommand instanceof PlotCommand);
                    $claimSubcommand = $plotCommand->getSubcommandByName("claim");
                    assert($claimSubcommand instanceof ClaimSubcommand);
                    yield from $claimSubcommand->execute($player, []);
                }
            }
        );
    }
}