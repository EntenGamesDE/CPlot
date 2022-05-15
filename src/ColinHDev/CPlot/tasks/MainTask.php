<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\tasks;

use ColinHDev\CPlot\math\Sphere;
use ColinHDev\CPlot\plots\BasePlot;
use ColinHDev\CPlot\plots\Plot;
use ColinHDev\CPlot\provider\DataProvider;
use ColinHDev\CPlot\ServerSettings;
use ColinHDev\CPlot\worlds\WorldSettings;
use pocketmine\color\Color;
use pocketmine\entity\Human;
use pocketmine\entity\object\ItemEntity;
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\world\particle\DustParticle;
use pocketmine\world\Position;
use pocketmine\world\WorldManager;

class MainTask extends Task {

    private WorldManager $worldManager;
    /** @var Position[] */
    private array $lastPositions = [];
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

            if (Server::getInstance()->getTick() % 5 === 0) {
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
                            $world->addParticle($particleSpawn, $particle);
                        } else if ($particleSpawn instanceof Sphere) {
                            /** @var Vector3 $point */
                            foreach ($particleSpawn->getPoints() as $point) {
                                if ($point->x < $worldBorder->minX || $point->x > $worldBorder->maxX || $point->z < $worldBorder->minZ || $point->z > $worldBorder->maxZ) {
                                    continue;
                                }
                                $world->addParticle(
                                    $point,
                                    $particle
                                );
                            }
                        }
                    }
                }
            }

            foreach ($world->updateEntities as $entity) {
                if ($entity instanceof Human || $entity instanceof ItemEntity) {
                    continue;
                }

                $entityId = $entity->getId();
                if ($entity->isClosed()) {
                    unset($this->lastPositions[$entityId]);
                    continue;
                }

                if (!$entity->hasMovementUpdate()) {
                    continue;
                }

                if (!isset($this->lastPositions[$entityId])) {
                    $this->lastPositions[$entityId] = $entity->getPosition();
                    continue;
                }

                $lastPosition = $this->lastPositions[$entityId];
                $position = $this->lastPositions[$entityId] = $entity->getPosition();
                if (
                    // Only if the world did not change, e.g. due to a teleport, we need to check how far the entity moved.
                    $position->world === $lastPosition->world &&
                    // Check if the entity moved across a block and if not, we already checked that block and the entity just
                    // moved in the borders between that one.
                    $position->getFloorX() === $lastPosition->getFloorX() &&
                    $position->getFloorY() === $lastPosition->getFloorY() &&
                    $position->getFloorZ() === $lastPosition->getFloorZ()
                ) {
                    continue;
                }

                $lastBasePlot = BasePlot::fromVector3($worldName, $worldSettings, $lastPosition);
                $basePlot = BasePlot::fromVector3($worldName, $worldSettings, $position);
                if ($lastBasePlot !== null && $basePlot !== null && $lastBasePlot->isSame($basePlot)) {
                    continue;
                }

                $lastPlot = $lastBasePlot?->toSyncPlot() ?? Plot::loadFromPositionIntoCache($lastPosition);
                $plot = $basePlot?->toSyncPlot() ?? Plot::loadFromPositionIntoCache($position);
                if ($lastPlot instanceof Plot && $plot instanceof Plot && $lastPlot->isSame($plot)) {
                    continue;
                }
                if ($lastPlot instanceof BasePlot || $plot instanceof BasePlot) {
                    continue;
                }

                $entity->flagForDespawn();
            }
        }
    }
}