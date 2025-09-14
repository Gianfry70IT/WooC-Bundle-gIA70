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

defined( 'ABSPATH' ) || exit;
global $product;
?>

<div id="wcb-ajax-messages"></div>

<div class="wcb-bundle-container">
    <?php if ( ! empty( $instructions ) ) : ?>
        <div class="wcb-bundle-instructions"><?php echo wp_kses_post( wpautop( $instructions ) ); ?></div>
    <?php endif; ?>

    <form class="wcb-bundle-form cart" method="post" enctype="multipart/form-data">
        <input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>">
        <?php foreach ( $bundle_groups as $group_index => $group ) : ?>
            <?php
            $selection_mode = $group['selection_mode'] ?? 'multiple';
            $is_required = ( $group['is_required'] ?? 'yes' ) === 'yes';
            $products_in_group = $group['products'] ?? [];
            $personalization_enabled = ($group['personalization_enabled'] ?? 'no') === 'yes';
            $personalization_label = $group['personalization_label'] ?? __('Testo Personalizzato', 'wcb-framework');
            $personalization_required = ($group['personalization_required'] ?? 'no') === 'yes';
            ?>
            <div class="wcb-bundle-group" 
                 data-group-index="<?php echo esc_attr( $group_index ); ?>" 
                 data-selection-mode="<?php echo esc_attr( $selection_mode ); ?>"
                 data-is-required="<?php echo esc_attr( $is_required ? 'true' : 'false' ); ?>"
                 data-min-qty="<?php echo esc_attr(absint($group['min_qty'] ?? 0)); ?>"
                 data-max-qty="<?php echo esc_attr(absint($group['max_qty'] ?? 0)); ?>"
                 data-total-qty="<?php echo esc_attr(absint($group['total_qty'] ?? 0)); ?>">
                
                <h3 class="wcb-group-title"><?php echo esc_html( $group['title'] ); ?></h3>
                <div class="wcb-group-description">
                    <?php
                    switch ($selection_mode) {
                        case 'single': echo esc_html__( 'Scegli un prodotto.', 'wcb-framework' ); break;
                        case 'multiple':
                            $min_qty = absint($group['min_qty'] ?? 0);
                            $max_qty = absint($group['max_qty'] ?? 0);
                            if ($max_qty > 0) { echo sprintf( esc_html__( 'Scegli da %d a %d prodotti.', 'wcb-framework' ), $min_qty, $max_qty ); } 
                            else { echo sprintf( esc_html__( 'Scegli almeno %d prodotti.', 'wcb-framework' ), $min_qty ); }
                            break;
                        case 'quantity': echo sprintf( esc_html__( 'Scegli una quantità totale di %d prodotti.', 'wcb-framework' ), absint($group['total_qty'] ?? 0) ); break;
                    }
                    ?>
                </div>
                <div class="wcb-group-products">
                    <?php foreach ( $products_in_group as $product_id ) :
                        $child_product = wc_get_product( $product_id ); if ( ! $child_product ) continue; 
                        $variation_data = [];
                        if ($child_product->is_type('variable')) {
                            foreach ($child_product->get_available_variations() as $variation) {
                                if (!empty($variation['attributes'])) $variation_data[] = $variation['attributes'];
                            }
                        }
                        ?>
                        <div class="wcb-product-item" 
                             data-product-id="<?php echo esc_attr($product_id); ?>" 
                             <?php if(!empty($variation_data)) echo "data-variation-data='" . esc_attr(json_encode($variation_data)) . "'"; ?>
                             <?php if($personalization_enabled && $personalization_required) echo 'data-personalization-required="true"'; ?>>

                            <label>
                                <?php if ( 'single' === $selection_mode ) : ?>
                                    <input type="radio" name="bundle_selection_radio[<?php echo esc_attr( $group_index ); ?>]" value="<?php echo esc_attr( $product_id ); ?>">
                                <?php elseif ( 'multiple' === $selection_mode ) : ?>
                                    <input type="checkbox" name="bundle_selection[<?php echo esc_attr( $group_index ); ?>][<?php echo esc_attr( $product_id ); ?>][selected]" value="1">
                                <?php endif; ?>
                                <span class="wcb-product-name"><?php echo esc_html( $child_product->get_name() ); ?></span>
                                <span class="wcb-product-price"><?php echo $child_product->get_price_html(); ?></span>
                                <?php if ( 'quantity' === $selection_mode ) : ?>
                                    <input class="wcb-quantity-input" type="number" name="wcb_quantity[<?php echo esc_attr( $group_index ); ?>][<?php echo esc_attr( $product_id ); ?>]" value="0" min="0" step="1">
                                <?php endif; ?>
                            </label>
                            
                            <?php if ($personalization_enabled && in_array($selection_mode, ['single', 'multiple'])) : ?>
                                <div class="wcb-personalization-field-container" style="display:none;">
                                    <label><?php echo esc_html($personalization_label); ?><?php if ($personalization_required) echo ' <span class="required">*</span>'; ?></label>
                                    <input type="text" class="wcb-personalization-input" name="wcb_personalization[<?php echo esc_attr($group_index); ?>][<?php echo esc_attr($product_id); ?>][0]">
                                </div>
                            <?php endif; ?>

                            <div class="wcb-variation-container" style="display:none;"><?php if ( $child_product->is_type( 'variable' ) ) : foreach ( $child_product->get_variation_attributes() as $attribute_name => $options ) : ?>
                                <div class="wcb-variation-field"><label><?php echo wc_attribute_label( $attribute_name ); ?>:</label><select class="wcb-variation-select" name="bundle_selection[<?php echo esc_attr( $group_index ); ?>][<?php echo esc_attr( $product_id ); ?>][attributes][attribute_<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>]" data-attribute-name="attribute_<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>" disabled><option value=""><?php _e('Scegli un\'opzione', 'wcb-framework'); ?></option><?php foreach ( $options as $option ) : ?><option value="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $option ); ?></option><?php endforeach; ?></select></div>
                            <?php endforeach; endif; ?></div>
                            
                            <div class="wcb-variation-sets-container"></div>
                            
                            <?php if ( $child_product->is_type( 'variable' ) && 'quantity' === $selection_mode ) : ?>
                                <div class="wcb-variation-fields-template" style="display: none;"><?php foreach ( $child_product->get_variation_attributes() as $attribute_name => $options ) : ?>
                                    <div class="wcb-variation-field"><label><?php echo wc_attribute_label( $attribute_name ); ?>:</label><select class="wcb-variation-select" name="wcb_variation_sets[<?php echo esc_attr( $group_index ); ?>][<?php echo esc_attr( $product_id ); ?>][__INDEX__][attribute_<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>]" data-attribute-name="attribute_<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>" disabled><option value=""><?php _e('Scegli un\'opzione', 'wcb-framework'); ?></option><?php foreach ( $options as $option ) : ?><option value="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $option ); ?></option><?php endforeach; ?></select></div>
                                <?php endforeach; ?></div>
                            <?php endif; ?>
                            
                            <?php if ($personalization_enabled && $selection_mode === 'quantity') : ?>
                                <div class="wcb-personalization-field-template" style="display: none;">
                                    <div class="wcb-personalization-field">
                                        <label><?php echo esc_html($personalization_label); ?><?php if ($personalization_required) echo ' <span class="required">*</span>'; ?></label>
                                        <input type="text" class="wcb-personalization-input" name="wcb_personalization[<?php echo esc_attr($group_index); ?>][<?php echo esc_attr($product_id); ?>][__INDEX__]">
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="woocommerce-variation-add-to-cart variations_button"><button type="submit" class="single_add_to_cart_button button alt" disabled><?php echo esc_html( $product->single_add_to_cart_text() ); ?></button></div>
    </form>
</div>
