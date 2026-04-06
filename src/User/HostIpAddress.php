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

namespace PrestaShop\Module\Newsmanv8\User;

use PrestaShop\Module\Newsmanv8\Config;
use PrestaShop\Module\Newsmanv8\Util\ServerIpResolver;
use PrestaShop\PrestaShop\Adapter\Configuration;

/*
 * Resolves the server (host) IP address with caching.
 *
 * On first call, auto-detects the public IP and saves it to configuration
 * so subsequent calls are instant.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class HostIpAddress implements IpAddressInterface
{
    public const NOT_FOUND = 'not found';

    protected ?string $ip = null;

    public function __construct(
        protected Config $config,
        protected ServerIpResolver $serverIpResolver,
        protected Configuration $configuration,
    ) {
    }

    public function getIp(): string
    {
        if (null !== $this->ip) {
            return $this->ip;
        }

        $ip = $this->config->getServerIp();
        if (!empty($ip)) {
            if (self::NOT_FOUND === $ip) {
                $this->ip = '';
            } else {
                $this->ip = $ip;
            }

            return $this->ip;
        }

        $ip = $this->serverIpResolver->resolve();
        if (!empty($ip)) {
            $this->configuration->set(Config::KEY_SERVER_IP, $ip);
            $this->ip = $ip;

            return $this->ip;
        }

        $this->configuration->set(Config::KEY_SERVER_IP, self::NOT_FOUND);
        $this->ip = '';

        return $this->ip;
    }
}
