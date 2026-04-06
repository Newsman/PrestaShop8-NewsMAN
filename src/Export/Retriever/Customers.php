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

if (!defined('_PS_VERSION_')) {
    exit;
}

class Customers extends AbstractRetriever implements RetrieverInterface
{
    public const DEFAULT_PAGE_SIZE = 1000;

    /**
     * @param array<string, mixed> $data
     * @param array<int> $shopIds
     *
     * @return array<mixed>
     */
    public function process(array $data = [], array $shopIds = []): array
    {
        $data['default_page_size'] = self::DEFAULT_PAGE_SIZE;

        $parameters = $this->processListParameters($data, $shopIds);

        $customers = $this->getCustomers($parameters, $shopIds);

        \Hook::exec('actionNewsmanExportRetrieverCustomersProcessFetchAfter', [
            'customers' => $customers,
            'parameters' => $parameters,
            'shop_id' => $shopIds[0] ?? null,
            'shop_ids' => $shopIds,
        ]);

        if (empty($customers)) {
            return [];
        }

        $customerIds = array_column($customers, 'customer_id');
        $phones = $this->getCustomerPhones($customerIds);

        $result = [];
        foreach ($customers as $customer) {
            try {
                $result[] = $this->processCustomer($customer, $phones, $shopIds);
            } catch (\Exception $e) {
                $this->logger->logException($e);
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<mixed>|int
     */
    /**
     * @param array<int> $shopIds
     */
    public function getCustomers(array $params = [], array $shopIds = [], bool $isCount = false)
    {
        $langId = $this->getDefaultLanguageId($shopIds[0] ?? null);

        $sql = 'SELECT ';
        if (!$isCount) {
            $sql .= "c.id_customer AS customer_id,
                c.id_default_group AS customer_group_id,
                c.id_shop AS shop_id,
                c.firstname AS firstname,
                c.lastname AS lastname,
                c.email AS email,
                c.newsletter AS newsletter,
                c.active AS status,
                c.date_add AS date_added,
                c.date_upd AS date_modified,
                CONCAT(c.firstname, ' ', c.lastname) AS name,
                gl.name AS customer_group";
        } else {
            $sql .= 'COUNT(DISTINCT c.id_customer) AS total';
        }

        $sql .= ' FROM ' . _DB_PREFIX_ . 'customer AS c';
        $sql .= ' LEFT JOIN ' . _DB_PREFIX_ . 'group_lang gl
                    ON (c.id_default_group = gl.id_group AND gl.id_lang = ' . (int) $langId . ')';
        $sql .= ' WHERE c.deleted = 0';

        if (!empty($shopIds)) {
            $sql .= ' AND c.id_shop IN (' . implode(',', array_map('intval', $shopIds)) . ')';
        }

        $where = [];
        if (!empty($params['filters'])) {
            foreach ($params['filters'] as $filter) {
                if (is_array($filter)) {
                    $where[] = implode(' AND ', $filter);
                } else {
                    $where[] = $filter;
                }
            }
        }

        if (!empty($where)) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }

        if (isset($params['sort']) && isset($params['order'])) {
            $sql .= ' ORDER BY ' . $params['sort'] . ' ' . $params['order'];
        }

        if (!$isCount) {
            $start = 0;
            if (isset($params['start']) && $params['start'] >= 0) {
                $start = (int) $params['start'];
            }
            $limit = $params['default_page_size'];
            if (isset($params['limit']) && $params['limit'] >= 1) {
                $limit = (int) $params['limit'];
            }
            $sql .= ' LIMIT ' . $start . ',' . $limit;
        }

        if ($isCount) {
            $result = \Db::getInstance()->getValue($sql);

            return $result ? (int) $result : 0;
        }

        $rows = \Db::getInstance()->executeS($sql);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<int|string> $customerIds
     *
     * @return array<int, string>
     */
    protected function getCustomerPhones(array $customerIds): array
    {
        if (empty($customerIds)) {
            return [];
        }

        $sql = 'SELECT a.id_customer,'
            . ' COALESCE(NULLIF(a.phone, \'\'), NULLIF(a.phone_mobile, \'\'), \'\') AS phone'
            . ' FROM ' . _DB_PREFIX_ . 'address a'
            . ' INNER JOIN ('
            . '     SELECT id_customer, MIN(id_address) AS id_address'
            . '     FROM ' . _DB_PREFIX_ . 'address'
            . '     WHERE id_customer IN (' . implode(',', array_map('intval', $customerIds)) . ')'
            . '     AND deleted = 0'
            . '     GROUP BY id_customer'
            . ' ) first_addr ON a.id_address = first_addr.id_address';

        $rows = \Db::getInstance()->executeS($sql);
        if (!is_array($rows)) {
            return [];
        }

        $phones = [];
        foreach ($rows as $row) {
            $phone = $this->cleanPhone((string) $row['phone']);
            if (!empty($phone)) {
                $phones[(int) $row['id_customer']] = $phone;
            }
        }

        return $phones;
    }

    /**
     * @param array<string, mixed> $customer
     * @param array<int, string> $phones
     *
     * @return array<string, mixed>
     */
    /**
     * @param array<int> $shopIds
     */
    protected function processCustomer(array $customer, array $phones, array $shopIds = []): array
    {
        $row = [
            'customer_id' => $customer['customer_id'],
            'firstname' => $customer['firstname'],
            'lastname' => $customer['lastname'],
            'email' => $customer['email'],
            'date_created' => $customer['date_added'],
            'source' => 'PrestaShop customers',
            'customer_groups' => [
                [
                    'id' => (int) $customer['customer_group_id'],
                    'name' => $customer['customer_group'] ?? '',
                ],
            ],
        ];

        if ($this->config->isRemarketingSendTelephoneByShopIds($shopIds)) {
            $row['phone'] = $phones[(int) $customer['customer_id']] ?? '';
        }

        $shopId = $shopIds[0] ?? null;
        $hookResult = \Hook::exec(
            'actionNewsmanExportRetrieverCustomersProcessCustomerAfter',
            ['row' => $row, 'customer' => $customer, 'shop_id' => $shopId, 'shop_ids' => $shopIds],
            null,
            false,
            true,
            false,
            null,
            true
        );
        if (is_array($hookResult) && isset($hookResult['row'])) {
            $row = $hookResult['row'];
        }

        return $row;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getWhereParametersMapping(): array
    {
        return array_merge(parent::getWhereParametersMapping(), [
            'created_at' => [
                'field' => 'c.date_add',
                'quote' => true,
                'type' => 'string',
            ],
            'modified_at' => [
                'field' => 'c.date_upd',
                'quote' => true,
                'type' => 'string',
            ],
            'customer_id' => [
                'field' => 'c.id_customer',
                'quote' => false,
                'type' => 'int',
            ],
            'customer_ids' => [
                'field' => 'c.id_customer',
                'quote' => false,
                'multiple' => true,
                'force_array' => true,
                'type' => 'int',
            ],
            'email' => [
                'field' => 'c.email',
                'quote' => true,
                'type' => 'string',
            ],
            'name' => [
                'field' => "CONCAT(c.firstname, ' ', c.lastname)",
                'quote' => true,
                'type' => 'string',
            ],
            'firstname' => [
                'field' => 'c.firstname',
                'quote' => true,
                'type' => 'string',
            ],
            'lastname' => [
                'field' => 'c.lastname',
                'quote' => true,
                'type' => 'string',
            ],
            'confirmed' => [
                'field' => 'c.newsletter',
                'quote' => false,
                'type' => 'int',
            ],
            'customer_group_id' => [
                'field' => 'c.id_default_group',
                'quote' => false,
                'type' => 'int',
            ],
            'status' => [
                'field' => 'c.active',
                'quote' => false,
                'type' => 'int',
            ],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function getAllowedSortFields(): array
    {
        return array_merge(parent::getAllowedSortFields(), [
            'name' => 'name',
            'email' => 'c.email',
            'firstname' => 'c.firstname',
            'lastname' => 'c.lastname',
            'customer_group' => 'customer_group',
            'status' => 'c.active',
            'created_at' => 'c.date_add',
            'modified_at' => 'c.date_upd',
            'customer_id' => 'c.id_customer',
        ]);
    }
}
