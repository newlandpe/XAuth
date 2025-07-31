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
        $bruteforceConfig = $this->plugin->getConfig()->get('bruteforce_protection');
        if (!is_array($bruteforceConfig)) {
            $bruteforceConfig = [];
        }

        $enabled = (bool)($bruteforceConfig['enabled'] ?? false);
        $maxAttempts = (int)($bruteforceConfig['max_attempts'] ?? 0);
        $blockTimeMinutes = (int)($bruteforceConfig['block_time_minutes'] ?? 0);

        if ($enabled && $this->plugin->getAuthManager()->isPlayerBlocked($player, $maxAttempts, $blockTimeMinutes)) {
            $remainingMinutes = $this->plugin->getAuthManager()->getRemainingBlockTime($player, $blockTimeMinutes);
            $message = (string)($this->plugin->getCustomMessages()->get("messages")["login_attempts_exceeded"] ?? "");
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
                $message = (string)($this->plugin->getCustomMessages()->get("messages")["incorrect_password"] ?? "");
                $player->sendMessage($message);
                $this->sendLoginForm($player); // Resend the form
                return;
            }

            $this->plugin->getDataProvider()->updatePlayerIp($player);
            $this->plugin->getAuthManager()->authenticatePlayer($player);
            (new PlayerLoginEvent($player))->call();
            $message = (string)($this->plugin->getCustomMessages()->get("messages")["login_success"] ?? "");
            $player->sendMessage($message);
        });

        $form->setTitle("Login");
        $form->addInput("Password", "", null, true);
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
                $message = (string)($this->plugin->getCustomMessages()->get("messages")["password_too_short"] ?? "");
                $player->sendMessage($message);
                $this->sendRegisterForm($player);
                return;
            }

            if ($password !== $confirmPassword) {
                $message = (string)($this->plugin->getCustomMessages()->get("messages")["password_mismatch"] ?? "");
                $player->sendMessage($message);
                $this->sendRegisterForm($player);
                return;
            }

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $this->plugin->getDataProvider()->registerPlayer($player, $hashedPassword);
            (new PlayerRegisterEvent($player))->call();
            $this->plugin->getAuthManager()->authenticatePlayer($player);
            $message = (string)($this->plugin->getCustomMessages()->get("messages")["register_success"] ?? "");
            $player->sendMessage($message);
        });

        $form->setTitle("Register");
        $form->addInput("Password", "", null, true);
        $form->addInput("Confirm Password", "", null, true);
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
                $message = (string)($this->plugin->getCustomMessages()->get("messages")["incorrect_password"] ?? "");
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
                $message = (string)($this->plugin->getCustomMessages()->get("messages")["password_mismatch"] ?? "");
                $player->sendMessage($message);
                $this->sendChangePasswordForm($player);
                return;
            }

            $newHashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $this->plugin->getDataProvider()->changePassword($player, $newHashedPassword);
            (new PlayerChangePasswordEvent($player))->call();
            $message = (string)($this->plugin->getCustomMessages()->get("messages")["change_password_success"] ?? "");
            $player->sendMessage($message);
        });

        $lang = $this->plugin->getCustomMessages();
        $formsConfig = is_array($lang) ? ($lang->get("forms") ?? []) : [];
        $changepasswordConfig = is_array($formsConfig) ? ($formsConfig["changepassword"] ?? []) : [];

        $title = (string)($changepasswordConfig["title"] ?? "");
        $content = (string)($changepasswordConfig["content"] ?? "");
        $oldPasswordLabel = (string)($changepasswordConfig["old_password_label"] ?? "");
        $newPasswordLabel = (string)($changepasswordConfig["new_password_label"] ?? "");
        $confirmNewPasswordLabel = (string)($changepasswordConfig["confirm_new_password_label"] ?? "");

        $form->setTitle($title);
        $form->addLabel($content);
        $form->addInput($oldPasswordLabel, "", null, true);
        $form->addInput($newPasswordLabel, "", null, true);
        $form->addInput($confirmNewPasswordLabel, "", null, true);
        $player->sendForm($form);
    }
}
