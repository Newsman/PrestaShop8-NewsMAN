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

namespace PrestaShop\Module\Newsmanv8\Export\Order;

use PrestaShop\PrestaShop\Adapter\Configuration;

if (!defined('_PS_VERSION_')) {
    exit;
}

class StatusMapper
{
    /** @var array<int, string> */
    protected static array $cache = [];

    protected Configuration $configuration;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function map(int $orderStateId, string $orderStatusName = ''): string
    {
        if (isset(self::$cache[$orderStateId])) {
            return self::$cache[$orderStateId];
        }

        $normalized = $this->resolve($orderStateId);

        self::$cache[$orderStateId] = $normalized;

        return $normalized;
    }

    protected function resolve(int $orderStateId): string
    {
        $psChecque = (int) $this->configuration->get('PS_OS_CHEQUE');
        $psBankwire = (int) $this->configuration->get('PS_OS_BANKWIRE');
        $psPayment = (int) $this->configuration->get('PS_OS_PAYMENT');
        $psPreparation = (int) $this->configuration->get('PS_OS_PREPARATION');
        $psShipped = (int) $this->configuration->get('PS_OS_SHIPPING');
        $psDelivered = (int) $this->configuration->get('PS_OS_DELIVERED');
        $psCanceled = (int) $this->configuration->get('PS_OS_CANCELED');
        $psRefund = (int) $this->configuration->get('PS_OS_REFUND');
        $psError = (int) $this->configuration->get('PS_OS_ERROR');
        $psOutOfStockPaid = (int) $this->configuration->get('PS_OS_OUTOFSTOCK_PAID');
        $psOutOfStockUnpaid = (int) $this->configuration->get('PS_OS_OUTOFSTOCK_UNPAID');
        $psCod = (int) $this->configuration->get('PS_OS_COD_VALIDATION');

        $chequeStates = [$psChecque, $psBankwire, $psOutOfStockUnpaid, $psCod];
        $paymentStates = [$psPayment, $psOutOfStockPaid];
        if (in_array($orderStateId, $chequeStates, true)) {
            return 'cheque';
        }
        if (in_array($orderStateId, $paymentStates, true)) {
            return 'payment';
        }
        if ($orderStateId === $psPreparation) {
            return 'preparation';
        }
        if ($orderStateId === $psShipped) {
            return 'shipped';
        }
        if ($orderStateId === $psDelivered) {
            return 'delivered';
        }
        if ($orderStateId === $psCanceled) {
            return 'order_canceled';
        }
        if ($orderStateId === $psRefund) {
            return 'refund';
        }
        if ($orderStateId === $psError) {
            return 'payment_error';
        }

        return $this->slugify($orderStateId);
    }

    protected function slugify(int $orderStateId): string
    {
        $orderState = new \OrderState($orderStateId, (int) \Configuration::get('PS_LANG_DEFAULT'));
        $name = is_array($orderState->name) ? (string) reset($orderState->name) : (string) $orderState->name;

        if (empty($name)) {
            return 'status_' . $orderStateId;
        }

        $slug = mb_strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim($slug, '_');

        return $slug ?: 'status_' . $orderStateId;
    }
}
