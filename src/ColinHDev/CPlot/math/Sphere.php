<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\math;

use pocketmine\math\Vector3;
use pocketmine\utils\Random;

class Sphere {

    public float $mx;
    public float $my;
    public float $mz;
    public float $rx;
    public float $ry;
    public float $rz;

    public function __construct(float $mx, float $my, float $mz, float $rx, float $ry, float $rz) {
        $this->mx = $mx;
        $this->my = $my;
        $this->mz = $mz;
        $this->rx = $rx;
        $this->ry = $ry;
        $this->rz = $rz;
    }

    public function getXIntersection(float $x) : Vector3|Sphere|null {
        $distance = abs($this->mx - $x);
        if ($distance > $this->rx) {
            return null;
        }
        if ($distance < $this->rx) {
            $h = $this->rx - $distance;
            $R = sqrt($h * (2 * $this->rx - $h));
            return new Sphere($x, $this->my, $this->mz, 0.0, $R, $R);
        }
        return new Vector3($x, $this->my, $this->mz);
    }

    public function getZIntersection(float $z) : Vector3|Sphere|null {
        $distance = abs($this->mz - $z);
        if ($distance > $this->rz) {
            return null;
        }
        if ($distance < $this->rz) {
            $h = $this->rz - $distance;
            $R = sqrt($h * (2 * $this->rz - $h));
            return new Sphere($this->mx, $this->my, $z, $R, $R, 0.0);
        }
        return new Vector3($this->mx, $this->my, $z);
    }

    /**
     * @phpstan-return \Generator<int, Vector3, void, void>
     */
    public function getPoints() : \Generator {
        $random = new Random();
        $points = 2 * $this->rx + 2 * $this->ry + 2 * $this->rz;
        for ($i = 0; $i < $points; $i++) {
            $theta = $random->nextFloat() * 2 * M_PI;
            $rand = sqrt($random->nextFloat());
            yield new Vector3(
                $this->mx + $this->rx * $rand * cos($theta),
                $this->my + $this->ry * $rand * sin($theta),
                $this->mz + $this->rz * $rand * cos($theta)
            );
        }
    }
}