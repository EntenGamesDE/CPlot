<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\listener;

use ColinHDev\CPlot\attributes\BooleanAttribute;
use ColinHDev\CPlot\attributes\BooleanListAttribute;
use ColinHDev\CPlot\attributes\StringAttribute;
use ColinHDev\CPlot\player\settings\SettingIDs;
use ColinHDev\CPlot\plots\BasePlot;
use ColinHDev\CPlot\plots\flags\FlagIDs;
use ColinHDev\CPlot\plots\Plot;
use ColinHDev\CPlot\provider\DataProvider;
use ColinHDev\CPlot\ResourceManager;
use ColinHDev\CPlot\worlds\WorldSettings;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use Ramsey\Uuid\Uuid;
use SOFe\AwaitGenerator\Await;

class PlayerMoveListener implements Listener {

    public function onPlayerMove(PlayerMoveEvent $event) : void {
        if ($event->isCancelled()) {
            return;
        }
        $to = $event->getTo();
        $worldSettings = DataProvider::getInstance()->loadWorldIntoCache($to->getWorld()->getFolderName());
        if (!($worldSettings instanceof WorldSettings)) {
            return;
        }

        $alignPlot = new BasePlot($to->world->getFolderName(), $worldSettings, 0, 0);
        $alignPlotPosition = $alignPlot->getVector3();
        $distanceToBorder = (($worldSettings->getPlotSize() + $worldSettings->getRoadSize()) * 3 + $worldSettings->getRoadSize()) / 2;
        $serverMiddleX = ($alignPlotPosition->x - $worldSettings->getRoadSize()) + $distanceToBorder;
        $serverMiddleZ = ($alignPlotPosition->x - $worldSettings->getRoadSize()) + $distanceToBorder;
        $distanceToBorder--;
        $borderNorth = $serverMiddleZ - $distanceToBorder;
        $borderSouth = $serverMiddleZ + $distanceToBorder;
        $borderWest = $serverMiddleX - $distanceToBorder;
        $borderEast = $serverMiddleX + $distanceToBorder;
        if ($to->x >= $borderEast || $to->x <= $borderWest || $to->z >= $borderSouth || $to->z <= $borderNorth) {
            $from = $event->getFrom();
            if ($from->x < $borderEast && $from->x > $borderWest && $from->z < $borderSouth && $from->z > $borderNorth) {
                $oppositeMoveDirection = $from->subtractVector($to);
                $event->getPlayer()->knockBack($oppositeMoveDirection->x, $oppositeMoveDirection->z, 0.6);
            } else if ($from->x >= ++$borderEast || $from->x <= --$borderWest || $from->z >= ++$borderSouth || $from->z <= --$borderNorth) {
                $alignPlot->teleportTo($event->getPlayer());
            }
            return;
        }

        Await::f2c(
            static function () use ($event) : \Generator {
                /** @var Plot|null $plotTo */
                $plotTo = yield from Plot::awaitFromPosition($event->getTo());
                /** @var Plot|null $plotFrom */
                $plotFrom = yield from Plot::awaitFromPosition($event->getFrom());

                $player = $event->getPlayer();
                if (!$player->isConnected()) {
                    return;
                }
                $playerUUID = $player->getUniqueId()->getBytes();

                if ($plotTo instanceof Plot) {
                    // check if player is denied and hasn't bypass permission
                    if (!$player->hasPermission("cplot.bypass.deny")) {
                        if ($plotTo->isPlotDenied($playerUUID) && $plotTo->isOnPlot($player->getPosition())) {
                            $plotTo->teleportTo($player, false, false);
                            return;
                        }
                    }

                    // flags on plot enter
                    if ($plotFrom === null) {
                        // settings on plot enter
                        $playerData = yield from DataProvider::getInstance()->awaitPlayerDataByUUID($playerUUID);
                        if ($playerData !== null) {
                            foreach ($plotTo->getFlags() as $flag) {
                                $setting = $playerData->getSettingNonNullByID(SettingIDs::BASE_SETTING_WARN_FLAG . $flag->getID());
                                if (!($setting instanceof BooleanListAttribute)) {
                                    continue;
                                }
                                foreach ($setting->getValue() as $value) {
                                    if ($value === $flag->getValue()) {
                                        $player->sendMessage(
                                            ResourceManager::getInstance()->getPrefix() .
                                            ResourceManager::getInstance()->translateString(
                                                "player.move.setting.warn_flag",
                                                [$flag->getID(), $flag->toString()]
                                            )
                                        );
                                        continue 2;
                                    }
                                }
                            }
                        }

                        // title flag && message flag
                        $title = "";
                        /** @var BooleanAttribute $flag */
                        $flag = $plotTo->getFlagNonNullByID(FlagIDs::FLAG_TITLE);
                        if ($flag->getValue() === true) {
                            $title .= ResourceManager::getInstance()->translateString(
                                "player.move.plotEnter.title.coordinates",
                                [$plotTo->getWorldName(), $plotTo->getX(), $plotTo->getZ()]
                            );
                            if ($plotTo->hasPlotOwner()) {
                                $plotOwners = [];
                                foreach ($plotTo->getPlotOwners() as $plotOwner) {
                                    $plotOwnerData = yield from DataProvider::getInstance()->awaitPlayerDataByUUID($plotOwner->getPlayerUUID());
                                    $plotOwners[] = $plotOwnerData?->getPlayerName() ?? "ERROR:" . $plotOwner->getPlayerUUID();
                                }
                                $title .= ResourceManager::getInstance()->translateString(
                                    "player.move.plotEnter.title.owner",
                                    [
                                        implode(ResourceManager::getInstance()->translateString("player.move.plotEnter.title.owner.separator"), $plotOwners)
                                    ]
                                );
                            }
                        }
                        /** @var StringAttribute $flag */
                        $flag = $plotTo->getFlagNonNullByID(FlagIDs::FLAG_MESSAGE);
                        if ($flag->getValue() !== "") {
                            $title .= ResourceManager::getInstance()->translateString(
                                "player.move.plotEnter.title.flag.message",
                                [$flag->getValue()]
                            );
                        }
                        $player->sendTip($title);

                        // plot_enter flag
                        /** @var BooleanAttribute $flag */
                        $flag = $plotTo->getFlagNonNullByID(FlagIDs::FLAG_PLOT_ENTER);
                        if ($flag->getValue() === true) {
                            foreach ($plotTo->getPlotOwners() as $plotOwner) {
                                $owner = $player->getServer()->getPlayerByUUID(Uuid::fromBytes($plotOwner->getPlayerUUID()));
                                $owner?->sendMessage(
                                    ResourceManager::getInstance()->getPrefix() .
                                    ResourceManager::getInstance()->translateString(
                                        "player.move.plotEnter.flag.plot_enter",
                                        [$player->getName(), $plotTo->getWorldName(), $plotTo->getX(), $plotTo->getZ()]
                                    )
                                );
                            }
                        }

                        // TODO: check_offlinetime flag && offline system
                    }
                }

                // plot leave
                if ($plotFrom instanceof Plot && $plotTo === null) {
                    // plot_leave flag
                    /** @var BooleanAttribute $flag */
                    $flag = $plotFrom->getFlagNonNullByID(FlagIDs::FLAG_PLOT_LEAVE);
                    if ($flag->getValue() === true) {
                        foreach ($plotFrom->getPlotOwners() as $plotOwner) {
                            $owner = $player->getServer()->getPlayerByUUID(Uuid::fromBytes($plotOwner->getPlayerUUID()));
                            $owner?->sendMessage(
                                ResourceManager::getInstance()->getPrefix() .
                                ResourceManager::getInstance()->translateString(
                                    "player.move.plotLeave.flag.plot_leave",
                                    [$player->getName(), $plotFrom->getWorldName(), $plotFrom->getX(), $plotFrom->getZ()]
                                )
                            );
                        }
                    }
                }
            }
        );
    }
}