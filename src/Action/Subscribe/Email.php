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
    /**
     * Newsman API error code of subscriber.saveUnsubscribe for an email that is not on the list.
     */
    public const API_CODE_NO_SUCH_SUBSCRIBER = 121;

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
            $this->initSubscribeEmail->executeForShop($context, $shopConstraint);

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
            $subscriberId = $this->subscribeEmail->executeForShop($context, $shopConstraint);

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
                    $this->addSubscriber->executeForShop($segContext, $shopConstraint);
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
            $this->unsubscribeEmail->executeForShop($context, $shopConstraint);

            \Hook::exec('actionNewsmanAfterUnsubscribe', [
                'email' => $email,
            ]);
        } catch (\Exception $e) {
            $this->logger->logException($e);
        }
    }

    /**
     * Unsubscribe an email right away because of a personal data erasure request.
     *
     * Always a direct unsubscribe (no confirmation email, also with Double Opt-in), sent with the
     * Newsman account of the given shop. An email that is not on the list counts as done.
     *
     * @return bool False when Newsman could not be reached or returned another error
     */
    public function unsubscribeForErasure(string $email, ShopConstraint $shopConstraint): bool
    {
        if (empty($email) || !$this->isAllow($shopConstraint)) {
            return true;
        }

        $hookResponses = \Hook::exec('actionNewsmanBeforeUnsubscribe', [
            'email' => $email,
        ], null, true);
        if (is_array($hookResponses) && $this->isHookCancelled($hookResponses)) {
            return true;
        }

        $context = new UnsubscribeContext();
        $context->setListId($this->config->getListId($shopConstraint))
            ->setEmail($email)
            ->setIp($this->ipAddress->getIp());

        try {
            $this->unsubscribeEmail->executeForShop($context, $shopConstraint);

            \Hook::exec('actionNewsmanAfterUnsubscribe', [
                'email' => $email,
            ]);

            return true;
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException && self::API_CODE_NO_SUCH_SUBSCRIBER === $e->getCode()) {
                $this->logger->info(sprintf(
                    'Email %s is not on the Newsman list %s, nothing to unsubscribe',
                    $email,
                    $context->getListId()
                ));

                return true;
            }

            $this->logger->logException($e);

            return false;
        }
    }
}
