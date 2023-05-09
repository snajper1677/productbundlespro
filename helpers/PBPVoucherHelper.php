<?php
/**
 * NOTICE OF LICENSE
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 *
 * @author    Musaffar Patel
 * @copyright 2016-2019 Musaffar Patel
 * @license   LICENSE.txt
 */

class PBPVoucherHelper
{

    static $route = 'pbpvoucherhelper';

    public static function generateCode($length, $prefix)
    {
        return $prefix.substr(str_shuffle(str_repeat($x = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / strlen($x)))), 1, $length);
    }

    /**
     * Add voucher to the cart
     * @param $id_cart
     * @param $id_customer
     * @param $expire_days
     * @param $amount
     * @param $id_currency
     * @param $names
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function addVoucher($id_cart, $id_customer, $expire_days, $amount, $id_currency, $names)
    {
        if ($expire_days == 0) {
            $expire_days = 365;
        }

        $date_from = date('Y-m-d');
        $date_to = date('Y-m-d', strtotime($date_from.' + '.$expire_days.' days'));
        $languages = Language::getLanguages();

        $cart_rule = new CartRule();
        $cart_rule->id_customer = (int)$id_customer;
        $cart_rule->date_from = pSQL($date_from);
        $cart_rule->date_to = pSQL($date_to);
        $cart_rule->description = '';
        $cart_rule->quantity = 1;
        $cart_rule->quantity_per_user = 1;
        $cart_rule->priority = 1;
        $cart_rule->partial_use = 1;
        $cart_rule->code = pSQL(self::generateCode(4, 'PBP-'));
        $cart_rule->reduction_amount = (float)$amount;
        $cart_rule->reduction_currency = (int)$id_currency;
        $cart_rule->reduction_tax = true;

        foreach ($languages as $language) {
            $name = $names[$language['id_lang']];
            if ($name == '') {
                $cart_rule->name[$language['id_lang']] = 'Bundle Discount';
            } else {
                $cart_rule->name[$language['id_lang']] = $name;
            }
        }
        $cart_rule->add();

        if ($cart_rule->id > 0) {
            DB::getInstance()->insert(
                'cart_cart_rule',
                array(
                    'id_cart' => (int)$id_cart,
                    'id_cart_rule' => (int)$cart_rule->id,
                )
            );
        }
    }

    /**
     * Remove existing vouchers generated by this module from the DB
     * @param $id_cart
     * @param $id_customer
     */
    public static function clearVoucher($id_cart, $id_customer = 0)
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('cart_cart_rule', 'ccr');
        $sql->innerJoin('cart_rule', 'cr', 'ccr.id_cart_rule = cr.id_cart_rule AND cr.code LIKE "PBP-%"');
        $sql->where('ccr.id_cart = ' . (int)$id_cart);
        $cart_cart_rules = Db::getInstance()->executeS($sql);

        if (empty($cart_cart_rules)) {
            return false;
        }

        foreach ($cart_cart_rules as $cart_cart_rule) {
            DB::getInstance()->delete('cart_cart_rule', 'id_cart_rule = ' . (int)$cart_cart_rule['id_cart_rule'] . ' AND id_cart = ' . (int)$id_cart);
            DB::getInstance()->delete('cart_rule_lang', 'id_cart_rule = ' . (int)$cart_cart_rule['id_cart_rule']);
            DB::getInstance()->delete('cart_rule', 'id_cart_rule = ' . (int)$cart_cart_rule['id_cart_rule']);
        }
    }

    /**
     * Determines if the user cart contains a bundle voucher already
     * @param $id_cart
     * @return bool
     */
    public static function cartHasBundleVoucher($id_cart)
    {
        $sql = new DbQuery();
        $sql->select('count(*) AS total_count');
        $sql->from('cart_cart_rule', 'ccr');
        $sql->innerJoin('cart_rule', 'cr', 'ccr.id_cart_rule = cr.id_cart_rule AND cr.code LIKE "PBP-%"');
        $sql->where('ccr.id_cart = ' . (int)$id_cart);
        $count = Db::getInstance()->getValue($sql);

        if ($count > 0) {
            return $count;
        } else {
            return false;
        }
    }

    /**
     * Get monetary value of LRP voucher associated with a cart
     * @param $cart
     * @return int
     */
    public static function getVoucherValue($cart)
    {
        $points_redeemed_value = 0;
        $rules = $cart->getCartRules();
        foreach ($rules as $rule) {
            if (substr($rule['code'], 0, 4) == 'PBP-') {
                $points_redeemed_value = $rule['value_real'];
            }
        }
        return $points_redeemed_value;
    }
}