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

namespace Luthfi\XAuth\database;

use InvalidArgumentException;
use Luthfi\XAuth\Main;
use SOFe\AwaitGenerator\Await;

class DataProviderFactory {

    /**
     * @param Main $plugin
     */
    public static function create(Main $plugin, array $config): Await {
        $providerType = strtolower((string)($config['type'] ?? 'yaml'));

        return Await::f2c(function () use ($plugin, $providerType) {
            $provider = self::createProvider($plugin, $providerType);
            yield $provider->initialize();
            return $provider;
        });
    }

    public static function createProvider(Main $plugin, string $providerType): DataProviderInterface {
        switch ($providerType) {
            case 'sqlite':
                return new SqliteProvider($plugin);
            case 'mysql':
                return new MysqlProvider($plugin);
            default:
                throw new InvalidArgumentException("Invalid data provider: " . $providerType);
        }
    }
}
