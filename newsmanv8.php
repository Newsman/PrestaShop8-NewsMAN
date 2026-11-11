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

use PrestaShop\Module\Newsmanv8\Action\Order\Save as OrderSaveAction;
use PrestaShop\Module\Newsmanv8\Action\Order\Status as OrderStatusAction;
use PrestaShop\Module\Newsmanv8\Action\Subscribe\Email as SubscribeEmailAction;
use PrestaShop\Module\Newsmanv8\Config;
use PrestaShop\Module\Newsmanv8\Logger;
use PrestaShop\Module\Newsmanv8\Remarketing\Renderer as RemarketingRenderer;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;

if (!defined('_PS_VERSION_')) {
    exit;
}

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

/**
 * @see ModuleCore
 */
class Newsmanv8 extends Module
{
    public const HOOKS = [
        'actionCustomerAccountAdd',
        'actionObjectCustomerUpdateBefore',
        'actionObjectCustomerDeleteBefore',
        'actionNewsletterRegistrationAfter',
        // Official GDPR compliance module (psgdpr).
        'actionDeleteGDPRCustomer',
        'registerGDPRConsent',
        'actionValidateOrder',
        'actionOrderStatusPostUpdate',
        'actionFrontControllerSetMedia',
        'displayAfterBodyOpeningTag',
        'displayBeforeBodyClosingTag',
        'displayOrderConfirmation',
        'displayBackOfficeHeader',
        // Custom hooks for 3rd party developers — Action layer.
        'actionNewsmanBeforeSubscribe',
        'actionNewsmanAfterSubscribe',
        'actionNewsmanBeforeUnsubscribe',
        'actionNewsmanAfterUnsubscribe',
        'actionNewsmanBeforeOrderSave',
        'actionNewsmanAfterOrderSave',
        'actionNewsmanWebhookEvent',
        'actionNewsmanBeforeExport',
        'actionNewsmanAfterExport',
        'actionNewsmanRemarketingRender',
        // Service layer hooks (before API calls).
        'actionNewsmanServiceSubscribeEmailBefore',
        'actionNewsmanServiceUnsubscribeEmailBefore',
        'actionNewsmanServiceInitSubscribeEmailBefore',
        'actionNewsmanServiceExportCsvSubscribersBefore',
        'actionNewsmanServiceAddSubscriberBefore',
        'actionNewsmanServiceSaveOrderBefore',
        'actionNewsmanServiceSaveOrdersBefore',
        'actionNewsmanServiceSetPurchaseStatusBefore',
        'actionNewsmanServiceUpdateFeedBefore',
        'actionNewsmanServiceSetFeedOnListBefore',
        'actionNewsmanServiceGetListAllBefore',
        'actionNewsmanServiceGetSegmentAllBefore',
        'actionNewsmanServiceGetSettingsBefore',
        'actionNewsmanServiceSaveListIntegrationSetupBefore',
        // API client hooks.
        'actionNewsmanApiClientGetParamsBefore',
        'actionNewsmanApiClientPostParamsBefore',
        'actionNewsmanApiClientRequestParamsBefore',
        'actionNewsmanApiClientExecuteCurlOptionsBefore',
        // Remarketing script hooks.
        'actionNewsmanRemarketingTrackingScriptAfter',
        'actionNewsmanRemarketingTrackingAttributesBefore',
        // Export retriever hooks.
        'actionNewsmanExportRetrieverProcessListParamsBefore',
        'actionNewsmanExportRetrieverProcessListParamsAfter',
        'actionNewsmanExportRetrieverGetStoreUrlBefore',
        'actionNewsmanExportRetrieverPoolGetRetrieverListBefore',
        'actionNewsmanExportRequestGetAllKnownParametersAfter',
        'actionNewsmanExportRetrieverCustomersProcessFetchAfter',
        'actionNewsmanExportRetrieverCustomersProcessCustomerAfter',
        'actionNewsmanExportRetrieverSubscribersProcessSubscriberAfter',
        'actionNewsmanExportRetrieverOrdersProcessFetchAfter',
        'actionNewsmanExportRetrieverOrdersProcessOrderAfter',
        'actionNewsmanExportRetrieverProductsFeedProcessFetchAfter',
        'actionNewsmanExportRetrieverProductsFeedProcessProductAfter',
        'actionNewsmanExportRetrieverSendSubscribersProcessSubscriberAfter',
        'actionNewsmanExportRetrieverCouponsProcessCouponBefore',
    ];

    public const MODULE_ADMIN_CONTROLLERS = [
        [
            'class_name' => 'Newsmanv8ConfigurationAdminParentController',
            'visible' => false,
            'parent_class_name' => 'AdminParentModulesSf',
            'name' => 'Newsman',
        ],
        [
            'class_name' => 'Newsmanv8ConfigurationAdminController',
            'visible' => true,
            'parent_class_name' => 'Newsmanv8ConfigurationAdminParentController',
            'name' => 'Settings',
        ],
        [
            'class_name' => 'Newsmanv8LogViewerAdminController',
            'visible' => true,
            'parent_class_name' => 'Newsmanv8ConfigurationAdminParentController',
            'name' => 'Logs',
        ],
    ];

    /**
     * Emails already unsubscribed from a Newsman list in this request, by a personal data erasure or by
     * a customer delete, as "email|user ID|list ID" keys, so the other hook does not send the same call
     * again (psgdpr 1.x runs the erasure hook before deleting the customer, psgdpr 2.x deletes first).
     *
     * @var array<string, bool>
     */
    protected static array $unsubscribedEmailLists = [];

    /**
     * Emails of the customers deleted in this request, by customer ID: psgdpr 2.x deletes the customer
     * first and then runs the erasure hook with the customer ID only.
     *
     * @var array<int, string>
     */
    protected static array $deletedCustomerEmails = [];

    public function __construct()
    {
        $this->name = 'newsmanv8';
        $this->tab = 'advertising_marketing';
        $this->version = '8.0.10';
        $this->author = 'Newsman by Dazoot';
        $this->module_key = '00c167886eb6f3c7d318ea806a563b32';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => '8.99.99',
        ];

        parent::__construct();

        $this->displayName = $this->trans('Newsman', [], 'Modules.Newsmanv8.Admin');
        $this->description = $this->trans(
            'Newsman email marketing and SMS integration for PrestaShop.',
            [],
            'Modules.Newsmanv8.Admin'
        );

        $this->controllers = ['api', 'cart'];
    }

    /**
     * @return bool
     */
    public function install()
    {
        return parent::install()
            && $this->registerHook(static::HOOKS)
            && $this->installTab()
            && $this->installDefaultConfiguration();
    }

    /**
     * Set default configuration values.
     *
     * @return bool
     */
    public function installDefaultConfiguration(): bool
    {
        /** @var PrestaShop\PrestaShop\Adapter\Configuration $configuration */
        $configuration = $this->get('prestashop.adapter.legacy.configuration');

        $configuration->set(Config::KEY_ACTIVE, 1);
        $configuration->set(Config::KEY_SEND_USER_IP, 1);
        $configuration->set(Config::KEY_SERVER_IP, '');
        $configuration->set(Config::KEY_REMARKETING_STATUS, 1);
        $configuration->set(Config::KEY_REMARKETING_SEND_TELEPHONE, 1);
        $configuration->set(Config::KEY_REMARKETING_THEME_CART_COMPATIBILITY, 1);
        $configuration->set(Config::KEY_LOG_SEVERITY, Config::LOG_ERROR);
        $configuration->set(Config::KEY_LOG_CLEAN_DAYS, Config::DEFAULT_LOG_CLEAN_DAYS);
        $configuration->set(Config::KEY_API_TIMEOUT, Config::DEFAULT_API_TIMEOUT);
        $configuration->set(Config::KEY_DEV_ACTIVE_USER_IP, 0);
        $configuration->set(Config::KEY_DEV_USER_IP, '');

        return true;
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        return $this->uninstallTab()
            && $this->removeConfiguration()
            && parent::uninstall();
    }

    /**
     * Redirect to Symfony admin controller.
     */
    public function getContent()
    {
        /** @var \Symfony\Component\Routing\RouterInterface $router */
        $router = $this->get('router');
        Tools::redirectAdmin(
            $router->generate('newsmanv8_configuration')
        );

        return '';
    }

    /**
     * Install admin tabs for Symfony controllers.
     *
     * @return bool
     */
    protected function installTab(): bool
    {
        foreach (static::MODULE_ADMIN_CONTROLLERS as $controller) {
            $tab = new Tab();
            $tab->class_name = $controller['class_name'];
            $tab->module = $this->name;
            $tab->active = true;

            $parentId = (int) Db::getInstance()->getValue(
                'SELECT `id_tab` FROM `' . _DB_PREFIX_ . 'tab` WHERE `class_name` = \'' . pSQL($controller['parent_class_name']) . '\''
            );
            $tab->id_parent = $parentId ?: -1;

            $languages = Language::getLanguages(false);
            foreach ($languages as $lang) {
                $tab->name[$lang['id_lang']] = $controller['name'];
            }

            if (!$tab->add()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Remove admin tabs.
     *
     * @return bool
     */
    protected function uninstallTab(): bool
    {
        foreach (array_reverse(static::MODULE_ADMIN_CONTROLLERS) as $controller) {
            $tabId = (int) Db::getInstance()->getValue(
                'SELECT `id_tab` FROM `' . _DB_PREFIX_ . 'tab` WHERE `class_name` = \'' . pSQL($controller['class_name']) . '\''
            );
            if ($tabId) {
                $tab = new Tab($tabId);
                if (!$tab->delete()) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Remove all module configuration values.
     *
     * @return bool
     */
    protected function removeConfiguration(): bool
    {
        $keys = Config::getAllKeys();
        foreach ($keys as $key) {
            Configuration::deleteByName($key);
        }

        return true;
    }

    /**
     * Hook: remarketing page events before body closing tag.
     *
     * Area: Front
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayAfterBodyOpeningTag(array $params): string
    {
        try {
            /** @var RemarketingRenderer $renderer */
            $renderer = $this->get(RemarketingRenderer::class);

            return $renderer->renderTrackingScript($this->context);
        } catch (Exception $e) {
            /** @var Logger $logger */
            $logger = $this->get(Logger::class);
            $logger->logException($e);

            return '';
        }
    }

    public function hookDisplayBeforeBodyClosingTag(array $params): string
    {
        try {
            /** @var RemarketingRenderer $renderer */
            $renderer = $this->get(RemarketingRenderer::class);

            return $renderer->renderBodyClosingTag($this->context);
        } catch (Exception $e) {
            /** @var Logger $logger */
            $logger = $this->get(Logger::class);
            $logger->logException($e);

            return '';
        }
    }

    /**
     * Hook: purchase tracking on order confirmation page.
     *
     * Area: Front
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayOrderConfirmation(array $params): string
    {
        if (!isset($params['order'])) {
            return '';
        }

        try {
            /** @var Order $order */
            $order = $params['order'];
            $shopId = (int) $order->id_shop ?: null;

            /** @var RemarketingRenderer $renderer */
            $renderer = $this->get(RemarketingRenderer::class);

            return $renderer->renderPurchaseTracking($order, Config::shopConstraint($shopId));
        } catch (Exception $e) {
            /** @var Logger $logger */
            $logger = $this->get(Logger::class);
            $logger->logException($e);

            return '';
        }
    }

    /**
     * Hook: admin CSS/JS.
     *
     * Area: Admin
     *
     * @param array $params
     *
     * @return void
     */
    public function hookDisplayBackOfficeHeader(array $params): void
    {
        $controller = Tools::getValue('controller');

        if ('AdminDashboard' === $controller) {
            try {
                /** @var PrestaShop\Module\Newsmanv8\Util\LogFileReader $logReader */
                $logReader = $this->get(PrestaShop\Module\Newsmanv8\Util\LogFileReader::class);
                $logReader->cleanOldLogs();
            } catch (Exception $e) {
                // Silently ignore — cleanup is best-effort.
            }
        }
    }

    /**
     * Hook: new customer registration -> subscribe to Newsman.
     *
     * Area: Front, Admin
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionCustomerAccountAdd(array $params): void
    {
        if (!isset($params['newCustomer'])) {
            return;
        }

        /** @var Customer $customer */
        $customer = $params['newCustomer'];

        if (empty($customer->email) || !$customer->newsletter) {
            return;
        }

        $shopConstraint = Config::shopConstraint((int) $customer->id_shop ?: null);
        /** @var Config $config */
        $config = $this->get(Config::class);
        if (!$config->isEnabled($shopConstraint)) {
            return;
        }

        /** @var SubscribeEmailAction $action */
        $action = $this->get(SubscribeEmailAction::class);
        $action->subscribe(
            $customer->email,
            $customer->firstname,
            $customer->lastname,
            [],
            [],
            $shopConstraint
        );
    }

    /**
     * Hook: customer update (newsletter toggle, active toggle, soft delete) -> subscribe/unsubscribe.
     *
     * Area: Front, Admin
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionObjectCustomerUpdateBefore(array $params): void
    {
        if (!isset($params['object'])) {
            return;
        }

        /** @var Customer $customer */
        $customer = $params['object'];

        if (empty($customer->id) || empty($customer->email)) {
            return;
        }

        $existingCustomer = new Customer($customer->id);
        if (!Validate::isLoadedObject($existingCustomer)) {
            return;
        }

        $shopConstraint = Config::shopConstraint((int) $customer->id_shop ?: null);
        /** @var Config $config */
        $config = $this->get(Config::class);
        if (!$config->isEnabled($shopConstraint)) {
            return;
        }

        /** @var SubscribeEmailAction $action */
        $action = $this->get(SubscribeEmailAction::class);

        $oldNewsletter = (bool) $existingCustomer->newsletter;
        $newNewsletter = (bool) $customer->newsletter;

        if ($oldNewsletter !== $newNewsletter) {
            if ($newNewsletter && !$oldNewsletter) {
                $action->subscribe(
                    $customer->email,
                    $customer->firstname,
                    $customer->lastname,
                    [],
                    [],
                    $shopConstraint
                );
            } elseif (!$newNewsletter && $oldNewsletter) {
                $action->unsubscribe($customer->email, $shopConstraint);
            }

            return;
        }

        $oldDeleted = (bool) $existingCustomer->deleted;
        $newDeleted = (bool) $customer->deleted;

        if (!$oldDeleted && $newDeleted && $oldNewsletter) {
            $action->unsubscribe($customer->email, $shopConstraint);
        }
    }

    /**
     * Hook: customer hard delete -> unsubscribe from Newsman.
     *
     * Area: Front, Admin
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionObjectCustomerDeleteBefore(array $params): void
    {
        if (!isset($params['object'])) {
            return;
        }

        /** @var Customer $customer */
        $customer = $params['object'];

        if (!empty($customer->id) && !empty($customer->email)) {
            static::$deletedCustomerEmails[(int) $customer->id] = (string) $customer->email;
        }

        if (empty($customer->email) || !$customer->newsletter) {
            return;
        }

        $shopConstraint = Config::shopConstraint((int) $customer->id_shop ?: null);
        /** @var Config $config */
        $config = $this->get(Config::class);
        if (!$config->isEnabled($shopConstraint)) {
            return;
        }

        // psgdpr 1.x runs actionDeleteGDPRCustomer first and then deletes the customer:
        // the email may already be unsubscribed from this list in this request.
        $key = $this->getErasedEmailListKey($customer->email, $config, $shopConstraint);
        if (isset(static::$unsubscribedEmailLists[$key])) {
            return;
        }
        static::$unsubscribedEmailLists[$key] = true;

        /** @var SubscribeEmailAction $action */
        $action = $this->get(SubscribeEmailAction::class);
        $action->unsubscribe($customer->email, $shopConstraint);
    }

    /**
     * Hook: personal data erasure in the Official GDPR compliance module (psgdpr), for a customer
     * account or for an email only (e.g. a newsletter subscriber without an account).
     *
     * The email is unsubscribed right away from the Newsman list of every shop, whatever the shop
     * stores locally, because it can be on the list from other sources. A Newsman error is logged
     * and never stops the erasure.
     *
     * Area: Admin
     *
     * psgdpr 1.x passes the customer fields, or ['email' => ...], or ['phone' => ...]. psgdpr 2.x wraps
     * the payload in $params[0]: ['email' => ...], ['phone' => ...], or the ID of the customer it has
     * just deleted (the email is then taken from hookActionObjectCustomerDeleteBefore).
     *
     * @param array<int|string, mixed> $params
     *
     * @return string json_encode(true), or json_encode() of an error message, as psgdpr expects
     */
    public function hookActionDeleteGDPRCustomer(array $params): string
    {
        $email = $this->getErasureEmail($params);
        if ('' === $email || !Validate::isEmail($email)) {
            return (string) json_encode(true);
        }

        $done = true;
        try {
            /** @var Config $config */
            $config = $this->get(Config::class);
            /** @var SubscribeEmailAction $action */
            $action = $this->get(SubscribeEmailAction::class);

            foreach ($config->getShopConstraintsPerList() as $shopConstraint) {
                // psgdpr 2.x: the customer delete may already have unsubscribed the email from this list.
                $key = $this->getErasedEmailListKey($email, $config, $shopConstraint);
                if (isset(static::$unsubscribedEmailLists[$key])) {
                    continue;
                }
                static::$unsubscribedEmailLists[$key] = true;
                if (!$action->unsubscribeForErasure($email, $shopConstraint)) {
                    $done = false;
                }
            }
        } catch (Throwable $e) {
            $done = false;
            try {
                /** @var Logger $logger */
                $logger = $this->get(Logger::class);
                $logger->logException($e);
            } catch (Throwable $loggerError) {
                // Logging is best-effort, the erasure must go on.
            }
        }

        if ($done) {
            return (string) json_encode(true);
        }

        return (string) json_encode($this->trans(
            'Newsman: the email could not be unsubscribed from the Newsman list. See the Newsman logs.',
            [],
            'Modules.Newsmanv8.Admin'
        ));
    }

    /**
     * Hook: lists Newsman among the compliant modules of the Official GDPR compliance module (psgdpr).
     *
     * psgdpr only reads which modules are registered on this hook and never runs it. Newsman has no
     * storefront form of its own, so there is no consent checkbox to show.
     *
     * Area: Admin
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function hookRegisterGDPRConsent(array $params): void
    {
    }

    /**
     * Email of a personal data erasure request, for psgdpr 1.x and 2.x payloads.
     *
     * @param array<int|string, mixed> $params
     */
    protected function getErasureEmail(array $params): string
    {
        $data = $params;
        if (!isset($params['email']) && array_key_exists(0, $params)) {
            $data = $params[0];
        }

        if (is_array($data)) {
            return isset($data['email']) && is_string($data['email']) ? trim($data['email']) : '';
        }

        if ((is_int($data) || (is_string($data) && ctype_digit($data))) && isset(static::$deletedCustomerEmails[(int) $data])) {
            return trim(static::$deletedCustomerEmails[(int) $data]);
        }

        return '';
    }

    /**
     * Key of an email unsubscribed from a Newsman list in this request.
     */
    protected function getErasedEmailListKey(string $email, Config $config, ?ShopConstraint $shopConstraint): string
    {
        return strtolower(trim($email)) . '|' . $config->getUserId($shopConstraint) . '|' . $config->getListId($shopConstraint);
    }

    /**
     * Hook: ps_emailsubscription newsletter form subscribe/unsubscribe.
     *
     * Handles guest visitors and customers who subscribe/unsubscribe via the
     * newsletter form (ps_emailsubscription uses direct SQL, so ObjectModel
     * hooks do not fire).
     *
     * Area: Front
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionNewsletterRegistrationAfter(array $params): void
    {
        if (!empty($params['error']) || empty($params['email'])) {
            return;
        }

        /** @var Config $config */
        $config = $this->get(Config::class);
        if (!$config->isEnabled()) {
            return;
        }

        $email = (string) $params['email'];
        $actionType = isset($params['action']) ? (int) $params['action'] : -1;

        /** @var SubscribeEmailAction $action */
        $action = $this->get(SubscribeEmailAction::class);

        if (0 === $actionType) {
            $action->subscribeSingleOptin($email, '', '', []);
        } elseif (1 === $actionType) {
            $action->unsubscribe($email);
        }
    }

    /**
     * Hook: new order placed -> send to Newsman remarketing.
     *
     * Area: Front, Admin
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionValidateOrder(array $params): void
    {
        if (!isset($params['order'])) {
            return;
        }

        /** @var Order $order */
        $order = $params['order'];

        if (empty($order->id)) {
            return;
        }

        $shopConstraint = Config::shopConstraint((int) $order->id_shop ?: null);
        /** @var Config $config */
        $config = $this->get(Config::class);
        if (!$config->isEnabled($shopConstraint)) {
            return;
        }

        /** @var OrderSaveAction $saveAction */
        $saveAction = $this->get(OrderSaveAction::class);
        $saveAction->execute((int) $order->id, true, $shopConstraint);

        // Subscribe customer to Newsman if they opted in for newsletter during checkout.
        // Only subscribe if newsletter_date_add is within the last hour to avoid
        // re-subscribing customers who were already synced from a previous order.
        if (isset($params['customer']) && $params['customer'] instanceof Customer) {
            /** @var Customer $customer */
            $customer = $params['customer'];
            if (!empty($customer->email) && $customer->newsletter) {
                $newsletterDate = (!empty($customer->newsletter_date_add) && $customer->newsletter_date_add !== '0000-00-00 00:00:00')
                    ? strtotime($customer->newsletter_date_add)
                    : time();
                $oneHourAgo = time() - 3600;
                if ($newsletterDate !== false && $newsletterDate >= $oneHourAgo) {
                    /** @var SubscribeEmailAction $subscribeAction */
                    $subscribeAction = $this->get(SubscribeEmailAction::class);
                    $subscribeAction->subscribe(
                        $customer->email,
                        $customer->firstname,
                        $customer->lastname,
                        [],
                        [],
                        $shopConstraint
                    );
                }
            }
        }
    }

    /**
     * Hook: order status change -> update Newsman.
     *
     * Area: Front, Admin
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionOrderStatusPostUpdate(array $params): void
    {
        if (!isset($params['id_order']) || !isset($params['newOrderStatus'])) {
            return;
        }

        $orderId = (int) $params['id_order'];
        /** @var OrderState $newOrderStatus */
        $newOrderStatus = $params['newOrderStatus'];

        if (empty($orderId) || empty($newOrderStatus->id)) {
            return;
        }

        $order = new Order($orderId);
        $shopConstraint = Config::shopConstraint(
            Validate::isLoadedObject($order) ? (int) $order->id_shop : null
        );
        /** @var Config $config */
        $config = $this->get(Config::class);
        if (!$config->isEnabled($shopConstraint)) {
            return;
        }

        // $statusAction = $this->get(OrderStatusAction::class);
        // $statusAction->execute($orderId, (int) $newOrderStatus->id, $shopConstraint);

        /** @var OrderSaveAction $saveAction */
        $saveAction = $this->get(OrderSaveAction::class);
        $saveAction->execute($orderId, false, $shopConstraint);
    }

    /**
     * Hook: enqueue front-end assets.
     *
     * Area: Front
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionFrontControllerSetMedia(array $params): void
    {
    }

    // Custom hooks for 3rd party developers — Action layer.

    public function hookActionNewsmanBeforeSubscribe(array $params): void
    {
    }

    public function hookActionNewsmanAfterSubscribe(array $params): void
    {
    }

    public function hookActionNewsmanBeforeUnsubscribe(array $params): void
    {
    }

    public function hookActionNewsmanAfterUnsubscribe(array $params): void
    {
    }

    public function hookActionNewsmanBeforeOrderSave(array $params): void
    {
    }

    public function hookActionNewsmanAfterOrderSave(array $params): void
    {
    }

    public function hookActionNewsmanWebhookEvent(array $params): void
    {
    }

    public function hookActionNewsmanBeforeExport(array $params): void
    {
    }

    public function hookActionNewsmanAfterExport(array $params): void
    {
    }

    public function hookActionNewsmanRemarketingRender(array $params): void
    {
    }

    // Service layer hooks (before API calls).

    public function hookActionNewsmanServiceSubscribeEmailBefore(array $params): void
    {
    }

    public function hookActionNewsmanServiceUnsubscribeEmailBefore(array $params): void
    {
    }

    public function hookActionNewsmanServiceInitSubscribeEmailBefore(array $params): void
    {
    }

    public function hookActionNewsmanServiceExportCsvSubscribersBefore(array $params): void
    {
    }

    public function hookActionNewsmanServiceAddSubscriberBefore(array $params): void
    {
    }

    public function hookActionNewsmanServiceSaveOrderBefore(array $params): void
    {
    }

    public function hookActionNewsmanServiceSaveOrdersBefore(array $params): void
    {
    }

    public function hookActionNewsmanServiceSetPurchaseStatusBefore(array $params): void
    {
    }

    public function hookActionNewsmanServiceUpdateFeedBefore(array $params): void
    {
    }

    public function hookActionNewsmanServiceSetFeedOnListBefore(array $params): void
    {
    }

    public function hookActionNewsmanServiceGetListAllBefore(array $params): void
    {
    }

    public function hookActionNewsmanServiceGetSegmentAllBefore(array $params): void
    {
    }

    public function hookActionNewsmanServiceGetSettingsBefore(array $params): void
    {
    }

    public function hookActionNewsmanServiceSaveListIntegrationSetupBefore(array $params): void
    {
    }

    // API client hooks.

    public function hookActionNewsmanApiClientGetParamsBefore(array $params): void
    {
    }

    public function hookActionNewsmanApiClientPostParamsBefore(array $params): void
    {
    }

    public function hookActionNewsmanApiClientRequestParamsBefore(array $params): void
    {
    }

    public function hookActionNewsmanApiClientExecuteCurlOptionsBefore(array $params): void
    {
    }

    // Remarketing script hooks.

    public function hookActionNewsmanRemarketingTrackingScriptAfter(array $params): void
    {
    }

    public function hookActionNewsmanRemarketingTrackingAttributesBefore(array $params): void
    {
    }

    // Export retriever hooks.

    public function hookActionNewsmanExportRetrieverProcessListParamsBefore(array $params): void
    {
    }

    public function hookActionNewsmanExportRetrieverProcessListParamsAfter(array $params): void
    {
    }

    public function hookActionNewsmanExportRetrieverGetStoreUrlBefore(array $params): void
    {
    }

    public function hookActionNewsmanExportRetrieverPoolGetRetrieverListBefore(array $params): void
    {
    }

    public function hookActionNewsmanExportRequestGetAllKnownParametersAfter(array $params): void
    {
    }

    public function hookActionNewsmanExportRetrieverCustomersProcessFetchAfter(array $params): void
    {
    }

    public function hookActionNewsmanExportRetrieverCustomersProcessCustomerAfter(array $params): void
    {
    }

    public function hookActionNewsmanExportRetrieverSubscribersProcessSubscriberAfter(array $params): void
    {
    }

    public function hookActionNewsmanExportRetrieverOrdersProcessFetchAfter(array $params): void
    {
    }

    public function hookActionNewsmanExportRetrieverOrdersProcessOrderAfter(array $params): void
    {
    }

    public function hookActionNewsmanExportRetrieverProductsFeedProcessFetchAfter(array $params): void
    {
    }

    public function hookActionNewsmanExportRetrieverProductsFeedProcessProductAfter(array $params): void
    {
    }

    public function hookActionNewsmanExportRetrieverSendSubscribersProcessSubscriberAfter(array $params): void
    {
    }

    public function hookActionNewsmanExportRetrieverCouponsProcessCouponBefore(array $params): void
    {
    }
}
