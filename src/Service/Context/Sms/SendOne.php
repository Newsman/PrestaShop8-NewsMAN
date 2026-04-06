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

namespace PrestaShop\Module\Newsmanv8\Service\Context\Sms;

use PrestaShop\Module\Newsmanv8\Service\Context\Store;

if (!defined('_PS_VERSION_')) {
    exit;
}

class SendOne extends Store
{
    protected string $to = '';
    protected string $text = '';

    public function setTo(string $to): static
    {
        $this->to = $to;

        return $this;
    }

    public function getTo(): string
    {
        return $this->to;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }
}
