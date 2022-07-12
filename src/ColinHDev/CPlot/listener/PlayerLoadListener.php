<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\listener;

use ColinHDev\CPlot\provider\DataProvider;
use ColinHDev\CPlot\provider\LanguageManager;
use ManagerSystem\event\PlayerLoadEvent;
use pocketmine\event\Listener;
use SOFe\AwaitGenerator\Await;

class PlayerLoadListener implements Listener {

    /**
     * @handleCancelled false
     */
    public function onPlayerLogin(PlayerLoadEvent $event) : void {
        $player = $event->getPlayer();
        Await::g2c(
            DataProvider::getInstance()->updatePlayerData(
                $player->getUniqueId()->getBytes(),
                $player->getXuid(),
                $player->getName()
            ),
            null,
            static function() use ($player) : void {
                if ($player->isConnected()) {
                    $player->kick(
                        LanguageManager::getInstance()->getProvider()->translateString(["prefix", "player.login.savePlayerDataError"])
                    );
                }
            }
        );
    }
}