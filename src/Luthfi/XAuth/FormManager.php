<?php

declare(strict_types=1);

namespace Luthfi\XAuth;

use jojoe77777\FormAPI\CustomForm;
use Luthfi\XAuth\event\PlayerLoginEvent;
use Luthfi\XAuth\event\PlayerRegisterEvent;
use Luthfi\XAuth\event\PlayerChangePasswordEvent;
use pocketmine\player\Player;

class FormManager {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function sendLoginForm(Player $player): void {
        $bruteforceConfig = (array)$this->plugin->getConfig()->get('bruteforce_protection');

        $enabled = (bool)($bruteforceConfig['enabled'] ?? false);
        $maxAttempts = (int)($bruteforceConfig['max_attempts'] ?? 0);
        $blockTimeMinutes = (int)($bruteforceConfig['block_time_minutes'] ?? 0);

        if ($enabled && $this->plugin->getAuthManager()->isPlayerBlocked($player, $maxAttempts, $blockTimeMinutes)) {
            $remainingMinutes = $this->plugin->getAuthManager()->getRemainingBlockTime($player, $blockTimeMinutes);
            $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["login_attempts_exceeded"] ?? "");
            $player->sendMessage(str_replace('{minutes}', (string)$remainingMinutes, $message));
            return;
        }

        $form = new CustomForm(function (Player $player, ?array $data): void {
            if ($data === null) {
                return;
            }

            $password = (string)($data[0] ?? '');
            $playerData = $this->plugin->getDataProvider()->getPlayer($player);

            if ($playerData === null) {
                // This should not happen if the logic is correct
                return;
            }

            if ($this->plugin->getDataProvider()->isPlayerLocked($player->getName())) {
                $player->sendMessage("§cYour account has been locked by an administrator.");
                return;
            }

            if (!password_verify($password, (string)($playerData["password"] ?? ''))) {
                $this->plugin->getAuthManager()->incrementLoginAttempts($player);
                $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["incorrect_password"] ?? "");
                $player->sendMessage($message);
                $this->sendLoginForm($player); // Resend the form
                return;
            }

            $this->plugin->getDataProvider()->updatePlayerIp($player);
            $this->plugin->getAuthManager()->authenticatePlayer($player);
            (new PlayerLoginEvent($player))->call();
            $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["login_success"] ?? "");
            $player->sendMessage($message);
        });

        $form->setTitle((string)(((array)$this->plugin->getCustomMessages()->get("forms"))["login"]["title"] ?? "Login"));
        $form->addInput((string)(((array)$this->plugin->getCustomMessages()->get("forms"))["login"]["password_label"] ?? "Password"), (string)(((array)$this->plugin->getCustomMessages()->get("forms"))["login"]["password_placeholder"] ?? ""), null, null);
        $player->sendForm($form);
    }

    public function sendRegisterForm(Player $player): void {
        $form = new CustomForm(function (Player $player, ?array $data): void {
            if ($data === null) {
                return;
            }

            $password = (string)($data[0] ?? '');
            $confirmPassword = (string)($data[1] ?? '');

            if (strlen($password) < 6) {
                $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["password_too_short"] ?? "");
                $player->sendMessage($message);
                $this->sendRegisterForm($player);
                return;
            }

            if ($password !== $confirmPassword) {
                $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["password_mismatch"] ?? "");
                $player->sendMessage($message);
                $this->sendRegisterForm($player);
                return;
            }

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $this->plugin->getDataProvider()->registerPlayer($player, $hashedPassword);
            (new PlayerRegisterEvent($player))->call();
            $this->plugin->getAuthManager()->authenticatePlayer($player);
            $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["register_success"] ?? "");
            $player->sendMessage($message);
        });

        $form->setTitle((string)(((array)$this->plugin->getCustomMessages()->get("forms"))["register"]["title"] ?? "Register"));
        $form->addInput((string)(((array)$this->plugin->getCustomMessages()->get("forms"))["register"]["password_label"] ?? "Password"), (string)(((array)$this->plugin->getCustomMessages()->get("forms"))["register"]["password_placeholder"] ?? ""), null, null);
        $form->addInput((string)(((array)$this->plugin->getCustomMessages()->get("forms"))["register"]["confirm_password_label"] ?? "Confirm Password"), (string)(((array)$this->plugin->getCustomMessages()->get("forms"))["register"]["confirm_password_placeholder"] ?? ""), null, null);
        $player->sendForm($form);
    }

    public function sendChangePasswordForm(Player $player): void {
        $form = new CustomForm(function (Player $player, ?array $data): void {
            if ($data === null) {
                return;
            }

            $oldPassword = (string)($data[1] ?? '');
            $newPassword = (string)($data[2] ?? '');
            $confirmNewPassword = (string)($data[3] ?? '');

            $playerData = $this->plugin->getDataProvider()->getPlayer($player);
            if ($playerData === null) {
                return;
            }

            if (!password_verify($oldPassword, (string)($playerData["password"] ?? ''))) {
                $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["incorrect_password"] ?? "");
                $player->sendMessage($message);
                $this->sendChangePasswordForm($player);
                return;
            }

            if (($message = $this->plugin->getPasswordValidator()->validatePassword($newPassword)) !== null) {
                $player->sendMessage($message);
                $this->sendChangePasswordForm($player);
                return;
            }

            if ($newPassword !== $confirmNewPassword) {
                $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["password_mismatch"] ?? "");
                $player->sendMessage($message);
                $this->sendChangePasswordForm($player);
                return;
            }

            $newHashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $this->plugin->getDataProvider()->changePassword($player, $newHashedPassword);
            (new PlayerChangePasswordEvent($player))->call();
            $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["change_password_success"] ?? "");
            $player->sendMessage($message);
        });

        $lang = $this->plugin->getCustomMessages();
        $formsConfig = (array)($lang->get("forms") ?? []);
        $changepasswordConfig = (array)($formsConfig["changepassword"] ?? []);

        $title = (string)($changepasswordConfig["title"] ?? "");
        $content = (string)($changepasswordConfig["content"] ?? "");
        $oldPasswordLabel = (string)($changepasswordConfig["old_password_label"] ?? "");
        $newPasswordLabel = (string)($changepasswordConfig["new_password_label"] ?? "");
        $confirmNewPasswordLabel = (string)($changepasswordConfig["confirm_new_password_label"] ?? "");

        $form->setTitle((string)($changepasswordConfig["title"] ?? "Change Password"));
        $form->addLabel((string)($changepasswordConfig["content"] ?? ""));
        $form->addInput((string)($changepasswordConfig["old_password_label"] ?? "Old Password"), "", null, null);
        $form->addInput((string)($changepasswordConfig["new_password_label"] ?? "New Password"), "", null, null);
        $form->addInput((string)($changepasswordConfig["confirm_new_password_label"] ?? "Confirm New Password"), "", null, null);
        $player->sendForm($form);
    }
}
