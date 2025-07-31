<?php

declare(strict_types=1);

namespace Luthfi\XAuth;

use Luthfi\XAuth\commands\LoginCommand;
use Luthfi\XAuth\commands\RegisterCommand;
use Luthfi\XAuth\commands\ResetPasswordCommand;
use Luthfi\XAuth\commands\XAuthCommand;
use Luthfi\XAuth\database\DataProviderFactory;
use Luthfi\XAuth\database\DataProviderInterface;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener {

    use Luthfi\XAuth\commands\LoginCommand;
use Luthfi\XAuth\commands\RegisterCommand;
use Luthfi\XAuth\commands\ResetPasswordCommand;
use Luthfi\XAuth\commands\XAuthCommand;
use Luthfi\XAuth\database\DataProviderFactory;
use Luthfi\XAuth\database\DataProviderInterface;
use Luthfi\XAuth\event\PlayerLoginEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener {

    private ?DataProviderInterface $dataProvider = null;
    private ?Config $configData = null;
    private ?Config $languageMessages = null;
    private ?AuthManager $authManager = null;
    private ?FormManager $formManager = null;
    private ?PasswordValidator $passwordValidator = null;

    public function onEnable(): void {
        $this->authManager = new AuthManager();
        $this->passwordValidator = new PasswordValidator($this);
        $this->formManager = new FormManager($this);
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this, $this->authManager), $this);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
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
    }

    public function onJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();

        if ($this->formManager !== null) {
            $playerData = $this->dataProvider->getPlayer($player);
            if ($playerData !== null) {
                $this->formManager->sendLoginForm($player);
            } else {
                $this->formManager->sendRegisterForm($player);
            }
            return;
        }

        $playerData = $this->dataProvider->getPlayer($player);

        if ($playerData !== null) {
            $ip = (string)($playerData["ip"] ?? "");
            $currentIp = $player->getNetworkSession()->getIp();

            if ((bool)($this->configData->get("auto-login") ?? false) && $ip === $currentIp) {
                $this->authManager->authenticatePlayer($player);
                (new PlayerLoginEvent($player))->call();
                $message = (string)($this->languageMessages->get("messages")["login_success"] ?? "");
                $player->sendMessage($message);
                $this->sendTitleMessage($player, "login_success");
            } else {
                $message = (string)($this->languageMessages->get("messages")["login_prompt"] ?? "");
                $player->sendMessage($message);
                $this->sendTitleMessage($player, "login_prompt");
            }
        } else {
            $message = (string)($this->languageMessages->get("messages")["register_prompt"] ?? "");
            $player->sendMessage($message);
            $this->sendTitleMessage($player, "register_prompt");
        }
    }

    private function checkConfigVersion(): void {
        $currentVersion = (float)($this->configData->get("config-version", 1.0) ?? 1.0);
        if ($currentVersion < 1.0) {
            $this->getLogger()->warning("Your config.yml is outdated! Please update it to the latest version.");
        }
    }

    private function sendTitleMessage(Player $player, string $messageKey): void {
        if ((bool)($this->configData->get("enable_titles") ?? false)) {
            $titlesConfig = $this->languageMessages->get("titles");
            if (is_array($titlesConfig) && isset($titlesConfig[$messageKey])) {
                $titleConfig = $titlesConfig[$messageKey];
                $title = (string)($titleConfig["title"] ?? "");
                $subtitle = (string)($titleConfig["subtitle"] ?? "");
                $interval = (int)(($titleConfig["interval"] ?? 0) * 20);

                $this->getScheduler()->scheduleRepeatingTask(new class($player, $title, $subtitle) extends \pocketmine\scheduler\Task {
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
