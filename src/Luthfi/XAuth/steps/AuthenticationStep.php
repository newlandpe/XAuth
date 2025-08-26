<?php

declare(strict_types=1);

namespace Luthfi\XAuth\steps;

use pocketmine\player\Player;

interface AuthenticationStep {

    public function getId(): string;
    public function start(Player $player): void;
    public function complete(Player $player): void;
    public function skip(Player $player): void;
}
