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

use Luthfi\XAuth\Main;
use pocketmine\scheduler\Task;

class CleanupSessionsTask extends Task {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun(): void {
        Await::f2c(function () {
            $this->plugin->getLogger()->debug("Attempting to clean up expired sessions asynchronously.");
            yield from $this->plugin->getDataProvider()->cleanupExpiredSessions();
            $this->plugin->getLogger()->debug("Cleaned up expired sessions.");
        });
    }
}
