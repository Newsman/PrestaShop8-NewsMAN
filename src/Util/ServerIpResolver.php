<?php
/**
 * Copyright © Dazoot Software S.R.L. All rights reserved.
 *
 * @author Newsman by Dazoot <support@newsman.com>
 * @copyright Copyright © Dazoot Software S.R.L. All rights reserved.
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *
 * @website https://www.newsman.ro/
 */

namespace PrestaShop\Module\Newsmanv8\Util;

/*
 * Resolves the server's public IP address.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class ServerIpResolver
{
    /**
     * Free IP-lookup service URLs.
     * Each service returns the public IP as plain text.
     *
     * @var string[]
     */
    protected array $services = [
        'https://api.ipify.org',
        'https://ipinfo.io/ip',
        'https://ifconfig.me/ip',
        'https://icanhazip.com',
    ];

    /**
     * Resolve the server's public IP address.
     *
     * Tries the lookup services in random order and returns the first valid
     * IP address found. Falls back to $_SERVER['SERVER_ADDR'] and gethostname()
     * if all services are unreachable.
     */
    public function resolve(): string
    {
        $services = $this->services;
        shuffle($services);

        foreach ($services as $url) {
            $ip = $this->fetchFromService($url);
            if ($this->isValidIp($ip)) {
                return $ip;
            }
        }

        if (!empty($_SERVER['SERVER_ADDR'])) {
            return (string) $_SERVER['SERVER_ADDR'];
        }

        $hostname = gethostname();
        if (false !== $hostname) {
            $ip = gethostbyname($hostname);
            if ($ip !== $hostname) {
                return $ip;
            }
        }

        return '';
    }

    /**
     * Fetch the IP from a single lookup service using native PHP cURL.
     */
    private function fetchFromService(string $url): string
    {
        if (!function_exists('curl_init')) {
            return '';
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $result = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (false === $result || 200 !== $httpCode) {
            return '';
        }

        return trim((string) $result);
    }

    /**
     * Check whether a string is a valid IP address.
     */
    private function isValidIp(string $ip): bool
    {
        return !empty($ip) && false !== filter_var($ip, FILTER_VALIDATE_IP);
    }
}
