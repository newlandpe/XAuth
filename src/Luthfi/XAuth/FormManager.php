<?php

declare(strict_types=1);

namespace Luthfi\XAuth;

use jojoe77777\FormAPI\CustomForm;
use Luthfi\XAuth\event\PlayerLoginEvent;
use pocketmine\player\Player;

class FormManager {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function sendLoginForm(Player $player): void {
        $bruteforceConfig = $this->plugin->getConfig()->get('bruteforce_protection');
        if ($bruteforceConfig['enabled'] && $this->plugin->getAuthManager()->isPlayerBlocked($player, $bruteforceConfig['max_attempts'], $bruteforceConfig['block_time_minutes'])) {
            $remainingMinutes = $this->plugin->getAuthManager()->getRemainingBlockTime($player, $bruteforceConfig['block_time_minutes']);
            $player->sendMessage(str_replace('{minutes}', (string)$remainingMinutes, $this->plugin->getCustomMessages()->get("messages")["login_attempts_exceeded"]));
            return;
        }

        $form = new CustomForm(function (Player $player, ?array $data): void {
            if ($data === null) {
                return;
            }

            $password = $data[0];
            $playerData = $this->plugin->getDataProvider()->getPlayer($player);

            if ($playerData === null) {
                // This should not happen if the logic is correct
                return;
            }

            if ($this->plugin->getDataProvider()->isPlayerLocked($player->getName())) {
                $player->sendMessage("§cYour account has been locked by an administrator.");
                return;
            }

            if (!password_verify($password, $playerData["password"])) {
                $this->plugin->getAuthManager()->incrementLoginAttempts($player);
                $player->sendMessage($this->plugin->getCustomMessages()->get("messages")["incorrect_password"]);
                $this->sendLoginForm($player); // Resend the form
                return;
            }

            $this->plugin->getDataProvider()->updatePlayerIp($player);
            $this->plugin->getAuthManager()->authenticatePlayer($player);
            (new PlayerLoginEvent($player))->call();
            $player->sendMessage($this->plugin->getCustomMessages()->get("messages")["login_success"]);
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

            $password = $data[0];
            $confirmPassword = $data[1];

            if (strlen($password) < 6) {
                $player->sendMessage($this->plugin->getCustomMessages()->get("messages")["password_too_short"]);
                $this->sendRegisterForm($player);
                return;
            }

            if ($password !== $confirmPassword) {
                $player->sendMessage($this->plugin->getCustomMessages()->get("messages")["password_mismatch"]);
                $this->sendRegisterForm($player);
                return;
            }

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $this->plugin->getDataProvider()->registerPlayer($player, $hashedPassword);
            (new PlayerRegisterEvent($player))->call();
            $this->plugin->getAuthManager()->authenticatePlayer($player);
            $player->sendMessage($this->plugin->getCustomMessages()->get("messages")["register_success"]);
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

            $oldPassword = $data[1];
            $newPassword = $data[2];
            $confirmNewPassword = $data[3];

            $playerData = $this->plugin->getDataProvider()->getPlayer($player);
            if ($playerData === null) {
                return;
            }

            if (!password_verify($oldPassword, $playerData["password"])) {
                $player->sendMessage($this->plugin->getCustomMessages()->get("messages")["incorrect_password"]);
                $this->sendChangePasswordForm($player);
                return;
            }

            if (($message = $this->plugin->getPasswordValidator()->validatePassword($newPassword)) !== null) {
                $player->sendMessage($message);
                $this->sendChangePasswordForm($player);
                return;
            }

            if ($newPassword !== $confirmNewPassword) {
                $player->sendMessage($this->plugin->getCustomMessages()->get("messages")["password_mismatch"]);
                $this->sendChangePasswordForm($player);
                return;
            }

            $newHashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $this->plugin->getDataProvider()->changePassword($player, $newHashedPassword);
            (new PlayerChangePasswordEvent($player))->call();
            $player->sendMessage($this->plugin->getCustomMessages()->get("messages")["change_password_success"]);
        });

        $lang = $this->plugin->getCustomMessages();
        $form->setTitle($lang->get("forms")["changepassword"]["title"]);
        $form->addLabel($lang->get("forms")["changepassword"]["content"]);
        $form->addInput($lang->get("forms")["changepassword"]["old_password_label"], "", null, true);
        $form->addInput($lang->get("forms")["changepassword"]["new_password_label"], "", null, true);
        $form->addInput($lang->get("forms")["changepassword"]["confirm_new_password_label"], "", null, true);
        $player->sendForm($form);
    }
}
