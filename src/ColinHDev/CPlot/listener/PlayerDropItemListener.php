<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\listener;

use ColinHDev\CPlot\attributes\BooleanAttribute;
use ColinHDev\CPlot\plots\flags\FlagIDs;
use ColinHDev\CPlot\plots\Plot;
use ColinHDev\CPlot\utils\APIHolder;
use ColinHDev\CPlot\ServerSettings;
use ColinHDev\CPlot\worlds\WorldSettings;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;

class PlayerDropItemListener implements Listener {
    use APIHolder;

    /**
     * @handleCancelled false
     */
    public function onPlayerDropItem(PlayerDropItemEvent $event) : void {
        $player = $event->getPlayer();
        $position = $player->getPosition();
        /** @phpstan-var WorldSettings|false|null $worldSettings */
        $worldSettings = $this->getAPI()->getOrLoadWorldSettings($position->getWorld())->getResult();
        if (!($worldSettings instanceof WorldSettings)) {
            if ($worldSettings !== false) {
                $event->cancel();
            }
            return;
        }
        $worldBorder = ServerSettings::getInstance()->getWorldBorder($position->getWorld()->getFolderName(), $worldSettings);
        if (!$worldBorder->isVectorInside($position->asVector3())) {
            $event->cancel();
            return;
        }

        /** @phpstan-var Plot|false|null $plot */
        $plot = $this->getAPI()->getOrLoadPlotAtPosition($position)->getResult();
        if ($plot instanceof Plot) {
            if ($player->hasPermission("cplot.interact.plot")) {
                return;
            }

            if ($plot->isPlotOwner($player)) {
                return;
            }
            if ($plot->isPlotTrusted($player)) {
                return;
            }
            if ($plot->isPlotHelper($player)) {
                foreach ($plot->getPlotOwners() as $plotOwner) {
                    $owner = $plotOwner->getPlayerData()->getPlayer();
                    if ($owner !== null) {
                        return;
                    }
                }
            }

            /** @var BooleanAttribute $flag */
            $flag = $plot->getFlagNonNullByID(FlagIDs::FLAG_ITEM_DROP);
            if ($flag->getValue() === true) {
                return;
            }
        }

        $event->cancel();
    }
}