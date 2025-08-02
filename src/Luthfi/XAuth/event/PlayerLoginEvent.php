<?php

declare(strict_types=1);

namespace Luthfi\XAuth\event;

use pocketmine\event\player\PlayerEvent;
use pocketmine\player\Player;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;

class PlayerLoginEvent extends PlayerEvent implements Cancellable {
    use CancellableTrait;

    private bool $isAuthenticationDelayed = false;
    private bool $isAutoLogin;

    public function __construct(Player $player, bool $isAutoLogin = false) {
        $this->player = $player;
        $this->isAutoLogin = $isAutoLogin;
    }

    public function isAutoLogin(): bool {
        return $this->isAutoLogin;
    }

    public function setAuthenticationDelayed(bool $isDelayed): void {
        $this->isAuthenticationDelayed = $isDelayed;
    }

    public function isAuthenticationDelayed(): bool {
        return $this->isAuthenticationDelayed;
    }
}
