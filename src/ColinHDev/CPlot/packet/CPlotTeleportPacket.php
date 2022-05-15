<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\packet;

use matze\cloudbridge\network\packets\types\PlayerTransferPacket;
use matze\cloudbridge\network\packets\utils\PlayerTransferCoordinates;
use matze\cloudbridge\network\packets\utils\PlayerTransferDestination;

class CPlotTeleportPacket extends PlayerTransferPacket {

    /** @var PlayerTransferCoordinates */
    public PlayerTransferDestination $destination;

    public static function create(PlayerTransferDestination $destination) : self {
        assert($destination instanceof PlayerTransferCoordinates);
        $packet = new self;
        $packet->destination = $destination;
        return $packet;
    }

    public static function createFromCoordinates(string $playerName, string $serverName, string $worldName, float $x, float $y, float $z, float $yaw, float $pitch) : self {
        return self::create(
            new PlayerTransferCoordinates($playerName, $serverName, $worldName, $x, $y, $z, $yaw, $pitch)
        );
    }
}