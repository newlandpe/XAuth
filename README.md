# XAuth

[![Poggit CI](https://poggit.pmmp.io/ci.shield/newlandpe/XAuth/XAuth)](https://poggit.pmmp.io/ci/newlandpe/XAuth/XAuth)

A simple, secure, and extensible authentication plugin for PocketMine-MP, modernized for 2FA integration. This plugin is a fork of LuthMC's XAuth, aiming to provide enhanced security features and maintainability.

## Features

- **Player Authentication:** Secure registration and login system for your server.
- **Password Management:** Players can reset their passwords.
- **Admin Controls:** Server administrators can manage player accounts (lock/unlock, lookup, set password, unregister).
- **Session Management:** Secure session management with configurable expiration and IP-based validation.
- **Configurable Authentication Flow:** Customize the sequence of authentication steps, allowing for easy integration of third-party plugins like 2FA or captchas.
- **Multi-language Support:** Customizable messages for various languages.
- **Extensible Design:** Built with extensibility in mind, enabling future features like 2FA or external plugin hooks.
- **[PlaceholderAPI](https://github.com/MohamadRZ4/Placeholder) Integration:** Display authentication status and other data through placeholders.
- **ScoreHud Integration:** Show authentication status and other data on scoreboards.
- **WaterdogPE Compatibility:** Ensures correct handling of player IP and XUID when connecting through WaterdogPE proxy.

## Installation

1. Download the latest stable version of XAuth from [Poggit CI](https://poggit.pmmp.io/ci/newlandpe/XAuth/XAuth) (or your preferred download source).
2. Place the `XAuth.phar` file into the `plugins/` folder of your PocketMine-MP server.
3. Restart your server.

## Configuration

The plugin generates a `config.yml` file in `plugin_data/XAuth/` upon first run. It contains a wide range of customizable settings, including:

- **Language & Titles:** Change plugin language and control title messages on join.
- **Authentication Flow:** Define the order of login steps (e.g. `captcha_login`, `auto_login`, `xauth_login`, `xauth_register`, `binding_manager_2fa`).
- **Auto-login:** Configure security level, session lifetime, and cleanup intervals.
- **Password Security:** Complexity requirements, weak-password checks, and modern hashing algorithms (BCRYPT, ARGON2).
- **Forms:** Optional UI-based login/register forms with close/kick handling.
- **Database:** Choose between `yaml`, `json`, `sqlite`, or `mysql` for storing player data.
- **Brute-force Protection:** Limit failed attempts, apply temporary blocks, and kick offenders.
- **Command Settings:** Fine-tune commands like `/logout`, `/unregister`, and forced password changes.
- **Session & Timeouts:** Control login timeout and session expiration.
- **IP Limits:** Restrict number of registrations or connections per IP.
- **Player Restrictions:** Define what unauthenticated players can/cannot do (chat, move, break blocks, use items, etc.).
- **Protection:** Temporarily change game mode, teleport to safe spawn, and protect player state until login.
- **Visibility:** Configure in-world invisibility, Tab list hiding, and blindness effect for unauthenticated players.
- **GeoIP Filtering:** Allow or block countries/regions.
- **WaterdogPE Fixes:** Handle proxy-related authentication issues.

### Language Configuration

Language messages are located in `plugin_data/XAuth/lang/`. You can modify existing language files or add new ones.

## Commands

Here are the commands available in XAuth:

- `/register <password> <confirm_password>`: Registers a new account on the server.
- `/login <password>`: Logs into your registered account.
- `/resetpassword <old_password> <new_password> <confirm_password>`: Resets your account password.
- `/logout`: Logs out from your account.
- `/unregister`: Unregisters your own account.
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
| `xauth.command.logout` | Allow player to logout from their account | `true` |
| `xauth.command.unregister` | Allow player to unregister their own account | `true` |
| `xauth.command.admin` | Allows usage of the /xauth command | `op` |

## API for Developers

XAuth provides several public methods in its `Main` class that other plugins can utilize for integration:

- `getAuthenticationService()`: Access the authentication service for player authentication logic.
- `getRegistrationService()`: Access the registration service.
- `getSessionService()`: Access the session service.
- `getPlayerStateService()`: Access the player state service.
- `getPlayerVisibilityService()`: Access the player visibility service.
- `getDataProvider()`: Interact with the plugin's data storage for player account information.
- `getPasswordValidator()`: Access the password validation logic.
- `getPasswordHasher()`: Access the password hashing logic.

Example of accessing the plugin:

```php
$xauth = $this->getServer()->getPluginManager()->getPlugin("XAuth");
if ($xauth instanceof \Luthfi\XAuth\Main) {
    // You can now use methods like $xauth->getDataProvider()->isPlayerRegistered($player->getName());
    // Or access services:
    $authService = $xauth->getAuthenticationService();
}
```

### Custom Authentication Steps (Authentication Flow API)

XAuth features a powerful and extensible Authentication Flow system, allowing other plugins to register their own authentication steps. This is the **recommended** way to integrate features like 2FA, captchas, or any other verification process.

Registered steps can be ordered and enabled via the `authentication-flow-order` list in `config.yml`.

To register a custom step, you need to:

1. **Define your custom authentication step class.**
   This class must `implement Luthfi\XAuth\steps\AuthenticationStep` and provide implementations for `getId()`, `start()`, `complete()`, and `skip()` methods. The constructor should accept the `Luthfi\XAuth\Main` plugin instance.

   ```php
   // In your plugin's src/MyPlugin/Steps/MyCustomStep.php
   <?php

   declare(strict_types=1);

   namespace MyPlugin\Steps;

   use Luthfi\XAuth\Main as XAuthMain;
   use Luthfi\XAuth\steps\AuthenticationStep;
   use pocketmine\player\Player;

   class MyCustomStep implements AuthenticationStep {

       private XAuthMain $xauthPlugin;

       public function __construct(XAuthMain $xauthPlugin) {
           $this->xauthPlugin = $xauthPlugin;
       }

       public function getId(): string {
           return 'myplugin_custom_check'; // Unique ID for your step
       }

       public function start(Player $player): void {
           // This method is called when the authentication flow reaches this step.
           // Here, you would present a form, send a message, or initiate any action
           // required for the player to complete this step.
           $player->sendMessage("§e[MyPlugin] Please type '/verify' to complete this step.");
           // Example: $this->xauthPlugin->getFormManager()->sendCustomForm($player);
       }

       public function complete(Player $player): void {
           // This method is called when your step is successfully completed.
           // It advances the XAuth authentication flow.
           $this->xauthPlugin->getAuthenticationFlowManager()->completeStep($player, $this->getId());
       }

       public function skip(Player $player): void {
           // This method is called when your step should be skipped.
           // It advances the XAuth authentication flow.
           $this->xauthPlugin->getAuthenticationFlowManager()->skipStep($player, $this->getId());
       }
   }
   ```

2. **Register your custom step in your plugin's `Main` class.**
   In your plugin's `onEnable()` method, get the XAuth plugin instance and register your custom step.

   ```php
   // In your plugin's src/MyPlugin/Main.php
   <?php

   declare(strict_types=1);

   namespace MyPlugin;

   use Luthfi\XAuth\Main as XAuthMain;
   use MyPlugin\Steps\MyCustomStep; // Import your custom step class
   use pocketmine\plugin\PluginBase;

   class Main extends PluginBase {

       public function onEnable(): void {
           $xauth = $this->getServer()->getPluginManager()->getPlugin("XAuth");
           if ($xauth instanceof XAuthMain) {
               $xauth->registerAuthenticationStep(new MyCustomStep($xauth));
               $this->getLogger()->info("MyCustomStep registered with XAuth.");
           } else {
               $this->getLogger()->warning("XAuth plugin not found. MyCustomStep will not be registered.");
           }
       }

       // ... other plugin methods ...
   }
   ```

3. **Trigger completion from your plugin.**
   Once the player completes the action required by your custom step (e.g., submits a form, types a command), you need to explicitly tell XAuth that the step is complete.

   ```php
   // In your plugin's src/MyPlugin/Command/VerifyCommand.php (example command)
   <?php

   declare(strict_types=1);

   namespace MyPlugin\Command;

   use Luthfi\XAuth\Main as XAuthMain;
   use MyPlugin\Steps\MyCustomStep; // Import your custom step class
   use pocketmine\command\Command;
   use pocketmine\command\CommandSender;
   use pocketmine\player\Player;

   class VerifyCommand extends Command {

       private XAuthMain $xauthPlugin;

       public function __construct(XAuthMain $xauthPlugin) {
           parent::__construct("verify", "Verify yourself", "/verify");
           $this->xauthPlugin = $xauthPlugin;
       }

       public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
           if (!$sender instanceof Player) {
               $sender->sendMessage("This command can only be used by players.");
               return false;
           }

           // Check if the player is currently in the authentication flow and needs this step
           // You can use getPlayerAuthenticationStepStatus() from AuthenticationFlowManager
           $authFlowManager = $this->xauthPlugin->getAuthenticationFlowManager();
           $status = $authFlowManager->getPlayerAuthenticationStepStatus($sender, "myplugin_custom_check");

           if ($status === null) { // If the step is not yet completed or skipped
               // Mark the step as complete by retrieving the step instance from the flow manager
               $customStep = $authFlowManager->getStep("myplugin_custom_check");
               if ($customStep instanceof MyCustomStep) {
                   $customStep->complete($sender);
                   $sender->sendMessage("§aVerification complete!");
               } else {
                   // This case should ideally not happen if your step is registered correctly.
                   $sender->sendMessage("§cCould not process verification. Please contact an administrator.");
               }
               return true;
           }

           $sender->sendMessage("§cYou don't need to verify yourself at this moment.");
           return false;
       }
   }
   ```

4. **Add your step ID to XAuth's `config.yml`.**
   Finally, add the unique ID of your custom step (`myplugin_custom_check` in this example) to the `authentication-flow-order` list in XAuth's `config.yml` to activate it.

   ```yaml
   # Example config.yml snippet:
   authentication-flow-order:
     - xauth_login
     - myplugin_custom_check
     - binding_manager_2fa
   ```

## Events

XAuth dispatches custom events that other plugins can listen to for integration.

### PlayerPreAuthenticateEvent

Dispatched when a player successfully authenticates (e.g., by password), but before they are officially logged in and visible to others. This event is cancellable. If cancelled, you can provide an optional kick message.

> [!WARNING]
> While this event can be used for checks before login, it is **not recommended** for adding new authentication steps (like 2FA or captcha). For this purpose, use the **Authentication Flow API** instead, which provides proper sequencing and timeout handling.

```php
use Luthfi\XAuth\event\PlayerPreAuthenticateEvent;
use pocketmine\event\Listener;

class MyPreAuthenticateListener implements Listener {

    /**
     * @param PlayerPreAuthenticateEvent $event
     * @priority NORMAL
     */
    public function onPlayerPreAuthenticate(PlayerPreAuthenticateEvent $event): void {
        $player = $event->getPlayer();
        $loginType = $event->getLoginType(); // Can be 'manual' or 'auto'

        // Example: Log the authentication type and send a welcome message
        $this->getServer()->getLogger()->info(
            "Player " . $player->getName() . " is about to be authenticated via " . $loginType . " login."
        );
        
        $player->sendMessage("§aAuthentication successful. Finalizing your login...");
    }
}
```

### PlayerAuthenticateFailedEvent

Dispatched when a player fails to authenticate (e.g., by providing an incorrect password).

```php
use Luthfi\XAuth\event\PlayerAuthenticateFailedEvent;
use pocketmine\event\Listener;

class MyAuthFailedListener implements Listener {

    /**
     * @param PlayerAuthenticateFailedEvent $event
     * @priority NORMAL
     */
    public function onPlayerAuthenticateFailed(PlayerAuthenticateFailedEvent $event): void {
        $player = $event->getPlayer();
        $failedAttempts = $event->getFailedAttempts();
        $this->getServer()->getLogger()->info("Player " . $player->getName() . " failed to log in. Failed attempts: " . $failedAttempts);
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
- `PlayerAuthActionEvent::ACTION_COMMAND`
- `PlayerAuthActionEvent::ACTION_CHAT`
- `PlayerAuthActionEvent::ACTION_BLOCK_BREAK`
- `PlayerAuthActionEvent::ACTION_BLOCK_PLACE`
- `PlayerAuthActionEvent::ACTION_INTERACT`
- `PlayerAuthActionEvent::ACTION_ITEM_USE`
- `PlayerAuthActionEvent::ACTION_DROP_ITEM`
- `PlayerAuthActionEvent::ACTION_PICKUP_ITEM`
- `PlayerAuthActionEvent::ACTION_INVENTORY_CHANGE`
- `PlayerAuthActionEvent::ACTION_INVENTORY_TRANSACTION`
- `PlayerAuthActionEvent::ACTION_CRAFT`
- `PlayerAuthActionEvent::ACTION_DAMAGE_RECEIVE`
- `PlayerAuthActionEvent::ACTION_DAMAGE_DEAL`

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
use pocketmine\item\Item;
use pocketmine\item\VanillaItems; // Import VanillaItems for easy access to common items

class MyRegisterListener implements Listener {

    /**
     * @param PlayerRegisterEvent $event
     * @priority NORMAL
     */
    public function onPlayerRegister(PlayerRegisterEvent $event): void {
        $player = $event->getPlayer();
        $player->sendMessage("Thanks for registering, " . $player->getName() . "!");

        // Give the player 10 diamonds as a welcome gift
        $diamonds = VanillaItems::DIAMOND()->setCount(10);
        $player->getInventory()->addItem($diamonds);
        $player->sendMessage("§aYou received 10 diamonds as a welcome gift!");
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
$this->getServer()->getPluginManager()->registerEvents(new MyPreAuthenticateListener(), $this);
$this->getServer()->getPluginManager()->registerEvents(new MyAuthActionListener(), $this);
$this->getServer()->getPluginManager()->registerEvents(new MyUnregisterListener(), $this);
$this->getServer()->getPluginManager()->registerEvents(new MyRegisterListener(), $this);
$this->getServer()->getPluginManager()->registerEvents(new MyChangePasswordListener(), $this);
$this->getServer()->getPluginManager()->registerEvents(new MyAuthenticateListener(), $this);
$this->getServer()->getPluginManager()->registerEvents(new MyDeauthenticateListener(), $this);
$this->getServer()->getPluginManager()->registerEvents(new MyStateRestoreListener(), $this);
$this->getServer()->getPluginManager()->registerEvents(new MyStateSaveListener(), $this);
```

## Supported ScoreHud Tags

XAuth provides a set of placeholders for integration with PlaceholderAPI, enabling the display of player authentication status and other relevant information.

The values for `is_authenticated`, `is_registered`, and `is_locked` can be customized in the language files (e.g., `lang/en.yml`). By default, these return `Yes` or `No`.

The following placeholders are available:

| Tag | Description |
| --- | --- |
| `{xauth.is_authenticated}` | Returns "Yes" if the player is authenticated, otherwise "No". |
| `{xauth.is_registered}` | Returns "Yes" if the player is registered, otherwise "No". |
| `{xauth.is_locked}` | Returns "Yes" if the player's account is locked, otherwise "No". |
| `{xauth.authenticated_players}` | Returns the number of authenticated players online. |
| `{xauth.unauthenticated_players}` | Returns the number of unauthenticated players online. |

**Example Usage in ScoreHud Configuration:**

```yaml
# Example ScoreHud configuration snippet
scoreboard:
  title: "§l§bMy Server"
  lines:
    - "§f"
    - "§eAuthenticated: §a{xauth.is_authenticated}"
    - "§eRegistered: §a{xauth.is_registered}"
    - "§eOnline Players: §b{online_players}/{max_players}"
    - "§eAuth Players: §b{xauth.authenticated_players}"
    - "§eUnauth Players: §b{xauth.unauthenticated_players}"
    - "§f"
```

## PlaceholderAPI Placeholders

XAuth provides a set of placeholders for integration with PlaceholderAPI, enabling the display of player authentication status and other relevant information. The following placeholders are available:

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
