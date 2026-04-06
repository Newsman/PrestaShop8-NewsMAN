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

namespace PrestaShop\Module\Newsmanv8\Action\Order;

use PrestaShop\Module\Newsmanv8\Config;
use PrestaShop\Module\Newsmanv8\Export\Order\StatusMapper;
use PrestaShop\Module\Newsmanv8\Logger;
use PrestaShop\Module\Newsmanv8\Service\Context\Remarketing\SetPurchaseStatus as SetPurchaseStatusContext;
use PrestaShop\Module\Newsmanv8\Service\Remarketing\SetPurchaseStatus;
use PrestaShop\PrestaShop\Adapter\Configuration;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Status
{
    protected Config $config;
    protected Logger $logger;
    protected StatusMapper $statusMapper;
    protected SetPurchaseStatus $setPurchaseStatus;
    protected Configuration $configuration;

    public function __construct(
        Config $config,
        Logger $logger,
        StatusMapper $statusMapper,
        SetPurchaseStatus $setPurchaseStatus,
        Configuration $configuration,
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->statusMapper = $statusMapper;
        $this->setPurchaseStatus = $setPurchaseStatus;
        $this->configuration = $configuration;
    }

    /**
     * Send order status update to Newsman API.
     */
    public function execute(int $orderId, int $newOrderStateId, ?ShopConstraint $shopConstraint = null): void
    {
        if (!$this->config->isEnabled($shopConstraint)) {
            return;
        }

        try {
            $orderStateName = $this->getOrderStateName($newOrderStateId);
            $orderStatus = $this->statusMapper->map($newOrderStateId, $orderStateName);

            $context = new SetPurchaseStatusContext();
            $context->setListId($this->config->getListId($shopConstraint))
                ->setOrderId((string) $orderId)
                ->setOrderStatus($orderStatus);

            $this->setPurchaseStatus->execute($context);
        } catch (\Exception $e) {
            $this->logger->logException($e);
        }
    }

    protected function getOrderStateName(int $orderStateId): string
    {
        $langId = (int) $this->configuration->get('PS_LANG_DEFAULT');
        $sql = 'SELECT name FROM ' . _DB_PREFIX_ . 'order_state_lang'
            . ' WHERE id_order_state = ' . $orderStateId
            . ' AND id_lang = ' . $langId;

        $result = \Db::getInstance()->getValue($sql);

        return $result ? (string) $result : '';
    }
}
