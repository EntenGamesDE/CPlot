<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\tasks;

use ColinHDev\CPlot\math\Sphere;
use ColinHDev\CPlot\provider\DataProvider;
use ColinHDev\CPlot\ServerSettings;
use ColinHDev\CPlot\worlds\WorldSettings;
use pocketmine\color\Color;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\world\particle\DustParticle;
use pocketmine\world\World;
use pocketmine\world\WorldManager;
use SOFe\AwaitGenerator\Await;

class ParticleSpawnTask extends Task {

    private WorldManager $worldManager;
    private ServerSettings $serverSettings;
    /** @phpstan-var array<int, string> */
    private array $servers = [];
    private DustParticle $borderParticle;
    private DustParticle $existingServerParticle;
    private DustParticle $unknownServerParticle;

    public function __construct() {
        $this->worldManager = Server::getInstance()->getWorldManager();
        $this->borderParticle = new DustParticle(new Color(130, 2, 150));
        $this->existingServerParticle = new DustParticle(new Color(0, 251, 0));
        $this->unknownServerParticle = new DustParticle(new Color(251, 162, 0));
    }

    public function onRun() : void {
        if (!DataProvider::getInstance()->isInitialized()) {
            return;
        }
        if (!isset($this->serverSettings)) {
            $this->serverSettings = ServerSettings::getInstance();
            $this->updateServerData();
        }
        static $taskRunCounter = 0;
        $taskRunCounter++;
        // updateServerData() is called after every 240 times this task is run, so it is called about once per minute
        // since the task runs every 0.25 seconds.
        if ($taskRunCounter >= 240) {
            $taskRunCounter = 0;
            $this->updateServerData();
        }
        foreach ($this->worldManager->getWorlds() as $world) {
            $worldName = $world->getFolderName();
            $worldSettings = DataProvider::getInstance()->loadWorldIntoCache($worldName);
            if (!$worldSettings instanceof WorldSettings) {
                continue;
            }

            $worldBorder = $this->serverSettings->getWorldBorder($worldName, $worldSettings);
            foreach ($world->getPlayers() as $player) {
                $location = $player->getLocation();
                $sphere = new Sphere($location->x, $location->y, $location->z, 10.0, 10.0, 10.0);
                foreach (
                    [
                        $sphere->getXIntersection($worldBorder->minX),
                        $sphere->getXIntersection($worldBorder->maxX),
                        $sphere->getZIntersection($worldBorder->minZ),
                        $sphere->getZIntersection($worldBorder->maxZ)
                    ] as $particleSpawn) {
                    if ($particleSpawn instanceof Vector3) {
                        $world->addParticle($particleSpawn, $this->borderParticle, [$player]);
                    } else if ($particleSpawn instanceof Sphere) {
                        /** @var Vector3 $point */
                        foreach ($particleSpawn->getPoints() as $point) {
                            if ($point->x < $worldBorder->minX || $point->x > $worldBorder->maxX || $point->y < World::Y_MIN || $point->y >= World::Y_MAX || $point->z < $worldBorder->minZ || $point->z > $worldBorder->maxZ) {
                                continue;
                            }
                            $x = $point->getFloorX() - $worldSettings->getRoadSize();
                            if ($x >= 0) {
                                $difX = $x % ($worldSettings->getPlotSize() + $worldSettings->getRoadSize());
                            } else {
                                $difX = abs(($x - $worldSettings->getPlotSize() + 1) % ($worldSettings->getPlotSize() + $worldSettings->getRoadSize()));
                            }
                            if ($difX > $worldSettings->getPlotSize() && $point->x !== $worldBorder->minX && $point->x !== $worldBorder->maxX) {
                                continue;
                            }
                            $z = $point->getFloorZ() - $worldSettings->getRoadSize();
                            if ($z >= 0) {
                                $difZ = $z % ($worldSettings->getPlotSize() + $worldSettings->getRoadSize());
                            } else {
                                $difZ = abs(($z - $worldSettings->getPlotSize() + 1) % ($worldSettings->getPlotSize() + $worldSettings->getRoadSize()));
                            }
                            if ($difZ > $worldSettings->getPlotSize() && $point->z !== $worldBorder->minZ && $point->z !== $worldBorder->maxZ) {
                                continue;
                            }
                            $world->addParticle($point, $this->borderParticle, [$player]);
                        }
                    }
                }
            }
            foreach ($this->serverSettings->getWorldPassways($worldName, $worldSettings) as $facing => $passways) {
                $particle = isset($this->servers[$facing]) ? $this->existingServerParticle : $this->unknownServerParticle;
                switch ($facing) {
                    case Facing::NORTH:
                        $minZ = $worldBorder->minZ;
                        $maxZ = $minZ + 1;
                        break;
                    case Facing::SOUTH:
                        $maxZ = $worldBorder->maxZ;
                        $minZ = $maxZ - 1;
                        break;
                    case Facing::WEST:
                        $minX = $worldBorder->minX;
                        $maxX = $minX + 1;
                        break;
                    case Facing::EAST:
                        $maxX = $worldBorder->maxX;
                        $minX = $maxX - 1;
                        break;
                }
                foreach ($passways as $passway) {
                    switch ($facing) {
                        case Facing::NORTH:
                        case Facing::SOUTH:
                            $minX = $passway->minX;
                            $maxX = $passway->maxX;
                            break;
                        case Facing::WEST:
                        case Facing::EAST:
                            $minZ = $passway->minZ;
                            $maxZ = $passway->maxZ;
                            break;
                    }
                    foreach ($world->getPlayers() as $player) {
                        $y = $player->getLocation()->y;
                        $minY = $y - 10;
                        $maxY = $y + 10;
                        for ($i = 0; $i < 30; $i++) {
                            $world->addParticle(
                                new Vector3(
                                    $minX + mt_rand(0, (int) (($maxX * 100) - ($minX * 100))) / 100,
                                    $minY + mt_rand(0, (int) (($maxY * 100) - ($minY * 100))) / 100,
                                    $minZ + mt_rand(0, (int) (($maxZ * 100) - ($minZ * 100))) / 100
                                ),
                                $particle,
                                [$player]
                            );
                        }
                    }
                }
            }
        }
    }

    private function updateServerData() : void {
        Await::f2c(
            function() : \Generator {
                $serverX = $this->serverSettings->getX();
                $serverZ = $this->serverSettings->getZ();
                $serverName = yield from DataProvider::getInstance()->awaitServerNameByCoordinates($serverX + 1, $serverZ);
                if (is_string($serverName)) {
                    $this->servers[Facing::EAST] = $serverName;
                } else {
                    unset($this->servers[Facing::EAST]);
                }
                $serverName = yield from DataProvider::getInstance()->awaitServerNameByCoordinates($serverX - 1, $serverZ);
                if (is_string($serverName)) {
                    $this->servers[Facing::WEST] = $serverName;
                } else {
                    unset($this->servers[Facing::WEST]);
                }
                $serverName = yield from DataProvider::getInstance()->awaitServerNameByCoordinates($serverX, $serverZ + 1);
                if (is_string($serverName)) {
                    $this->servers[Facing::SOUTH] = $serverName;
                } else {
                    unset($this->servers[Facing::SOUTH]);
                }
                $serverName = yield from DataProvider::getInstance()->awaitServerNameByCoordinates($serverX, $serverZ - 1);
                if (is_string($serverName)) {
                    $this->servers[Facing::NORTH] = $serverName;
                } else {
                    unset($this->servers[Facing::NORTH]);
                }
            }
        );
    }
}