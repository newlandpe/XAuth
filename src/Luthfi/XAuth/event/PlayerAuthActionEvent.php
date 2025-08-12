<?php

declare(strict_types=1);

namespace Luthfi\XAuth\event;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\player\PlayerEvent;
use pocketmine\player\Player;

/**
 * Called when an unauthenticated player tries to perform a protected action,
 * such as moving, sending a command, or breaking a block. This event can be cancelled
 * to allow the action.
 */
class PlayerAuthActionEvent extends PlayerEvent implements Cancellable 
{
    use CancellableTrait;

    public const ACTION_MOVE = 'move';
    public const ACTION_COMMAND = 'command';
    public const ACTION_CHAT = 'chat';
    public const ACTION_BLOCK_BREAK = 'block_break';
    public const ACTION_BLOCK_PLACE = 'block_place';
    public const ACTION_INTERACT = 'interact';
    public const ACTION_ITEM_USE = 'item_use';
    public const ACTION_DROP_ITEM = 'drop_item';
    public const ACTION_PICKUP_ITEM = 'pickup_item';
    public const ACTION_INVENTORY_CHANGE = 'inventory_change';
    public const ACTION_INVENTORY_TRANSACTION = 'inventory_transaction';
    public const ACTION_CRAFT = 'craft';
    public const ACTION_DAMAGE_RECEIVE = 'damage_receive';
    public const ACTION_DAMAGE_DEAL = 'damage_deal';

    private string $actionType;

    public function __construct(Player $player, string $actionType) {
        $this->player = $player;
        $this->actionType = $actionType;
    }

    public function getActionType(): string {
        return $this->actionType;
    }
}
