<?php

declare(strict_types=1);

namespace Luthfi\XAuth;

use pocketmine\utils\Config;

class PasswordValidator {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function validatePassword(string $password): ?string {
        $complexityConfig = (array)$this->plugin->getConfig()->get('password_complexity');

        $minLength = (int)($complexityConfig['min_length'] ?? 6);
        if (strlen($password) < $minLength) {
            $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["password_too_short"] ?? "");
            return str_replace('{length}', (string)$minLength, $message);
        }

        $maxLength = (int)($complexityConfig['max_length'] ?? 64);
        if (strlen($password) > $maxLength) {
            $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["password_too_long"] ?? "");
            return str_replace('{length}', (string)$maxLength, $message);
        }

        $requireUppercase = (bool)($complexityConfig['require_uppercase'] ?? false);
        if ($requireUppercase && !preg_match('/[A-Z]/', $password)) {
            return (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["password_no_uppercase"] ?? "");
        }

        $requireLowercase = (bool)($complexityConfig['require_lowercase'] ?? false);
        if ($requireLowercase && !preg_match('/[a-z]/', $password)) {
            return (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["password_no_lowercase"] ?? "");
        }

        $requireNumber = (bool)($complexityConfig['require_number'] ?? false);
        if ($requireNumber && !preg_match('/[0-9]/', $password)) {
            return (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["password_no_number"] ?? "");
        }

        $requireSymbol = (bool)($complexityConfig['require_symbol'] ?? false);
        if ($requireSymbol && !preg_match('/[^a-zA-Z0-9]/', $password)) {
            return (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["password_no_symbol"] ?? "");
        }

        return null;
    }
}
