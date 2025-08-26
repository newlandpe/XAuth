<?php

declare(strict_types=1);

namespace Luthfi\XAuth\utils;

use Luthfi\XAuth\database\DataProviderFactory;
use Luthfi\XAuth\Main;

class MigrationManager {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function runInitialMigration(): void {
        if (!($this->plugin->getConfig()->get('enable_migration', true))) {
            return;
        }

        $dataPath = $this->plugin->getDataFolder() . "players.yml";
        if (!file_exists($dataPath)) {
            return;
        }

        if (!is_readable($dataPath)) {
            $this->plugin->getLogger()->error("Cannot read players.yml (check permissions). Migration aborted.");
            return;
        }

        $data = yaml_parse_file($dataPath);
        if ($data === false || empty($data)) {
            return; // File is empty or invalid, nothing to migrate
        }

        $firstPlayer = current($data);
        $isOldFormat = $this->isOldFormat($firstPlayer);
        $configuredProvider = $this->plugin->getConfig()->get('database', [])['provider'] ?? 'sqlite';

        if (!$isOldFormat && $configuredProvider === 'yaml') {
            // The file is new format and the user wants to use it. No migration needed.
            return;
        }

        if ($isOldFormat) {
            $this->plugin->getLogger()->info("Old format players.yml detected. Upgrading and migrating to '{$configuredProvider}'...");
        } else {
            // This case is for when the file is new format, but the user wants to move to a different provider.
            $this->plugin->getLogger()->info("Found players.yml but a different provider is configured. Migrating to '{$configuredProvider}'...");
        }

        try {
            $results = $this->migrate('yaml', $configuredProvider, true);

            $this->plugin->getLogger()->info("Migration complete!");
            $this->plugin->getLogger()->info("- Migrated: {$results['migrated']} players");
            $this->plugin->getLogger()->info("- Skipped (already exist): {$results['skipped']} players");

            if (!rename($dataPath, $dataPath . ".migrated")) {
                $this->plugin->getLogger()->warning("Could not rename players.yml. Remove it manually to avoid re-migration.");
            }

        } catch (Throwable $t) {
            $this->plugin->getLogger()->critical("Migration failed: " . $t->getMessage());
            $this->plugin->getLogger()->debug("Stack trace: " . $t->getTraceAsString());
            $this->plugin->getLogger()->warning("Server may not work correctly until data is migrated.");
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function migrate(string $sourceType, string $destinationType, bool $isInitialYaml = false): array {
        $sourceProvider = DataProviderFactory::createProvider($this->plugin, $sourceType, $isInitialYaml);
        $destinationProvider = DataProviderFactory::createProvider($this->plugin, $destinationType);

        $allPlayerData = $sourceProvider->getAllPlayerData();

        $migratedCount = 0;
        $skippedCount = 0;

        foreach ($allPlayerData as $playerName => $playerData) {
            if ($destinationProvider->isPlayerRegistered($playerName)) {
                $skippedCount++;
                continue;
            }

            $upgradedData = $this->upgradeData($playerData);
            
            $destinationProvider->registerPlayerRaw($playerName, $upgradedData);
            $migratedCount++;
        }

        $sourceProvider->close();
        if (!$isInitialYaml || $sourceType !== $destinationType) {
            $destinationProvider->close();
        }

        return ['migrated' => $migratedCount, 'skipped' => $skippedCount, 'total' => count($allPlayerData)];
    }

    private function isOldFormat(array $playerData): bool {
        if (!isset($playerData['password'])) {
            return false;
        }
        $passwordInfo = password_get_info($playerData['password']);
        return $passwordInfo['algo'] === 0; // 0 means 'unknown algorithm', likely plain text
    }

    private function upgradeData(array $data): array {
        if ($this->isOldFormat($data)) {
            $data['password'] = $this->plugin->getPasswordHasher()->hashPassword($data['password']);
        }

        $defaults = [
            'ip' => null,
            'locked' => 0,
            'registered_at' => time(),
            'registration_ip' => null,
            'last_login_at' => time(),
            'blocked_until' => 0,
            'must_change_password' => 0
        ];

        foreach ($defaults as $key => $value) {
            if (!isset($data[$key])) {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}
