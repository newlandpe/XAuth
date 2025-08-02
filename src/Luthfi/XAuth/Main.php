<?php

declare(strict_types=1);

namespace Luthfi\XAuth;

use Luthfi\XAuth\commands\LoginCommand;
use Luthfi\XAuth\commands\RegisterCommand;
use Luthfi\XAuth\commands\ResetPasswordCommand;
use Luthfi\XAuth\commands\XAuthCommand;
use Luthfi\XAuth\database\DataProviderFactory;
use Luthfi\XAuth\database\DataProviderInterface;
use Luthfi\XAuth\listener\PlayerActionListener;
use Luthfi\XAuth\listener\PlayerSessionListener;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class Main extends PluginBase {

    private ?DataProviderInterface $dataProvider = null;
    private ?Config $configData = null;
    private ?Config $languageMessages = null;
    private ?AuthManager $authManager = null;
    private ?FormManager $formManager = null;
    private ?PasswordValidator $passwordValidator = null;

    /** @var array<string, \pocketmine\scheduler\TaskHandler> */
    private array $titleTasks = [];

    /** @var array<string, \pocketmine\scheduler\TaskHandler> */
    private array $kickTasks = [];

    /** @var array<string, bool> */
    private array $forcePasswordChange = [];

    public function onEnable(): void {
        $this->authManager = new AuthManager($this);
        $this->passwordValidator = new PasswordValidator($this);
        $this->formManager = new FormManager($this);
        $this->getServer()->getPluginManager()->registerEvents(new PlayerActionListener($this), $this);
        $this->getServer()->getPluginManager()->registerEvents(new PlayerSessionListener($this), $this);
        $this->saveDefaultConfig();
        $this->saveResource("lang/en.yml");
        $this->saveResource("lang/id.yml");
        $this->saveResource("lang/ru.yml");
        $this->saveResource("lang/uk.yml");
        $this->dataProvider = DataProviderFactory::create($this);
        $this->configData = $this->getConfig();
        $language = $this->configData->get("language", "en");
        if (!is_string($language)) {
            $language = "en";
        }
        $this->languageMessages = new Config($this->getDataFolder() . "lang/" . $language . ".yml", Config::YAML);
        $this->checkConfigVersion();
        $this->getServer()->getCommandMap()->register("register", new RegisterCommand($this));
        $this->getServer()->getCommandMap()->register("login", new LoginCommand($this));
        $this->getServer()->getCommandMap()->register("resetpassword", new ResetPasswordCommand($this));
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
    }

    private function checkConfigVersion(): void {
        $currentVersion = (float)($this->configData->get("config-version") ?? 1.0);
        if ($currentVersion < 1.0) {
            $this->getLogger()->warning((string)(((array)$this->languageMessages->get("messages"))["config_outdated_warning"] ?? "Your config.yml is outdated! Please update it to the latest version."));
        }
    }

    public function sendTitleMessage(Player $player, string $messageKey): void {
        if ((bool)($this->configData->get("enable_titles") ?? false)) {
            $titlesConfig = (array)($this->languageMessages->get("titles") ?? []);
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
        $this->cancelKickTask($player);
        $this->getDataProvider()->updatePlayerIp($player);
        $this->authManager->authenticatePlayer($player);

        $autoLoginEnabled = (bool)($this->configData->getNested('auto-login.enabled') ?? false);

        if ($autoLoginEnabled) {
            $lifetime = (int)($this->configData->getNested('auto-login.lifetime_seconds') ?? 2592000); // Default to 30 days
            $this->getDataProvider()->createSession($player->getName(), $player->getNetworkSession()->getIp(), $lifetime);
        }

        $player->sendMessage((string)(((array)$this->languageMessages->get("messages"))["login_success"] ?? ""));
        $this->sendTitleMessage($player, "login_success");
        $this->clearTitleTask($player);
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

    public function onDisable(): void {
        if ($this->dataProvider !== null) {
            $this->dataProvider->close();
        }
    }

    public function reloadConfig(): void {
        parent::reloadConfig();
        $this->configData = $this->getConfig();
        $language = (string)($this->configData->get("language", "en") ?? "en");
        $this->languageMessages = new Config($this->getDataFolder() . "lang/" . $language . ".yml", Config::YAML);
        $this->getLogger()->info("XAuth configuration and language messages reloaded.");
    }
}
