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

namespace PrestaShop\Module\Newsmanv8\Action\Subscribe;

use PrestaShop\Module\Newsmanv8\Config;
use PrestaShop\Module\Newsmanv8\Logger;
use PrestaShop\Module\Newsmanv8\Service\Context\InitSubscribeEmail as InitSubscribeContext;
use PrestaShop\Module\Newsmanv8\Service\Context\Segment\AddSubscriber as AddSubscriberContext;
use PrestaShop\Module\Newsmanv8\Service\Context\SubscribeEmail as SubscribeContext;
use PrestaShop\Module\Newsmanv8\Service\Context\UnsubscribeEmail as UnsubscribeContext;
use PrestaShop\Module\Newsmanv8\Service\InitSubscribeEmail;
use PrestaShop\Module\Newsmanv8\Service\Segment\AddSubscriber;
use PrestaShop\Module\Newsmanv8\Service\SubscribeEmail;
use PrestaShop\Module\Newsmanv8\Service\UnsubscribeEmail;
use PrestaShop\Module\Newsmanv8\User\IpAddress;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Email
{
    protected Config $config;
    protected Logger $logger;
    protected InitSubscribeEmail $initSubscribeEmail;
    protected SubscribeEmail $subscribeEmail;
    protected UnsubscribeEmail $unsubscribeEmail;
    protected AddSubscriber $addSubscriber;
    protected IpAddress $ipAddress;

    public function __construct(
        Config $config,
        Logger $logger,
        InitSubscribeEmail $initSubscribeEmail,
        SubscribeEmail $subscribeEmail,
        UnsubscribeEmail $unsubscribeEmail,
        AddSubscriber $addSubscriber,
        IpAddress $ipAddress,
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->initSubscribeEmail = $initSubscribeEmail;
        $this->subscribeEmail = $subscribeEmail;
        $this->unsubscribeEmail = $unsubscribeEmail;
        $this->addSubscriber = $addSubscriber;
        $this->ipAddress = $ipAddress;
    }

    public function isAllow(?ShopConstraint $shopConstraint = null): bool
    {
        return $this->config->isEnabled($shopConstraint);
    }

    /**
     * Check if any module returned a cancel signal from a hook.
     *
     * 3rd party modules can return ['cancel' => true] from before-hooks
     * to prevent the action from executing.
     *
     * @param array<string, mixed> $hookResponses
     */
    protected function isHookCancelled(array $hookResponses): bool
    {
        foreach ($hookResponses as $response) {
            if (is_array($response) && !empty($response['cancel'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $properties
     * @param array<string, mixed> $options
     */
    public function subscribe(
        string $email,
        string $firstname,
        string $lastname,
        array $properties = [],
        array $options = [],
        ?ShopConstraint $shopConstraint = null,
    ): void {
        if (empty($email) || !$this->isAllow($shopConstraint)) {
            return;
        }

        if ($this->config->isDoubleOptin($shopConstraint)) {
            $this->subscribeDoubleOptin($email, $firstname, $lastname, $properties, $options, $shopConstraint);
        } else {
            $this->subscribeSingleOptin($email, $firstname, $lastname, $properties, $shopConstraint);
        }
    }

    /**
     * @param array<string, mixed> $properties
     * @param array<string, mixed> $options
     */
    public function subscribeDoubleOptin(
        string $email,
        string $firstname,
        string $lastname,
        array $properties = [],
        array $options = [],
        ?ShopConstraint $shopConstraint = null,
    ): void {
        $hookParams = [
            'email' => $email,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'properties' => $properties,
        ];
        $hookResponses = \Hook::exec('actionNewsmanBeforeSubscribe', $hookParams, null, true);
        if (is_array($hookResponses) && $this->isHookCancelled($hookResponses)) {
            return;
        }

        $context = new InitSubscribeContext();
        $context->setListId($this->config->getListId($shopConstraint))
            ->setEmail($email)
            ->setFirstname($firstname)
            ->setLastname($lastname)
            ->setIp($this->ipAddress->getIp())
            ->setProperties($properties)
            ->setOptions($options);

        try {
            $this->initSubscribeEmail->execute($context);

            \Hook::exec('actionNewsmanAfterSubscribe', [
                'email' => $email,
                'firstname' => $firstname,
                'lastname' => $lastname,
            ]);
        } catch (\Exception $e) {
            $this->logger->logException($e);
        }
    }

    /**
     * @param array<string, mixed> $properties
     */
    public function subscribeSingleOptin(
        string $email,
        string $firstname,
        string $lastname,
        array $properties = [],
        ?ShopConstraint $shopConstraint = null,
    ): void {
        $hookParams = [
            'email' => $email,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'properties' => $properties,
        ];
        $hookResponses = \Hook::exec('actionNewsmanBeforeSubscribe', $hookParams, null, true);
        if (is_array($hookResponses) && $this->isHookCancelled($hookResponses)) {
            return;
        }

        $context = new SubscribeContext();
        $context->setListId($this->config->getListId($shopConstraint))
            ->setEmail($email)
            ->setFirstname($firstname)
            ->setLastname($lastname)
            ->setIp($this->ipAddress->getIp())
            ->setProperties($properties);

        try {
            $subscriberId = $this->subscribeEmail->execute($context);

            \Hook::exec('actionNewsmanAfterSubscribe', [
                'email' => $email,
                'firstname' => $firstname,
                'lastname' => $lastname,
            ]);

            $segmentId = $this->config->getSegmentId($shopConstraint);
            if (!empty($segmentId) && !empty($subscriberId)) {
                $segContext = new AddSubscriberContext();
                $segContext->setListId($this->config->getListId($shopConstraint))
                    ->setSegmentId($segmentId)
                    ->setSubscriberId((string) $subscriberId);

                try {
                    $this->addSubscriber->execute($segContext);
                } catch (\Exception $e) {
                    $this->logger->logException($e);
                }
            }
        } catch (\Exception $e) {
            $this->logger->logException($e);
        }
    }

    public function unsubscribe(string $email, ?ShopConstraint $shopConstraint = null): void
    {
        if (empty($email) || !$this->isAllow($shopConstraint)) {
            return;
        }

        $hookResponses = \Hook::exec('actionNewsmanBeforeUnsubscribe', [
            'email' => $email,
        ], null, true);
        if (is_array($hookResponses) && $this->isHookCancelled($hookResponses)) {
            return;
        }

        $context = new UnsubscribeContext();
        $context->setListId($this->config->getListId($shopConstraint))
            ->setEmail($email)
            ->setIp($this->ipAddress->getIp());

        try {
            $this->unsubscribeEmail->execute($context);

            \Hook::exec('actionNewsmanAfterUnsubscribe', [
                'email' => $email,
            ]);
        } catch (\Exception $e) {
            $this->logger->logException($e);
        }
    }
}
