<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\particles;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\ParticleIds;
use pocketmine\world\particle\Particle;

class DragonBreathTrailParticle implements Particle {

    public function encode(Vector3 $pos) : array {
        return [LevelEventPacket::standardParticle(ParticleIds::DRAGON_BREATH_TRAIL, 0, $pos)];
    }
}