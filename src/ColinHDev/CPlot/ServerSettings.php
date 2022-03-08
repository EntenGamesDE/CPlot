<?php

namespace ColinHDev\CPlot;

use ColinHDev\CPlot\plots\BasePlot;
use ColinHDev\CPlot\worlds\WorldSettings;
use pocketmine\math\AxisAlignedBB;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;

class ServerSettings {
    use SingletonTrait;

    private int $ID;
    private int $worldSize = 13;
    private int $x;
    private int $z;
    /** @phpstan-var array<string, AxisAlignedBB> */
    private array $worldAABBs = [];

    public function __construct(int $ID, int $x, int $z) {
        $this->ID = $ID;
        $this->x = $x;
        $this->z = $z;
    }

    public function getID() : int {
        return $this->ID;
    }

    public function getWorldSize() : int {
        return $this->worldSize;
    }

    public function getX() : int {
        return $this->x;
    }

    public function getZ() : int {
        return $this->z;
    }

    public function getWorldBorder(string $worldName, WorldSettings $worldSettings) : AxisAlignedBB {
        if (isset($this->worldAABBs[$worldName])) {
            return clone $this->worldAABBs[$worldName];
        }
        $alignPlot = new BasePlot($worldName, $worldSettings, $this->x * $this->worldSize, $this->z * $this->worldSize);
        $alignPlotPosition = $alignPlot->getVector3();
        $borderLength = ($worldSettings->getPlotSize() + $worldSettings->getRoadSize()) * $this->worldSize + $worldSettings->getRoadSize();
        $distanceToBorderFromMiddle = $borderLength / 2;
        $serverMiddleX = ($alignPlotPosition->x - $worldSettings->getRoadSize()) + $distanceToBorderFromMiddle;
        $serverMiddleZ = ($alignPlotPosition->z - $worldSettings->getRoadSize()) + $distanceToBorderFromMiddle;
        $distanceToBorderFromMiddle--;
        $aabb = new AxisAlignedBB(
            $serverMiddleX - $distanceToBorderFromMiddle,
            PHP_INT_MIN,
            $serverMiddleZ - $distanceToBorderFromMiddle,
            $serverMiddleX + $distanceToBorderFromMiddle,
            PHP_INT_MAX,
            $serverMiddleZ + $distanceToBorderFromMiddle
        );
        $this->worldAABBs[$worldName] = $aabb;
        return clone $aabb;
    }
}