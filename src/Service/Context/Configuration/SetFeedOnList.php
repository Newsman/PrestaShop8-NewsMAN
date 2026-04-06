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

class SetFeedOnList extends EmailList
{
    protected string $url = '';
    protected string $website = '';
    protected string $type = 'fixed';

    /** @var bool|string */
    protected bool|string $returnId = false;

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setWebsite(string $website): static
    {
        $this->website = $website;

        return $this;
    }

    public function getWebsite(): string
    {
        return $this->website;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setReturnId(bool|string $returnId): static
    {
        $this->returnId = $returnId;

        return $this;
    }

    public function getReturnId(): bool|string
    {
        return $this->returnId;
    }
}
