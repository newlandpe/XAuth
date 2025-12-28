<?php

/*
 *
 *  _          _   _     __  __  ____ _      __  __    _         _   _
 * | |   _   _| |_| |__ |  \/  |/ ___( )___  \ \/ /   / \  _   _| |_| |__
 * | |  | | | | __| '_ \| |\/| | |   |// __|  \  /   / _ \| | | | __| '_ \
 * | |__| |_| | |_| | | | |  | | |___  \__ \  /  \  / ___ \ |_| | |_| | | |
 * |_____\__,_|\__|_| |_|_|  |_|\____| |___/ /_/\_\/_/   \_\__,_|\__|_| |_|
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

namespace Luthfi\XAuth\network\handler;

use Closure;
use JsonMapper;
use JsonMapper_Exception;
use Luthfi\XAuth\Main;
use pocketmine\network\mcpe\handler\LoginPacketHandler;
use pocketmine\network\mcpe\JwtException;
use pocketmine\network\mcpe\JwtUtils;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\login\ClientData;
use pocketmine\network\PacketHandlingException;
use pocketmine\player\PlayerInfo;
use pocketmine\player\XboxLivePlayerInfo;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use ReflectionClass;
use ReflectionProperty;

class WaterdogExtrasLoginPacketHandler extends LoginPacketHandler {

    private Main $plugin;

    public function __construct(Server $server, NetworkSession $session, string $Waterdog_XUID, string $Waterdog_IP, Main $plugin) {
        $this->plugin = $plugin;
        $messages = (array)$this->plugin->getCustomMessages()->get("messages", []);

        if ($server->getOnlineMode()) {
            $kickMessage = (string)($messages['waterdog_online_mode_kick'] ?? "This server is in online-mode and cannot accept logins from a proxy.");
            $session->disconnect($kickMessage);
            return;
        }

        if ($server->getConfigGroup()->getPropertyBool("player.verify-xuid", true)) {
            $kickMessage = (string)($messages['waterdog_verify_xuid_kick'] ?? "This server has XUID verification enabled and cannot accept logins from a proxy.");
            $session->disconnect($kickMessage);
            return;
        }

        $playerInfoConsumer = Closure::bind(function (PlayerInfo $info) use ($session, $Waterdog_XUID, $Waterdog_IP): void {
            $session->ip = $Waterdog_IP;
            $session->info = new XboxLivePlayerInfo($Waterdog_XUID, $info->getUsername(), $info->getUuid(), $info->getSkin(), $info->getLocale(), $info->getExtraData());
            $session->logger->setPrefix($session->getLogPrefix());
            $session->logger->info("Player: " . TextFormat::AQUA . $info->getUsername() . TextFormat::RESET);
        }, $this, $session);
        $authCallback = Closure::bind(function (bool $isAuthenticated, bool $authRequired, ?string $error, ?string $clientPubKey) use ($session): void {
            $session->setAuthenticationStatus(true, $authRequired, $error, $clientPubKey);
        }, $this, $session);
        parent::__construct($server, $session, $playerInfoConsumer, $authCallback);
    }


    protected function parseClientData(string $clientDataJwt): ClientData {
        try {
            [, $clientDataClaims,] = JwtUtils::parse($clientDataJwt);
        } catch (JwtException $e) {
            throw PacketHandlingException::wrap($e);
        }
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $mapper->bExceptionOnMissingData = true;
        $mapper->bExceptionOnUndefinedProperty = true;
        try {
            $clientDataProperties = array_map(fn (ReflectionProperty $property) => $property->getName(), (new ReflectionClass(ClientData::class))->getProperties());
            foreach ($clientDataClaims as $k => $v) {
                if (!in_array($k, $clientDataProperties)) {
                    unset($clientDataClaims[$k]);
                }
            }
            unset($properties);
            $clientData = $mapper->map($clientDataClaims, new ClientData());
        } catch (JsonMapper_Exception $e) {
            throw PacketHandlingException::wrap($e);
        }
        return $clientData;
    }
}
