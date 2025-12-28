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

namespace Luthfi\XAuth\database;

use InvalidArgumentException;
use Luthfi\XAuth\Main;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;

class DataProviderFactory {

    public static function create(Main $plugin, array $config): DataProviderInterface {
        $providerType = strtolower((string)($config['type'] ?? 'sqlite'));
        return self::createProvider($plugin, $providerType);
    }

    public static function createProvider(Main $plugin, string $providerType): DataProviderInterface {
        $fullDbConfig = $plugin->getConfig()->get('database', []);

        if (!isset($fullDbConfig[$providerType])) {
            throw new InvalidArgumentException("Configuration for '" . $providerType . "' not found in the 'database' section of config.yml");
        }

        $providerConfig = $fullDbConfig[$providerType];
        $providerConfig['type'] = $providerType;

        $logQueries = $plugin->getConfig()->getNested('database.log_queries', false);

        $originalType = $fullDbConfig['type'] ?? null;
        $fullDbConfig['type'] = $providerType;

        $connector = libasynql::create(
            $plugin,
            $fullDbConfig,
            [$providerType => $providerType . ".sql"],
            $logQueries
        );

        if ($originalType !== null) {
            $fullDbConfig['type'] = $originalType;
        }

        switch ($providerType) {
            case 'sqlite':
                return new SqliteProvider($plugin, $connector);
            case 'mysql':
                return new MysqlProvider($plugin, $connector);
            default:
                throw new InvalidArgumentException("Invalid data provider type: " . $providerType);
        }
    }
}