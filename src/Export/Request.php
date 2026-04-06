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

namespace PrestaShop\Module\Newsmanv8\Export;

use PrestaShop\Module\Newsmanv8\Config;
use PrestaShop\Module\Newsmanv8\Export\Retriever\Authenticator;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Request
{
    /** @var array<string> */
    protected array $knownParameters = [
        'newsman',
        'start',
        'limit',
        'created_at',
        'modified_at',
        'last-days',
        'sort',
        'order',
        'subscriber_id',
        'subscriber_ids',
        'customer_id',
        'customer_ids',
        'order_id',
        'order_ids',
        'product_id',
        'product_ids',
        'cron',
        'type',
        'value',
        'batch_size',
        'prefix',
        'expire_date',
        'min_amount',
        'currency',
        'sql',
        'email',
    ];

    /** @var array<string, string> */
    protected array $cronParams = [
        'cron' => 'cron-subscribers.json',
        'cron-orders' => 'cron-orders.json',
    ];

    protected Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function isExportRequest(): bool
    {
        $newsman = $this->getParam('newsman');

        if (empty($newsman)) {
            foreach ($this->cronParams as $cronParam => $code) {
                $val = $this->getParam($cronParam);
                if ($val === 'true' || $val === '1') {
                    return true;
                }
            }
        }

        return !empty($newsman);
    }

    /**
     * @return array<string, mixed>
     */
    public function getRequestParameters(): array
    {
        $parameters = $this->getAllKnownParameters();

        $apiKey = $this->getApiKeyFromHeader();
        if (!empty($apiKey) && empty($parameters[Authenticator::API_KEY_PARAM])) {
            $parameters[Authenticator::API_KEY_PARAM] = $apiKey;
        }

        return $parameters;
    }

    public function getShopId(): ?int
    {
        $shopId = $this->getParam('shop_id');
        if ($shopId !== null && $shopId !== '') {
            return (int) $shopId;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getAllKnownParameters(): array
    {
        $parameters = [];
        $hashKey = Authenticator::API_KEY_PARAM;

        $hashVal = $this->getParam($hashKey);
        if (!empty($hashVal)) {
            $parameters[$hashKey] = $hashVal;
        }

        foreach ($this->knownParameters as $parameter) {
            $val = $this->getParam($parameter);
            if ($val !== null) {
                $parameters[$parameter] = $val;
            }
        }

        foreach ($this->cronParams as $cronParam => $code) {
            $val = $this->getParam($cronParam);
            if ($val === 'true' || $val === '1') {
                $parameters['newsman'] = $code;
                break;
            }
        }

        $hookResult = \Hook::exec(
            'actionNewsmanExportRequestGetAllKnownParametersAfter',
            ['parameters' => $parameters],
            null,
            false,
            true,
            false,
            null,
            true
        );
        if (is_array($hookResult) && isset($hookResult['parameters'])) {
            $parameters = $hookResult['parameters'];
        }

        return $parameters;
    }

    protected function getApiKeyFromHeader(): string
    {
        $auth = '';

        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
        }

        if (empty($auth)) {
            $auth = $this->getHeaderValue('authorization');
            if (empty($auth)) {
                $name = $this->config->getExportAuthHeaderName();
                if (!empty($name)) {
                    $alt = $this->getHeaderValue($name);
                    if (!empty($alt)) {
                        return trim((string) $alt);
                    }
                }

                return '';
            }
        }

        if (stripos($auth, 'Bearer') !== false) {
            return trim(str_ireplace('Bearer', '', $auth));
        }

        return $auth;
    }

    /**
     * @return string|null
     */
    protected function getParam(string $name)
    {
        if (isset($_GET[$name])) {
            return $_GET[$name];
        }
        if (isset($_POST[$name])) {
            return $_POST[$name];
        }

        return null;
    }

    /**
     * @return string|false
     */
    protected function getHeaderValue(string $name)
    {
        $name = strtolower($name);
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $headerName => $value) {
                if (strtolower($headerName) === $name) {
                    return $value;
                }
            }
        }

        return false;
    }
}
