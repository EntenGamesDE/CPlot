<?php

namespace ColinHDev\CPlot;

use pocketmine\utils\SingletonTrait;

class ServerSettings {
    use SingletonTrait;

    private int $ID;
    private int $worldSize = 13;
    private int $X;
    private int $Z;

    public function __construct(int $ID, int $X, int $Z) {
        $this->ID = $ID;
        $this->X = $X;
        $this->Z = $Z;
    }

    public function getID() : int {
        return $this->ID;
    }

    public function getWorldSize() : int {
        return $this->worldSize;
    }

    public function getX() : int {
        return $this->X;
    }

    public function getZ() : int {
        return $this->Z;
    }
}