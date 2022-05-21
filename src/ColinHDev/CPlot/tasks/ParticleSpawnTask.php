<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\tasks;

use ColinHDev\CPlot\math\Sphere;
use ColinHDev\CPlot\provider\DataProvider;
use ColinHDev\CPlot\ServerSettings;
use ColinHDev\CPlot\worlds\WorldSettings;
use pocketmine\color\Color;
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
        foreach ($this->worldManager->getWorlds() as $world) {
            $worldName = $world->getFolderName();
            $worldSettings = DataProvider::getInstance()->loadWorldIntoCache($worldName);
            if (!$worldSettings instanceof WorldSettings) {
                continue;
            }

            $worldBorder = $this->serverSettings->getWorldBorder($worldName, $worldSettings);
            foreach ($world->getPlayers() as $player) {
                $location = $player->getLocation();
                $sphere = new Sphere($location->x, $location->y, $location->z, 15.0, 15.0, 15.0);
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
                            if ($point->x < $worldBorder->minX || $point->x > $worldBorder->maxX || $point->z < $worldBorder->minZ || $point->z > $worldBorder->maxZ) {
                                continue;
                            }
                            $world->addParticle($point, $this->borderParticle, [$player]);
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
                    $this->servers[World::chunkHash($serverX + 1, $serverZ)] = $serverName;
                }
                $serverName = yield from DataProvider::getInstance()->awaitServerNameByCoordinates($serverX - 1, $serverZ);
                if (is_string($serverName)) {
                    $this->servers[World::chunkHash($serverX - 1, $serverZ)] = $serverName;
                }
                $serverName = yield from DataProvider::getInstance()->awaitServerNameByCoordinates($serverX, $serverZ + 1);
                if (is_string($serverName)) {
                    $this->servers[World::chunkHash($serverX, $serverZ + 1)] = $serverName;
                }
                $serverName = yield from DataProvider::getInstance()->awaitServerNameByCoordinates($serverX, $serverZ - 1);
                if (is_string($serverName)) {
                    $this->servers[World::chunkHash($serverX, $serverZ - 1)] = $serverName;
                }
            }
        );
    }
}