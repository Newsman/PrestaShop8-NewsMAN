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

namespace PrestaShop\Module\Newsmanv8\Service\Context\Configuration;

if (!defined('_PS_VERSION_')) {
    exit;
}

class SaveListIntegrationSetup extends EmailList
{
    protected string $integration = 'prestashop';

    /** @var array<string, mixed> */
    protected array $payload = [];

    public function setIntegration(string $integration): static
    {
        $this->integration = $integration;

        return $this;
    }

    public function getIntegration(): string
    {
        return $this->integration;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
