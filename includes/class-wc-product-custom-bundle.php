<?php
/*
 * includes/class-wc-product-custom-bundle.php - Versione 2.8.2
 * Author: Gianfranco Greco con Codice Sorgente
 * Copyright (c) 2025 Gianfranco Greco
 * Licensed under the GNU GPL v2 or later: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WC_Product_Custom_Bundle')) {
class WC_Product_Custom_Bundle extends WC_Product
{

    public function __construct($product)
    {
        parent::__construct($product);
    }

    public function get_type()
    {
        return 'custom_bundle';
    }

    /**
     * Come WC_Product::is_purchasable() ma senza richiedere un prezzo: con il prezzo
     * calcolato il bundle non ne ha uno proprio. Bozze, privati e cestino restano
     * acquistabili solo da chi può modificarli (anteprima admin).
     */
    public function is_purchasable()
    {
        $purchasable = $this->exists() && ('publish' === $this->get_status() || current_user_can('edit_post', $this->get_id()));
        return apply_filters('woocommerce_is_purchasable', $purchasable, $this);
    }
}
}
