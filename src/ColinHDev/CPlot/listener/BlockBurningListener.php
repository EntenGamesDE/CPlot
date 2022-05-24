<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\listener;

use ColinHDev\CPlot\attributes\BooleanAttribute;
use ColinHDev\CPlot\plots\BasePlot;
use ColinHDev\CPlot\plots\flags\FlagIDs;
use ColinHDev\CPlot\plots\Plot;
use ColinHDev\CPlot\provider\DataProvider;
use ColinHDev\CPlot\ServerSettings;
use ColinHDev\CPlot\worlds\WorldSettings;
use pocketmine\event\block\BlockBurnEvent;
use pocketmine\event\Listener;

class BlockBurningListener implements Listener {

    public function onBlockBurn(BlockBurnEvent $event) : void {
        if ($event->isCancelled()) {
            return;
        }

        $position = $event->getBlock()->getPosition();
        $worldName = $position->getWorld()->getFolderName();
        $worldSettings = DataProvider::getInstance()->loadWorldIntoCache($worldName);
        if ($worldSettings === null) {
            $event->cancel();
            return;
        }
        if (!$worldSettings instanceof WorldSettings) {
            return;
        }
        $worldBorder = ServerSettings::getInstance()->getWorldBorder($worldName, $worldSettings);
        if (!$worldBorder->isVectorInside($position->asVector3())) {
            $event->cancel();
            return;
        }

        $plot = Plot::loadFromPositionIntoCache($position);
        if ($plot instanceof BasePlot && !$plot instanceof Plot) {
            $event->cancel();
            return;
        }
        if ($plot instanceof Plot) {
            /** @var BooleanAttribute $flag */
            $flag = $plot->getFlagNonNullByID(FlagIDs::FLAG_BURNING);
            if ($flag->getValue() === true) {
                return;
            }
        }

        $event->cancel();
    }
}