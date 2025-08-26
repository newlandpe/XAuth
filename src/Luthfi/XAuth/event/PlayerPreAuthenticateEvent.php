<?php

declare(strict_types=1);

namespace Luthfi\XAuth\event;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\player\PlayerEvent;
use pocketmine\player\Player;

/**
 * Called when a player successfully authenticates (e.g. by password).
 * This event can be cancelled by other plugins (e.g. 2FA).
 */
class PlayerPreAuthenticateEvent extends PlayerEvent implements Cancellable {
    use CancellableTrait;

    public const LOGIN_TYPE_MANUAL = 'manual';
    public const LOGIN_TYPE_AUTO = 'auto';

    private string $loginType;
    private ?string $kickMessage = null;

    public function __construct(Player $player, string $loginType = self::LOGIN_TYPE_MANUAL) {
        $this->player = $player;
        $this->loginType = $loginType;
    }

    public function getLoginType(): string {
        return $this->loginType;
    }

    public function cancel(?string $kickMessage = null): void {
        $this->kickMessage = $kickMessage;
        $this->setCancelled(true);
    }

    public function getKickMessage(): ?string {
        return $this->kickMessage;
    }
}
