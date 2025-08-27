<?php

/*
 *
 * __  __    _         _   _
 * \ \/ /   / \  _   _| |_| |__
 *  \  /   / _ \| | | | __| '_ \
 *  /  \  / ___ \ |_| | |_| | | |
 * /_/\_\/_/   \_\__,_|\__|_| |_|
 *
 * This program is free software: you can redistribute and/or modify
 * it under the terms of the CSSM Unlimited License v2.0.
 *
 * This license permits unlimited use, modification, and distribution
 * for any purpose while maintaining authorship attribution.
 *
 * The software is provided "as is" without warranty of any kind.
 *
 * @author LuthMC
 * @author Sergiy Chernega
 * @link https://chernega.eu.org/
 *
 *
 */

declare(strict_types=1);

namespace Luthfi\XAuth\tasks;

use Luthfi\XAuth\database\DataProviderFactory;
use Luthfi\XAuth\Main;
use Luthfi\XAuth\utils\MigrationManager;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use Throwable;

class MigrationTask extends AsyncTask {

    private string $sourceType;
    private string $destinationType;
    private string $senderName;

    public function __construct(string $sourceType, string $destinationType, string $senderName) {
        $this->sourceType = $sourceType;
        $this->destinationType = $destinationType;
        $this->senderName = $senderName;
    }

    public function onRun(): void {
        $plugin = Server::getInstance()->getPluginManager()->getPlugin("XAuth");
        if (!$plugin instanceof Main) {
            $this->setResult(['error' => 'Plugin not found or disabled.']);
            return;
        }

        try {
            $sourceProvider = DataProviderFactory::createProvider($plugin, $this->sourceType);
            $destinationProvider = DataProviderFactory::createProvider($plugin, $this->destinationType);

            $allPlayerData = $sourceProvider->getAllPlayerData(); // Still fetching all for now, will optimize later
            $total = count($allPlayerData);

            $migratedCount = 0;
            $skippedCount = 0;
            $processedCount = 0;

            $this->publishProgress(['type' => 'start', 'total' => $total]);

            foreach ($allPlayerData as $playerName => $playerData) {
                if ($destinationProvider->isPlayerRegistered($playerName)) {
                    $skippedCount++;
                } else {
                    $destinationProvider->registerPlayerRaw($playerName, $playerData);
                    $migratedCount++;
                }
                $processedCount++;

                if ($processedCount % 100 === 0 || $processedCount === $total) { // Update progress every 100 accounts or at the end
                    $this->publishProgress(['type' => 'progress', 'processed' => $processedCount, 'total' => $total, 'migrated' => $migratedCount, 'skipped' => $skippedCount]);
                }
            }

            $sourceProvider->close();
            $destinationProvider->close();

            $this->setResult([
                'success' => true,
                'migrated' => $migratedCount,
                'skipped' => $skippedCount,
                'total' => $total
            ]);

        } catch (Throwable $t) {
            $this->setResult(['error' => $t->getMessage()]);
        }
    }

    public function onProgressUpdate(Server $server, $progress): void {
        $plugin = $server->getPluginManager()->getPlugin("XAuth");
        if (!$plugin instanceof Main) return;

        $sender = $server->getCommandSender($this->senderName);
        if ($sender === null) return;

        $messages = (array)$plugin->getCustomMessages()->get("messages");

        if ($progress['type'] === 'start') {
            $sender->sendMessage(str_replace(
                ['{source_provider}', '{destination_provider}'],
                [$this->sourceType, $this->destinationType],
                (string)($messages["xauth_migration_start"] ?? "§eStarting migration from '{source_provider}' to '{destination_provider}'...")
            ));
            $sender->sendMessage(str_replace(
                '{count}', (string)$progress['total'],
                (string)($messages["xauth_migration_found_players"] ?? "§aFound {count} players to migrate.")
            ));
        } elseif ($progress['type'] === 'progress') {
            $message = (string)($messages["xauth_migration_progress"] ?? "§aMigrated: {migrated}/{total} (Skipped: {skipped})");
            $message = str_replace(
                ['{migrated}', '{total}', '{skipped}'],
                [(string)$progress['migrated'], (string)$progress['total'], (string)$progress['skipped']],
                $message
            );
            $sender->sendMessage($message);
        }
    }

    public function onCompletion(Server $server): void {
        MigrationManager::setMigrationInProgress(false);

        $plugin = $server->getPluginManager()->getPlugin("XAuth");
        if (!$plugin instanceof Main) return;

        $sender = $server->getCommandSender($this->senderName);
        if ($sender === null) return;

        $result = $this->getResult();
        $messages = (array)$plugin->getCustomMessages()->get("messages");

        if (isset($result['error'])) {
            $sender->sendMessage((string)($messages["xauth_migration_error_prefix"] ?? "§cError: ") . $result['error']);
        } else {
            $sender->sendMessage((string)($messages["xauth_migration_complete"] ?? "§aMigration complete!"));
            $sender->sendMessage(str_replace(
                '{count}', (string)$result['migrated'],
                (string)($messages["xauth_migration_migrated_count"] ?? "§a- Migrated: {count} players")
            ));
            $sender->sendMessage(str_replace(
                '{count}', (string)$result['skipped'],
                (string)($messages["xauth_migration_skipped_count"] ?? "§e- Skipped (already exist): {count} players")
            ));
        }
    }
}
