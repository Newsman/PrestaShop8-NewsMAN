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

namespace PrestaShop\Module\Newsmanv8\Service;

use PrestaShop\Module\Newsmanv8\Api\Client;
use PrestaShop\Module\Newsmanv8\Api\ClientInterface;
use PrestaShop\Module\Newsmanv8\Api\Context as ApiContext;
use PrestaShop\Module\Newsmanv8\Api\ContextInterface;
use PrestaShop\Module\Newsmanv8\Config;
use PrestaShop\Module\Newsmanv8\Logger;
use PrestaShop\Module\Newsmanv8\Service\Context\AbstractContext;
use PrestaShop\Module\Newsmanv8\Validator\Email as EmailValidator;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;

if (!defined('_PS_VERSION_')) {
    exit;
}

abstract class AbstractService implements ServiceInterface
{
    protected Config $config;
    protected Logger $logger;
    protected Client $apiClient;
    protected EmailValidator $emailValidator;
    protected ?ShopConstraint $shopConstraint = null;

    public function __construct(
        Config $config,
        Logger $logger,
        Client $apiClient,
        EmailValidator $emailValidator,
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->apiClient = $apiClient;
        $this->emailValidator = $emailValidator;
    }

    /**
     * @return array<mixed>|string
     */
    public function execute(AbstractContext $context): array|string
    {
        return [];
    }

    public function setShopConstraint(?ShopConstraint $shopConstraint): static
    {
        $this->shopConstraint = $shopConstraint;

        return $this;
    }

    public function getShopConstraint(): ?ShopConstraint
    {
        return $this->shopConstraint;
    }

    protected function createApiContext(): ContextInterface
    {
        $context = new ApiContext();
        $context->setUserId($this->config->getUserId($this->shopConstraint))
            ->setApiKey($this->config->getApiKey($this->shopConstraint));

        return $context;
    }

    protected function createApiClient(): ClientInterface
    {
        return $this->apiClient;
    }

    /**
     * Dispatch a "before" hook for this service.
     *
     * Auto-generates the hook name from the concrete class name.
     * E.g. Service\SubscribeEmail => actionNewsmanServiceSubscribeEmailBefore
     *      Service\Configuration\GetListAll => actionNewsmanServiceConfigurationGetListAllBefore
     *
     * 3rd party modules can listen to these hooks to inspect or modify
     * the context object before the API call is made.
     */
    protected function dispatchServiceHookBefore(AbstractContext $context): void
    {
        $className = get_class($this);
        $pos = strpos($className, 'Service\\');
        if ($pos !== false) {
            $suffix = substr($className, $pos + 8);
        } else {
            $suffix = (new \ReflectionClass($this))->getShortName();
        }
        $hookName = 'actionNewsmanService' . str_replace('\\', '', $suffix) . 'Before';
        \Hook::exec($hookName, ['context' => $context]);
    }

    /**
     * @throws \RuntimeException
     */
    protected function validateEmail(string $email): void
    {
        if (!$this->emailValidator->isValid($email)) {
            $e = new \RuntimeException(sprintf('Invalid email address %s', $email));
            $this->logger->logException($e);

            throw $e;
        }
    }
}
