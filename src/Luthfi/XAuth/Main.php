<?php

/*
 *
 *  _          _   _     __  __  ____ _      __  __    _         _   _
 * | |   _   _| |_| |__ |  \/  |/ ___( )___  \ \/ /   / \  _   _| |_| |__
 * | |  | | | | __| '_ \| |\/| | |   |// __|  \  /   / _ \| | | | __| '_ \
 * | |__| |_| | |_| | | | |  | | |___  \__ \  /  \  / ___ \ |_| | |_| | | |
 * |_____\__,_|\__|_| |_|_|  |_|\____| |___/ /_/\_\/_/   \_\__,_|\__|_| |_|
 *
 * This program is free software: you can redistribute and/or modify
 * it under the terms of the CSSM Unlimited License v2.0.
 *
 * This license permits unlimited use, modification, and distribution
 * for any purpose while maintaining authorship attribution.
 *
 * The software is provided "as is" without warranty of any kind.
 *
 * @author LuthMC
 * @author Sergiy Chernega
 * @link https://chernega.eu.org/
 *
 *
 */

declare(strict_types=1);

namespace Luthfi\XAuth;

use Ifera\ScoreHud\ScoreHud;
use Luthfi\XAuth\commands\LoginCommand;
use Luthfi\XAuth\commands\LogoutCommand;
use Luthfi\XAuth\commands\RegisterCommand;
use Luthfi\XAuth\commands\ResetPasswordCommand;
use Luthfi\XAuth\commands\UnregisterCommand;
use Luthfi\XAuth\commands\XAuthCommand;
use Luthfi\XAuth\database\DataProviderFactory;
use Luthfi\XAuth\database\DataProviderInterface;
use Luthfi\XAuth\event\PlayerStateRestoreEvent;
use Luthfi\XAuth\event\PlayerStateSaveEvent;
use Luthfi\XAuth\expansion\XAuthExpansion;
use Luthfi\XAuth\flow\AuthenticationFlowManager;
use Luthfi\XAuth\listener\GeoIPListener;
use Luthfi\XAuth\listener\PlayerActionListener;
use Luthfi\XAuth\listener\PlayerSessionListener;
use Luthfi\XAuth\listener\ScoreHudListener;
use Luthfi\XAuth\listener\WaterdogFixListener;
use Luthfi\XAuth\service\AuthenticationService;
use Luthfi\XAuth\service\PlayerStateService;
use Luthfi\XAuth\service\PlayerVisibilityService;
use Luthfi\XAuth\service\PluginControlService;
use Luthfi\XAuth\service\RegistrationService;
use Luthfi\XAuth\service\SessionService;
use Luthfi\XAuth\service\TitleManager;
use Luthfi\XAuth\steps\AuthenticationStep;
use Luthfi\XAuth\steps\AutoLoginStep;
use Luthfi\XAuth\steps\XAuthLoginStep;
use Luthfi\XAuth\steps\XAuthRegisterStep;
use Luthfi\XAuth\tasks\CleanupSessionsTask;
use Luthfi\XAuth\tasks\KickTask;

use Luthfi\XAuth\utils\MigrationManager;
use MohamadRZ4\Placeholder\PlaceholderAPI;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;
use SOFe\AwaitGenerator\Await;
use Throwable;

class Main extends PluginBase {

    private ?DataProviderInterface $dataProvider = null;
    private ?Config $configData = null;
    private ?Config $languageMessages = null;
    private ?FormManager $formManager = null;
    private ?PasswordValidator $passwordValidator = null;
    private ?PasswordHasher $passwordHasher = null;
    private ?AuthenticationService $authenticationService = null;
    private ?RegistrationService $registrationService = null;
    private ?SessionService $sessionService = null;
    private ?PlayerStateService $playerStateService = null;
    private ?PlayerVisibilityService $playerVisibilityService = null;
    private ?PluginControlService $pluginControlService = null;
    private ?MigrationManager $migrationManager = null;
    private ?AuthenticationFlowManager $authenticationFlowManager = null;
    private ?TitleManager $titleManager = null;



    /** @var array<string, \pocketmine/scheduler/TaskHandler> */
    private array $kickTasks = [];

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

        $this->migrationManager = new MigrationManager($this);
        $this->passwordValidator = new PasswordValidator($this);
        $this->formManager = new FormManager($this);
        $this->passwordHasher = new PasswordHasher($this);
        $this->playerVisibilityService = new PlayerVisibilityService($this);
        $this->playerStateService = new PlayerStateService($this, $this->playerVisibilityService);
        $this->authenticationService = new AuthenticationService($this);
        $this->registrationService = new RegistrationService($this);
        $this->sessionService = new SessionService($this);
        $this->pluginControlService = new PluginControlService($this);
        $this->titleManager = new TitleManager($this);

        try {
            $this->dataProvider = DataProviderFactory::create($this, (array)$this->configData->get('database'));
            $this->dataProvider->initializeSync();
            $this->getLogger()->debug("DataProvider initialized (SYNC).");
            $this->onDatabaseInitialized();
        } catch (Throwable $e) {
            $this->getLogger()->error("Failed to initialize DataProvider: " . $e->getMessage());
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }
    }

    private function onDatabaseInitialized(): void {
        $this->authenticationFlowManager = new AuthenticationFlowManager($this);

        $this->authenticationFlowManager->registerAuthenticationStep(new AutoLoginStep($this));
        $this->authenticationFlowManager->registerAuthenticationStep(new XAuthLoginStep($this));
        $this->authenticationFlowManager->registerAuthenticationStep(new XAuthRegisterStep($this));

        $autoLoginEnabled = (bool)($this->configData->getNested("auto-login.enabled") ?? false);
        if ($autoLoginEnabled) {
            $cleanupInterval = (int)($this->configData->getNested("auto-login.cleanup_interval_minutes") ?? 60);
            $this->getScheduler()->scheduleRepeatingTask(new CleanupSessionsTask($this), $cleanupInterval * 20 * 60);
            $this->getLogger()->debug("Expired session cleanup task scheduled.");
        }

        $this->getServer()->getPluginManager()->registerEvents(new PlayerActionListener($this), $this);
        $this->getServer()->getPluginManager()->registerEvents(new PlayerSessionListener($this), $this);

        if ((bool)($this->configData->getNested("waterdog-fix.enabled") ?? false)) {
            if ($this->getServer()->getConfigGroup()->getPropertyBool("player.verify-xuid", true)) {
                $this->getLogger()->warning("XAuth's WaterdogPE fix may not work correctly. To prevent issues, set 'player.verify-xuid' in pocketmine.yml to 'false'");
            }
            if ($this->getServer()->getOnlineMode()) {
                $this->getLogger()->alert("XAuth's WaterdogPE fix is not compatible with online mode. Please set 'xbox-auth' in server.properties to 'off'");
            }
            $this->getServer()->getPluginManager()->registerEvents(new WaterdogFixListener($this), $this);
            $this->getLogger()->info("WaterdogPE fix enabled!");
        }

        if ((bool)(($this->configData->getNested("geoip.enabled") ?? false))) {
            $this->getServer()->getPluginManager()->registerEvents(new GeoIPListener($this), $this);
        }

        $scoreHudPlugin = $this->getServer()->getPluginManager()->getPlugin("ScoreHud");
        if ($scoreHudPlugin instanceof ScoreHud) {
            $this->getServer()->getPluginManager()->registerEvents(new ScoreHudListener($this), $this);
        }

        $placeholderAPI = $this->getServer()->getPluginManager()->getPlugin("PlaceholderAPI");
        if ($placeholderAPI instanceof PlaceholderAPI) {
            $placeholderAPI->registerExpansion(new XAuthExpansion($this));
        }

        $this->getServer()->getCommandMap()->register("register", new RegisterCommand($this));
        $this->getServer()->getCommandMap()->register("login", new LoginCommand($this));
        $this->getServer()->getCommandMap()->register("resetpassword", new ResetPasswordCommand($this));
        $this->getServer()->getCommandMap()->register("logout", new LogoutCommand($this));
        $this->getServer()->getCommandMap()->register("unregister", new UnregisterCommand($this));
        $this->getServer()->getCommandMap()->register("xauth", new XAuthCommand($this));
    }

    private function checkConfigVersion(): void {
        $currentVersion = (float)$this->configData->get("config-version", 1.0);
        if ($currentVersion < 1.0) {
            $this->getLogger()->warning((string)(((array)$this->getCustomMessages()->get("messages"))["config_outdated_warning"] ?? "Your config.yml is outdated! Please update it to the latest version."));
        }
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

    public function getAuthenticationService(): ?AuthenticationService {
        return $this->authenticationService;
    }

    public function getRegistrationService(): ?RegistrationService {
        return $this->registrationService;
    }

    public function getSessionService(): ?SessionService {
        return $this->sessionService;
    }

    public function getPlayerStateService(): ?PlayerStateService {
        return $this->playerStateService;
    }

    public function getPlayerVisibilityService(): ?PlayerVisibilityService {
        return $this->playerVisibilityService;
    }

    public function getPluginControlService(): ?PluginControlService {
        return $this->pluginControlService;
    }

    public function getMigrationManager(): ?MigrationManager {
        return $this->migrationManager;
    }

    public function getAuthenticationFlowManager(): ?AuthenticationFlowManager {
        return $this->authenticationFlowManager;
    }

    public function getTitleManager(): ?TitleManager {
        return $this->titleManager;
    }

    /**
     * Registers an authentication step with XAuth.
     *
     * @param AuthenticationStep $step The authentication step object to register.
     */
    public function registerAuthenticationStep(AuthenticationStep $step): void {
        $this->authenticationFlowManager->registerAuthenticationStep($step);
    }

    /**
     * Starts the authentication flow for a player, or advances to a specific step.
     *
     * @param Player $player
     * @param string|null $startStepId If provided, starts from this step. Otherwise, starts from the beginning.
     */
    public function startAuthenticationStep(Player $player, ?string $startStepId = null): void {
        $this->authenticationFlowManager->startAuthenticationFlow($player, $startStepId);
    }

    /**
     * Marks an authentication step as complete for a player and advances to the next step.
     *
     * @param Player $player
     * @param string $completedStepId The ID of the step that was just completed.
     */
    public function completeAuthenticationStep(Player $player, string $completedStepId): void {
        $this->getLogger()->warning("completeAuthenticationStep is deprecated. Use AuthenticationFlowManager::completeStep or skipStep.");
        $this->authenticationFlowManager->completeStep($player, $completedStepId);
    }

    /**
     * Returns the completion status of a specific authentication step for a player.
     *
     * @param Player $player
     * @param string $stepId The ID of the step to check.
     * @return string|null 'completed', 'skipped', or null if the step has not been reached or recorded.
     */
    public function getPlayerAuthenticationStepStatus(Player $player, string $stepId): ?string {
        return $this->authenticationFlowManager->getPlayerAuthenticationStepStatus($player, $stepId);
    }

    /**
     * @return array<string, AuthenticationStep>
     */
    public function getAuthenticationSteps(): array {
        return $this->authenticationFlowManager->getAuthenticationSteps();
    }

    public function getOrderedAuthenticationSteps(): array {
        return $this->authenticationFlowManager->getOrderedAuthenticationSteps();
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
            $this->kickTasks[$player->getName()] = $this->getScheduler()->scheduleDelayedTask(new KickTask($this, $player), $loginTimeout * 20);
        }
    }



    public function onDisable(): void {
        if ($this->dataProvider !== null) {
            $this->dataProvider->close();
        }
    }
}
