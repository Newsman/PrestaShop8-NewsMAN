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

class BaseOrders extends AbstractRetriever implements RetrieverInterface
{
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
        throw new \BadMethodCallException('Not implemented.');
    }

    /**
     * @param array<string, mixed> $params
     * @param array<int> $shopIds
     *
     * @return array<mixed>|int
     */
    public function getOrders(array $params = [], array $shopIds = [], bool $isCount = false)
    {
        $langId = $this->getDefaultLanguageId($shopIds[0] ?? null);

        $sql = 'SELECT ';
        if (!$isCount) {
            $sql .= 'o.*, osl.name AS order_status_name,'
                . ' a.firstname, a.lastname, a.company AS billing_company, a.phone, a.phone_mobile,'
                . ' c.email';
        } else {
            $sql .= 'COUNT(*) AS total';
        }
        $sql .= ' FROM `' . _DB_PREFIX_ . 'orders` o';
        $sql .= ' LEFT JOIN `' . _DB_PREFIX_ . 'order_state_lang` osl'
            . ' ON (o.current_state = osl.id_order_state AND osl.id_lang = ' . (int) $langId . ')';
        $sql .= ' LEFT JOIN `' . _DB_PREFIX_ . 'address` a ON o.id_address_invoice = a.id_address';
        $sql .= ' LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON o.id_customer = c.id_customer';

        $where = [];
        if (!empty($shopIds)) {
            $where[] = 'o.id_shop IN (' . implode(',', array_map('intval', $shopIds)) . ')';
        }

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
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        if (isset($params['sort']) && isset($params['order'])) {
            $sql .= ' ORDER BY ' . $params['sort'] . ' ' . $params['order'];
        } else {
            $sql .= ' ORDER BY o.id_order DESC';
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
}
