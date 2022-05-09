<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\packet;

use matze\cloudbridge\Loader;
use matze\cloudbridge\network\packets\DataPacket;

class CPlotTeleportPacket extends DataPacket {

    public string $playerName;
    public string $serverName;
    public string $worldName;
    public float $x;
    public float $y;
    public float $z;
    public float $yaw;
    public float $pitch;

    public static function create(string $playerName, string $serverName, string $worldName, float $x, float $y, float $z, float $yaw, float $pitch) : self {
        $packet = new self();
        $packet->playerName = $playerName;
        $packet->serverName = $serverName;
        $packet->worldName = $worldName;
        $packet->x = $x;
        $packet->y = $y;
        $packet->z = $z;
        $packet->yaw = $yaw;
        $packet->pitch = $pitch;
        return $packet;
    }

    public function send() : void {
        Loader::getInstance()->getSocket()->write($this);
    }
}