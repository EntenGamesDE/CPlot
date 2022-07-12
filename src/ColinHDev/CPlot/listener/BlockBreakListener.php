<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\listener;

use ColinHDev\CPlot\attributes\BlockListAttribute;
use ColinHDev\CPlot\plots\flags\FlagIDs;
use ColinHDev\CPlot\plots\Plot;
use ColinHDev\CPlot\provider\DataProvider;
use ColinHDev\CPlot\provider\LanguageManager;
use ColinHDev\CPlot\ServerSettings;
use ColinHDev\CPlot\worlds\WorldSettings;
use ColinHDev\CPlot\utils\APIHolder;
use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;

class BlockBreakListener implements Listener {
    use APIHolder;

    /**
     * @handleCancelled false
     */
    public function onBlockBreak(BlockBreakEvent $event) : void {
        $position = $event->getBlock()->getPosition();
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
            $player = $event->getPlayer();
            if ($player->hasPermission("cplot.break.plot")) {
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

            $block = $event->getBlock();
            /** @var BlockListAttribute $flag */
            $flag = $plot->getFlagNonNullByID(FlagIDs::FLAG_BREAK);
            /** @var Block $value */
            foreach ($flag->getValue() as $value) {
                if ($block->isSameType($value)) {
                    return;
                }
            }

        } else if ($plot === false) {
            if ($event->getPlayer()->hasPermission("cplot.break.road")) {
                return;
            }
        }

        $event->cancel();
    }
}