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

/*
 * Extracts the remote client IP address from request headers.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class RemoteAddress
{
    protected ?string $remoteAddress = null;

    /**
     * @param string[] $alternativeHeaders
     */
    public function __construct(
        protected array $alternativeHeaders = [],
    ) {
    }

    /**
     * Read the raw address from server variables.
     */
    public function readAddress(): ?string
    {
        $remoteAddress = null;
        foreach ($this->alternativeHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $remoteAddress = (string) $_SERVER[$header];
                break;
            }
        }

        if (null === $remoteAddress) {
            $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        }

        return $remoteAddress;
    }

    /**
     * Filter a raw address string (which may contain comma-separated proxy chain).
     *
     * Returns the last valid public IP from the chain, or null if none found.
     */
    public function filterAddress(string $remoteAddress): ?string
    {
        if (str_contains($remoteAddress, ',')) {
            $ipList = explode(',', $remoteAddress);
        } else {
            $ipList = [$remoteAddress];
        }

        $ipList = array_filter(
            $ipList,
            function (string $ip): bool {
                return (bool) filter_var(
                    trim($ip),
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                );
            }
        );

        // Get last IP from the chain (most likely the real IP behind proxies).
        reset($ipList);
        $remoteAddress = empty($ipList) ? '' : trim(end($ipList));

        return $remoteAddress ?: null;
    }

    /**
     * Retrieve the client remote address.
     *
     * Result is cached in memory for the duration of the request.
     */
    public function getRemoteAddress(): string
    {
        if (null !== $this->remoteAddress) {
            return $this->remoteAddress;
        }

        $rawAddress = $this->readAddress();
        if (null === $rawAddress) {
            $this->remoteAddress = '';

            return '';
        }

        $filtered = $this->filterAddress($rawAddress);
        $this->remoteAddress = $filtered ?? '';

        return $this->remoteAddress;
    }
}
