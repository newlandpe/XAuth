<?php

declare(strict_types=1);

namespace Luthfi\XAuth;

use Luthfi\XAuth\commands\LoginCommand;
use Luthfi\XAuth\commands\LogoutCommand;
use Luthfi\XAuth\commands\RegisterCommand;
use Luthfi\XAuth\commands\ResetPasswordCommand;
use Luthfi\XAuth\commands\XAuthCommand;
use Luthfi\XAuth\database\DataProviderFactory;
use Luthfi\XAuth\database\DataProviderInterface;
use Luthfi\XAuth\event\PlayerAuthenticateEvent;
use Luthfi\XAuth\event\PlayerDeauthenticateEvent;
use Luthfi\XAuth\event\PlayerStateRestoreEvent;
use Luthfi\XAuth\event\PlayerStateSaveEvent;
use Luthfi\XAuth\expansion\XAuthExpansion;
use Luthfi\XAuth\listener\GeoIPListener;
use Luthfi\XAuth\listener\PlayerActionListener;
use Luthfi\XAuth\listener\PlayerSessionListener;
use Luthfi\XAuth\utils\MigrationManager;
use MohamadRZ4\Placeholder\PlaceholderAPI;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\network\mcpe\protocol\types\skin\SkinImage;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\world\Position;

class Main extends PluginBase implements Listener {

    private ?DataProviderInterface $dataProvider = null;
    private ?Config $configData = null;
    private ?Config $languageMessages = null;
    private ?AuthManager $authManager = null;
    private ?FormManager $formManager = null;
    private ?PasswordValidator $passwordValidator = null;
    private ?PasswordHasher $passwordHasher = null;

    /** @var array<string, \pocketmine\scheduler\TaskHandler> */
    private array $titleTasks = [];

    /** @var array<string, \pocketmine\scheduler\TaskHandler> */
    private array $kickTasks = [];

    /** @var array<string, bool> */
    private array $forcePasswordChange = [];

    /** @var array<string, PlayerState> */
    private array $protectedStates = [];

    /** @var array<string, string> */
    public array $deviceIds = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->saveResource("lang/en.yml");
        $this->saveResource("lang/id.yml");
        $this->saveResource("lang/ru.yml");
        $this->saveResource("lang/uk.yml");

        $this->configData = $this->getConfig();
        $language = (string)$this->configData->get("language", "en");
        $this->languageMessages = new Config($this->getDataFolder() . "lang/" . $language . ".yml", Config::YAML);
        $this->checkConfigVersion();

        $migrationManager = new MigrationManager($this);
        $migrationManager->prepareMigration();

        $this->dataProvider = DataProviderFactory::create($this, $this->configData->get('database'));
        $this->authManager = new AuthManager($this);
        $this->passwordValidator = new PasswordValidator($this);
        $this->formManager = new FormManager($this);
        $this->passwordHasher = new PasswordHasher($this);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getPluginManager()->registerEvents(new PlayerActionListener($this), $this);
        $this->getServer()->getPluginManager()->registerEvents(new PlayerSessionListener($this), $this);

        if ((bool)(($this->configData->getNested("geoip.enabled") ?? false))) {
            $this->getServer()->getPluginManager()->registerEvents(new GeoIPListener($this), $this);
        }

        $this->getServer()->getCommandMap()->register("register", new RegisterCommand($this));
        $this->getServer()->getCommandMap()->register("login", new LoginCommand($this));
        $this->getServer()->getCommandMap()->register("resetpassword", new ResetPasswordCommand($this));
        $this->getServer()->getCommandMap()->register("logout", new LogoutCommand($this));
        $this->getServer()->getCommandMap()->register("xauth", new XAuthCommand($this));

        $autoLoginEnabled = (bool)($this->configData->getNested("auto-login.enabled") ?? false);

        if ($autoLoginEnabled) {
            $cleanupInterval = (int)($this->configData->getNested("auto-login.cleanup_interval_minutes") ?? 60);
            $this->getScheduler()->scheduleRepeatingTask(new class($this) extends \pocketmine\scheduler\Task {
                private Main $plugin;

                public function __construct(Main $plugin) {
                    $this->plugin = $plugin;
                }

                public function onRun(): void {
                    $this->plugin->getDataProvider()->cleanupExpiredSessions();
                    $this->plugin->getLogger()->debug("Cleaned up expired sessions.");
                }
            }, $cleanupInterval * 20 * 60); // Convert minutes to ticks (20 ticks per second)
        }

        $placeholderAPI = $this->getServer()->getPluginManager()->getPlugin("PlaceholderAPI");
        if ($placeholderAPI instanceof PlaceholderAPI) {
            $placeholderAPI->registerExpansion(new XAuthExpansion($this));
        }

        $migrationManager->runMigration();
    }

    private function checkConfigVersion(): void {
        $currentVersion = (float)$this->configData->get("config-version", 1.0);
        if ($currentVersion < 1.0) {
            $this->getLogger()->warning((string)(((array)$this->getCustomMessages()->get("messages"))["config_outdated_warning"] ?? "Your config.yml is outdated! Please update it to the latest version."));
        }
    }

    public function sendTitleMessage(Player $player, string $messageKey): void {
        $this->clearTitleTask($player);

        if ((bool)$this->configData->get("enable_titles", false)) {
            $titlesConfig = (array)$this->getCustomMessages()->get("titles", []);
            if (isset($titlesConfig[$messageKey])) {
                $titleConfig = $titlesConfig[$messageKey];
                $title = (string)($titleConfig["title"] ?? "");
                $subtitle = (string)($titleConfig["subtitle"] ?? "");
                $interval = (int)(($titleConfig["interval"] ?? 0) * 20);

                $handler = $this->getScheduler()->scheduleRepeatingTask(new class($player, $title, $subtitle) extends \pocketmine\scheduler\Task {
                    private Player $player;
                    private string $title;
                    private string $subtitle;

                    public function __construct(Player $player, string $title, string $subtitle) {
                        $this->player = $player;
                        $this->title = $title;
                        $this->subtitle = $subtitle;
                    }

                    public function onRun(): void {
                        if ($this->player->isOnline()) {
                            $this->player->sendTitle($this->title, $this->subtitle);
                        }
                    }
                }, $interval);
                $this->titleTasks[$player->getName()] = $handler;
            }
        }
    }

    public function getAuthManager(): ?AuthManager {
        return $this->authManager;
    }

    public function getDataProvider(): ?DataProviderInterface {
        return $this->dataProvider;
    }

    public function getCustomMessages(): ?Config {
        return $this->languageMessages;
    }

    public function getPasswordValidator(): ?PasswordValidator {
        return $this->passwordValidator;
    }

    public function getPasswordHasher(): ?PasswordHasher {
        return $this->passwordHasher;
    }

    public function getFormManager(): ?FormManager {
        return $this->formManager;
    }

    public function startForcePasswordChange(Player $player): void {
        $this->forcePasswordChange[strtolower($player->getName())] = true;
        $this->formManager->sendForceChangePasswordForm($player);
    }

    public function stopForcePasswordChange(Player $player): void {
        unset($this->forcePasswordChange[strtolower($player->getName())]);
    }

    public function isForcingPasswordChange(Player $player): bool {
        return isset($this->forcePasswordChange[strtolower($player->getName())]);
    }

    public function forceLogin(Player $player): void {
        (new PlayerAuthenticateEvent($player))->call();
    }

    public function clearTitleTask(Player $player): void {
        $name = $player->getName();
        if (isset($this->titleTasks[$name])) {
            $this->titleTasks[$name]->cancel();
            unset($this->titleTasks[$name]);
        }
    }

    public function cancelKickTask(Player $player): void {
        $name = $player->getName();
        if (isset($this->kickTasks[$name])) {
            $this->kickTasks[$name]->cancel();
            unset($this->kickTasks[$name]);
        }
    }

    public function scheduleKickTask(Player $player): void {
        $loginTimeout = (int)($this->configData->getNested("session.login-timeout") ?? 30);
        if ($loginTimeout > 0) {
            $this->kickTasks[$player->getName()] = $this->getScheduler()->scheduleDelayedTask(new class($this, $player) extends \pocketmine\scheduler\Task {
                private Main $plugin;
                private Player $player;

                public function __construct(Main $plugin, Player $player) {
                    $this->plugin = $plugin;
                    $this->player = $player;
                }

                public function onRun(): void {
                    if ($this->player->isOnline() && !$this->plugin->getAuthManager()->isPlayerAuthenticated($this->player)) {
                        $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["login_timeout"] ?? "§cYou took too long to log in.");
                        $this->player->kick($message);
                    }
                }
            }, $loginTimeout * 20); // Convert seconds to ticks
        }
    }

    public function scheduleClearTitleTask(Player $player, int $delayTicks): void {
        $this->getScheduler()->scheduleDelayedTask(new class($this, $player) extends \pocketmine\scheduler\Task {
            private Main $plugin;
            private Player $player;

            public function __construct(Main $plugin, Player $player) {
                $this->plugin = $plugin;
                $this->player = $player;
            }

            public function onRun(): void {
                if ($this->player->isOnline()) {
                    $this->plugin->clearTitleTask($this->player);
                }
            }
        }, $delayTicks);
    }

    public function onDisable(): void {
        if ($this->dataProvider !== null) {
            $this->dataProvider->close();
        }
    }

    public function reloadConfig(): void {
        $oldData = [];
        if ($this->configData !== null) {
            $oldData = [
                'in_world_visibility' => (array)$this->configData->get('in_world_visibility', []),
                'player_list_visibility' => (array)$this->configData->get('player_list_visibility', []),
                'apply_blindness' => (bool)$this->configData->get('apply_blindness', true)
            ];
        }

        parent::reloadConfig();
        $this->configData = $this->getConfig();
        $language = (string)(($this->configData->get("language", "en") ?? "en"));
        $this->languageMessages = new Config($this->getDataFolder() . "lang/" . $language . ".yml", Config::YAML);

        if (!empty($oldData)) {
            $newData = [
                'in_world_visibility' => (array)$this->configData->get('in_world_visibility', []),
                'player_list_visibility' => (array)$this->configData->get('player_list_visibility', []),
                'apply_blindness' => (bool)$this->configData->get('apply_blindness', true)
            ];

            if ($oldData !== $newData) {
                foreach ($this->getServer()->getOnlinePlayers() as $player) {
                    if (!$this->getAuthManager()->isPlayerAuthenticated($player)) {
                        $this->updatePlayerVisibility($player);
                    }
                }
            }
        }

        $this->getLogger()->info("XAuth configuration and language messages reloaded.");
    }

    public function savePlayerState(Player $player): void {
        $state = new PlayerState($player, $this);
        $this->protectedStates[strtolower($player->getName())] = $state;
        (new PlayerStateSaveEvent($player, $state))->call();
    }

    public function restorePlayerState(Player $player): void {
        $name = strtolower($player->getName());
        if (isset($this->protectedStates[$name])) {
            $event = new PlayerStateRestoreEvent($player, $this->protectedStates[$name]);
            $event->call();
            if ($event->isCancelled()) {
                unset($this->protectedStates[$name]);
                return;
            }
            $this->protectedStates[$name]->restore($player);
            unset($this->protectedStates[$name]);
        }
    }

    public function removePlayerState(Player $player): void {
        unset($this->protectedStates[strtolower($player->getName())]);
    }

    public function hideFromPlayerList(Player $player, ?array $recipients = null): void {
        $recipients ??= $this->getServer()->getOnlinePlayers();
        if(empty($recipients)) return;

        NetworkBroadcastUtils::broadcastPackets(
            $recipients,
            [PlayerListPacket::remove([PlayerListEntry::createRemovalEntry($player->getUniqueId())])]
        );
    }

    public function showInPlayerList(Player $player, ?array $recipients = null): void {
        $recipients ??= $this->getServer()->getOnlinePlayers();
        if(empty($recipients)) return;

        $networkSession = $player->getNetworkSession();
        $typeConverter = $networkSession->getTypeConverter();
        $skinData = $typeConverter->getSkinAdapter()->toSkinData($player->getSkin());

        NetworkBroadcastUtils::broadcastPackets(
            $recipients,
            [PlayerListPacket::add([PlayerListEntry::createAdditionEntry(
                $player->getUniqueId(),
                $player->getId(),
                $player->getName(),
                $skinData
            )])]
        );
    }

    public function protectPlayer(Player $player): void {
        $this->savePlayerState($player);

        $config = $this->getConfig();
        $protectionConfig = (array)$config->get('protection');

        if ((bool)(($protectionConfig['force_survival'] ?? true))) {
            $player->setGamemode(GameMode::SURVIVAL());
        }

        $teleportConfig = (array)($protectionConfig['teleport'] ?? []);
        if ((bool)($teleportConfig['enabled'] ?? false)) {
            $worldName = (string)(($teleportConfig['world'] ?? $this->getServer()->getWorldManager()->getDefaultWorld()->getFolderName()));
            if ($world = $this->getServer()->getWorldManager()->getWorldByName($worldName)) {
                $coords = (array)($teleportConfig['coords'] ?? []);
                $x = (float)(($coords['x'] ?? $world->getSafeSpawn()->getX()));
                $y = (float)(($coords['y'] ?? $world->getSafeSpawn()->getY()));
                $z = (float)(($coords['z'] ?? $world->getSafeSpawn()->getZ()));
                $player->teleport(new Position($x, $y, $z, $world));
            }
        }

        if ((bool)(($protectionConfig['protect_player_state'] ?? true))) {
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->getOffHandInventory()->clearAll();
            $player->getEffects()->clear();
            $player->setHealth($player->getMaxHealth());
            $player->getHungerManager()->setFood($player->getHungerManager()->getMaxFood());
            $player->getXpManager()->setXpLevel(0);
            $player->getXpManager()->setXpProgress(0.0);
        }

        $this->updatePlayerVisibility($player);
    }

    public function updatePlayerVisibility(Player $player): void {
        $config = $this->getConfig();
        $inWorldConfig = (array)$config->get('in_world_visibility', []);
        $playerListConfig = (array)$config->get('player_list_visibility', []);
        $blindnessEnabled = (bool)$config->get('apply_blindness', true);

        // In-world visibility
        $inWorldMode = strtolower((string)(($inWorldConfig['mode'] ?? 'packets')));
        if ($inWorldMode === 'packets') {
            foreach ($this->getServer()->getOnlinePlayers() as $onlinePlayer) {
                if ($player === $onlinePlayer) continue;
                $onlinePlayer->hidePlayer($player);
                if (!$this->authManager->isPlayerAuthenticated($onlinePlayer)) {
                    $player->hidePlayer($onlinePlayer);
                }
            }
        } elseif ($inWorldMode === 'effect') {
            $player->getEffects()->add(new EffectInstance(VanillaEffects::INVISIBILITY(), 2147483647, 0, false));
        } else {
            $player->getEffects()->remove(VanillaEffects::INVISIBILITY());
            foreach ($this->getServer()->getOnlinePlayers() as $onlinePlayer) {
                if ($player !== $onlinePlayer) {
                    $onlinePlayer->showPlayer($player);
                }
            }
        }

        if ((bool)(($inWorldConfig['hide_others_from_unauthenticated'] ?? true))) {
            foreach ($this->getServer()->getOnlinePlayers() as $onlinePlayer) {
                if ($onlinePlayer !== $player) {
                    $player->hidePlayer($onlinePlayer);
                }
            }
        } else {
            foreach ($this->getServer()->getOnlinePlayers() as $onlinePlayer) {
                if ($onlinePlayer !== $player) {
                    $player->showPlayer($onlinePlayer);
                }
            }
        }

        // Player list visibility
        foreach ($this->getServer()->getOnlinePlayers() as $onlinePlayer) {
            if ($player === $onlinePlayer) continue;

            if ((bool)($playerListConfig['hide'] ?? true)) {
                $this->hideFromPlayerList($player, [$onlinePlayer]);
            } else {
                $this->showInPlayerList($player, [$onlinePlayer]);
            }

            if ((bool)($playerListConfig['hide_others_from_unauthenticated'] ?? false)) {
                $this->hideFromPlayerList($onlinePlayer, [$player]);
            } else {
                $this->showInPlayerList($onlinePlayer, [$player]);
            }
        }

        // Blindness effect
        if ($blindnessEnabled) {
            $player->getEffects()->add(new EffectInstance(VanillaEffects::BLINDNESS(), 2147483647, 0, false));
        } else {
            $player->getEffects()->remove(VanillaEffects::BLINDNESS());
        }
    }

    public function onPacketSend(DataPacketSendEvent $event): void {
        $playerListConfig = (array)$this->configData->get('player_list_visibility', []);
        if ((bool)($playerListConfig['hide'] ?? true) === false) {
            return;
        }

        $packets = $event->getPackets();
        $modifiedPackets = [];
        $hasChanges = false;

        foreach ($packets as $packet) {
            if (!$packet instanceof PlayerListPacket) {
                $modifiedPackets[] = $packet;
                continue;
            }

            if ($packet->type !== PlayerListPacket::TYPE_ADD) {
                $modifiedPackets[] = $packet;
                continue;
            }

            $modifiedEntries = [];

            foreach ($packet->entries as $entry) {
                $playerName = $entry->username;
                $player = $this->getServer()->getPlayerExact($playerName);

                if ($player === null || !$this->getAuthManager()->isPlayerAuthenticated($player)) {
                    $hasChanges = true;
                    continue;
                }

                $modifiedEntries[] = $entry;
            }

            if (empty($modifiedEntries)) {
                $hasChanges = true;
                continue;
            }

            if (count($modifiedEntries) !== count($packet->entries)) {
                $packet->entries = $modifiedEntries;
                $hasChanges = true;
            }

            $modifiedPackets[] = $packet;
        }

        if ($hasChanges) {
            $event->setPackets($modifiedPackets);
        }
    }

    /**
     * @param PlayerAuthenticateEvent $event
     * @priority HIGHEST
     */
    public function onPlayerAuthenticate(PlayerAuthenticateEvent $event): void {
        $player = $event->getPlayer();

        $this->cancelKickTask($player);
        $this->getDataProvider()->updatePlayerIp($player);
        $this->authManager->authenticatePlayer($player);

        $autoLoginConfig = (array)$this->configData->get('auto-login', []);
        $autoLoginEnabled = (bool)($autoLoginConfig['enabled'] ?? false);
        $securityLevel = (int)($autoLoginConfig['security_level'] ?? 1);

        $lowerPlayerName = strtolower($player->getName());
        $deviceId = $this->deviceIds[$lowerPlayerName] ?? null;
        unset($this->deviceIds[$lowerPlayerName]);

        if ($autoLoginEnabled && $deviceId !== null) {
            $sessions = $this->getDataProvider()->getSessionsByPlayer($player->getName());
            $ip = $player->getNetworkSession()->getIp();
            $lifetime = (int)($autoLoginConfig['lifetime_seconds'] ?? 2592000);
            $refreshSession = (bool)($autoLoginConfig['refresh_session_on_login'] ?? true);

            $existingSessionId = null;
            foreach ($sessions as $sessionId => $sessionData) {
                $ipMatch = ($sessionData['ip_address'] ?? '') === $ip;
                $deviceIdMatch = ($sessionData['device_id'] ?? null) === $deviceId;

                if ($securityLevel === 1 && $ipMatch && $deviceIdMatch) {
                    $existingSessionId = $sessionId;
                    break;
                }
                if ($securityLevel === 0 && $ipMatch) {
                    $existingSessionId = $sessionId;
                    break;
                }
            }

            if ($existingSessionId !== null) {
                if ($refreshSession) {
                    $this->getDataProvider()->refreshSession($existingSessionId, $lifetime);
                }
            } else {
                $this->getDataProvider()->createSession($player->getName(), $ip, $deviceId, $lifetime);
            }
        }

        $message = (string)(((array)$this->getCustomMessages()->get("messages"))["login_success"] ?? "");
        $player->sendMessage($message);
        $this->sendTitleMessage($player, "login_success");
        $this->scheduleClearTitleTask($player, 2 * 20);
        $this->restorePlayerState($player);

        $playerListConfig = (array)$this->configData->get('player_list_visibility', []);
        $inWorldConfig = (array)$this->configData->get('in_world_visibility', []);

        // Handles player-list visibility
        foreach ($this->getServer()->getOnlinePlayers() as $onlinePlayer) {
            if ($player === $onlinePlayer) continue;

            if ($this->getAuthManager()->isPlayerAuthenticated($onlinePlayer)) {
                // Handle authenticated recipients
                if ((bool)($playerListConfig['hide'] ?? true)) {
                    $this->showInPlayerList($player, [$onlinePlayer]);
                }
                if (strtolower((string)($inWorldConfig['mode'] ?? 'packets')) === 'packets') {
                    $onlinePlayer->showPlayer($player);
                }
                if ((bool)($playerListConfig['hide_others_from_unauthenticated'] ?? false)) {
                    $this->showInPlayerList($onlinePlayer, [$player]);
                }
                if ((bool)($inWorldConfig['hide_others_from_unauthenticated'] ?? true)) {
                    $player->showPlayer($onlinePlayer);
                }
            } else {
                // Handle unauthenticated recipients
                if ((bool)($playerListConfig['hide_others_from_unauthenticated'] ?? false) === false) {
                    $this->showInPlayerList($player, [$onlinePlayer]);
                }
                if ((bool)($inWorldConfig['hide_others_from_unauthenticated'] ?? true) === false) {
                    $onlinePlayer->showPlayer($player);
                }
            }
        }

        $maxSessions = (int)($this->configData->getNested("auto-login.max_sessions_per_player") ?? 5);
        if ($maxSessions > 0) {
            $sessions = $this->getDataProvider()->getSessionsByPlayer($player->getName());
            if (count($sessions) > $maxSessions) {
                uasort($sessions, function($a, $b) {
                    return (($a['login_time'] ?? 0) <=> ($b['login_time'] ?? 0));
                });

                $sessionsToDeleteCount = count($sessions) - $maxSessions;
                $sessionsToDelete = array_slice(array_keys($sessions), 0, $sessionsToDeleteCount);

                foreach ($sessionsToDelete as $sessionId) {
                    $this->getDataProvider()->deleteSession($sessionId);
                }
            }
        }
    }

    /**
     * @param PlayerDeauthenticateEvent $event
     * @priority HIGHEST
     */
    public function onPlayerDeauthenticate(PlayerDeauthenticateEvent $event): void {
        $player = $event->getPlayer();

        $this->cancelKickTask($player);
        $this->clearTitleTask($player);

        $this->getAuthManager()->deauthenticatePlayer($player);

        if (!$event->isQuit()) {
            $this->protectPlayer($player);
            $this->scheduleKickTask($player);

            $playerData = $this->getDataProvider()->getPlayer($player);
            if ($playerData !== null) {
                $formsEnabled = (bool)($this->getConfig()->getNested("forms.enabled") ?? true);
                $message = (string)(((array)$this->getCustomMessages()->get("messages"))["login_prompt"] ?? "");
                $player->sendMessage($message);
                if ($formsEnabled) {
                    $this->getFormManager()->sendLoginForm($player);
                } else {
                    $this->sendTitleMessage($player, "login_prompt");
                }
            } else {
                $formsEnabled = (bool)($this->getConfig()->getNested("forms.enabled") ?? true);
                $message = (string)(((array)$this->getCustomMessages()->get("messages"))["register_prompt"] ?? "");
                $player->sendMessage($message);
                if ($formsEnabled) {
                    $this->getFormManager()->sendRegisterForm($player);
                } else {
                    $this->sendTitleMessage($player, "register_prompt");
                }
            }
        } else {
            $this->restorePlayerState($player);
        }
    }
}
