<?php

declare(strict_types=1);

namespace Luthfi\XAuth\utils;

use Luthfi\XAuth\Main;

class MigrationManager {

    private Main $plugin;
    private bool $migrationNeeded = false;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function prepareMigration(): void {
        $oldDataPath = $this->plugin->getDataFolder() . "players.yml";
        $this->plugin->getLogger()->debug("Checking for old data file at: " . $oldDataPath);
        $migratedPath = $this->plugin->getDataFolder() . "players.yml.old";

        if (file_exists($migratedPath)) {
            $this->migrationNeeded = true;
            $this->plugin->getLogger()->info("Found 'players.yml.old', migration will be attempted.");
            return;
        }

        if (!file_exists($oldDataPath)) {
            return;
        }

        $data = yaml_parse_file($oldDataPath);
        if ($data === false || empty($data)) {
            return;
        }

        $firstPlayer = current($data);
        if (is_array($firstPlayer) && (isset($firstPlayer['registered_at']) || password_get_info((string)($firstPlayer['password'] ?? ''))['algo'])) {
            return; // It's new format, do nothing.
        }

        $this->plugin->getLogger()->info("Old XAuth data file (players.yml) found. Preparing for migration.");
        if (rename($oldDataPath, $migratedPath)) {
            $this->migrationNeeded = true;
            $this->plugin->getLogger()->info("Renamed old data file to 'players.yml.old'. The new data provider will be created.");
        } else {
            $this->plugin->getLogger()->error("Could not rename old data file. Migration aborted. Please check file permissions.");
        }
    }

    public function runMigration(): void {
        if (!$this->migrationNeeded) {
            return;
        }
        $this->plugin->getLogger()->info("Starting asynchronous migration from players.yml.old...");
        $this->plugin->getServer()->getAsyncPool()->submitTask(new MigrationTask($this->plugin->getDataFolder()));
    }
}
