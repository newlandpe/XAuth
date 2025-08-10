<?php

declare(strict_types=1);

namespace Luthfi\XAuth\utils;

use Luthfi\XAuth\Main;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class MigrationTask extends AsyncTask {

    private string $dataFolder;

    public function __construct(string $dataFolder) {
        $this->dataFolder = $dataFolder;
    }

    public function onRun(): void {
        $oldDataPath = $this->dataFolder . "players.yml.old";
        $tempDataPath = $this->dataFolder . "migration_data.temp";

        if (!file_exists($oldDataPath)) {
            $this->setResult(['error' => 'Old data file (players.yml.old) not found.']);
            return;
        }

        $oldData = yaml_parse_file($oldDataPath);
        if ($oldData === false) {
            $this->setResult(['error' => 'Failed to parse old data file (players.yml.old).']);
            return;
        }

        file_put_contents($tempDataPath, json_encode($oldData));

        $this->setResult(['success' => true, 'temp_path' => $tempDataPath]);
    }

    public function onCompletion(): void {
        $plugin = Server::getInstance()->getPluginManager()->getPlugin("XAuth");
        if (!$plugin instanceof Main) {
            Server::getInstance()->getLogger()->error("[XAuth Migration] Plugin not found or disabled.");
            return;
        }

        $result = $this->getResult();
        if (isset($result['error'])) {
            Server::getInstance()->getLogger()->error("[XAuth Migration] " . $result['error']);
            return;
        }

        if (!isset($result['success']) || !$result['success']) {
            Server::getInstance()->getLogger()->error("[XAuth Migration] An unknown error occurred during migration task.");
            return;
        }

        $tempDataPath = $result['temp_path'];
        if (!file_exists($tempDataPath)) {
            Server::getInstance()->getLogger()->error("[XAuth Migration] Temporary migration file not found.");
            return;
        }

        $oldData = json_decode(file_get_contents($tempDataPath), true);
        @unlink($tempDataPath);

        $dataProvider = $plugin->getDataProvider();
        $passwordHasher = $plugin->getPasswordHasher();

        if ($dataProvider === null || $passwordHasher === null) {
            Server::getInstance()->getLogger()->error("[XAuth Migration] DataProvider or PasswordHasher is not available. Migration failed.");
            return;
        }

        $total = count($oldData);
        $migratedCount = 0;
        $skippedCount = 0;

        foreach ($oldData as $playerName => $playerData) {
            $playerName = strtolower((string)$playerName);
            if ($dataProvider->isPlayerRegistered($playerName)) {
                $skippedCount++;
                continue;
            }

            if (!is_array($playerData) || !isset($playerData['password']) || !is_string($playerData['password'])) {
                continue;
            }

            $hashedPassword = $passwordHasher->hashPassword($playerData['password']);

            $migratedPlayerData = [
                "password" => $hashedPassword,
                "ip" => $playerData['ip'] ?? null,
                "registered_at" => time(),
                "registration_ip" => $playerData['ip'] ?? null,
                "last_login_at" => time(),
                "locked" => false,
                "blocked_until" => 0,
                "must_change_password" => false
            ];

            $dataProvider->registerPlayerRaw($playerName, $migratedPlayerData);
            $migratedCount++;
        }

        Server::getInstance()->getLogger()->info("Migration from players.yml.old completed.");
        Server::getInstance()->getLogger()->info("Migrated {$migratedCount}/{$total} accounts. Skipped {$skippedCount} (already existed).");

        $oldDataPath = $this->dataFolder . "players.yml.old";
        if (file_exists($oldDataPath)) {
            @unlink($oldDataPath);
        }
    }
}
