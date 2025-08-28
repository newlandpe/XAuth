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

class PasswordValidator {

    private Main $plugin;
    private array $weakPasswords = [];

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->loadWeakPasswords();
    }

    private function loadWeakPasswords(): void {
        $config = $this->plugin->getConfig();
        $enableWeakCheck = (bool)($config->getNested('password_complexity.enable_weak_password_check') ?? false);

        if ($enableWeakCheck) {
            $weakPasswordFile = (string)($config->getNested('password_complexity.weak_password_list_file') ?? 'weak_passwords.txt');

            if (!preg_match('/^(?:[A-Z]:\\\\|\\\\|\/)/i', $weakPasswordFile)) {
                $filePath = $this->plugin->getDataFolder() . $weakPasswordFile;
            } else {
                $filePath = $weakPasswordFile;
            }

            // If using the default weak password file and it doesn't exist, create it.
            if ($weakPasswordFile === 'weak_passwords.txt' && !file_exists($filePath)) {
                $this->plugin->saveResource('weak_passwords.txt');
            }

            if (file_exists($filePath)) {
                $list = array_map('trim', file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
                $list = array_map('strtolower', $list);
                $this->weakPasswords = array_flip($list);
            } else {
                $this->plugin->getLogger()->warning("Weak password list file not found: {$filePath}");
            }
        }
    }

    public function validatePassword(string $password): ?string {
        $complexityConfig = (array)$this->plugin->getConfig()->get('password_complexity');

        $minLength = (int)($complexityConfig['min_length'] ?? 6);
        if (strlen($password) < $minLength) {
            return str_replace('{length}', (string)$minLength, $this->getMessage("password_too_short"));
        }

        $maxLength = (int)($complexityConfig['max_length'] ?? 64);
        if (strlen($password) > $maxLength) {
            return str_replace('{length}', (string)$maxLength, $this->getMessage("password_too_long"));
        }

        $requireUppercase = (bool)($complexityConfig['require_uppercase'] ?? false);
        if ($requireUppercase && !preg_match('/[A-Z]/', $password)) {
            return $this->getMessage("password_no_uppercase");
        }

        $requireLowercase = (bool)($complexityConfig['require_lowercase'] ?? false);
        if ($requireLowercase && !preg_match('/[a-z]/', $password)) {
            return $this->getMessage("password_no_lowercase");
        }

        $requireNumber = (bool)($complexityConfig['require_number'] ?? false);
        if ($requireNumber && !preg_match('/[0-9]/', $password)) {
            return $this->getMessage("password_no_number");
        }

        $requireSymbol = (bool)($complexityConfig['require_symbol'] ?? false);
        if ($requireSymbol && !preg_match('/[^a-zA-Z0-9]/', $password)) {
            return $this->getMessage("password_no_symbol");
        }

        // Check against weak password list
        if (!empty($this->weakPasswords) && isset($this->weakPasswords[strtolower($password)])) {
            return $this->getMessage("password_is_weak");
        }

        return null;
    }

    private function getMessage(string $key): string {
        $messages = $this->plugin->getCustomMessages()->get("messages");
        return is_array($messages) && isset($messages[$key]) ? (string)$messages[$key] : "";
    }
}
