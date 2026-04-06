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

class SubscribeEmail extends UnsubscribeEmail
{
    protected string $firstname = '';
    protected string $lastname = '';

    /** @var array<string, mixed> */
    protected array $properties = [];

    public function setFirstname(string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getFirstname(): string
    {
        if (empty($this->firstname)) {
            return self::NULL_VALUE;
        }

        return $this->firstname;
    }

    public function setLastname(string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getLastname(): string
    {
        if (empty($this->lastname)) {
            return self::NULL_VALUE;
        }

        return $this->lastname;
    }

    /**
     * @param array<string, mixed> $properties
     */
    public function setProperties(array $properties): static
    {
        $this->properties = $properties;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }
}
