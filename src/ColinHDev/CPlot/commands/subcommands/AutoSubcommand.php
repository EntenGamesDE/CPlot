<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\commands\subcommands;

use ColinHDev\CPlot\commands\PlotCommand;
use ColinHDev\CPlot\commands\Subcommand;
use ColinHDev\CPlot\packet\PlayerTransferToPlotPacket;
use ColinHDev\CPlot\player\PlayerData;
use ColinHDev\CPlot\plots\Plot;
use ColinHDev\CPlot\plots\PlotPlayer;
use ColinHDev\CPlot\plots\TeleportDestination;
use ColinHDev\CPlot\provider\DataProvider;
use ColinHDev\CPlot\provider\LanguageManager;
use ColinHDev\CPlot\ResourceManager;
use ColinHDev\CPlot\ServerSettings;
use ColinHDev\CPlot\worlds\WorldSettings;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use function is_string;

/**
 * @phpstan-extends Subcommand<mixed, mixed, mixed, null>
 */
class AutoSubcommand extends Subcommand {

    private bool $automaticClaim;
    private ?string $fallbackWorld;
    private PlotCommand $command;

    public function __construct(string $key, PlotCommand $command) {
        parent::__construct($key);
        $this->automaticClaim = match(ResourceManager::getInstance()->getConfig()->get("auto.automaticClaim", false)) {
            true, "true" => true,
            default => false
        };
        $fallbackWorld = ResourceManager::getInstance()->getConfig()->get("auto.fallbackWorld", false);
        if ($fallbackWorld === false || $fallbackWorld === "false" || !is_string($fallbackWorld)) {
            $this->fallbackWorld = null;
        } else {
            $this->fallbackWorld = $fallbackWorld;
        }
        $this->command = $command;
    }

    public function execute(CommandSender $sender, array $args) : \Generator {
        if (!$sender instanceof Player) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "auto.senderNotOnline"]);
            return null;
        }

        $claimSubcommand = $this->command->getSubcommandByName("claim");
        assert($claimSubcommand instanceof ClaimSubcommand);
        if ($this->automaticClaim) {
            $playerData = yield DataProvider::getInstance()->awaitPlayerDataByPlayer($sender);
            if (!($playerData instanceof PlayerData)) {
                return null;
            }
            /** @phpstan-var array<string, Plot> $claimedPlots */
            $claimedPlots = yield DataProvider::getInstance()->awaitPlotsByPlotPlayer($playerData->getPlayerID(), PlotPlayer::STATE_OWNER);
            $claimedPlotsCount = count($claimedPlots);
            $maxPlots = $claimSubcommand->getMaxPlotsOfPlayer($sender);
            if ($claimedPlotsCount > $maxPlots) {
                yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "auto.plotLimitReached" => [$claimedPlotsCount, $maxPlots]]);
                return null;
            }
        }

        $worldName = $sender->getWorld()->getFolderName();
        $worldSettings = yield from DataProvider::getInstance()->awaitWorld($worldName);
        if (!($worldSettings instanceof WorldSettings) && is_string($this->fallbackWorld)) {
            $worldName = $this->fallbackWorld;
            $worldSettings = yield from DataProvider::getInstance()->awaitWorld($worldName);
        }
        if (!($worldSettings instanceof WorldSettings)) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "auto.noPlotWorld"]);
            return null;
        }

        $serverName = null;
        /** @phpstan-var ServerSettings|null $server */
        $server = yield from DataProvider::getInstance()->awaitServerWithLeastClaimedPlots($worldName);
        if ($server instanceof ServerSettings) {
            /** @var Plot|null $plot */
            $plot = yield from DataProvider::getInstance()->awaitNextFreePlotByServer($server->getX(), $server->getZ(), $worldName, $worldSettings);
            if ($plot === null) {
                yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "auto.noPlotFound"]);
                return null;
            }
            $serverName = $server->getName();
        } else {
            /** @var Plot|null $plot */
            $plot = yield DataProvider::getInstance()->awaitNextFreePlot($worldName, $worldSettings);
            if ($plot === null) {
                yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "auto.noPlotFound"]);
                return null;
            }
        }

        if ($plot->isOnServer()) {
            if (!($plot->teleportTo($sender))) {
                yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "auto.teleportError" => [$plot->getWorldName(), $plot->getX(), $plot->getZ()]]);
            } else {
                yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "auto.success" => [$plot->getWorldName(), $plot->getX(), $plot->getZ()]]);
                if ($this->automaticClaim) {
                    yield from $claimSubcommand->execute($sender, []);
                }
            }
        } else {
            /** @phpstan-var string $serverName */
            $serverName = $serverName ?? yield from DataProvider::getInstance()->awaitServerNameByCoordinatesNonNull($plot->getServerX(), $plot->getServerZ());
            $packet = PlayerTransferToPlotPacket::create(
                $sender->getName(),
                $serverName,
                $plot->getWorldName(), $plot->getX(), $plot->getZ(),
                TeleportDestination::PLOT_SPAWN_OR_EDGE, true
            );
            $packet->send();
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "auto.success.plotNotOnServer" => [$plot->getWorldName(), $plot->getX(), $plot->getZ()]]);
        }
        return null;
    }
}