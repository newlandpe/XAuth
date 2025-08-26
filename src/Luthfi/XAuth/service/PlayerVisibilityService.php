<?php

declare(strict_types=1);

namespace Luthfi\XAuth\service;

use Luthfi\XAuth\Main;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\player\Player;

class PlayerVisibilityService {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function updatePlayerVisibility(Player $targetPlayer): void {
        foreach ($this->plugin->getServer()->getOnlinePlayers() as $otherPlayer) {
            if ($targetPlayer === $otherPlayer) continue;

            // Update how this online player sees the target player
            $this->resolveVisibility($otherPlayer, $targetPlayer);

            // Update how the target player sees this online player
            $this->resolveVisibility($targetPlayer, $otherPlayer);
        }
    }

    private function resolveVisibility(Player $observer, Player $subject): void {
        $authenticationService = $this->plugin->getAuthenticationService();
        $config = $this->plugin->getConfig();

        $observerAuthenticated = $authenticationService->isPlayerAuthenticated($observer);
        $subjectAuthenticated = $authenticationService->isPlayerAuthenticated($subject);

        if ($observerAuthenticated) {
            $inWorldMode = strtolower((string)($config->getNested('in_world_visibility.mode') ?? 'packets'));
            if ($subjectAuthenticated) {
                // Both authenticated - show everywhere
                $observer->showPlayer($subject);
                if ($inWorldMode === 'effect') {
                    $subject->getEffects()->remove(VanillaEffects::INVISIBILITY());
                }
            } else {
                // Observer authenticated, subject not - apply hiding logic
                if ($inWorldMode === 'packets') {
                    $observer->hidePlayer($subject);
                } elseif ($inWorldMode === 'effect') {
                    $subject->getEffects()->add(new EffectInstance(VanillaEffects::INVISIBILITY(), 2147483647, 0, false));
                } else {
                    $observer->showPlayer($subject);
                    $subject->getEffects()->remove(VanillaEffects::INVISIBILITY());
                }
            }

            // Handle player list visibility
            if (!$subjectAuthenticated && (bool)$config->getNested('player_list_visibility.hide', true)) {
                $this->hideFromPlayerList($observer, $subject);
            } else {
                $this->showInPlayerList($observer, $subject);
            }
        } else {
            // Observer not authenticated
            if ($config->getNested('in_world_visibility.hide_others_from_unauthenticated', true)) {
                $observer->hidePlayer($subject);
            } else {
                $observer->showPlayer($subject);
            }

            if ($config->getNested('player_list_visibility.hide_others_from_unauthenticated', false)) {
                $this->hideFromPlayerList($observer, $subject);
            } else {
                $this->showInPlayerList($observer, $subject);
            }
        }
    }

    private function hideFromPlayerList(Player $observer, Player $subject): void {
        NetworkBroadcastUtils::broadcastPackets(
            [$observer],
            [PlayerListPacket::remove([
                PlayerListEntry::createRemovalEntry($subject->getUniqueId())
            ])]
        );
    }

    private function showInPlayerList(Player $observer, Player $subject): void {
        $networkSession = $subject->getNetworkSession();
        if (!$networkSession->isConnected()) {
            return;
        }

        $typeConverter = $networkSession->getTypeConverter();
        $skinData = $typeConverter->getSkinAdapter()->toSkinData($subject->getSkin());

        NetworkBroadcastUtils::broadcastPackets(
            [$observer],
            [PlayerListPacket::add([
                PlayerListEntry::createAdditionEntry(
                    $subject->getUniqueId(),
                    $subject->getId(),
                    $subject->getName(),
                    $skinData
                )
            ])]
        );
    }
}
