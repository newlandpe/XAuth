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

namespace Luthfi\XAuth\utils;

use Luthfi\XAuth\database\DataProviderFactory;
use Luthfi\XAuth\Main;

class MigrationManager {

    private Main $plugin;

    private static bool $isMigrationInProgress = false;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public static function isMigrationInProgress(): bool {
        return self::$isMigrationInProgress;
    }

    public static function setMigrationInProgress(bool $inProgress): void {
        self::$isMigrationInProgress = $inProgress;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function migrate(string $sourceType, string $destinationType, string $senderName): void {
        if (self::isMigrationInProgress()) {
            throw new \RuntimeException("A migration is already in progress. Please wait for it to complete.");
        }

        self::setMigrationInProgress(true);
        $this->plugin->getServer()->getAsyncPool()->submitTask(new MigrationTask($sourceType, $destinationType, $senderName));
    }
}
