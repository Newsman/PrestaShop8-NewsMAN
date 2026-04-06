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
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @see ModuleFrontControllerCore
 */
class Newsmanv8CartModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        $cart = $this->context->cart;
        $products = [];

        if ($cart && $cart->id) {
            $cartProducts = $cart->getProducts();
            foreach ($cartProducts as $product) {
                $price = (float) ($product['price_wt'] ?? $product['price']);
                $products[] = [
                    'id' => (string) $product['id_product'],
                    'name' => (string) $product['name'],
                    'price' => round($price, 2),
                    'quantity' => (int) $product['cart_quantity'],
                ];
            }
        }

        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        echo json_encode($products);
        exit;
    }
}
