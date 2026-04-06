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

namespace PrestaShop\Module\Newsmanv8\Service\Context;

if (!defined('_PS_VERSION_')) {
    exit;
}

class InitUnsubscribeEmail extends UnsubscribeEmail
{
    /** @var array<string, mixed>|null */
    protected ?array $options = null;

    /**
     * @param array<string, mixed>|null $options
     */
    public function setOptions(?array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOptions(): ?array
    {
        return $this->options;
    }
}
