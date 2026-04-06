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

namespace PrestaShop\Module\Newsmanv8\Export\Retriever;

use PrestaShop\Module\Newsmanv8\Export\V1\ApiV1Exception;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Coupons extends AbstractRetriever implements RetrieverInterface
{
    /**
     * @param array<string, mixed> $data
     *
     * @return array<mixed>
     */
    public function process(array $data = [], array $shopIds = []): array
    {
        $shopId = $shopIds[0] ?? null;
        $this->logger->info(sprintf('Add coupons: %s', json_encode($data)));

        $isV1 = isset($data['_v1_filter_fields']);
        $batchSize = !isset($data['batch_size']) ? 1 : (int) $data['batch_size'];
        $prefix = !isset($data['prefix']) ? '' : $data['prefix'];
        $expireDate = isset($data['expire_date']) ? $data['expire_date'] : null;
        $minAmount = !isset($data['min_amount']) ? -1 : (float) $data['min_amount'];

        if (!isset($data['type'])) {
            if ($isV1) {
                throw new ApiV1Exception(8001, 'Missing "type" parameter', 400);
            }

            return ['status' => 0, 'msg' => 'Missing type param'];
        }

        $discountType = (int) $data['type'];
        if (!in_array($discountType, [0, 1], true)) {
            if ($isV1) {
                throw new ApiV1Exception(8002, 'Invalid "type" parameter: must be 0 (fixed) or 1 (percent)', 400);
            }

            return ['status' => 0, 'msg' => 'Invalid type param'];
        }

        if (!isset($data['value'])) {
            if ($isV1) {
                throw new ApiV1Exception(8003, 'Missing "value" parameter', 400);
            }

            return ['status' => 0, 'msg' => 'Missing value param'];
        }

        $value = (float) $data['value'];
        if ($value <= 0) {
            if ($isV1) {
                throw new ApiV1Exception(8004, 'Invalid "value" parameter: must be greater than 0', 400);
            }

            return ['status' => 0, 'msg' => 'Invalid value param'];
        }

        if ($batchSize < 1 && $isV1) {
            throw new ApiV1Exception(8005, 'Invalid "batch_size" parameter: must be >= 1', 400);
        }

        if (null !== $expireDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expireDate) && $isV1) {
            throw new ApiV1Exception(8006, 'Invalid "expire_date" format: expected YYYY-MM-DD', 400);
        }

        try {
            $couponsCodes = [];
            for ($step = 0; $step < $batchSize; ++$step) {
                $couponCode = $this->processCoupon($discountType, $prefix, $expireDate, $value, $minAmount, $shopId, $shopIds);
                $couponsCodes[] = $couponCode;
            }

            $this->logger->info(sprintf('Added %d coupons %s', count($couponsCodes), implode(', ', $couponsCodes)));

            return ['status' => 1, 'codes' => $couponsCodes];
        } catch (\Exception $e) {
            $this->logger->logException($e);

            if ($isV1) {
                throw new ApiV1Exception(8007, 'Failed to create coupons', 500);
            }

            return ['status' => 0, 'msg' => $e->getMessage()];
        }
    }

    /**
     * Create a cart rule (coupon) in PrestaShop.
     */
    protected function processCoupon(
        int $discountType,
        string $prefix,
        ?string $expireDate,
        float $value,
        float $minAmount,
        ?int $shopId = null,
        array $shopIds = [],
    ): string {
        $fullCouponCode = $this->generateCouponCode($prefix);

        $dateFrom = date('Y-m-d H:i:s');
        $dateTo = ($expireDate !== null)
            ? date('Y-m-d H:i:s', strtotime($expireDate . ' 23:59:59'))
            : date('Y-m-d H:i:s', strtotime('+5 year'));

        $reductionPercent = 0.0;
        $reductionAmount = 0.0;
        if ($discountType === 1) {
            $reductionPercent = $value;
        } else {
            $reductionAmount = $value;
        }

        $minAmountValue = ($minAmount > 0) ? $minAmount : 0;

        \Hook::exec('actionNewsmanExportRetrieverCouponsProcessCouponBefore', [
            'coupon_code' => $fullCouponCode,
            'discount_type' => $discountType,
            'value' => $value,
            'expire_date' => $expireDate,
            'min_amount' => $minAmount,
            'shop_id' => $shopId,
            'shop_ids' => $shopIds,
        ]);

        $db = \Db::getInstance();
        $db->insert('cart_rule', [
            'id_customer' => 0,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'description' => 'Generated Coupon ' . pSQL($fullCouponCode),
            'quantity' => 1,
            'quantity_per_user' => 1,
            'priority' => 1,
            'partial_use' => 0,
            'code' => pSQL($fullCouponCode),
            'minimum_amount' => (float) $minAmountValue,
            'minimum_amount_tax' => 0,
            'minimum_amount_currency' => 0,
            'minimum_amount_shipping' => 0,
            'country_restriction' => 0,
            'carrier_restriction' => 0,
            'group_restriction' => 0,
            'cart_rule_restriction' => 0,
            'product_restriction' => 0,
            'shop_restriction' => ($shopId !== null) ? 1 : 0,
            'free_shipping' => 0,
            'reduction_percent' => $reductionPercent,
            'reduction_amount' => $reductionAmount,
            'reduction_tax' => 1,
            'reduction_currency' => 0,
            'reduction_product' => 0,
            'highlight' => 0,
            'active' => 1,
            'date_add' => date('Y-m-d H:i:s'),
            'date_upd' => date('Y-m-d H:i:s'),
        ]);

        $cartRuleId = (int) $db->Insert_ID();

        $langId = $this->getDefaultLanguageId($shopId);
        $db->insert('cart_rule_lang', [
            'id_cart_rule' => $cartRuleId,
            'id_lang' => $langId,
            'name' => 'Generated Coupon ' . pSQL($fullCouponCode),
        ]);

        if ($shopId !== null && $cartRuleId > 0) {
            $db->insert('cart_rule_shop', [
                'id_cart_rule' => $cartRuleId,
                'id_shop' => (int) $shopId,
            ]);
        }

        return $fullCouponCode;
    }

    protected function generateCouponCode(string $prefix): string
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $failSafe = 0;

        do {
            ++$failSafe;
            $couponCode = '';
            for ($i = 0; $i < 8; ++$i) {
                $couponCode .= $characters[random_int(0, strlen($characters) - 1)];
            }
            $fullCouponCode = $prefix . $couponCode;

            $existing = \Db::getInstance()->getValue(
                'SELECT id_cart_rule FROM ' . _DB_PREFIX_ . "cart_rule WHERE code = '" . pSQL($fullCouponCode) . "'"
            );
        } while (!empty($existing) && $failSafe < 3);

        return $fullCouponCode;
    }
}
