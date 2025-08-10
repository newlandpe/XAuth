# XAuth

[![Poggit CI](https://poggit.pmmp.io/ci.shield/newlandpe/XAuth/XAuth)](https://poggit.pmmp.io/ci/newlandpe/XAuth/XAuth)

A simple, secure, and extensible authentication plugin for PocketMine-MP, modernized for 2FA integration. This plugin is a fork of LuthMC's XAuth, aiming to provide enhanced security features and maintainability.

## Features

- **Player Authentication:** Secure registration and login system for your server.
- **Password Management:** Players can reset their passwords.
- **Admin Controls:** Server administrators can manage player accounts (lock/unlock, lookup, set password, unregister).
- **Session Management:** Secure session management with configurable expiration and IP-based validation.
- **Multi-language Support:** Customizable messages for various languages.
- **Extensible Design:** Built with extensibility in mind, allowing for future integrations like 2FA.
- **[PlaceholderAPI](https://github.com/MohamadRZ4/Placeholder) Integration:** Display authentication status and other data through placeholders.

## Installation

1. Download the latest stable version of XAuth from [Poggit CI](https://poggit.pmmp.io/ci/newlandpe/XAuth/XAuth) (or your preferred download source).
2. Place the `XAuth.phar` file into the `plugins/` folder of your PocketMine-MP server.
3. Restart your server.

## Configuration

The plugin generates a `config.yml` file in `plugin_data/XAuth/` upon first run. You can customize various settings there, including:

- Language settings
- Password requirements
- Auto-login options
- Session management settings
- Title message settings

Language messages are located in `plugin_data/XAuth/lang/`. You can modify existing language files or add new ones.

## Commands

Here are the commands available in XAuth:

- `/register <password> <password>`: Registers a new account on the server.
- `/login <password>`: Logs into your registered account.
- `/resetpassword <old_password> <new_password>`: Resets your account password.
- `/xauth <subcommand>`: Administrative commands for XAuth.
  - `/xauth help`: Show this help guide.
  - `/xauth lock <player_name>`: Locks a player's account, preventing them from logging in.
  - `/xauth unlock <player_name>`: Unlocks a player's account.
  - `/xauth lookup <player_name>`: Displays detailed information about a player's account.
  - `/xauth setpassword <player_name> <new_password>`: Sets a new password for a player's account.
  - `/xauth unregister <player_name>`: Unregisters a player's account, deleting their data.
  - `/xauth reload`: Reloads the plugin's configuration and language files.
  - `/xauth migrate-provider <source_provider> <destination_provider>`: Migrates player data from one data provider to another (e.g., YAML to SQLite). (Console only)
  - `/xauth forcepasswordchange <player_name>`: Forces a player to change their password on next login.
  - `/xauth status <subcommand>`: Manage online player authentication status.
    - `/xauth status list [player]`: List online players and their authentication status.
    - `/xauth status end <player>`: End a player's current online session, forcing re-authentication.
  - `/xauth sessions <subcommand>`: Manage player sessions.
    - `/xauth sessions list [player]`: List active sessions for a player.
    - `/xauth sessions terminate <session_id>`: Terminate a specific session.
    - `/xauth sessions terminateall <player>`: Terminate all sessions for a player.
    - `/xauth sessions cleanup`: Clean up all expired sessions.

### Permissions

| Permission | Description | Default |
| --- | --- | --- |
| `xauth.command.register` | Allow player to register an account | `true` |
| `xauth.command.login` | Allow player to login to their account | `true` |
| `xauth.command.resetpassword` | Allow player to reset their password | `true` |
| `xauth.command.admin` | Allows usage of the /xauth command | `op` |

## API for Developers

XAuth provides several public methods in its `Main` class that other plugins can utilize for integration:

- `getAuthManager()`: Access the authentication manager for player authentication logic.
- `getDataProvider()`: Interact with the plugin's data storage for player account information.
- `getCustomMessages()`: Retrieve custom language messages.
- `getPasswordValidator()`: Access the password validation logic.
- `getFormManager()`: (If applicable) Access the form UI manager.

Example of accessing the plugin:

```php
$xauth = $this->getServer()->getPluginManager()->getPlugin("XAuth");
if ($xauth instanceof \Luthfi\XAuth\Main) {
    // You can now use methods like $xauth->getDataProvider()->isPlayerRegistered($player->getName());
}
```

## Events

XAuth dispatches custom events that other plugins can listen to for integration.

### PlayerLoginEvent

Dispatched when a player successfully logs into their account. This event is cancellable and can be set to `isAuthenticationDelayed()` by other plugins (e.g., 2FA plugins) to temporarily halt the login process. If delayed, the login must be manually completed by calling `$this->getServer()->getPluginManager()->getPlugin("XAuth")->forceLogin($player);` after external authentication is successful.

```php
use Luthfi\XAuth\event\PlayerLoginEvent;
use pocketmine\event\Listener;

class MyLoginListener implements Listener {

    /**
     * @param PlayerLoginEvent $event
     * @priority NORMAL
     */
    public function onPlayerLogin(PlayerLoginEvent $event): void {
        $player = $event->getPlayer();
        $player->sendMessage("Welcome back, " . $player->getName() . "!");
        // Example for 2FA integration: delay authentication
        // $event->setAuthenticationDelayed(true);
        // $player->sendMessage("Please complete 2FA verification.");
    }
}
```

### PlayerAuthActionEvent

Dispatched when an unauthenticated player attempts to perform certain actions. Other plugins can listen to this event to allow or deny specific actions for unauthenticated players.

```php
use Luthfi\XAuth\event\PlayerAuthActionEvent;
use pocketmine\event\Listener;

class MyAuthActionListener implements Listener {

    /**
     * @param PlayerAuthActionEvent $event
     * @priority NORMAL
     */
    public function onPlayerAuthAction(PlayerAuthActionEvent $event): void {
        $player = $event->getPlayer();
        $actionType = $event->getActionType();

        // Example: Allow unauthenticated players to chat, but nothing else
        if ($actionType === PlayerAuthActionEvent::ACTION_CHAT) {
            $event->setCancelled(false); // Allow chat
        } else {
            $event->setCancelled(true); // Deny other actions
            $player->sendMessage("§cYou must be authenticated to perform this action!");
        }
    }
}
```

**Available PlayerAuthActionEvent types:**
- `PlayerAuthActionEvent::ACTION_MOVE`
- `PlayerAuthActionEvent::ACTION_CHAT`
- `PlayerAuthActionEvent::ACTION_COMMAND`
- `PlayerAuthActionEvent::ACTION_INTERACT`
- `PlayerAuthActionEvent::ACTION_DROP_ITEM`
- `PlayerAuthActionEvent::ACTION_DAMAGE`
- `PlayerAuthActionEvent::ACTION_PICKUP_ITEM`
- `PlayerAuthActionEvent::ACTION_BLOCK_BREAK`
- `PlayerAuthActionEvent::ACTION_BLOCK_PLACE`
- `PlayerAuthActionEvent::ACTION_ITEM_USE`
- `PlayerAuthActionEvent::ACTION_INVENTORY_CHANGE`

### PlayerUnregisterEvent

Dispatched when a player's account is unregistered (e.g., by an admin using `/xauth unregister`).

```php
use Luthfi\XAuth\event\PlayerUnregisterEvent;
use pocketmine\event\Listener;
use pocketmine\player\IPlayer; // Use IPlayer as the player might be offline

class MyUnregisterListener implements Listener {

    /**
     * @param PlayerUnregisterEvent $event
     * @priority NORMAL
     */
    public function onPlayerUnregister(PlayerUnregisterEvent $event): void {
        $player = $event->getPlayer(); // This is an IPlayer
        $this->getServer()->getLogger()->info("Player " . $player->getName() . " has been unregistered.");
    }
}
```

### PlayerRegisterEvent

Dispatched when a player successfully registers a new account.

```php
use Luthfi\XAuth\event\PlayerRegisterEvent;
use pocketmine\event\Listener;

class MyRegisterListener implements Listener {

    /**
     * @param PlayerRegisterEvent $event
     * @priority NORMAL
     */
    public function onPlayerRegister(PlayerRegisterEvent $event): void {
        $player = $event->getPlayer();
        $player->sendMessage("Thanks for registering, " . $player->getName() . "!");
    }
}
```

### PlayerChangePasswordEvent

Dispatched when a player successfully changes their password.

```php
use Luthfi\XAuth\event\PlayerChangePasswordEvent;
use pocketmine\event\Listener;

class MyChangePasswordListener implements Listener {

    /**
     * @param PlayerChangePasswordEvent $event
     * @priority NORMAL
     */
    public function onChangePassword(PlayerChangePasswordEvent $event): void {
        $player = $event->getPlayer();
        $player->sendMessage("Your password has been successfully changed, " . $player->getName() . "!");
    }
}
```

### PlayerAuthenticateEvent

Dispatched when a player is successfully authenticated.

```php
use Luthfi\XAuth\event\PlayerAuthenticateEvent;
use pocketmine\event\Listener;

class MyAuthenticateListener implements Listener {

    /**
     * @param PlayerAuthenticateEvent $event
     * @priority NORMAL
     */
    public function onAuthenticate(PlayerAuthenticateEvent $event): void {
        $player = $event->getPlayer();
        $player->sendMessage("You have been authenticated!");
    }
}
```

### PlayerDeauthenticateEvent

Dispatched when a player is deauthenticated.

```php
use Luthfi\XAuth\event\PlayerDeauthenticateEvent;
use pocketmine\event\Listener;

class MyDeauthenticateListener implements Listener {

    /**
     * @param PlayerDeauthenticateEvent $event
     * @priority NORMAL
     */
    public function onDeauthenticate(PlayerDeauthenticateEvent $event): void {
        $player = $event->getPlayer();
        $player->sendMessage("You have been deauthenticated.");
    }
}
```

### PlayerStateRestoreEvent

Dispatched when a player's state (inventory, effects, etc.) is restored after authentication. This event is cancellable.

```php
use Luthfi\XAuth\event\PlayerStateRestoreEvent;
use pocketmine\event\Listener;

class MyStateRestoreListener implements Listener {

    /**
     * @param PlayerStateRestoreEvent $event
     * @priority NORMAL
     */
    public function onStateRestore(PlayerStateRestoreEvent $event): void {
        $player = $event->getPlayer();
        $player->sendMessage("Your state has been restored.");
    }
}
```

### PlayerStateSaveEvent

Dispatched when a player's state is saved before authentication.

```php
use Luthfi\XAuth\event\PlayerStateSaveEvent;
use pocketmine\event\Listener;

class MyStateSaveListener implements Listener {

    /**
     * @param PlayerStateSaveEvent $event
     * @priority NORMAL
     */
    public function onStateSave(PlayerStateSaveEvent $event): void {
        $player = $event->getPlayer();
        $player->sendMessage("Your state has been saved.");
    }
}
```

To listen for these events, register your listener class in your plugin's `onEnable()` method:

```php
$this->getServer()->getPluginManager()->registerEvents(new MyLoginListener(), $this);
$this->getServer()->getPluginManager()->registerEvents(new MyUnregisterListener(), $this);
$this->getServer()->getPluginManager()->registerEvents(new MyRegisterListener(), $this);
$this->getServer()->getPluginManager()->registerEvents(new MyChangePasswordListener(), $this);
$this->getServer()->getPluginManager()->registerEvents(new MyAuthenticateListener(), $this);
$this->getServer()->getPluginManager()->registerEvents(new MyDeauthenticateListener(), $this);
$this->getServer()->getPluginManager()->registerEvents(new MyStateRestoreListener(), $this);
$this->getServer()->getPluginManager()->registerEvents(new MyStateSaveListener(), $this);
```

## PlaceholderAPI Integration

XAuth supports PlaceholderAPI to display player authentication status and other information. The following placeholders are available:

| Placeholder | Description |
| --- | --- |
| `%xauth_is_authenticated%` | Returns "Yes" if the player is authenticated, otherwise "No". |
| `%xauth_is_registered%` | Returns "Yes" if the player is registered, otherwise "No". |
| `%xauth_is_locked%` | Returns "Yes" if the player's account is locked, otherwise "No". |
| `%xauth_authenticated_players%` | Returns the number of authenticated players online. |
| `%xauth_unauthenticated_players%` | Returns the number of unauthenticated players online. |

## Contributing

Contributions are welcome and appreciated! Here's how you can contribute:

1. Fork the project on GitHub.
2. Create your feature branch (`git checkout -b feature/AmazingFeature`).
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`).
4. Push to the branch (`git push origin feature/AmazingFeature`).
5. Open a Pull Request.

Please make sure to update tests as appropriate and adhere to the existing coding style.

## License

This project is licensed under the CSSM Unlimited License v2 (CSSM-ULv2). Please note that this is a custom license. See the [LICENSE](LICENSE) file for details.
