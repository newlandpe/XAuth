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

use poggit\libasynql\SqlError;
use SOFe\AwaitGenerator\Await;

class SqliteProvider extends AbstractDataProvider {

    protected function init(): void {
        Await::f2c(function () {
            try {
                yield $this->connector->asyncGeneric('xauth.pragma.foreign_keys');
                $this->plugin->getLogger()->debug("SQLite foreign keys enabled.");
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to enable SQLite foreign keys: " . $error->getMessage());
            }
        });
    }

    protected function getSqlMap(): array {
        return [
            "sqlite" => "sqlite.sql"
        ];
    }
}
