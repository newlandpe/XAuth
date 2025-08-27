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

namespace Luthfi\XAuth;

use InvalidArgumentException;
use pocketmine\utils\Config;

class PasswordHasher {

    private Main $plugin;
    private string $algorithm;
    private array $options;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->loadConfig();
    }

    private function loadConfig(): void {
        $hashingConfig = (array)$this->plugin->getConfig()->get('password_hashing');
        $algorithmName = strtoupper((string)($hashingConfig['algorithm'] ?? 'BCRYPT'));
        $options = (array)($hashingConfig['options'] ?? []);

        switch ($algorithmName) {
            case 'BCRYPT':
                $this->algorithm = PASSWORD_BCRYPT;
                $this->options = (array)($options['BCRYPT'] ?? ['cost' => 10]);
                break;
            case 'ARGON2ID':
                if (defined('PASSWORD_ARGON2ID')) {
                    $this->algorithm = PASSWORD_ARGON2ID;
                    $this->options = (array)($options['ARGON2ID'] ?? ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]);
                } else {
                    $this->plugin->getLogger()->warning("ARGON2ID algorithm is not available on this PHP version. Falling back to BCRYPT.");
                    $this->algorithm = PASSWORD_BCRYPT;
                    $this->options = (array)($options['BCRYPT'] ?? ['cost' => 10]);
                }
                break;
            case 'ARGON2I':
                if (defined('PASSWORD_ARGON2I')) {
                    $this->algorithm = PASSWORD_ARGON2I;
                    $this->options = (array)($options['ARGON2I'] ?? ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]);
                } else {
                    $this->plugin->getLogger()->warning("ARGON2I algorithm is not available on this PHP version. Falling back to BCRYPT.");
                    $this->algorithm = PASSWORD_BCRYPT;
                    $this->options = (array)($options['BCRYPT'] ?? ['cost' => 10]);
                }
                break;
            default:
                $this->plugin->getLogger()->warning("Unknown password hashing algorithm '{$algorithmName}'. Falling back to BCRYPT.");
                $this->algorithm = PASSWORD_BCRYPT;
                $this->options = (array)($options['BCRYPT'] ?? ['cost' => 10]);
                break;
        }
    }

    public function hashPassword(string $password): string {
        return password_hash($password, $this->algorithm, $this->options);
    }

    public function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool {
        return password_needs_rehash($hash, $this->algorithm, $this->options);
    }
}
