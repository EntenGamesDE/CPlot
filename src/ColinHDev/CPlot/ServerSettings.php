<?php

declare(strict_types=1);

namespace ColinHDev\CPlot;

use ColinHDev\CPlot\plots\BasePlot;
use ColinHDev\CPlot\provider\DataProvider;
use ColinHDev\CPlot\worlds\WorldSettings;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;
use SOFe\AwaitGenerator\Await;

class ServerSettings {
    use SingletonTrait;

    private string $name;
    private int $worldSize = 13;
    private int $x;
    private int $z;
    /** @phpstan-var array<string, Vector3> */
    private array $worldMiddles = [];
    /** @phpstan-var array<string, AxisAlignedBB> */
    private array $worldAABBs = [];
    /** @phpstan-var array<string, array<int, AxisAlignedBB[]>> */
    private array $worldPassways = [];
    /** @phpstan-var array<int, string> */
    private array $serversAround = [];

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

    public function getWorldMiddle(string $worldName, WorldSettings $worldSettings) : Vector3 {
        if (isset($this->worldMiddles[$worldName])) {
            return clone $this->worldMiddles[$worldName];
        }
        $alignPlot = new BasePlot($worldName, $worldSettings, $this->x * $this->worldSize, $this->z * $this->worldSize);
        $alignPlotPosition = $alignPlot->getVector3();
        $borderLength = ($worldSettings->getPlotSize() + $worldSettings->getRoadSize()) * $this->worldSize + $worldSettings->getRoadSize();
        $distanceToBorderFromMiddle = $borderLength / 2;
        $serverMiddleX = ($alignPlotPosition->x - $worldSettings->getRoadSize()) + $distanceToBorderFromMiddle;
        $serverMiddleZ = ($alignPlotPosition->z - $worldSettings->getRoadSize()) + $distanceToBorderFromMiddle;
        $worldMiddle = new Vector3($serverMiddleX, $alignPlotPosition->y, $serverMiddleZ);
        $this->worldMiddles[$worldName] = $worldMiddle;
        return clone $worldMiddle;
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
        $borderLength = $roadPlotLength * $this->worldSize;
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
                ($z + $i * $roadPlotLength) + $worldSettings->getRoadSize() - 1
            );
            $this->worldPassways[$worldName][Facing::EAST][] = new AxisAlignedBB(
                $worldBorder->maxX,
                World::Y_MIN,
                ($z + $i * $roadPlotLength) + 1,
                $worldBorder->maxX,
                World::Y_MAX,
                ($z + $i * $roadPlotLength) + $worldSettings->getRoadSize() - 1
            );
        }
        return $this->worldPassways[$worldName];
    }

    /**
     *
     * @return array<int, string>
     */
    public function getServersAround() : array {
        return $this->serversAround;
    }

    public function updateServerData() : void {
        Await::f2c(
            function() : \Generator {
                $serverX = $this->getX();
                $serverZ = $this->getZ();
                $serverName = yield from DataProvider::getInstance()->awaitServerNameByCoordinates($serverX + 1, $serverZ);
                if (is_string($serverName)) {
                    $this->serversAround[Facing::EAST] = $serverName;
                } else {
                    unset($this->serversAround[Facing::EAST]);
                }
                $serverName = yield from DataProvider::getInstance()->awaitServerNameByCoordinates($serverX - 1, $serverZ);
                if (is_string($serverName)) {
                    $this->serversAround[Facing::WEST] = $serverName;
                } else {
                    unset($this->serversAround[Facing::WEST]);
                }
                $serverName = yield from DataProvider::getInstance()->awaitServerNameByCoordinates($serverX, $serverZ + 1);
                if (is_string($serverName)) {
                    $this->serversAround[Facing::SOUTH] = $serverName;
                } else {
                    unset($this->serversAround[Facing::SOUTH]);
                }
                $serverName = yield from DataProvider::getInstance()->awaitServerNameByCoordinates($serverX, $serverZ - 1);
                if (is_string($serverName)) {
                    $this->serversAround[Facing::NORTH] = $serverName;
                } else {
                    unset($this->serversAround[Facing::NORTH]);
                }
            }
        );
    }
}