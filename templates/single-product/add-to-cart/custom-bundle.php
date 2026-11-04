<?php
/*
 * templates/single-product/add-to-cart/custom-bundle.php - Versione 2.8.1
 * Author: Gianfranco Greco con Codice Sorgente
 * Copyright (c) 2025 Gianfranco Greco
 * Licensed under the GNU GPL v2 or later: https://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) || exit;

global $product;

// Ensure $product is valid. If global is null (e.g. shortcode in post), try to use the one passed to template args.
if ( ! is_a( $product, 'WC_Product' ) && isset( $args['product'] ) && is_a( $args['product'], 'WC_Product' ) ) {
    $product = $args['product'];
}

// Fallback: if still null, try to get from global post if it's a product
if ( ! is_a( $product, 'WC_Product' ) && get_post_type() === 'product' ) {
    $product = wc_get_product( get_the_ID() );
}

if ( ! is_a( $product, 'WC_Product' ) ) {
    return;
}

$price_sublabel = get_post_meta($product->get_id(), '_bundle_price_sublabel', true);
// Recupero Default Globale per Nascondere Prezzi in Override
$default_hide_override = get_option('wcb_default_hide_override_price', true);

$layout_settings = ( isset( $layout_settings ) && is_array( $layout_settings ) )
    ? $layout_settings
    : ( class_exists( 'WCB_Theme_Settings' ) ? WCB_Theme_Settings::get_bundle_layout_settings( $product->get_id() ) : [] );

$style_mode       = $layout_settings['style_mode'] ?? 'classic';
$items_layout     = $layout_settings['items_layout'] ?? 'list';
$grid_columns     = absint( $layout_settings['grid_columns'] ?? 3 );
if ( $grid_columns < 2 || $grid_columns > 4 ) {
    $grid_columns = 3;
}
$summary_position = $layout_settings['summary_position'] ?? 'sidebar';

$form_classes = implode( ' ', [
    'wcb-bundle-form',
    'cart',
    'wcb-style-' . esc_attr( $style_mode ),
    'wcb-view-' . esc_attr( $items_layout ),
    'wcb-cols-' . esc_attr( $grid_columns ),
    'wcb-summary-pos-' . esc_attr( $summary_position ),
] );

$form_pricing_data = [
    'type'                => get_post_meta( $product->get_id(), '_bundle_pricing_type', true ), // vuoto = calcolato, come nel carrello
    'fixed_price'         => wc_get_price_to_display( $product ),
    'discount_amount'     => floatval( get_post_meta( $product->get_id(), '_bundle_discount_amount', true ) ),
    'discount_percentage' => floatval( get_post_meta( $product->get_id(), '_bundle_discount_percentage', true ) ),
    'currency_symbol'     => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
    'decimal_separator'   => wc_get_price_decimal_separator(),
    'thousand_separator'  => wc_get_price_thousand_separator(),
    'currency_position'   => get_option( 'woocommerce_currency_pos' ),
];

?>

<div id="wcb-ajax-messages"></div>

<div id="wcb-lightbox" class="wcb-lightbox">
    <div class="wcb-lightbox-content">
        <span class="wcb-lightbox-close">&times;</span>
        <img class="wcb-lightbox-image" src="" alt="">
        <div class="wcb-lightbox-caption"></div>
    </div>
</div>

<dialog id="wcb-desc-modal" class="wcb-desc-modal" closedby="any" aria-labelledby="wcb-desc-modal-title">
    <div class="wcb-desc-modal-inner">
        <div class="wcb-desc-modal-header">
            <h3 id="wcb-desc-modal-title" class="wcb-desc-modal-title"></h3>
            <button type="button" class="wcb-desc-modal-close" aria-label="<?php esc_attr_e( 'Chiudi', 'wcb-framework' ); ?>">&times;</button>
        </div>
        <div class="wcb-desc-modal-body"></div>
    </div>
</dialog>

<form class="<?php echo esc_attr( $form_classes ); ?>" style="--wcb-grid-cols: <?php echo esc_attr( $grid_columns ); ?>;" data-pricing="<?php echo esc_attr( wp_json_encode( $form_pricing_data ) ); ?>" method="post" enctype="multipart/form-data">
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
                    if (empty($personalization_fields) && !empty($group['personalization_label'])) {
                        $personalization_fields[] = ['label' => $group['personalization_label'], 'required' => $group['personalization_required'] ?? 'no'];
                    }
                    $products_settings = $group['products_settings'] ?? [];
                    $group_price_override = isset($group['price_override']) && $group['price_override'] !== '' ? floatval($group['price_override']) : null;
                    
                    // --- LOGICA OVERRIDE PREZZO E VISIBILITÀ (FIX 2.5) ---
                    $group_price_override = null;
                    if (in_array($selection_mode, ['single', 'multiple'], true) && isset($group['price_override']) && $group['price_override'] !== '') {
                        // Accetta anche '0' come valore valido (solo per modalità single/multiple)
                        $group_price_override = floatval($group['price_override']);
                    }
                    
                    $show_price_behavior = $group['show_price_behavior'] ?? 'default';
                    $should_hide_prices = false;
                    
                    // Se c'è un override (anche 0.00), applichiamo la logica di visibilità
                    if ($group_price_override !== null) {
                        if ($show_price_behavior === 'hide') {
                            $should_hide_prices = true;
                        } elseif ($show_price_behavior === 'default') {
                            $should_hide_prices = $default_hide_override; // Usa il default globale
                        }
                        // Se è 'show', rimane false
                    }

                    // Calcolo Visualizzazione Prezzo Gruppo Tassato (per Header)
                    $group_override_display = '';
                    if ($group_price_override !== null && !empty($products_in_group)) {
                        $dummy_prod = wc_get_product($products_in_group[0]);
                        if($dummy_prod) $group_override_display = wc_get_price_to_display($dummy_prod, array('price' => $group_price_override));
                        else $group_override_display = $group_price_override;
                    }
                    ?>
                    <div class="wcb-bundle-group" 
                         data-group-index="<?php echo esc_attr( $group_index ); ?>" 
                         data-selection-mode="<?php echo esc_attr( $selection_mode ); ?>"
                         data-is-required="<?php echo esc_attr( $is_required ? 'true' : 'false' ); ?>"
                         data-min-qty="<?php echo esc_attr(absint($group['min_qty'] ?? 0)); ?>"
                         data-max-qty="<?php echo esc_attr(absint($group['max_qty'] ?? 0)); ?>"
                         data-total-qty="<?php echo esc_attr(absint($group['total_qty'] ?? 0)); ?>"
                         data-group-price-override="<?php echo esc_attr($group_override_display); ?>">
                        
                        <h3 class="wcb-group-title">
                            <?php echo esc_html( $group['title'] ); ?>
                            <?php 
                            if ( $group_override_display ) : ?>
                                <span class="wcb-group-header-price">
                                    <?php echo wc_price($group_override_display); ?>
                                </span>
                            <?php endif; ?>
                        </h3>
                        
                        <div class="wcb-group-description">
                            <?php
                                $rules = [];
                                if ($is_required) $rules[] = __('Selezione obbligatoria', 'wcb-framework');
                                switch ($selection_mode) {
                                    case 'multiple':
                                    case 'multiple_quantity':
                                        $min = absint($group['min_qty'] ?? 0);
                                        $max = absint($group['max_qty'] ?? 0);
                                        
                                        if ($min > 0 && $max > 0) {
                                            if ($min === $max) {
                                                $rules[] = sprintf(__('Scegli %d prodotti', 'wcb-framework'), $min);
                                            } else {
                                                $rules[] = sprintf(__('Scegli da %d a %d prodotti', 'wcb-framework'), $min, $max);
                                            }
                                        } elseif ($min > 0) {
                                            $rules[] = sprintf(__('Scegli almeno %d prodotti', 'wcb-framework'), $min);
                                        }
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
                                    // Opzioni delle varianti che hanno almeno una combinazione disponibile:
                                    // [attribute_key => [valore => true]], oppure [attribute_key => true] se una variante disponibile accetta "qualsiasi"
                                    $available_variation_options = [];
                                    if ($child_product->is_type('variable')) {
                                        foreach ($child_product->get_available_variations() as $variation) {
                                            // Le varianti esaurite restano fuori: il JS non le propone tra le combinazioni possibili
                                            if (empty($variation['is_in_stock'])) continue;
                                            // '_price' viaggia insieme agli attributi: il JS lo usa per il totale live
                                            if (!empty($variation['attributes'])) $variation_data[] = array_merge($variation['attributes'], ['_price' => $variation['display_price']]);
                                            foreach ((array) $variation['attributes'] as $attr_key => $attr_value) {
                                                if ($attr_value === '' || $attr_value === null) {
                                                    $available_variation_options[$attr_key] = true;
                                                } elseif (($available_variation_options[$attr_key] ?? null) !== true) {
                                                    $available_variation_options[$attr_key][$attr_value] = true;
                                                }
                                            }
                                        }
                                    }
                                    // Esaurito: scorta a 0 / stato "Esaurito" (gli articoli in arretrato restano ordinabili),
                                    // oppure prodotto variabile senza nessuna variante disponibile
                                    $is_unavailable = ! $child_product->is_in_stock()
                                        || ( $child_product->is_type('variable') && empty($variation_data) );
                                    $variation_option_attrs = function ($attribute_name, $option) use ($available_variation_options, $is_unavailable) {
                                        $key = 'attribute_' . sanitize_title($attribute_name);
                                        $allowed = $available_variation_options[$key] ?? [];
                                        if ($is_unavailable || $allowed === true || isset($allowed[$option])) return ['', ''];
                                        return [' disabled', ' – ' . __('esaurito', 'wcb-framework')];
                                    };
                                    
                                    $p_settings = $products_settings[$product_id] ?? [];
                                    $item_min = absint($p_settings['min_qty'] ?? 1);
                                    $item_step = absint($p_settings['step'] ?? 1);
                                    $raw_item_override = isset($p_settings['price']) && $p_settings['price'] !== '' ? floatval($p_settings['price']) : null;

                                    $final_item_price_override = null;
                                    if ($raw_item_override !== null) {
                                        $final_item_price_override = wc_get_price_to_display($child_product, array('price' => $raw_item_override));
                                    }

                                    $raw_extra_unit_price = isset($p_settings['extra_unit_price']) && $p_settings['extra_unit_price'] !== '' ? floatval($p_settings['extra_unit_price']) : 0;
                                    $has_extra_units = $raw_extra_unit_price > 0;
                                    $display_extra_unit_price = $has_extra_units ? wc_get_price_to_display($child_product, array('price' => $raw_extra_unit_price)) : 0;
                                    $extra_unit_label = !empty($p_settings['extra_unit_label']) ? $p_settings['extra_unit_label'] : __('Unità in eccesso', 'wcb-framework');

                                    $price_html = $child_product->get_price_html();
                                    
                                    // LOGICA VISUALIZZAZIONE PREZZO APPLICATA
                                    if ($final_item_price_override !== null) {
                                        // Override specifico sull'ITEM vince sempre
                                        $price_html = wc_price($final_item_price_override);
                                    } elseif ($group_price_override !== null) {
                                        // Override di GRUPPO attivo
                                        if ($should_hide_prices) {
                                            // Stampa span per compatibilità CSS Modern Theme
                                            $price_html = '<span class="wcb-included-badge">' . __('Incluso nel bundle', 'wcb-framework') . '</span>';
                                        }
                                    }
                                    
                                    $has_required_fields = false;
                                    if ($personalization_enabled) {
                                        foreach($personalization_fields as $field) {
                                            if (($field['required'] ?? 'no') === 'yes') $has_required_fields = true;
                                        }
                                    }
                                    ?>
                                    <div class="wcb-product-item<?php echo $is_unavailable ? ' wcb-out-of-stock' : ''; ?>"
                                        data-product-id="<?php echo esc_attr($product_id); ?>"
                                        <?php if ($is_unavailable) echo 'data-out-of-stock="true" aria-disabled="true"'; ?>
                                        data-base-price="<?php echo esc_attr( wc_get_price_to_display( $child_product ) ); ?>"
                                        data-item-min="<?php echo esc_attr($item_min); ?>"
                                        data-item-step="<?php echo esc_attr($item_step); ?>"
                                        <?php 
                                        if($final_item_price_override !== null) {
                                            echo 'data-price-override="'.esc_attr($final_item_price_override).'"'; 
                                        } 
                                        if($has_extra_units) {
                                            echo ' data-extra-unit-price="'.esc_attr($display_extra_unit_price).'"';
                                        }
                                        ?>
                                        <?php if(!empty($variation_data)) echo "data-variation-data='" . esc_attr(json_encode(array_values($variation_data))) . "'"; ?>
                                        <?php if($has_required_fields) echo 'data-personalization-required="true"'; ?>>                
                                        <label>
                                            <?php if ( in_array( $selection_mode, ['single', 'multiple'] ) ) : ?>
                                                <input type="<?php echo $selection_mode === 'single' ? 'radio' : 'checkbox'; ?>" 
                                                       name="bundle_selection<?php echo $selection_mode === 'single' ? '_radio' : ''; ?>[<?php echo esc_attr( $group_index ); ?>]<?php echo $selection_mode === 'multiple' ? '['.esc_attr($product_id).'][selected]' : ''; ?>"
                                                       value="<?php echo $selection_mode === 'single' ? esc_attr($product_id) : '1'; ?>"<?php echo $is_unavailable ? ' disabled' : ''; ?>>
                                            <?php endif; ?>
                                            
                                            <div class="wcb-product-thumbnail wcb-gallery-wrapper">
                                                <?php 
                                                $main_image_id = $child_product->get_image_id();
                                                $gallery_ids = $child_product->get_gallery_image_ids();
                                                $all_images = array_merge( $main_image_id ? [$main_image_id] : [], $gallery_ids );
                                                
                                                if ( count($all_images) > 0 ) : 
                                                    if ( count($all_images) > 1 ) : ?>
                                                        <span class="wcb-gallery-nav wcb-prev" title="Precedente">&lsaquo;</span>
                                                        <span class="wcb-gallery-nav wcb-next" title="Successiva">&rsaquo;</span>
                                                        <span class="wcb-gallery-counter">1/<?php echo count($all_images); ?></span>
                                                    <?php endif;
                                                    foreach($all_images as $index => $img_id) :
                                                        $thumb_url = wp_get_attachment_image_url($img_id, 'woocommerce_thumbnail') ?: wp_get_attachment_image_url($img_id, 'thumbnail');
                                                        $full_url = wp_get_attachment_image_url($img_id, 'full');
                                                        $active_class = ($index === 0) ? 'active' : '';
                                                        ?>
                                                        <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr($child_product->get_name()); ?>" data-full-image="<?php echo esc_url($full_url); ?>" class="wcb-thumbnail-image <?php echo $active_class; ?>" data-index="<?php echo $index; ?>" loading="lazy" decoding="async">
                                                    <?php endforeach;
                                                else: echo wc_placeholder_img('woocommerce_thumbnail'); endif; ?>
                                            </div>
                                            
                                            <div class="wcb-product-info">
                                                <?php 
                                                $default_qty = absint($p_settings['default_qty'] ?? 1);
                                                $qty_label = '<span class="wcb-qty-badge">'.$default_qty.'x</span> ';
                                                $raw_short_desc = trim( strip_shortcodes( wp_strip_all_tags( $child_product->get_short_description() ) ) );
                                                $raw_long_desc_html = $child_product->get_description();
                                                $raw_long_desc_text = trim( strip_shortcodes( wp_strip_all_tags( $raw_long_desc_html ) ) );
                                                $excerpt_source = $raw_short_desc !== '' ? $raw_short_desc : $raw_long_desc_text;
                                                $product_snippet = $excerpt_source !== '' ? wp_trim_words( $excerpt_source, 18, '…' ) : '';
                                                $has_long_description = ( $raw_long_desc_text !== '' );
                                                ?>
                                                <span class="wcb-product-name"><?php echo $qty_label . esc_html( $child_product->get_name() ); ?></span>
                                                <?php if ( $is_unavailable ) : ?>
                                                    <span class="wcb-stock-badge"><?php echo esc_html( apply_filters( 'wcb_out_of_stock_label', __( 'Momentaneamente non disponibile', 'wcb-framework' ), $child_product ) ); ?></span>
                                                <?php endif; ?>
                                                <?php if ( $product_snippet !== '' || $has_long_description ) : ?>
                                                    <div class="wcb-product-excerpt">
                                                        <?php if ( $product_snippet !== '' ) : ?>
                                                            <span class="wcb-product-excerpt-text"><?php echo esc_html( $product_snippet ); ?></span>
                                                        <?php endif; ?>
                                                        <?php if ( $has_long_description ) : ?>
                                                            <button type="button" class="wcb-open-desc-modal" data-product-id="<?php echo esc_attr( $product_id ); ?>" aria-haspopup="dialog">
                                                                <?php esc_html_e( 'Leggi descrizione completa', 'wcb-framework' ); ?>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <span class="wcb-product-price price"><?php echo $price_html; ?></span>
                                                <?php if ( in_array( $selection_mode, ['quantity', 'multiple_quantity'] ) ) : ?>
                                                    <div class="wcb-qty-wrapper">
                                                        <input class="wcb-quantity-input input-text qty text" type="number" name="wcb_quantity[<?php echo esc_attr( $group_index ); ?>][<?php echo esc_attr( $product_id ); ?>]" value="0" min="0" max="1000" step="<?php echo esc_attr($item_step); ?>" autocomplete="off" inputmode="numeric"<?php echo $is_unavailable ? ' disabled' : ''; ?>>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </label>

                                        <?php if ($has_extra_units) :
                                            $extra_input_id = 'wcb-extra-units-' . esc_attr($product->get_id()) . '-' . esc_attr($group_index) . '-' . esc_attr($product_id);
                                            $extra_help_id  = $extra_input_id . '-help';
                                        ?>
                                            <div class="wcb-extra-units-container" style="display:none;">
                                                <div class="wcb-extra-units-row">
                                                    <label for="<?php echo $extra_input_id; ?>" class="wcb-extra-units-label">
                                                        <span class="wcb-extra-units-title"><?php echo esc_html($extra_unit_label); ?></span>
                                                        <small id="<?php echo $extra_help_id; ?>" class="wcb-extra-units-rate">
                                                            (+<?php echo wp_kses_post(wc_price($display_extra_unit_price)); ?> <?php esc_html_e('cad.', 'wcb-framework'); ?>)
                                                        </small>
                                                    </label>
                                                    <div class="wcb-extra-units-controls">
                                                        <span class="wcb-extra-plus-prefix" aria-hidden="true">+</span>
                                                        <input type="number"
                                                               id="<?php echo $extra_input_id; ?>"
                                                               class="wcb-extra-units-input input-text qty text"
                                                               name="wcb_extra_units[<?php echo esc_attr($group_index); ?>][<?php echo esc_attr($product_id); ?>]"
                                                               value="0"
                                                               min="0"
                                                               max="1000"
                                                               step="1"
                                                               autocomplete="off"
                                                               inputmode="numeric"
                                                               aria-describedby="<?php echo $extra_help_id; ?>">
                                                        <span class="wcb-extra-units-subtotal" aria-live="polite"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($personalization_enabled && !empty($personalization_fields)) : ?>
                                            <div class="wcb-personalization-field-container" style="display:none;">
                                                <?php foreach ($personalization_fields as $fIndex => $field) : 
                                                    $is_field_req = ($field['required'] ?? 'no') === 'yes';
                                                    $req_attr = $is_field_req ? 'data-required="true"' : '';
                                                    $req_star = $is_field_req ? ' <span class="required">*</span>' : '';
                                                ?>
                                                    <div class="wcb-personalization-field"><label><?php echo esc_html($field['label']); ?><?php echo $req_star; ?></label><input type="text" class="wcb-personalization-input input-text" name="wcb_personalization[<?php echo esc_attr($group_index); ?>][<?php echo esc_attr($product_id); ?>][0][<?php echo esc_attr($fIndex); ?>]" <?php echo $req_attr; ?>></div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($child_product->is_type('variable')) : ?><div class="wcb-variation-sets-container" style="display:none;"></div><?php endif; ?>
                                        <div class="wcb-templates" style="display:none;">
                                            <?php if ( ! empty( $has_long_description ) ) : ?>
                                                <div class="wcb-full-description-template" data-product-title="<?php echo esc_attr( $child_product->get_name() ); ?>">
                                                    <?php echo wp_kses_post( wpautop( do_shortcode( $raw_long_desc_html ) ) ); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($child_product->is_type('variable')) : ?>
                                                <div class="wcb-variation-fields-template"><?php foreach ( $child_product->get_variation_attributes() as $attribute_name => $options ) : ?>
                                                    <div class="wcb-variation-field"><label><?php echo wc_attribute_label( $attribute_name ); ?>:</label><select class="wcb-variation-select" name="wcb_variation_sets[<?php echo esc_attr( $group_index ); ?>][<?php echo esc_attr($product_id); ?>][__INDEX__][attribute_<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>]" data-attribute-name="attribute_<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>" disabled><option value=""><?php _e('Scegli un\'opzione', 'wcb-framework'); ?></option><?php foreach ( $options as $option ) : list( $opt_disabled, $opt_suffix ) = $variation_option_attrs( $attribute_name, $option ); ?><option value="<?php echo esc_attr( $option ); ?>"<?php echo $opt_disabled; ?>><?php echo esc_html( $option . $opt_suffix ); ?></option><?php endforeach; ?></select></div>
                                                <?php endforeach; ?></div>
                                            <?php endif; ?>
                                            <?php if ($personalization_enabled && !empty($personalization_fields)) : ?>
                                                <div class="wcb-personalization-field-template">
                                                    <?php foreach ($personalization_fields as $fIndex => $field) : 
                                                        $req_attr = ($field['required'] ?? 'no') === 'yes' ? 'data-required="true"' : '';
                                                        $req_star = ($field['required'] ?? 'no') === 'yes' ? ' <span class="required">*</span>' : '';
                                                    ?>
                                                        <div class="wcb-personalization-field"><label><?php echo esc_html($field['label']); ?><?php echo $req_star; ?></label><input type="text" class="wcb-personalization-input input-text" name="wcb_personalization[<?php echo esc_attr($group_index); ?>][<?php echo esc_attr($product_id); ?>][__INDEX__][<?php echo esc_attr($fIndex); ?>]" <?php echo $req_attr; ?>></div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ( $child_product->is_type( 'variable' ) ) : ?>
                                            <div class="wcb-variation-container" style="display:none;">
                                                <?php foreach ( $child_product->get_variation_attributes() as $attribute_name => $options ) : ?>
                                                    <div class="wcb-variation-field"><label><?php echo wc_attribute_label( $attribute_name ); ?>:</label><select class="wcb-variation-select" name="bundle_selection[<?php echo esc_attr( $group_index ); ?>][<?php echo esc_attr( $product_id ); ?>][attributes][attribute_<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>]" data-attribute-name="attribute_<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>" disabled><option value=""><?php _e('Scegli un\'opzione', 'wcb-framework'); ?></option><?php foreach ( $options as $option ) : list( $opt_disabled, $opt_suffix ) = $variation_option_attrs( $attribute_name, $option ); ?><option value="<?php echo esc_attr( $option ); ?>"<?php echo $opt_disabled; ?>><?php echo esc_html( $option . $opt_suffix ); ?></option><?php endforeach; ?></select></div>
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
                
                <?php /* NUOVO: Etichetta Sotto Prezzo Desktop */ ?>
                <?php if ( ! empty( $price_sublabel ) ) : ?>
                    <div class="wcb-price-sublabel"><?php echo wp_kses_post( $price_sublabel ); ?></div>
                <?php endif; ?>

                <div class="wcb-summary-actions">
                    <button type="submit" class="single_add_to_cart_button button alt" disabled>
                        <?php echo esc_html( $product->single_add_to_cart_text() ); ?>
                    </button>
                </div>
            </div>
        </div>
    </div> 
    <div class="wcb-mobile-sticky-bar">
        <div class="wcb-mobile-price-col"> <div class="wcb-mobile-price">
                <span class="wcb-price-label"><?php esc_html_e('Totale:', 'wcb-framework'); ?></span>
                <span class="wcb-price-value"></span>
            </div>
            <?php /* NUOVO: Etichetta Sotto Prezzo Mobile */ ?>
            <?php if ( ! empty( $price_sublabel ) ) : ?>
                <div class="wcb-price-sublabel mobile"><?php echo wp_kses_post( $price_sublabel ); ?></div>
            <?php endif; ?>
        </div>
        <button type="button" class="button alt wcb-mobile-submit-btn" disabled>
            <?php echo esc_html( $product->single_add_to_cart_text() ); ?>
        </button>
    </div>
</form>