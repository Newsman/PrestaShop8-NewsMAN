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

namespace PrestaShop\Module\Newsmanv8\EventSubscriber;

use PrestaShop\Module\Newsmanv8\Action\Subscribe\Email as SubscribeEmailAction;
use PrestaShop\Module\Newsmanv8\Logger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/*
 * Intercepts the ps_emailsubscription admin "Unsubscribe" click for guest
 * subscribers. The module does raw SQL (SET active=0) without dispatching
 * any PrestaShop hook, so we catch the request early via kernel.request
 * and trigger the Newsman unsubscribe API call.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminEmailsubscriptionSubscriber implements EventSubscriberInterface
{
    private SubscribeEmailAction $subscribeEmailAction;
    private Logger $logger;

    public function __construct(
        SubscribeEmailAction $subscribeEmailAction,
        Logger $logger,
    ) {
        $this->subscribeEmailAction = $subscribeEmailAction;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // @phpstan-ignore-next-line
        if (!(method_exists($event, 'isMainRequest') ? $event->isMainRequest() : $event->isMasterRequest())) {
            return;
        }

        $request = $event->getRequest();

        $route = $request->attributes->get('_route');
        $moduleName = $request->attributes->get('module_name', '');

        if ($route !== 'admin_module_configure_action' || $moduleName !== 'ps_emailsubscription') {
            return;
        }

        if (!$request->query->has('subscribedmerged')) {
            return;
        }

        $id = $request->query->get('id', '');
        if (empty($id) || !preg_match('/^N(\d+)$/', (string) $id, $matches)) {
            return;
        }

        $guestId = (int) $matches[1];

        try {
            $email = \Db::getInstance()->getValue(
                'SELECT email FROM ' . _DB_PREFIX_ . 'emailsubscription WHERE id = ' . $guestId . ' AND active = 1'
            );

            if (empty($email)) {
                return;
            }

            $this->subscribeEmailAction->unsubscribe((string) $email);
        } catch (\Exception $e) {
            $this->logger->logException($e);
        }
    }
}
