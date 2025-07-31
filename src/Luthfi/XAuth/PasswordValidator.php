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
        $complexityConfig = $this->plugin->getConfig()->get('password_complexity');

        if (strlen($password) < $complexityConfig['min_length']) {
            return str_replace('{length}', (string)$complexityConfig['min_length'], $this->plugin->getCustomMessages()->get("messages")["password_too_short"]);
        }

        if ($complexityConfig['require_uppercase'] && !preg_match('/[A-Z]', $password)) {
            return $this->plugin->getCustomMessages()->get("messages")["password_no_uppercase"];
        }

        if ($complexityConfig['require_lowercase'] && !preg_match('/[a-z]', $password)) {
            return $this->plugin->getCustomMessages()->get("messages")["password_no_lowercase"];
        }

        if ($complexityConfig['require_number'] && !preg_match('/[0-9]', $password)) {
            return $this->plugin->getCustomMessages()->get("messages")["password_no_number"];
        }

        if ($complexityConfig['require_symbol'] && !preg_match('/[^a-zA-Z0-9]', $password)) {
            return $this->plugin->getCustomMessages()->get("messages")["password_no_symbol"];
        }

        return null;
    }
}
