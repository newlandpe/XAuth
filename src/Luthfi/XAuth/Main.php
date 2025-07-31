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

    private DataProviderInterface $dataProvider;
    private Config $configData;
    private Config $languageMessages;
    private AuthManager $authManager;

    public function onEnable(): void {
        $this->authManager = new AuthManager();
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
            $ip = $playerData["ip"];
            $currentIp = $player->getNetworkSession()->getIp();

            if ($this->configData->get("auto-login") && $ip === $currentIp) {
                $this->authManager->authenticatePlayer($player);
                (new PlayerLoginEvent($player))->call();
                $player->sendMessage($this->languageMessages->get("messages")["login_success"]);
                $this->sendTitleMessage($player, "login_success");
            } else {
                $player->sendMessage($this->languageMessages->get("messages")["login_prompt"]);
                $this->sendTitleMessage($player, "login_prompt");
            }
        } else {
            $player->sendMessage($this->languageMessages->get("messages")["register_prompt"]);
            $this->sendTitleMessage($player, "register_prompt");
        }
    }

    private function checkConfigVersion(): void {
        $currentVersion = $this->configData->get("config-version", 1.0);
        if ($currentVersion < 1.0) {
            $this->getLogger()->warning("Your config.yml is outdated! Please update it to the latest version.");
        }
    }

    private function sendTitleMessage(Player $player, string $messageKey): void {
        if ($this->configData->get("enable_titles")) {
            $titleConfig = $this->languageMessages->get("titles")[$messageKey];
            $title = $titleConfig["title"];
            $subtitle = $titleConfig["subtitle"];
            $interval = $titleConfig["interval"] * 20;

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

    public function getAuthManager(): AuthManager {
        return $this->authManager;
    }

    public function getDataProvider(): DataProviderInterface {
        return $this->dataProvider;
    }

    public function getCustomMessages(): Config {
        return $this->languageMessages;
    }

    public function getPasswordValidator(): PasswordValidator {
        return $this->passwordValidator;
    }

    public function getFormManager(): ?FormManager {
        return $this->formManager;
    }

    public function onDisable(): void {
        $this->dataProvider->close();
    }

    public function reloadConfig(): void {
        parent::reloadConfig();
        $this->configData = $this->getConfig();
        $language = $this->configData->get("language", "en");
        $this->languageMessages = new Config($this->getDataFolder() . "lang/" . $language . ".yml", Config::YAML);
        $this->getLogger()->info("XAuth configuration and language messages reloaded.");
    }
}
