<?php

declare(strict_types=1);

namespace ColinHDev\CPlot;

use ColinHDev\CPlot\plots\BasePlot;
use ColinHDev\CPlot\worlds\WorldSettings;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;

class ServerSettings {
    use SingletonTrait;

    private string $name;
    private int $worldSize = 13;
    private int $x;
    private int $z;
    /** @phpstan-var array<string, AxisAlignedBB> */
    private array $worldAABBs = [];
    /** @phpstan-var array<string, array<int, AxisAlignedBB[]>> */
    private array $worldPassways = [];

    public function __construct(string $name, int $x, int $z) {
        $this->name = $name;
        $this->x = $x;
        $this->z = $z;
    }

    public function getName() : string {
        return $this->name;
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

    /**
     * @phpstan-return array<int, AxisAlignedBB[]>
     */
    public function getWorldPassways(string $worldName, WorldSettings $worldSettings) : array {
        if (isset($this->worldPassways[$worldName])) {
            return $this->worldPassways[$worldName];
        }
        $worldBorder = $this->getWorldBorder($worldName, $worldSettings);
        $roadPlotLength = $worldSettings->getRoadSize() + $worldSettings->getPlotSize();
        $borderLength = ($roadPlotLength) * $this->worldSize + $worldSettings->getRoadSize();
        $x = $this->x * $borderLength;
        $z = $this->z * $borderLength;
        $this->worldPassways[$worldName] = [];
        $this->worldPassways[$worldName][Facing::NORTH] = [];
        $this->worldPassways[$worldName][Facing::SOUTH] = [];
        $this->worldPassways[$worldName][Facing::WEST] = [];
        $this->worldPassways[$worldName][Facing::EAST] = [];
        for ($i = 0; $i <= $this->worldSize; $i++) {
            $this->worldPassways[$worldName][Facing::NORTH][] = new AxisAlignedBB(
                ($x + $i * $roadPlotLength) + 1,
                World::Y_MIN,
                $worldBorder->minZ,
                ($x + $i * $roadPlotLength) + $worldSettings->getRoadSize() - 1,
                World::Y_MAX,
                $worldBorder->minZ
            );
            $this->worldPassways[$worldName][Facing::SOUTH][] = new AxisAlignedBB(
                ($x + $i * $roadPlotLength) + 1,
                World::Y_MIN,
                $worldBorder->maxZ,
                ($x + $i * $roadPlotLength) + $worldSettings->getRoadSize() - 1,
                World::Y_MAX,
                $worldBorder->maxZ
            );
            $this->worldPassways[$worldName][Facing::WEST][] = new AxisAlignedBB(
                $worldBorder->minX,
                World::Y_MIN,
                ($z + $i * $roadPlotLength) + 1,
                $worldBorder->minX,
                World::Y_MAX,
                ($x + $i * $roadPlotLength) + $worldSettings->getRoadSize() - 1
            );
            $this->worldPassways[$worldName][Facing::EAST][] = new AxisAlignedBB(
                $worldBorder->maxX,
                World::Y_MIN,
                ($z + $i * $roadPlotLength) + 1,
                $worldBorder->maxX,
                World::Y_MAX,
                ($x + $i * $roadPlotLength) + $worldSettings->getRoadSize() - 1
            );
        }
        return $this->worldPassways[$worldName];
    }
}