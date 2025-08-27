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

namespace Luthfi\XAuth\expansion;

use Luthfi\XAuth\Main;
use MohamadRZ4\Placeholder\expansion\PlaceholderExpansion;
use pocketmine\player\Player;

class XAuthExpansion extends PlaceholderExpansion {

    protected $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function getIdentifier(): string {
        return "xauth";
    }

    public function getAuthor(): string {
        return "Luthfi";
    }

    public function getVersion(): string {
        return "1.0.0";
    }

    public function onPlaceholderRequest(?Player $player, string $placeholder): ?string {
        if ($player === null) {
            switch ($placeholder) {
                case "authenticated_players":
                    $count = 0;
                    foreach ($this->plugin->getServer()->getOnlinePlayers() as $onlinePlayer) {
                        if ($this->plugin->getAuthenticationService()->isPlayerAuthenticated($onlinePlayer)) {
                            $count++;
                        }
                    }
                    return (string)$count;
                case "unauthenticated_players":
                    $count = 0;
                    foreach ($this->plugin->getServer()->getOnlinePlayers() as $onlinePlayer) {
                        if (!$this->plugin->getAuthenticationService()->isPlayerAuthenticated($onlinePlayer)) {
                            $count++;
                        }
                    }
                    return (string)$count;
            }
            return null;
        }

        switch ($placeholder) {
            case "is_authenticated":
                return $this->getTranslatedText($placeholder, $this->plugin->getAuthenticationService()->isPlayerAuthenticated($player));
            case "is_registered":
                return $this->getTranslatedText($placeholder, $this->plugin->getDataProvider()->isPlayerRegistered($player->getName()));
            case "is_locked":
                return $this->getTranslatedText($placeholder, $this->plugin->getDataProvider()->isPlayerLocked($player->getName()));
        }

        return null;
    }

    private function getTranslatedText(string $placeholder, bool $value): string {
        $key = "placeholders." . $placeholder . "." . ($value ? "true" : "false");
        $defaultValue = $value ? "Yes" : "No";
        return (string)($this->plugin->getCustomMessages()->getNested($key) ?? $defaultValue);
    }
}
