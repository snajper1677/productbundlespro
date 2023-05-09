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

class PBPReverseBundle
{
    /**
     * Duplicate an existing bundle
     * @param $id_bundle
     * @param $id_product_new
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function duplicateBundleReverse($id_bundle) {
        $pbp_bundle_model = new PBPBundleModel();
        $pbp_bundle_model->load($id_bundle);

        if (empty($pbp_bundle_model->id_bundle))
            return false;

        $old_product_a = array();
        $pbp_product_model = new PBPProductModel();
        $bundle_products = $pbp_product_model->getByBundle($id_bundle);

        $old_product_a['id_product'] = $pbp_bundle_model->id_product;
        $old_product_a['discount_type'] = $pbp_bundle_model->discount_type;
        $old_product_a['discount_amount'] = $pbp_bundle_model->discount_amount;
        $old_product_a['discount_tax'] = 'tax_ex';
        $old_product_a['allow_oos'] = 0;
        $old_product_a['qty'] = 1;

        foreach ($bundle_products as $bp) {
            $pbp_bundle_model_new = new PBPBundleModel();
            $pbp_bundle_model_new->id_bundle = (int)$pbp_bundle_model_new::getNewBundleID();
            $pbp_bundle_model_new->id_tab = (int)$pbp_bundle_model->id_tab;
            $pbp_bundle_model_new->id_product = (int)$bp['id_product'];
            $pbp_bundle_model_new->id_shop = (int)$pbp_bundle_model->id_shop;
            $pbp_bundle_model_new->enabled = (int)$pbp_bundle_model->enabled;
            $pbp_bundle_model_new->allow_selection = (int)$pbp_bundle_model->allow_selection;
            $pbp_bundle_model_new->position = 0;
            $pbp_bundle_model_new->discount_type = $bp['discount_type'];
            $pbp_bundle_model_new->discount_amount = (float)$bp['discount_amount'];
            $pbp_bundle_model_new->discount_enabled = (int)$pbp_bundle_model->discount_enabled;
            $pbp_bundle_model_new->add();

            $n = "Zestaw";
            $n .= "_" . $bp['id_product'];
            $i = 1;
            foreach ($bundle_products as $bpp) {
                if ($bp['id_product'] == $bpp['id_product'] && $bpp['qty'] > 1) {
                    self::productModel((int)$pbp_bundle_model_new->id_bundle, $bpp, $i, 1);
                }

                if ($bp['id_product'] == $bpp['id_product'])
                    continue;

                $n .= "_" . $bpp['id_product'];
                self::productModel((int)$pbp_bundle_model_new->id_bundle, $bpp, $i, 0);
                $i++;
            }
            self::productModel((int)$pbp_bundle_model_new->id_bundle, $old_product_a, $i, 0);
            $names = PBPBundleHelper::getNames($id_bundle);
            $n .= "_" . $old_product_a['id_product'];
            foreach ($names as $id_lang => $name) {
                $pbp_bundle_lang_model = new PBPBundleLangModel();
                $pbp_bundle_lang_model->id_bundle = (int)$pbp_bundle_model_new->id_bundle;
                $pbp_bundle_lang_model->id_lang = (int)$id_lang;
                $pbp_bundle_lang_model->name = pSQL($n);
                $pbp_bundle_lang_model->add();
            }
        }
        return true;
    }

    public static function productModel($idBundle, $product, $i, $qty) {
        $pbp_product_model_new = new PBPProductModel();
        $pbp_product_model_new->id_bundle = (int)$idBundle;
        $pbp_product_model_new->id_product = (int)$product['id_product'];
        $pbp_product_model_new->discount_type = pSQL($product['discount_type']);
        $pbp_product_model_new->discount_amount = (float)$product['discount_amount'];
        $pbp_product_model_new->discount_tax = pSQL($product['discount_tax']);
        $pbp_product_model_new->allow_oos = (int)$product['allow_oos'];
        $pbp_product_model_new->qty = (int)$product['qty'] - (int)$qty;
        $pbp_product_model_new->position = (int)$i;
        $pbp_product_model_new->add();
    }
}
