<?php
/*
 * includes/class-wc-product-custom-bundle.php - Versione 2.4.10
 * Author: Gianfranco Greco con Codice Sorgente
 * Copyright (c) 2025 Gianfranco Greco
 * Licensed under the GNU GPL v2 or later: https://www.gnu.org/licenses/gpl-2.0.html
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
