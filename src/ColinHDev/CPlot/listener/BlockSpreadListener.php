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
use pocketmine\block\Liquid;
use pocketmine\event\block\BlockSpreadEvent;
use pocketmine\event\Listener;

class BlockSpreadListener implements Listener {

    public function onBlockSpread(BlockSpreadEvent $event) : void {
        if ($event->isCancelled()) {
            return;
        }
        if (!$event->getNewState() instanceof Liquid) {
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
            $flag = $plot->getFlagNonNullByID(FlagIDs::FLAG_FLOWING);
            if ($flag->getValue() === true) {
                return;
            }
        }

        $event->cancel();
    }
}