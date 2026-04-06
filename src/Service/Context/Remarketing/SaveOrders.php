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

namespace PrestaShop\Module\Newsmanv8\Service\Context\Remarketing;

use PrestaShop\Module\Newsmanv8\Service\Context\Store;

if (!defined('_PS_VERSION_')) {
    exit;
}

class SaveOrders extends Store
{
    /** @var array<int, array<string, mixed>> */
    protected array $orders = [];

    /**
     * @param array<int, array<string, mixed>> $orders
     */
    public function setOrders(array $orders): static
    {
        $this->orders = $orders;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOrders(): array
    {
        return $this->orders;
    }
}
