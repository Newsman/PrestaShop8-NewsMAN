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

class SubscribersBase extends AbstractRetriever implements RetrieverInterface
{
    public const DEFAULT_PAGE_SIZE = 1000;
    public const GUEST_ID_OFFSET = 1000000;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<mixed>
     */
    /**
     * @param array<int> $shopIds
     */
    public function process(array $data = [], array $shopIds = []): array
    {
        $data['default_page_size'] = self::DEFAULT_PAGE_SIZE;

        $parameters = $this->processListParameters($data, $shopIds);

        $subscribers = $this->getSubscribers($parameters, $shopIds);
        if (empty($subscribers)) {
            return [];
        }

        $customerIds = [];
        foreach ($subscribers as $sub) {
            if ($sub['source_type'] === 'customer') {
                $customerIds[] = (int) $sub['subscriber_id'];
            }
        }
        $phones = $this->getCustomerPhones($customerIds);

        $result = [];
        foreach ($subscribers as $subscriber) {
            try {
                $result[] = $this->processSubscriber($subscriber, $phones, $shopIds);
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
    public function getSubscribers(array $params = [], array $shopIds = [], bool $isCount = false)
    {
        $shopFilter = !empty($shopIds)
            ? ' AND c.id_shop IN (' . implode(',', array_map('intval', $shopIds)) . ')'
            : '';
        $guestShopFilter = !empty($shopIds)
            ? ' AND e.id_shop IN (' . implode(',', array_map('intval', $shopIds)) . ')'
            : '';

        $customerSql = 'SELECT'
            . ' c.id_customer AS subscriber_id,'
            . ' c.firstname AS firstname,'
            . ' c.lastname AS lastname,'
            . ' c.email AS email,'
            . ' 1 AS confirmed,'
            . ' c.newsletter_date_add AS date_added,'
            . ' c.newsletter_date_add AS date_modified,'
            . ' c.ip_registration_newsletter AS ip,'
            . ' c.id_shop AS shop_id,'
            . " 'customer' AS source_type"
            . ' FROM ' . _DB_PREFIX_ . 'customer AS c'
            . ' WHERE c.deleted = 0 AND c.newsletter = 1'
            . $shopFilter;

        $hasGuests = $this->isEmailSubscriptionActive();

        if ($hasGuests) {
            $guestSql = 'SELECT'
                . ' (' . self::GUEST_ID_OFFSET . ' + e.id) AS subscriber_id,'
                . " '' AS firstname,"
                . " '' AS lastname,"
                . ' e.email AS email,'
                . ' e.active AS confirmed,'
                . ' e.newsletter_date_add AS date_added,'
                . ' e.newsletter_date_add AS date_modified,'
                . ' e.ip_registration_newsletter AS ip,'
                . ' e.id_shop AS shop_id,'
                . " 'guest' AS source_type"
                . ' FROM ' . _DB_PREFIX_ . 'emailsubscription AS e'
                . ' WHERE e.active = 1'
                . $guestShopFilter;

            $unionSql = '(' . $customerSql . ') UNION ALL (' . $guestSql . ')';
        } else {
            $unionSql = $customerSql;
        }

        if ($isCount) {
            $sql = 'SELECT COUNT(*) AS total FROM (' . $unionSql . ') AS s';

            $where = $this->buildWhereClause($params);
            if (!empty($where)) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }

            $result = \Db::getInstance()->getValue($sql);

            return $result ? (int) $result : 0;
        }

        $sql = 'SELECT * FROM (' . $unionSql . ') AS s';

        $where = $this->buildWhereClause($params);
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        if (isset($params['sort']) && isset($params['order'])) {
            $sql .= ' ORDER BY ' . $params['sort'] . ' ' . $params['order'];
        }

        $start = 0;
        if (isset($params['start']) && $params['start'] >= 0) {
            $start = (int) $params['start'];
        }
        $limit = $params['default_page_size'];
        if (isset($params['limit']) && $params['limit'] >= 1) {
            $limit = (int) $params['limit'];
        }
        $sql .= ' LIMIT ' . $start . ',' . $limit;

        $rows = \Db::getInstance()->executeS($sql);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $params
     */
    /**
     * @param array<int> $shopIds
     */
    public function getCountSubscribers(array $params = [], array $shopIds = []): int
    {
        return $this->getSubscribers($params, $shopIds, true);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string>
     */
    protected function buildWhereClause(array $params): array
    {
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

        return $where;
    }

    /**
     * @param array<int> $customerIds
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
     * @param array<string, mixed> $subscriber
     * @param array<int, string> $phones
     *
     * @return array<string, mixed>
     */
    /**
     * @param array<int> $shopIds
     */
    public function processSubscriber(array $subscriber, array $phones = [], array $shopIds = []): array
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    protected function isEmailSubscriptionActive(): bool
    {
        $moduleId = (int) \Module::getModuleIdByName('ps_emailsubscription');
        if ($moduleId <= 0) {
            return false;
        }
        $shopId = $this->config->getEffectiveShopId();
        $sql = 'SELECT 1 FROM `' . _DB_PREFIX_ . 'module_shop` WHERE `id_module` = ' . $moduleId . ' AND `id_shop` = ' . $shopId;

        return (bool) \Db::getInstance()->getValue($sql);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getWhereParametersMapping(): array
    {
        return array_merge(parent::getWhereParametersMapping(), [
            'created_at' => [
                'field' => 's.date_added',
                'quote' => true,
                'type' => 'string',
            ],
            'modified_at' => [
                'field' => 's.date_modified',
                'quote' => true,
                'type' => 'string',
            ],
            'subscriber_id' => [
                'field' => 's.subscriber_id',
                'quote' => false,
                'type' => 'int',
            ],
            'subscriber_ids' => [
                'field' => 's.subscriber_id',
                'quote' => false,
                'multiple' => true,
                'force_array' => true,
                'type' => 'int',
            ],
            'customer_id' => [
                'field' => 's.subscriber_id',
                'quote' => false,
                'type' => 'int',
            ],
            'email' => [
                'field' => 's.email',
                'quote' => true,
                'type' => 'string',
            ],
            'firstname' => [
                'field' => 's.firstname',
                'quote' => true,
                'type' => 'string',
            ],
            'lastname' => [
                'field' => 's.lastname',
                'quote' => true,
                'type' => 'string',
            ],
            'status' => [
                'field' => 's.confirmed',
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
            'email' => 's.email',
            'subscriber_id' => 's.subscriber_id',
            'created_at' => 's.date_added',
            'modified_at' => 's.date_modified',
        ]);
    }
}
