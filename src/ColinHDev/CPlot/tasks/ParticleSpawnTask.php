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
use pocketmine\world\WorldManager;

class ParticleSpawnTask extends Task {

    private WorldManager $worldManager;
    private ServerSettings $serverSettings;

    public function __construct() {
        $this->worldManager = Server::getInstance()->getWorldManager();
    }

    public function onRun() : void {
        if (!DataProvider::getInstance()->isInitialized()) {
            return;
        }
        if (!isset($this->serverSettings)) {
            $this->serverSettings = ServerSettings::getInstance();
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
                $particle = new DustParticle(new Color(130, 2, 150));
                foreach (
                    [
                        $sphere->getXIntersection($worldBorder->minX),
                        $sphere->getXIntersection($worldBorder->maxX),
                        $sphere->getZIntersection($worldBorder->minZ),
                        $sphere->getZIntersection($worldBorder->maxZ)
                    ] as $particleSpawn) {
                    if ($particleSpawn instanceof Vector3) {
                        $world->addParticle($particleSpawn, $particle, [$player]);
                    } else if ($particleSpawn instanceof Sphere) {
                        /** @var Vector3 $point */
                        foreach ($particleSpawn->getPoints() as $point) {
                            if ($point->x < $worldBorder->minX || $point->x > $worldBorder->maxX || $point->z < $worldBorder->minZ || $point->z > $worldBorder->maxZ) {
                                continue;
                            }
                            $world->addParticle(
                                $point,
                                $particle,
                                [$player]
                            );
                        }
                    }
                }
            }
        }
    }
}