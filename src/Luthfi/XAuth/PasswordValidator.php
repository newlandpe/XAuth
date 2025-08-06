<?php

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
            $filePath = $this->plugin->getDataFolder() . $weakPasswordFile;

            // If using the default weak password file and it doesn't exist, create it.
            if ($weakPasswordFile === 'weak_passwords.txt' && !file_exists($filePath)) {
                $this->plugin->saveResource('weak_passwords.txt');
            }

            if (file_exists($filePath)) {
                $this->weakPasswords = array_map('trim', file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
                $this->weakPasswords = array_map('strtolower', $this->weakPasswords);
            } else {
                $this->plugin->getLogger()->warning("Weak password list file not found: {$filePath}");
            }
        }
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

        // Check against weak password list
        if ((bool)($complexityConfig['enable_weak_password_check'] ?? false)) {
            if (in_array(strtolower($password), $this->weakPasswords, true)) {
                return (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["password_is_weak"] ?? "");
            }
        }

        return null;
    }
}
