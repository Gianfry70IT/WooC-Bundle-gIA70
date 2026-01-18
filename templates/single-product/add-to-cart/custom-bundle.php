<?php
/*
 * templates/single-product/add-to-cart/custom-bundle.php - Versione 2.3.6
 * Author: Gianfranco Greco con Codice Sorgente
 * Copyright (c) 2025 Gianfranco Greco
 * Licensed under the GNU GPL v2 or later: https://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) || exit;
global $product;
?>

<div id="wcb-ajax-messages"></div>

<div id="wcb-lightbox" class="wcb-lightbox">
    <div class="wcb-lightbox-content">
        <span class="wcb-lightbox-close">&times;</span>
        <img class="wcb-lightbox-image" src="" alt="">
        <div class="wcb-lightbox-caption"></div>
    </div>
</div>

<form class="wcb-bundle-form cart" method="post" enctype="multipart/form-data">
    <input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>">
    
    <div class="wcb-bundle-layout">
        <div class="wcb-content-col">
            <?php if ( ! empty( $instructions ) ) : ?>
                <div class="wcb-bundle-instructions"><?php echo wp_kses_post( wpautop( $instructions ) ); ?></div>
            <?php endif; ?>

            <div class="wcb-bundle-container">
                <?php foreach ( $bundle_groups as $group_index => $group ) : ?>
                    <?php
                    $selection_mode = $group['selection_mode'] ?? 'multiple';
                    $is_required = ( $group['is_required'] ?? 'yes' ) === 'yes';
                    $products_in_group = $group['products'] ?? [];
                    
                    $personalization_enabled = ($group['personalization_enabled'] ?? 'no') === 'yes';
                    $personalization_fields = $group['personalization_fields'] ?? [];
                    // Retrocompatibilità
                    if (empty($personalization_fields) && !empty($group['personalization_label'])) {
                        $personalization_fields[] = ['label' => $group['personalization_label'], 'required' => $group['personalization_required'] ?? 'no'];
                    }

                    // Recupera Settings Prodotti (Min/Step/Price)
                    $products_settings = $group['products_settings'] ?? [];
                    
                    $group_price_override = isset($group['price_override']) && $group['price_override'] !== '' ? floatval($group['price_override']) : null;
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
                                $rules = [];
                                if ($is_required) $rules[] = __('Selezione obbligatoria', 'wcb-framework');
                                switch ($selection_mode) {
                                    case 'multiple':
                                    case 'multiple_quantity':
                                        $min = absint($group['min_qty'] ?? 0);
                                        $max = absint($group['max_qty'] ?? 0);
                                        if ($min > 0 && $max > 0) $rules[] = sprintf(__('Scegli da %d a %d prodotti', 'wcb-framework'), $min, $max);
                                        elseif ($min > 0) $rules[] = sprintf(__('Scegli almeno %d prodotti', 'wcb-framework'), $min);
                                        break;
                                    case 'quantity':
                                        $total = absint($group['total_qty'] ?? 0);
                                        if ($total > 0) $rules[] = sprintf(__('Scegli esattamente %d prodotti', 'wcb-framework'), $total);
                                        break;
                                }
                                echo esc_html(implode('. ', $rules));
                            ?>
                        </div>

                        <?php if ( $selection_mode !== 'personalization_only' ) : ?>
                            <div class="wcb-group-products">
                                <?php foreach ( $products_in_group as $product_id ) :
                                    $child_product = wc_get_product( $product_id ); if ( ! $child_product ) continue; 
                                    $variation_data = [];
                                    if ($child_product->is_type('variable')) {
                                        $available_variations = $child_product->get_available_variations();
                                        foreach ($available_variations as $variation) {
                                            if (!empty($variation['attributes'])) $variation_data[] = $variation['attributes'];
                                        }
                                    }
                                    
                                    $image_id = $child_product->get_image_id();
                                    $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : wc_placeholder_img_src('thumbnail');
                                    $full_image_url = $image_id ? wp_get_attachment_image_url($image_id, 'full') : wc_placeholder_img_src('full');
                                    
                                    // --- LOGICA PREZZI AVANZATA ---
                                    $p_settings = $products_settings[$product_id] ?? [];
                                    $item_min = absint($p_settings['min_qty'] ?? 1);
                                    $item_step = absint($p_settings['step'] ?? 1);
                                    $item_price_override = isset($p_settings['price']) && $p_settings['price'] !== '' ? floatval($p_settings['price']) : null;

                                    $price_html = $child_product->get_price_html();
                                    if ($item_price_override !== null) {
                                        $price_html = wc_price($item_price_override);
                                    } elseif ($group_price_override !== null) {
                                        $price_html = wc_price($group_price_override);
                                    }
                                    
                                    $has_required_fields = false;
                                    if ($personalization_enabled) {
                                        foreach($personalization_fields as $field) {
                                            if (($field['required'] ?? 'no') === 'yes') $has_required_fields = true;
                                        }
                                    }
                                    ?>
                                    <div class="wcb-product-item" 
                                        data-product-id="<?php echo esc_attr($product_id); ?>"
                                        data-item-min="<?php echo esc_attr($item_min); ?>"
                                        data-item-step="<?php echo esc_attr($item_step); ?>"
                                        <?php if($item_price_override !== null) echo 'data-price-override="'.esc_attr($item_price_override).'"'; ?>
                                        <?php if($group_price_override !== null && $item_price_override === null) echo 'data-price-override="'.esc_attr($group_price_override).'"'; ?>
                                        <?php if(!empty($variation_data)) echo "data-variation-data='" . esc_attr(json_encode(array_values($variation_data))) . "'"; ?>
                                        <?php if($has_required_fields) echo 'data-personalization-required="true"'; ?>>
                
                                        <label>
                                            <?php if ( in_array( $selection_mode, ['single', 'multiple'] ) ) : ?>
                                                <input type="<?php echo $selection_mode === 'single' ? 'radio' : 'checkbox'; ?>" 
                                                       name="bundle_selection<?php echo $selection_mode === 'single' ? '_radio' : ''; ?>[<?php echo esc_attr( $group_index ); ?>]<?php echo $selection_mode === 'multiple' ? '['.esc_attr($product_id).'][selected]' : ''; ?>"
                                                       value="<?php echo $selection_mode === 'single' ? esc_attr($product_id) : '1'; ?>">
                                            <?php endif; ?>
                                            
                                            <div class="wcb-product-thumbnail">
                                                <img src="<?php echo esc_url($image_url); ?>" 
                                                     alt="<?php echo esc_attr($child_product->get_name()); ?>"
                                                     data-full-image="<?php echo esc_url($full_image_url); ?>"
                                                     class="wcb-thumbnail-image">
                                            </div>
                                            
                                            <div class="wcb-product-info">
                                                <span class="wcb-product-name"><?php echo esc_html( $child_product->get_name() ); ?></span>
                                                <span class="wcb-product-price"><?php echo $price_html; ?></span>
                                                
                                                <?php if ( in_array( $selection_mode, ['quantity', 'multiple_quantity'] ) ) : ?>
                                                    <div class="wcb-qty-wrapper">
                                                        <input class="wcb-quantity-input" type="number" 
                                                               name="wcb_quantity[<?php echo esc_attr( $group_index ); ?>][<?php echo esc_attr( $product_id ); ?>]" 
                                                               value="0" 
                                                               min="0" 
                                                               step="<?php echo esc_attr($item_step); ?>">
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </label>
                                        
                                        <?php if ($personalization_enabled && !empty($personalization_fields) && in_array($selection_mode, ['single', 'multiple'])) : ?>
                                            <div class="wcb-personalization-field-container" style="display:none;">
                                                <?php foreach ($personalization_fields as $fIndex => $field) : 
                                                    $is_field_req = ($field['required'] ?? 'no') === 'yes';
                                                    $req_attr = $is_field_req ? 'data-required="true"' : '';
                                                    $req_star = $is_field_req ? ' <span class="required">*</span>' : '';
                                                ?>
                                                    <div class="wcb-personalization-field">
                                                        <label><?php echo esc_html($field['label']); ?><?php echo $req_star; ?></label>
                                                        <input type="text" class="wcb-personalization-input" name="wcb_personalization[<?php echo esc_attr($group_index); ?>][<?php echo esc_attr($product_id); ?>][0][<?php echo $fIndex; ?>]" <?php echo $req_attr; ?>>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($child_product->is_type('variable')) : ?><div class="wcb-variation-sets-container" style="display:none;"></div><?php endif; ?>

                                        <div class="wcb-templates" style="display:none;">
                                            <?php if ($child_product->is_type('variable')) : ?>
                                                <div class="wcb-variation-fields-template"><?php foreach ( $child_product->get_variation_attributes() as $attribute_name => $options ) : ?>
                                                    <div class="wcb-variation-field"><label><?php echo wc_attribute_label( $attribute_name ); ?>:</label><select class="wcb-variation-select" name="wcb_variation_sets[<?php echo esc_attr( $group_index ); ?>][<?php echo esc_attr($product_id); ?>][__INDEX__][attribute_<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>]" data-attribute-name="attribute_<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>" disabled><option value=""><?php _e('Scegli un\'opzione', 'wcb-framework'); ?></option><?php foreach ( $options as $option ) : ?><option value="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $option ); ?></option><?php endforeach; ?></select></div>
                                                <?php endforeach; ?></div>
                                            <?php endif; ?>
                                            
                                            <?php if ($personalization_enabled && !empty($personalization_fields)) : ?>
                                                <div class="wcb-personalization-field-template">
                                                    <?php foreach ($personalization_fields as $fIndex => $field) : 
                                                        $req_attr = ($field['required'] ?? 'no') === 'yes' ? 'data-required="true"' : '';
                                                        $req_star = ($field['required'] ?? 'no') === 'yes' ? ' <span class="required">*</span>' : '';
                                                    ?>
                                                        <div class="wcb-personalization-field">
                                                            <label><?php echo esc_html($field['label']); ?><?php echo $req_star; ?></label>
                                                            <input type="text" class="wcb-personalization-input" name="wcb_personalization[<?php echo esc_attr($group_index); ?>][<?php echo esc_attr($product_id); ?>][__INDEX__][<?php echo $fIndex; ?>]" <?php echo $req_attr; ?>>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ( $child_product->is_type( 'variable' ) ) : ?>
                                            <div class="wcb-variation-container" style="display:none;">
                                                <?php foreach ( $child_product->get_variation_attributes() as $attribute_name => $options ) : ?>
                                                    <div class="wcb-variation-field">
                                                        <label><?php echo wc_attribute_label( $attribute_name ); ?>:</label>
                                                        <select class="wcb-variation-select" name="bundle_selection[<?php echo esc_attr( $group_index ); ?>][<?php echo esc_attr( $product_id ); ?>][attributes][attribute_<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>]" data-attribute-name="attribute_<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>" disabled><option value=""><?php _e('Scegli un\'opzione', 'wcb-framework'); ?></option><?php foreach ( $options as $option ) : ?><option value="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $option ); ?></option><?php endforeach; ?></select>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                    </div> 
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="wcb-sidebar-col">
            <div class="wcb-sticky-summary">
                <h3 class="wcb-summary-title"><?php esc_html_e('Riepilogo Bundle', 'wcb-framework'); ?></h3>
                <div class="wcb-bundle-price">
                    <span class="wcb-price-label"><?php esc_html_e('Totale:', 'wcb-framework'); ?></span>
                    <span class="wcb-price-value"></span>
                </div>
                <div class="wcb-summary-actions">
                    <button type="submit" class="single_add_to_cart_button button alt" disabled>
                        <?php echo esc_html( $product->single_add_to_cart_text() ); ?>
                    </button>
                </div>
            </div>
        </div>
    </div> 
    
    <div class="wcb-mobile-sticky-bar">
        <div class="wcb-mobile-price">
            <span class="wcb-price-label"><?php esc_html_e('Totale:', 'wcb-framework'); ?></span>
            <span class="wcb-price-value"></span>
        </div>
        <button type="button" class="button alt wcb-mobile-submit-btn" disabled>
            <?php echo esc_html( $product->single_add_to_cart_text() ); ?>
        </button>
    </div>
</form>