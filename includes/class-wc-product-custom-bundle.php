<?php
/*
 * Plugin Name:       WooC Bundle gIA70
 * Description:       Un framework per creare prodotti bundle personalizzabili, unendo un'amministrazione stabile con un frontend funzionale.
 * Version:           0.8.2
 * Author:            Gianfranco Greco con Codice Sorgente
 * Copyright (c) 2025 Gianfranco Greco
 * Licensed under the GNU GPL v2 or later: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wcb-framework
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Product_Custom_Bundle extends WC_Product {

    public function __construct( $product ) {
        parent::__construct( $product );
    }

    public function get_type() {
        return 'custom_bundle';
    }

    public function is_purchasable() {
        return true;
    }
}
