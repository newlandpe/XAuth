<?php

declare(strict_types=1);

namespace Luthfi\XAuth\database;

use InvalidArgumentException;
use Luthfi\XAuth\Main;

class DataProviderFactory {

    /**
     * @param Main $plugin
     */
    public static function create(Main $plugin): DataProviderInterface {
        $config = $plugin->getConfig()->get('database', []);
        if (!is_array($config)) {
            $config = [];
        }
        $providerType = strtolower((string)($config['provider'] ?? 'yaml'));

        switch ($providerType) {
            case 'yaml':
                return new YamlProvider($plugin);
            case 'json':
                return new JsonProvider($plugin);
            case 'sqlite':
                return new SqliteProvider($plugin);
            case 'mysql':
                return new MysqlProvider($plugin);
            default:
                throw new InvalidArgumentException("Invalid data provider: " . $providerType);
        }
    }
}
