<?php

declare(strict_types=1);

namespace Luthfi\XAuth;

use jojoe77777\FormAPI\CustomForm;
use Luthfi\XAuth\event\PlayerChangePasswordEvent;
use Luthfi\XAuth\event\PlayerLoginEvent;
use Luthfi\XAuth\event\PlayerRegisterEvent;
use pocketmine\player\Player;

class FormManager {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function sendLoginForm(Player $player, ?string $errorMessage = null): void {
        $messages = (array)$this->plugin->getCustomMessages()->get("messages");
        $formsConfig = (array)$this->plugin->getCustomMessages()->get("forms");
        $loginFormConfig = (array)($formsConfig["login"] ?? []);

        $bruteforceConfig = (array)$this->plugin->getConfig()->get('bruteforce_protection');
        $enabled = (bool)($bruteforceConfig['enabled'] ?? false);
        $maxAttempts = (int)($bruteforceConfig['max_attempts'] ?? 0);
        $blockTimeMinutes = (int)($bruteforceConfig['block_time_minutes'] ?? 0);

        if ($errorMessage === null && $enabled && $this->plugin->getAuthManager()->isPlayerBlocked($player, $maxAttempts, $blockTimeMinutes)) {
            $remainingMinutes = $this->plugin->getAuthManager()->getRemainingBlockTime($player, $blockTimeMinutes);
            $message = (string)($messages["login_attempts_exceeded"] ?? "§cYou have exceeded the number of login attempts. Please try again in {minutes} minutes.");
            $message = str_replace('{minutes}', (string)$remainingMinutes, $message);
            $kickOnBlock = (bool)($bruteforceConfig['kick_on_block'] ?? true);
            if ($kickOnBlock) {
                $player->kick($message);
            } else {
                $player->sendMessage($message);
            }
            return;
        }

        $form = new CustomForm(function (Player $player, ?array $data) use ($messages): void {
            if ($data === null) {
                if ((bool)($this->plugin->getConfig()->getNested("forms.kick-on-close") ?? false)) {
                    $player->kick((string)($messages["login_form_closed"] ?? "You have closed the login form."));
                } else {
                    $this->sendLoginForm($player);
                }
                return;
            }

            $password = (string)($data["password"] ?? '');
            $playerData = $this->plugin->getDataProvider()->getPlayer($player);

            if ($playerData === null) {
                $this->sendLoginForm($player, (string)($messages["not_registered"] ?? "§cYou are not registered."));
                return;
            }

            if ($this->plugin->getDataProvider()->isPlayerLocked($player->getName())) {
                $this->sendLoginForm($player, (string)($messages["account_locked_by_admin"] ?? "§cYour account has been locked by an administrator."));
                return;
            }

            $storedHash = (string)($playerData["password"] ?? '');

            if (!password_verify($password, $storedHash)) {
                $this->plugin->getAuthManager()->incrementLoginAttempts($player);
                $this->sendLoginForm($player, (string)($messages["incorrect_password"] ?? "§cIncorrect password. Please try again."));
                return;
            }

            if (password_needs_rehash($storedHash, PASSWORD_BCRYPT)) {
                $newHashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $this->plugin->getDataProvider()->changePassword($player, $newHashedPassword);
            }

            $this->plugin->cancelKickTask($player);
            $this->plugin->getAuthManager()->authenticatePlayer($player);

            $event = new PlayerLoginEvent($player);
            $event->call();

            if ($event->isAuthenticationDelayed() || $event->isCancelled()) {
                $this->plugin->getAuthManager()->deauthenticatePlayer($player);
                return;
            }

            $autoLoginEnabled = (bool)($this->plugin->getConfig()->getNested('auto-login.enabled') ?? false);

            if ($autoLoginEnabled) {
                $lifetime = (int)($this->plugin->getConfig()->getNested('auto-login.lifetime_seconds') ?? 2592000); // Default to 30 days
                $this->plugin->getDataProvider()->createSession($player->getName(), $player->getNetworkSession()->getIp(), $lifetime);
            }

            $this->plugin->getDataProvider()->updatePlayerIp($player);
            $player->sendMessage((string)($messages["login_success"] ?? "§aYou have successfully logged in!"));
        });

        $form->setTitle((string)($loginFormConfig["title"] ?? "Login"));
        $content = (string)($loginFormConfig["content"] ?? "");
        if (!empty($content)) {
            $form->addLabel($content);
        }
        if ($errorMessage !== null) {
            $form->addLabel($errorMessage);
        }
        $form->addInput((string)($loginFormConfig["password_label"] ?? "Password"), (string)($loginFormConfig["password_placeholder"] ?? ""), null, "password");
        $player->sendForm($form);
    }

    public function sendRegisterForm(Player $player, ?string $errorMessage = null): void {
        $messages = (array)$this->plugin->getCustomMessages()->get("messages");
        $formsConfig = (array)$this->plugin->getCustomMessages()->get("forms");
        $registerFormConfig = (array)($formsConfig["register"] ?? []);

        $form = new CustomForm(function (Player $player, ?array $data) use ($messages): void {
            if ($data === null) {
                if ((bool)($this->plugin->getConfig()->getNested("forms.kick-on-close") ?? false)) {
                    $player->kick((string)($messages["register_form_closed"] ?? "You have closed the registration form."));
                } else {
                    $this->sendRegisterForm($player);
                }
                return;
            }

            $password = (string)($data["password"] ?? '');
            $confirmPassword = (string)($data["confirm_password"] ?? '');

            if (($message = $this->plugin->getPasswordValidator()->validatePassword($password)) !== null) {
                $this->sendRegisterForm($player, $message);
                return;
            }

            if ($password !== $confirmPassword) {
                $this->sendRegisterForm($player, (string)($messages["password_mismatch"] ?? "§cPasswords do not match."));
                return;
            }

            $this->plugin->cancelKickTask($player);
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $this->plugin->getDataProvider()->registerPlayer($player, $hashedPassword);
            $this->plugin->getAuthManager()->authenticatePlayer($player);
            (new PlayerRegisterEvent($player))->call();
            $player->sendMessage((string)($messages["register_success"] ?? "§aYou have successfully registered!"));
        });

        $form->setTitle((string)($registerFormConfig["title"] ?? "Register"));
        $content = (string)($registerFormConfig["content"] ?? "");
        if (!empty($content)) {
            $form->addLabel($content);
        }
        if ($errorMessage !== null) {
            $form->addLabel($errorMessage);
        }
        $form->addInput((string)($registerFormConfig["password_label"] ?? "Password"), (string)($registerFormConfig["password_placeholder"] ?? ""), null, "password");
        $form->addInput((string)($registerFormConfig["confirm_password_label"] ?? "Confirm Password"), (string)($registerFormConfig["confirm_password_placeholder"] ?? ""), null, "confirm_password");
        $player->sendForm($form);
    }

    public function sendChangePasswordForm(Player $player, ?string $errorMessage = null): void {
        $messages = (array)$this->plugin->getCustomMessages()->get("messages");
        $formsConfig = (array)$this->plugin->getCustomMessages()->get("forms");
        $changePasswordFormConfig = (array)($formsConfig["changepassword"] ?? []);

        $form = new CustomForm(function (Player $player, ?array $data) use ($messages): void {
            if ($data === null) {
                return;
            }

            $oldPassword = (string)($data["old_password"] ?? '');
            $newPassword = (string)($data["new_password"] ?? '');
            $confirmNewPassword = (string)($data["confirm_new_password"] ?? '');

            $playerData = $this->plugin->getDataProvider()->getPlayer($player);
            if ($playerData === null) {
                // Should not happen if the player is authenticated
                return;
            }

            if (!password_verify($oldPassword, (string)($playerData["password"] ?? ''))) {
                $this->sendChangePasswordForm($player, (string)($messages["incorrect_password"] ?? "§cIncorrect password."));
                return;
            }

            if (($message = $this->plugin->getPasswordValidator()->validatePassword($newPassword)) !== null) {
                $this->sendChangePasswordForm($player, $message);
                return;
            }

            if ($newPassword !== $confirmNewPassword) {
                $this->sendChangePasswordForm($player, (string)($messages["password_mismatch"] ?? "§cPasswords do not match."));
                return;
            }

            $newHashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $this->plugin->getDataProvider()->changePassword($player, $newHashedPassword);
            (new PlayerChangePasswordEvent($player))->call();
            $player->sendMessage((string)($messages["change_password_success"] ?? "§aYour password has been changed successfully."));
        });

        $form->setTitle((string)($changePasswordFormConfig["title"] ?? "Change Password"));
        $content = (string)($changePasswordFormConfig["content"] ?? "");
        if (!empty($content)) {
            $form->addLabel($content);
        }
        if ($errorMessage !== null) {
            $form->addLabel($errorMessage);
        }
        $form->addInput((string)($changePasswordFormConfig["old_password_label"] ?? "Old Password"), "", null, "old_password");
        $form->addInput((string)($changePasswordFormConfig["new_password_label"] ?? "New Password"), "", null, "new_password");
        $form->addInput((string)($changePasswordFormConfig["confirm_new_password_label"] ?? "Confirm New Password"), "", null, "confirm_new_password");
        $player->sendForm($form);
    }

    public function sendForceChangePasswordForm(Player $player, ?string $errorMessage = null): void {
        $messages = (array)$this->plugin->getCustomMessages()->get("messages");
        $formsConfig = (array)$this->plugin->getCustomMessages()->get("forms");
        $forceChangePasswordFormConfig = (array)($formsConfig["forcechangepassword"] ?? []);

        $form = new CustomForm(function (Player $player, ?array $data) use ($messages): void {
            if ($data === null) {
                if ((bool)($this->plugin->getConfig()->getNested("forms.kick-on-close") ?? false)) {
                    $player->kick((string)($messages["force_change_password_closed"] ?? "You must change your password to continue."));
                } else {
                    $this->sendForceChangePasswordForm($player);
                }
                return;
            }

            $newPassword = (string)($data["new_password"] ?? '');
            $confirmNewPassword = (string)($data["confirm_new_password"] ?? '');

            if (($message = $this->plugin->getPasswordValidator()->validatePassword($newPassword)) !== null) {
                $this->sendForceChangePasswordForm($player, $message);
                return;
            }

            if ($newPassword !== $confirmNewPassword) {
                $this->sendForceChangePasswordForm($player, (string)($messages["password_mismatch"] ?? "§cPasswords do not match."));
                return;
            }

            $newHashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $this->plugin->getDataProvider()->changePassword($player, $newHashedPassword);
            $this->plugin->getDataProvider()->setMustChangePassword($player->getName(), false);
            $this->plugin->stopForcePasswordChange($player);

            (new PlayerChangePasswordEvent($player))->call();
            $player->sendMessage((string)($messages["change_password_success"] ?? "§aYour password has been changed successfully."));
        });

        $form->setTitle((string)($forceChangePasswordFormConfig["title"] ?? "Forced Password Change"));
        $form->addLabel((string)($messages["force_change_password_prompt"] ?? "For security reasons, you must change your password before you can continue."));
        if ($errorMessage !== null) {
            $form->addLabel($errorMessage);
        }
        $form->addInput((string)($forceChangePasswordFormConfig["new_password_label"] ?? "New Password"), "", null, "new_password");
        $form->addInput((string)($forceChangePasswordFormConfig["confirm_new_password_label"] ?? "Confirm New Password"), "", null, "confirm_new_password");
        $player->sendForm($form);
    }
}
