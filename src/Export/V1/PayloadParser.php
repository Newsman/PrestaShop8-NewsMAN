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

namespace PrestaShop\Module\Newsmanv8\Export\V1;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PayloadParser
{
    /** @var array<string, string> */
    public static array $methodMap = [
        'customer.list' => 'customers',
        'subscriber.list' => 'subscribers',
        'subscriber.subscribe' => 'subscriber-subscribe',
        'subscriber.unsubscribe' => 'subscriber-unsubscribe',
        'product.list' => 'products-feed',
        'order.list' => 'orders',
        'coupon.create' => 'coupons',
        'custom.sql' => 'custom-sql',
        'platform.name' => 'platform-name',
        'platform.version' => 'platform-version',
        'platform.language' => 'platform-language',
        'platform.language_version' => 'platform-language-version',
        'integration.name' => 'integration-name',
        'integration.version' => 'integration-version',
        'server.ip' => 'server-ip',
        'server.cloudflare' => 'server-cloudflare',
        'sql.name' => 'sql-name',
        'sql.version' => 'sql-version',
        'refresh.remarketing' => 'refresh-remarketing',
    ];

    public function isV1Payload(string $rawBody, string $contentType = ''): bool
    {
        if (!empty($contentType) && strpos($contentType, 'application/json') !== false) {
            return true;
        }
        $trimmed = ltrim($rawBody);

        return !empty($trimmed) && $trimmed[0] === '{';
    }

    /**
     * @return array{code: string, data: array<string, mixed>}
     *
     * @throws ApiV1Exception
     */
    public function parse(string $rawBody): array
    {
        $payload = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiV1Exception(1002, 'Invalid JSON payload', 400);
        }

        if (!is_array($payload) || !array_key_exists('method', $payload)) {
            throw new ApiV1Exception(1003, 'Missing "method" parameter', 400);
        }

        $method = $payload['method'];
        if (!isset(self::$methodMap[$method])) {
            throw new ApiV1Exception(1004, 'Unknown method: ' . $method, 404);
        }

        $params = isset($payload['params']) ? $payload['params'] : [];
        if (!is_array($params)) {
            throw new ApiV1Exception(1005, 'Invalid "params" parameter', 400);
        }

        $data = $params;
        $filterFields = [];
        if (isset($params['filter']) && is_array($params['filter'])) {
            foreach ($params['filter'] as $fieldName => $fieldValue) {
                $data[$fieldName] = $fieldValue;
                $filterFields[] = $fieldName;
            }
        }
        unset($data['filter']);
        $data['_v1_filter_fields'] = $filterFields;

        return [
            'code' => self::$methodMap[$method],
            'data' => $data,
        ];
    }
}
