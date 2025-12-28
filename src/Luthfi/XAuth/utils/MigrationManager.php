<?php

/*
 * 
 *  _          _   _     __  __  ____ _      __  __    _         _   _ 
 * | |   _   _| |_| |__ |  \/  |/ ___( )___  \ \/ /   / \  _   _| |_| |__
 * | |  | | | | __| '_ \| |\/| | |   |// __|  \  /   / _ \| | | | __| '_ \
 * | |__| |_| | |_| | | | |  | | |___  \__ \  /  \  / ___ \ |_| | |_| | | |
 * |_____\__,_|\__|_| |_|_|  |_|\____| |___/ /_/\_\/_/   \_\__,_|\__|_| |_|
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

namespace Luthfi\XAuth\utils;

use Luthfi\XAuth\database\DataProviderFactory;
use Luthfi\XAuth\Main;
use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;

class MigrationManager {

    private Main $plugin;

    private static bool $isMigrationInProgress = false;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public static function isMigrationInProgress(): bool {
        return self::$isMigrationInProgress;
    }

    private static function setMigrationInProgress(bool $inProgress): void {
        self::$isMigrationInProgress = $inProgress;
    }

    public function migrate(string $sourceType, string $destinationType): void {
        if ($sourceType === $destinationType) {
            $this->plugin->getLogger()->warning("Source and destination provider types are the same ('{$sourceType}'). No migration needed.");
            return;
        }

        if (self::isMigrationInProgress()) {
            throw new \RuntimeException("A migration is already in progress.");
        }

        $messages = (array)$this->plugin->getCustomMessages()->get("messages");

        self::setMigrationInProgress(true);
        $sourceProvider = null;
        $destinationProvider = null;

        try {
            $this->plugin->getLogger()->info("Creating database providers...");
            $sourceProvider = DataProviderFactory::createProvider($this->plugin, $sourceType);
            $destinationProvider = DataProviderFactory::createProvider($this->plugin, $destinationType);
            
            $sourceProvider->initializeSync();
            $destinationProvider->initializeSync();
            $this->plugin->getLogger()->info("Providers created successfully.");

            $this->plugin->getLogger()->info("Calculating total players from '{$sourceType}'...");
            $total = $sourceProvider->getTotalPlayerCountSync();
            $this->plugin->getLogger()->info(str_replace('{count}', (string)$total, (string)($messages["xauth_migration_found_players"] ?? "Found {count} players to migrate.")));

            if ($total === 0) {
                $this->plugin->getLogger()->info("Nothing to migrate.");
            } else {
                $migratedCount = 0;
                $skippedCount = 0;
                $batchSize = 100; // Process 100 players per batch

                for ($offset = 0; $offset < $total; $offset += $batchSize) {
                    $playerDataBatch = $sourceProvider->getPlayerDataPagedSync($batchSize, $offset);

                    foreach ($playerDataBatch as $playerData) {
                        if ($destinationProvider->isPlayerRegisteredSync($playerData['name'])) {
                            $skippedCount++;
                        } else {
                            $destinationProvider->registerPlayerRawSync($playerData['name'], $playerData);
                            $migratedCount++;
                        }
                    }
                    // Wait for the batch of inserts to complete
                    $destinationProvider->getConnector()->waitAll();

                    $processedCount = $offset + count($playerDataBatch);
                    $message = (string)($messages["xauth_migration_progress"] ?? "§aMigrated: {processed}/{total} (Skipped: {skipped})");
                    $message = str_replace(['{processed}', '{total}', '{skipped}'], [(string)$processedCount, (string)$total, (string)$skippedCount], $message);
                    $this->plugin->getLogger()->info(TextFormat::clean($message));
                }

                $this->plugin->getLogger()->info((string)($messages["xauth_migration_complete"] ?? "Migration complete!"));
                $this->plugin->getLogger()->info(str_replace('{count}', (string)$migratedCount, (string)($messages["xauth_migration_migrated_count"] ?? "- Migrated: {count} players")));
                $this->plugin->getLogger()->info(str_replace('{count}', (string)$skippedCount, (string)($messages["xauth_migration_skipped_count"] ?? "- Skipped (already exist): {count} players")));
            }
        } finally {
            $sourceProvider?->close();
            $destinationProvider?->close();
            self::setMigrationInProgress(false);
            $this->plugin->getLogger()->info("Migration process finished and connections closed.");
        }
    }
}
