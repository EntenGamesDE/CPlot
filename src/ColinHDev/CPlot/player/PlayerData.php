<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\player;

use ColinHDev\CPlot\attributes\BaseAttribute;
use ColinHDev\CPlot\player\settings\SettingManager;
use ColinHDev\CPlot\ResourceManager;
use pocketmine\player\OfflinePlayer;
use pocketmine\Server;
use Ramsey\Uuid\Uuid;

class PlayerData {

    private int $playerIdentifier;
    private ?string $playerUUID;
    private ?string $playerXUID;
    private ?string $playerName;
    private ?int $lastJoin;

    /** @phpstan-var array<string, BaseAttribute<mixed>> */
    private array $settings;

    /**
     * @phpstan-param array<string, BaseAttribute<mixed>> $settings
     */
    public function __construct(int $playerIdentifier, ?string $playerUUID, ?string $playerXUID, ?string $playerName, ?int $lastJoin, array $settings) {
        $this->playerIdentifier = $playerIdentifier;
        $this->playerUUID = $playerUUID;
        $this->playerXUID = $playerXUID;
        $this->playerName = $playerName;
        $this->lastJoin = $lastJoin;
        $this->settings = $settings;
    }

    public function getPlayerIdentifier() : int {
        return $this->playerIdentifier;
    }

    public function getPlayerUUID() : ?string {
        return $this->playerUUID;
    }

    public function getPlayerXUID() : ?string {
        return $this->playerXUID;
    }

    public function getPlayerName() : ?string {
        return $this->playerName;
    }

    /**
     * Returns the last time a player joined in seconds.
     * @throws \RuntimeException when called outside of main thread.
     */
    public function getLastJoin() : int {
        // player is online and therefore not inactive
        $player = Server::getInstance()->getPlayerByUUID(Uuid::fromBytes($this->playerUUID));
        if ($player !== null) {
            return time();
        }

        // check if the last time the player played should be fetched from the offline data file or the database
        switch (ResourceManager::getInstance()->getConfig()->get("lastPlayed.origin", "database")) {
            case "offline_data":
                // if the player isn't an instance of OfflinePlayer it is one of Player and therefore online on the server
                $offlinePlayer = Server::getInstance()->getOfflinePlayer($this->playerName);
                if (!$offlinePlayer instanceof OfflinePlayer) {
                    return time();
                }

                // check if the player's offline player data even exists
                // if not we try to fetch the last time the player played from the database
                // this could be null if the server admin deleted the player's offline data file
                $lastPlayed = $offlinePlayer->getLastPlayed();
                if ($lastPlayed !== null) {
                    // The last time a player joined is saved in milliseconds by PocketMine-MP. Therefore we divide by
                    // 1000 and cast it to an integer. The float gets rounded up in favour of the player.
                    $lastPlayed = (int) ceil($lastPlayed / 1000);
                    break;
                }

            default:
            case "database":
                // Since CPlot stores the last time a player joined in seconds, we do not need to divide anything here.
                /** @noinspection SuspiciousAssignmentsInspection */
                $lastPlayed = $this->lastJoin;
                break;
        }
        return $lastPlayed;
    }

    /**
     * @phpstan-return array<string, BaseAttribute<mixed>>
     */
    public function getSettings() : array {
        return $this->settings;
    }

    /**
     * @phpstan-return BaseAttribute<mixed>|null
     */
    public function getSettingByID(string $settingID) : ?BaseAttribute {
        if (!isset($this->settings[$settingID])) {
            return null;
        }
        return $this->settings[$settingID];
    }

    /**
     * @phpstan-return BaseAttribute<mixed>|null
     */
    public function getSettingNonNullByID(string $settingID) : ?BaseAttribute {
        $setting = $this->getSettingByID($settingID);
        if ($setting === null) {
            $setting = SettingManager::getInstance()->getSettingByID($settingID);
        }
        return $setting;
    }

    /**
     * @phpstan-template TAttributeValue
     * @phpstan-param BaseAttribute<TAttributeValue> $setting
     */
    public function addSetting(BaseAttribute $setting) : void {
        $this->settings[$setting->getID()] = $setting;
    }

    public function removeSetting(string $settingID) : void {
        unset($this->settings[$settingID]);
    }
}