<?php

declare(strict_types=1);

namespace Luthfi\XAuth\listener;

use Luthfi\XAuth\Main;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;

class GeoIPListener implements Listener {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onPlayerPreLogin(PlayerPreLoginEvent $event): void {
        $geoipConfig = (array)$this->plugin->getConfig()->get('geoip');
        $ip = $event->getIp();
        $isLocal = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        $countryList = (array)($geoipConfig['country-list'] ?? []);
        $mode = (string)($geoipConfig['mode'] ?? 'blacklist');

        if ($isLocal) {
            $isListed = in_array("LOCALHOST", $countryList, true);
            if (($mode === "blacklist" && $isListed) || ($mode === "whitelist" && !$isListed)) {
                $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["geoip.kick.local"] ?? "Connections from local networks are not allowed.");
                $event->setKickFlag(PlayerPreLoginEvent::KICK_FLAG_BANNED, $message);
            }
            return;
        }

        $this->plugin->getServer()->getAsyncPool()->submitTask(new class($ip, $event->getPlayerInfo()->getUsername(), $geoipConfig, $this->plugin->getCustomMessages()->get("messages")) extends \pocketmine\scheduler\AsyncTask {
            public function __construct(private string $ip, private string $username, private array $config, private array $messages) {}

            public function onRun(): void {
                $url = "http://ip-api.com/json/" . $this->ip . "?fields=status,countryCode";
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);

                $resolve = [];
                $forcedHosts = $this->config["network"]["force-resolve-hosts"] ?? [];
                foreach($forcedHosts as $domain => $ip_address) {
                    $resolve[] = $domain . ":80:" . $ip_address; // Port 80 for HTTP
                }
                if(!empty($resolve)){
                    curl_setopt($ch, CURLOPT_RESOLVE, $resolve);
                }

                $caPath = $this->config["network"]["custom-ca-bundle"] ?? "";
                if($caPath !== "" && file_exists($caPath)){
                    curl_setopt($ch, CURLOPT_CAINFO, $caPath);
                }

                $response = curl_exec($ch);
                $error = curl_error($ch);
                curl_close($ch);

                if ($response === false) {
                    $this->setResult(["success" => false, "error" => $error]);
                    return;
                }
                $this->setResult(["success" => true, "data" => json_decode($response, true)]);
            }

            public function onCompletion(): void {
                $server = \pocketmine\Server::getInstance();
                $result = $this->getResult();

                if (!$result["success"]) {
                    $server->getLogger()->warning("[XAuth GeoIP] Failed to fetch geo data for {$this->ip}: " . $result["error"]);
                    return;
                }

                $data = $result["data"];
                if (!isset($data["status"]) || $data["status"] !== "success" || !isset($data["countryCode"])) {
                    $server->getLogger()->warning("[XAuth GeoIP] Invalid response from API for {$this->ip}");
                    return;
                }

                $countryCode = $data["countryCode"];
                $mode = (string)($this->config["mode"] ?? "blacklist");
                $countryList = (array)($this->config["country-list"] ?? []);
                $isListed = in_array($countryCode, $countryList, true);

                if (($mode === "blacklist" && $isListed) || ($mode === "whitelist" && !$isListed)) {
                    $player = $server->getPlayerExact($this->username);
                    if($player !== null){
                        $message = (string)($this->messages["geoip.kick.country"] ?? "Your country is not allowed on this server.");
                        $player->kick($message);
                    }
                }
            }
        });
    }
}
