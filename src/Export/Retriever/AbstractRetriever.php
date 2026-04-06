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

namespace PrestaShop\Module\Newsmanv8\Export\Retriever;

use PrestaShop\Module\Newsmanv8\Config;
use PrestaShop\Module\Newsmanv8\Export\V1\ApiV1Exception;
use PrestaShop\Module\Newsmanv8\Logger;

if (!defined('_PS_VERSION_')) {
    exit;
}

abstract class AbstractRetriever implements RetrieverInterface
{
    protected Config $config;
    protected Logger $logger;

    public function __construct(Config $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    /**
     * @param array<int> $shopIds
     */
    public function processListParameters(array $data = [], array $shopIds = []): array
    {
        $shopId = $shopIds[0] ?? null;

        \Hook::exec('actionNewsmanExportRetrieverProcessListParamsBefore', [
            'data' => $data,
            'shop_id' => $shopId,
            'shop_ids' => $shopIds,
        ]);

        $params = $this->processListWhereParameters($data, $shopId);

        $sortFound = false;
        if (isset($data['sort'])) {
            $allowedSort = $this->getAllowedSortFields();
            if (isset($allowedSort[$data['sort']])) {
                $params['sort'] = $allowedSort[$data['sort']];
                $sortFound = true;
            } elseif (isset($data['_v1_filter_fields'])) {
                throw new ApiV1Exception(1008, 'Invalid sort field: ' . $data['sort'], 400);
            }
        }

        $params['order'] = 'ASC';
        if (isset($data['order']) && strcasecmp($data['order'], 'desc') === 0) {
            $params['order'] = 'DESC';
        }
        if (!$sortFound) {
            unset($params['sort'], $params['order']);
        }

        if (!isset($data['default_page_size'])) {
            $data['default_page_size'] = 1000;
        }
        $params['start'] = (!empty($data['start']) && $data['start'] > 0) ? (int) $data['start'] : 0;
        $params['limit'] = empty($data['limit']) ? $data['default_page_size'] : (int) $data['limit'];
        $params['default_page_size'] = (int) $data['default_page_size'];

        $hookResult = \Hook::exec(
            'actionNewsmanExportRetrieverProcessListParamsAfter',
            ['params' => $params, 'data' => $data, 'shop_id' => $shopId, 'shop_ids' => $shopIds],
            null,
            false,
            true,
            false,
            null,
            true
        );
        if (is_array($hookResult) && isset($hookResult['params'])) {
            $params = $hookResult['params'];
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function processListWhereParameters(array $data = [], ?int $shopId = null): array
    {
        if (!empty($data['_v1_filter_fields'])) {
            $allowedMapping = $this->getWhereParametersMapping();
            foreach ($data['_v1_filter_fields'] as $field) {
                if (!isset($allowedMapping[$field])) {
                    throw new ApiV1Exception(1006, 'Invalid filter field: ' . $field, 400);
                }
            }
        }

        $params = ['filters' => []];
        $operators = array_keys($this->getExpressionsDefinition());
        $expressions = $this->getExpressionsDefinition(false);
        $expressionsQuoted = $this->getExpressionsDefinition();

        foreach ($this->getWhereParametersMapping() as $requestName => $definition) {
            if (!isset($data[$requestName])) {
                continue;
            }

            $fieldName = $definition['field'];
            $isQuoted = !empty($definition['quote']);

            if (is_array($data[$requestName]) && !empty($data[$requestName]) && is_string(array_keys($data[$requestName])[0])) {
                $params['filters'][$fieldName] = [];
                foreach ($data[$requestName] as $operator => $value) {
                    if (!in_array($operator, $operators, true)) {
                        if (isset($data['_v1_filter_fields'])) {
                            throw new ApiV1Exception(1007, 'Invalid filter operator: ' . $operator, 400);
                        }
                        continue;
                    }

                    $expression = $isQuoted ? $expressionsQuoted[$operator] : $expressions[$operator];
                    $expression = str_replace(':field', $fieldName, $expression);

                    if ($operator === 'in' || $operator === 'nin') {
                        $separator = $isQuoted ? "','" : ',';
                        $expression = str_replace(
                            ':value',
                            implode($separator, $this->escapeValueForSql($value, $definition['type'])),
                            $expression
                        );
                    } else {
                        $expression = str_replace(':value', $this->escapeValueForSql($value, $definition['type']), $expression);
                    }

                    $params['filters'][$fieldName][] = $expression;
                }
            } elseif (is_array($data[$requestName]) && !empty($definition['multiple'])) {
                $value = $data[$requestName];
                $separator = $isQuoted ? "','" : ',';
                $params['filters'][$fieldName] = $fieldName . ' IN ('
                    . implode($separator, $this->escapeValueForSql($value, $definition['type'])) . ')';
            } else {
                $value = $data[$requestName];
                $params['filters'][$fieldName] = $fieldName . ' = '
                    . ($isQuoted ? "'" : '')
                    . $this->escapeValueForSql($value, $definition['type'])
                    . ($isQuoted ? "'" : '');
            }
        }

        return $params;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getWhereParametersMapping(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function getAllowedSortFields(): array
    {
        return [];
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public function escapeValueForSql($value, string $type)
    {
        if (is_array($value)) {
            $return = [];
            foreach ($value as $item) {
                $return[] = $this->escapeValueForSql($item, $type);
            }

            return $return;
        }

        if ($type === 'int') {
            return (int) $value;
        }

        return pSQL((string) $value);
    }

    /**
     * @return array<string, string>
     */
    public function getExpressionsDefinition(bool $addQuotes = true): array
    {
        $value = $addQuotes ? "':value'" : ':value';

        return [
            'eq' => ':field = ' . $value,
            'neq' => ':field <> ' . $value,
            'like' => ':field LIKE ' . $value,
            'nlike' => ':field NOT LIKE ' . $value,
            'in' => ':field IN(' . $value . ')',
            'nin' => ':field NOT IN(' . $value . ')',
            'is' => ':field IS ' . $value,
            'notnull' => ':field IS NOT NULL',
            'null' => ':field IS NULL',
            'gt' => ':field > ' . $value,
            'lt' => ':field < ' . $value,
            'gteq' => ':field >= ' . $value,
            'lteq' => ':field <= ' . $value,
            'from' => ':field >= ' . $value,
            'to' => ':field <= ' . $value,
        ];
    }

    protected function getDefaultLanguageId(?int $shopId = null): int
    {
        $sql = 'SELECT `id_lang` FROM `' . _DB_PREFIX_ . 'lang` WHERE `active` = 1 ORDER BY `id_lang` ASC';
        $result = \Db::getInstance()->getValue($sql);

        return $result ? (int) $result : 1;
    }

    protected function getShopUrl(?int $shopId = null): string
    {
        $currentShopId = $this->config->getEffectiveShopId();
        if ($shopId !== null && $currentShopId !== $shopId) {
            $shop = new \Shop($shopId);
            $url = $shop->getBaseURL(true);
        } else {
            $url = (new \Shop($currentShopId))->getBaseURL(true);
        }

        $hookResult = \Hook::exec(
            'actionNewsmanExportRetrieverGetStoreUrlBefore',
            ['url' => $url, 'shop_id' => $shopId],
            null,
            false,
            true,
            false,
            null,
            true
        );
        if (is_array($hookResult) && isset($hookResult['url'])) {
            $url = $hookResult['url'];
        }

        return $url;
    }

    /**
     * Clean phone number: remove non-digit chars except leading +.
     */
    protected function cleanPhone(string $phone): string
    {
        $phone = trim($phone);
        if (empty($phone)) {
            return '';
        }

        $prefix = '';
        if ($phone[0] === '+') {
            $prefix = '+';
            $phone = substr($phone, 1);
        }

        return $prefix . preg_replace('/[^0-9]/', '', $phone);
    }
}
