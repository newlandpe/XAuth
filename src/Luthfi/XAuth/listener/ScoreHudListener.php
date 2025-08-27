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

namespace Luthfi\XAuth\listener;

use Ifera\ScoreHud\event\PlayerTagUpdateEvent;
use Ifera\ScoreHud\event\ServerTagsUpdateEvent;
use Ifera\ScoreHud\scoreboard\ScoreTag;
use Ifera\ScoreHud\ScoreHud;
use Luthfi\XAuth\Main;
use Luthfi\XAuth\event\PlayerAuthenticateEvent;
use Luthfi\XAuth\event\PlayerDeauthenticateEvent;
use Luthfi\XAuth\event\PlayerRegisterEvent;
use Luthfi\XAuth\event\PlayerUnregisterEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;

class ScoreHudListener implements Listener {
    use SingletonTrait;

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        self::setInstance($this);
    }

    public static function updatePlayerTags(Player $player): void {
        $plugin = self::getInstance()->plugin;
        if (!ScoreHud::getInstance() instanceof ScoreHud) {
            return;
        }

        $isAuthenticated = $plugin->getAuthenticationService()->isPlayerAuthenticated($player);
        $isRegistered = $plugin->getDataProvider()->isPlayerRegistered($player->getName());
        $isLocked = $plugin->getDataProvider()->isPlayerLocked($player->getName());

        (new PlayerTagUpdateEvent($player, new ScoreTag("xauth.is_authenticated", $this->getScoreHudBooleanText("is_authenticated", $isAuthenticated))))->call();
        (new PlayerTagUpdateEvent($player, new ScoreTag("xauth.is_registered", $this->getScoreHudBooleanText("is_registered", $isRegistered))))->call();
        (new PlayerTagUpdateEvent($player, new ScoreTag("xauth.is_locked", $this->getScoreHudBooleanText("is_locked", $isLocked))))->call();
    }

    private function getScoreHudBooleanText(string $tag, bool $value): string {
        $key = "scorehud_tags." . $tag . "." . ($value ? "true" : "false");
        // Default values if not found in language file
        $defaultValue = $value ? "Yes" : "No";
        return (string)($this->plugin->getCustomMessages()->getNested($key) ?? $defaultValue);
    }

    public static function updateGlobalTags(): void {
        $plugin = self::getInstance()->plugin;
        if (!ScoreHud::getInstance() instanceof ScoreHud) {
            return;
        }

        $authenticatedPlayers = count($plugin->getAuthenticationService()->getAuthenticatedPlayers());
        $unauthenticatedPlayers = count($plugin->getServer()->getOnlinePlayers()) - $authenticatedPlayers;

        (new ServerTagsUpdateEvent([
            new ScoreTag("xauth.authenticated_players", (string)$authenticatedPlayers),
            new ScoreTag("xauth.unauthenticated_players", (string)$unauthenticatedPlayers)
        ]))->call();
    }

    public function onPlayerAuthenticate(PlayerAuthenticateEvent $event): void {
        self::updatePlayerTags($event->getPlayer());
        self::updateGlobalTags();
    }

    public function onPlayerDeauthenticate(PlayerDeauthenticateEvent $event): void {
        self::updatePlayerTags($event->getPlayer());
        self::updateGlobalTags();
    }

    public function onPlayerRegister(PlayerRegisterEvent $event): void {
        self::updatePlayerTags($event->getPlayer());
        self::updateGlobalTags();
    }

    public function onPlayerUnregister(PlayerUnregisterEvent $event): void {
        $player = $event->getPlayer();
        if ($player instanceof Player) {
            self::updatePlayerTags($player);
        }
        self::updateGlobalTags();
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void {
        // This event is handled in PlayerSessionListener to ensure session is initialized
    }
}
