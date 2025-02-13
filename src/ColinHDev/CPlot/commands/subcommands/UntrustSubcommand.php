<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\commands\subcommands;

use ColinHDev\CPlot\attributes\BooleanAttribute;
use ColinHDev\CPlot\commands\Subcommand;
use ColinHDev\CPlot\event\PlotPlayerRemoveAsyncEvent;
use ColinHDev\CPlot\player\PlayerData;
use ColinHDev\CPlot\player\settings\SettingIDs;
use ColinHDev\CPlot\plots\flags\FlagIDs;
use ColinHDev\CPlot\plots\Plot;
use ColinHDev\CPlot\plots\PlotPlayer;
use ColinHDev\CPlot\provider\DataProvider;
use ColinHDev\CPlot\provider\LanguageManager;
use ColinHDev\CPlot\worlds\WorldSettings;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;

/**
 * @phpstan-extends Subcommand<mixed, mixed, mixed, null>
 */
class UntrustSubcommand extends Subcommand {

    public function execute(CommandSender $sender, array $args) : \Generator {
        if (!$sender instanceof Player) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "untrust.senderNotOnline"]);
            return null;
        }

        if (count($args) === 0) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "untrust.usage"]);
            return null;
        }

        if ($args[0] !== "*") {
            $player = $sender->getServer()->getPlayerByPrefix($args[0]);
            if ($player instanceof Player) {
                $playerName = $player->getName();
            } else {
                yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "untrust.playerNotOnline" => $args[0]]);
                $playerName = $args[0];
                $player = yield DataProvider::getInstance()->awaitPlayerDataByName($playerName);
            }
        } else {
            $playerName = "*";
            $player = yield from DataProvider::getInstance()->awaitPlayerDataByXUID("*");
        }
        if (!($player instanceof Player) && !($player instanceof PlayerData)) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "untrust.playerNotFound" => $playerName]);
            return null;
        }

        if (!((yield DataProvider::getInstance()->awaitWorld($sender->getWorld()->getFolderName())) instanceof WorldSettings)) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "untrust.noPlotWorld"]);
            return null;
        }

        $plot = yield Plot::awaitFromPosition($sender->getPosition());
        if (!($plot instanceof Plot)) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "untrust.noPlot"]);
            return null;
        }
        if (!$plot->isOnServer()) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "untrust.plotNotOnServer"]);
            return null;
        }

        if (!$plot->hasPlotOwner()) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "untrust.noPlotOwner"]);
            return null;
        }
        if (!$sender->hasPermission("cplot.admin.untrust")) {
            if (!$plot->isPlotOwner($sender)) {
                yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "untrust.notPlotOwner"]);
                return null;
            }
        }

        /** @var BooleanAttribute $flag */
        $flag = $plot->getFlagNonNullByID(FlagIDs::FLAG_SERVER_PLOT);
        if ($flag->getValue() === true) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "untrust.serverPlotFlag" => $flag->getID()]);
            return null;
        }

        $plotPlayer = $plot->getPlotPlayerExact($player);
        if (!($plotPlayer instanceof PlotPlayer) || $plotPlayer->getState() !== PlotPlayer::STATE_TRUSTED) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "untrust.playerNotTrusted" => $playerName]);
            return null;
        }

        /** @phpstan-var PlotPlayerRemoveAsyncEvent $event */
        $event = yield from PlotPlayerRemoveAsyncEvent::create($plot, $plotPlayer, $sender);
        if ($event->isCancelled()) {
            return null;
        }

        $plot->removePlotPlayer($plotPlayer);
        $playerData = $plotPlayer->getPlayerData();
        yield DataProvider::getInstance()->deletePlotPlayer($plot, $playerData->getPlayerID());
        yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "untrust.success" => $playerName]);

        if ($player instanceof Player) {
            /** @var BooleanAttribute $setting */
            $setting = $playerData->getSettingNonNullByID(SettingIDs::SETTING_INFORM_TRUSTED_REMOVE);
            if ($setting->getValue() === true) {
                yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage(
                    $player,
                    ["prefix", "untrust.success.player" => [$sender->getName(), $plot->getWorldName(), $plot->getX(), $plot->getZ()]]
                );
            }
        }
        return null;
    }

    public function onError(CommandSender $sender, \Throwable $error) : void {
        if ($sender instanceof Player && !$sender->isConnected()) {
            return;
        }
        LanguageManager::getInstance()->getProvider()->sendMessage($sender, ["prefix", "untrust.saveError" => $error->getMessage()]);
    }
}