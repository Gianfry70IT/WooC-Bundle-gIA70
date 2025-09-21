<?php
/*
 * custom-bundle.php
 * Copyright (c) 2025 Gianfranco Greco
 * Licensed under the GNU GPL v2 or later: https://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) || exit;
global $product;
?>

<div id="wcb-ajax-messages"></div>

<!-- Lightbox Container -->
<div id="wcb-lightbox" class="wcb-lightbox">
    <div class="wcb-lightbox-content">
        <span class="wcb-lightbox-close">&times;</span>
        <img class="wcb-lightbox-image" src="" alt="">
        <div class="wcb-lightbox-caption"></div>
    </div>
</div>

<div class="wcb-bundle-container">
    <div class="wcb-bundle-price-container">
        <!-- Il contenitore del prezzo verrà inserito qui dal JavaScript -->
    </div>
    
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
                    // Descrizioni dinamiche qui...
                    ?>
                </div>
                <div class="wcb-group-products">
                    <?php foreach ( $products_in_group as $product_id ) :
                        $child_product = wc_get_product( $product_id ); if ( ! $child_product ) continue; 
                        $variation_data = [];
                        if ($child_product->is_type('variable')) {
                            $available_variations = $child_product->get_available_variations();
                            foreach ($available_variations as $variation) {
                                if (!empty($variation['attributes'])) {
                                    $variation_data[] = $variation['attributes'];
                                }
                            }
                        }
                        
                        $image_id = $child_product->get_image_id();
                        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : wc_placeholder_img_src('thumbnail');
                        $full_image_url = $image_id ? wp_get_attachment_image_url($image_id, 'full') : wc_placeholder_img_src('full');
                        ?>
                        <div class="wcb-product-item" 
                            data-product-id="<?php echo esc_attr($product_id); ?>"
                            <?php if(!empty($variation_data)) echo "data-variation-data='" . esc_attr(json_encode(array_values($variation_data))) . "'"; ?>
                            <?php if($personalization_enabled && $personalization_required) echo 'data-personalization-required="true"'; ?>>
    
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
                                    <span class="wcb-product-price"><?php echo $child_product->get_price_html(); ?></span>
                                </div>
                                
                                <?php if ( in_array( $selection_mode, ['quantity', 'multiple_quantity'] ) ) : ?>
                                    <input class="wcb-quantity-input" type="number" name="wcb_quantity[<?php echo esc_attr( $group_index ); ?>][<?php echo esc_attr( $product_id ); ?>]" value="0" min="0" step="1">
                                <?php endif; ?>
                            </label>
                            
                            <?php if ($personalization_enabled && in_array($selection_mode, ['single', 'multiple'])) : ?>
                                <div class="wcb-personalization-field-container" style="display:none;">
                                    <label><?php echo esc_html($personalization_label); ?><?php if ($personalization_required) echo ' <span class="required">*</span>'; ?></label>
                                    <input type="text" class="wcb-personalization-input" name="wcb_personalization[<?php echo esc_attr($group_index); ?>][<?php echo esc_attr($product_id); ?>][0]">
                                </div>
                            <?php endif; ?>

                            <?php if ( $child_product->is_type( 'variable' ) ) : ?>
                                <div class="wcb-variation-container" style="display:none;">
                                    <?php foreach ( $child_product->get_variation_attributes() as $attribute_name => $options ) : ?>
                                        <div class="wcb-variation-field">
                                            <label><?php echo wc_attribute_label( $attribute_name ); ?>:</label>
                                            <select class="wcb-variation-select" 
                                                    name="bundle_selection[<?php echo esc_attr( $group_index ); ?>][<?php echo esc_attr( $product_id ); ?>][attributes][attribute_<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>]" 
                                                    data-attribute-name="attribute_<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>" disabled>
                                                <option value=""><?php _e('Scegli un\'opzione', 'wcb-framework'); ?></option>
                                                <?php foreach ( $options as $option ) : ?>
                                                    <option value="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $option ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="wcb-variation-sets-container" style="display:none;"></div>
                                
                                <div class="wcb-templates" style="display:none;">
                                    <div class="wcb-variation-fields-template"><?php foreach ( $child_product->get_variation_attributes() as $attribute_name => $options ) : ?>
                                        <div class="wcb-variation-field"><label><?php echo wc_attribute_label( $attribute_name ); ?>:</label><select class="wcb-variation-select" name="wcb_variation_sets[<?php echo esc_attr( $group_index ); ?>][<?php echo esc_attr( $product_id ); ?>][__INDEX__][attribute_<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>]" data-attribute-name="attribute_<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>" disabled><option value=""><?php _e('Scegli un\'opzione', 'wcb-framework'); ?></option><?php foreach ( $options as $option ) : ?><option value="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $option ); ?></option><?php endforeach; ?></select></div>
                                    <?php endforeach; ?></div>
                                    
                                    <?php if ($personalization_enabled) : ?>
                                        <div class="wcb-personalization-field-template">
                                            <div class="wcb-personalization-field"><label><?php echo esc_html($personalization_label); ?><?php if ($personalization_required) echo ' <span class="required">*</span>'; ?></label><input type="text" class="wcb-personalization-input" name="wcb_personalization[<?php echo esc_attr($group_index); ?>][<?php echo esc_attr($product_id); ?>][__INDEX__]"></div>
                                        </div>
                                    <?php endif; ?>
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

