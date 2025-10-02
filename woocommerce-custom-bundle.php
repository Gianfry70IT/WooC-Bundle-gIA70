<?php
/**
 * Plugin Name:       WooC Bundle gIA70
 * Description:       Un framework per creare prodotti bundle personalizzabili, unendo un'amministrazione stabile con un frontend funzionale.
 * Version:           2.1.0
 * Author:            gIA70 - Gianfranco Greco
 * Copyright (c) 2025 Gianfranco Greco
 * Licensed under the GNU GPL v2 or later: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wcb-framework
 * GithubRepo:        Gianfry70IT/WooC-Bundle-gIA70
 * Primary Branch:    main
 * Requires at least: 6.0
 * Tested up to:      6.8.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}

add_action( 'admin_init', 'wcb_dependency_check_on_plugins_page' );

function wcb_dependency_check_on_plugins_page() {
    if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
        $plugin_basename = plugin_basename( __FILE__ );
        add_action( 'after_plugin_row_' . $plugin_basename, 'wcb_show_wc_dependency_notice_row', 10, 2 );
        add_filter( 'plugin_action_links_' . $plugin_basename, 'wcb_disable_activate_link_for_wc_dependency' );
    }
}

function wcb_show_wc_dependency_notice_row( $plugin_file, $plugin_data ) {
    $wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
    $colspan = $wp_list_table->get_column_count();
    ?>
    <tr class="plugin-update-tr active notice-error notice-alt">
        <td colspan="<?php echo $colspan; ?>" class="plugin-update colspanchange">
            <div class="update-message notice-error notice-alt inline">
                <p>
                    <?php
                    printf(
                        esc_html__( 'Questo plugin non può essere attivato perché richiede %s per funzionare. Per favore, installa e attiva WooCommerce.', 'wcb-framework' ),
                        '<strong>WooCommerce</strong>'
                    );
                    ?>
                </p>
            </div>
        </td>
    </tr>
    <?php
}

function wcb_disable_activate_link_for_wc_dependency( $actions ) {
    if ( isset( $actions['activate'] ) ) {
        $actions['activate'] = '<span>' . esc_html__( 'Attiva', 'wcb-framework' ) . '</span>';
    }
    return $actions;
}

function wcb_admin_notice_if_wc_deactivated() {
    if ( is_plugin_active( plugin_basename( __FILE__ ) ) && ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__( '"WooC Bundle gIA70" è stato disattivato. Richiede che WooCommerce sia installato e attivo per funzionare.', 'wcb-framework' );
        echo '</p></div>';
        deactivate_plugins( plugin_basename( __FILE__ ) );
    }
}
add_action( 'admin_notices', 'wcb_admin_notice_if_wc_deactivated' );

require_once plugin_dir_path( __FILE__ ) . 'updater.php';

function wcb_initialize_updater() {
    new WCB_GitHub_Updater( __FILE__ );
}
add_action( 'plugins_loaded', 'wcb_initialize_updater' );

final class WC_Custom_Bundle_Framework {

    private static $instance;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    public function init() {
        if ( ! class_exists( 'WooCommerce' ) ) return;

        require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-product-custom-bundle.php';

        add_filter( 'product_type_selector', [ $this, 'add_bundle_product_type' ] );
        add_filter( 'woocommerce_product_class', [ $this, 'load_bundle_product_class' ], 10, 2 );
        add_filter( 'woocommerce_product_data_tabs', [ $this, 'add_bundle_options_tab' ] );
        add_action( 'woocommerce_product_data_panels', [ $this, 'add_bundle_options_panel' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_action( 'woocommerce_product_options_general_product_data', [ $this, 'add_bundle_pricing_fields' ] );
        add_action( 'woocommerce_process_product_meta_custom_bundle', [ $this, 'save_bundle_meta' ] );
        add_action( 'wp_ajax_wcb_custom_product_search', [ $this, 'handle_custom_product_search' ] );
        
        add_action( 'woocommerce_single_product_summary', [ $this, 'display_bundle_options_start' ], 30 );
        add_shortcode( 'wcb_bundle_form', [ $this, 'bundle_shortcode_callback' ] );

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );

        add_action( 'wp_ajax_wcb_add_bundle_to_cart', [ $this, 'wcb_add_bundle_to_cart_handler' ] );
        add_action( 'wp_ajax_nopriv_wcb_add_bundle_to_cart', [ $this, 'wcb_add_bundle_to_cart_handler' ] );

        add_filter( 'woocommerce_get_item_data', [ $this, 'display_bundle_selections_in_cart' ], 10, 2 );
        add_filter( 'woocommerce_add_cart_item_data', [ $this, 'attach_personalization_to_separate_items' ], 10, 3 );
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'add_selections_to_order_items' ], 10, 4 );
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'calculate_bundle_price_in_cart' ], 99 );
        add_action( 'woocommerce_order_status_processing', [ $this, 'reduce_stock_for_bundle_items' ] );
        add_action( 'woocommerce_cart_item_removed', [ $this, 'handle_bundle_item_removed' ], 10, 2 );
        add_action( 'wp_ajax_wcb_get_variation_price', [ $this, 'get_variation_price_handler' ] );
        add_action( 'wp_ajax_nopriv_wcb_get_variation_price', [ $this, 'get_variation_price_handler' ] );
    }
    
    public function get_variation_price_handler() {
        check_ajax_referer('wcb-add-to-cart-nonce', 'security');
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $attributes = isset($_POST['attributes']) ? $_POST['attributes'] : [];
        
        if (!$product_id) {
            wp_send_json_error(['message' => 'Product ID mancante']);
            return;
        }
        
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) {
            wp_send_json_error(['message' => 'Prodotto non valido']);
            return;
        }
        
        $data_store = WC_Data_Store::load('product');
        $variation_id = $data_store->find_matching_product_variation($product, $attributes);
        
        if ($variation_id) {
            $variation = wc_get_product($variation_id);
            $price = wc_get_price_to_display($variation);
            wp_send_json_success(['price' => $price]);
        } else {
            wp_send_json_error(['message' => 'Variante non trovata']);
        }
    }
    
    public function add_bundle_product_type($types) {
        $types['custom_bundle'] = __('WooC Bundle gIA70', 'wcb-framework');
        return $types;
    }

    public function load_bundle_product_class($classname, $product_type) {
        if ('custom_bundle' == $product_type) {
            return 'WC_Product_Custom_Bundle';
        }
        return $classname;
    }

    public function add_bundle_options_tab($tabs) {
        $tabs['custom_bundle'] = [
            'label'    => __('WooC Bundle gIA70', 'wcb-framework'),
            'target'   => 'custom_bundle_options',
            'class'    => ['show_if_custom_bundle'],
            'priority' => 80,
        ];
        return $tabs;
    }

    public function wcb_add_bundle_to_cart_handler() {
        check_ajax_referer('wcb-add-to-cart-nonce', 'security');
        parse_str($_POST['form_data'], $form_data);
        
        $product_id = isset($form_data['add-to-cart']) ? intval($form_data['add-to-cart']) : 0;
        $product = wc_get_product($product_id);

        if (!$product) {
            wp_send_json_error(['messages' => [__('Prodotto non trovato.', 'wcb-framework')]]);
            return;
        }
        
        $bundle_groups = $product->get_meta('_bundle_groups', true);
        if(!is_array($bundle_groups)) $bundle_groups = [];
        $errors = [];
        $final_selections = [];

        $posted_selections = $form_data['bundle_selection'] ?? [];
        $posted_radio_selections = $form_data['bundle_selection_radio'] ?? [];
        $posted_quantities = $form_data['wcb_quantity'] ?? [];
        $posted_variation_sets_all = $form_data['wcb_variation_sets'] ?? [];
        $posted_personalizations = $form_data['wcb_personalization'] ?? [];

        foreach ($bundle_groups as $group_index => $group_config) {
            $is_required = ($group_config['is_required'] ?? 'no') === 'yes';
            $selection_mode = $group_config['selection_mode'];
            $personalization_enabled = ($group_config['personalization_enabled'] ?? 'no') === 'yes';
            $personalization_required = ($group_config['personalization_required'] ?? 'no') === 'yes';
            $personalization_label = $group_config['personalization_label'] ?? __('Personalizzazione', 'wcb-framework');
            
            $selection_instances = [];

            if ('single' === $selection_mode) {
                if (isset($posted_radio_selections[$group_index])) {
                    $pid = absint($posted_radio_selections[$group_index]);
                    $child_product = wc_get_product($pid);
                    if ($child_product) {
                        $item_id = $pid;
                        if ($child_product->is_type('variable')) {
                            $attributes = $posted_selections[$group_index][$pid]['attributes'] ?? [];
                            $item_id = $this->find_variation_id_from_attributes($pid, $attributes, $errors);
                        }
                        if ($item_id > 0) {
                            $personalization = sanitize_text_field($posted_personalizations[$group_index][$pid][0] ?? '');
                            if ($personalization_enabled && $personalization_required && empty($personalization)) {
                                $errors[] = sprintf(__('Il campo "%1$s" per %2$s è obbligatorio.', 'wcb-framework'), $personalization_label, $child_product->get_name());
                            }
                            $selection_instances[] = ['item_id' => $item_id, 'personalization' => $personalization, 'personalization_label' => $personalization_label];
                        }
                    }
                }
            } elseif ('multiple' === $selection_mode) {
                $group_selections = $posted_selections[$group_index] ?? [];
                foreach ($group_selections as $pid => $data) {
                    if (isset($data['selected'])) {
                        $pid = absint($pid);
                        $child_product = wc_get_product($pid);
                        if ($child_product) {
                            $item_id = $pid;
                            if ($child_product->is_type('variable')) {
                                $attributes = $data['attributes'] ?? [];
                                $item_id = $this->find_variation_id_from_attributes($pid, $attributes, $errors);
                            }
                             if ($item_id > 0) {
                                $personalization = sanitize_text_field($posted_personalizations[$group_index][$pid][0] ?? '');
                                if ($personalization_enabled && $personalization_required && empty($personalization)) {
                                    $errors[] = sprintf(__('Il campo "%1$s" per %2$s è obbligatorio.', 'wcb-framework'), $personalization_label, $child_product->get_name());
                                }
                                $selection_instances[] = ['item_id' => $item_id, 'personalization' => $personalization, 'personalization_label' => $personalization_label];
                            }
                        }
                    }
                }
            } elseif ('quantity' === $selection_mode || 'multiple_quantity' === $selection_mode) {
                $quantities_in_group = $posted_quantities[$group_index] ?? [];
                foreach ($quantities_in_group as $pid => $qty) {
                    $pid = absint($pid);
                    $qty = absint($qty);
                    if ($qty > 0) {
                        $child_product = wc_get_product($pid);
                        if ($child_product) {
                            if ($child_product->is_type('variable')) {
                                $variation_sets = $posted_variation_sets_all[$group_index][$pid] ?? [];
                                if (count($variation_sets) !== $qty) {
                                    $errors[] = sprintf(__('Il numero di configurazioni (%1$d) per "%2$s" non corrisponde alla quantità richiesta (%3$d).', 'wcb-framework'), count($variation_sets), $child_product->get_name(), $qty);
                                    continue;
                                }
                                foreach ($variation_sets as $index => $attributes) {
                                    $var_id = $this->find_variation_id_from_attributes($pid, $attributes, $errors);
                                    if ($var_id > 0) {
                                        $personalization = sanitize_text_field($posted_personalizations[$group_index][$pid][$index] ?? '');
                                        if ($personalization_enabled && $personalization_required && empty($personalization)) {
                                            $errors[] = sprintf(__('Il campo "%1$s" per %2$s (Pezzo %3$d) è obbligatorio.', 'wcb-framework'), $personalization_label, $child_product->get_name(), $index + 1);
                                        }
                                        $selection_instances[] = ['item_id' => $var_id, 'personalization' => $personalization, 'personalization_label' => $personalization_label];
                                    }
                                }
                            } else {
                                for ($i = 0; $i < $qty; $i++) {
                                    $personalization = sanitize_text_field($posted_personalizations[$group_index][$pid][$i] ?? '');
                                    if ($personalization_enabled && $personalization_required && empty($personalization)) {
                                        $errors[] = sprintf(__('Il campo "%1$s" per %2$s (Pezzo %3$d) è obbligatorio.', 'wcb-framework'), $personalization_label, $child_product->get_name(), $i + 1);
                                    }
                                    $selection_instances[] = ['item_id' => $pid, 'personalization' => $personalization, 'personalization_label' => $personalization_label];
                                }
                            }
                        }
                    }
                }
            }

            if ($is_required && empty($selection_instances)) {
                 if (empty($errors)) {
                     $errors[] = sprintf(__('Devi fare una selezione per il gruppo "%s".', 'wcb-framework'), $group_config['title']);
                 }
            }

            if (!empty($selection_instances) && empty($errors)) {
                $item_count = count($selection_instances);
                if ('multiple' === $selection_mode) {
                    $min = absint($group_config['min_qty']); $max = absint($group_config['max_qty']);
                    if ($item_count < $min || ($max > 0 && $item_count > $max)) {
                        $errors[] = $max > 0 ? sprintf(__('Per il gruppo "%1$s", scegli da %2$d a %3$d prodotti (ne hai scelti %4$d).', 'wcb-framework'), $group_config['title'], $min, $max, $item_count) : sprintf(__('Per il gruppo "%1$s", scegli almeno %2$d prodotti (ne hai scelti %3$d).', 'wcb-framework'), $group_config['title'], $min, $item_count);
                    }
                } elseif ('quantity' === $selection_mode) {
                    $total = absint($group_config['total_qty']);
                    if ($item_count !== $total) {
                        $errors[] = sprintf(__('Per il gruppo "%1$s", devi scegliere un totale di %2$d articoli (ne hai scelti %3$d).', 'wcb-framework'), $group_config['title'], $total, $item_count);
                    }
                } elseif ('multiple_quantity' === $selection_mode) {
                    $min = absint($group_config['min_qty']);
                    $max = absint($group_config['max_qty']);
                    if ($item_count < $min || ($max > 0 && $item_count > $max)) {
                        $errors[] = $max > 0 ? sprintf(__('Per il gruppo "%1$s", scegli una quantità totale da %2$d a %3$d (ne hai scelti %4$d).', 'wcb-framework'), $group_config['title'], $min, $max, $item_count) : sprintf(__('Per il gruppo "%1$s", scegli una quantità totale di almeno %2$d (ne hai scelti %3$d).', 'wcb-framework'), $group_config['title'], $min, $item_count);
                    }
                }
            }

            if (empty($errors) && !empty($selection_instances)) {
                $final_selections[$group_index] = $selection_instances;
            }
        }

        if (!empty($errors)) {
            wp_send_json_error(['messages' => array_unique($errors)]);
            return;
        }

        $add_as_separate = get_post_meta($product_id, '_bundle_add_as_separate_items', true) === 'yes';

        if ($add_as_separate) {
            $bundle_cart_id = uniqid('wcb_');
            $aggregated_items = [];

            foreach ($final_selections as $group_selections) {
                foreach ($group_selections as $selection) {
                    $item_id = $selection['item_id'];
                    $aggregated_items[$item_id] = ($aggregated_items[$item_id] ?? 0) + 1;
                }
            }

            foreach ($aggregated_items as $item_id => $quantity) {
                $product_to_add = wc_get_product($item_id);
                $variation_id = $product_to_add->is_type('variation') ? $product_to_add->get_id() : 0;
                $parent_id = $product_to_add->is_type('variation') ? $product_to_add->get_parent_id() : $product_to_add->get_id();
                $attributes = $product_to_add->is_type('variation') ? $product_to_add->get_variation_attributes() : [];
                
                $cart_item_data = [
                    'wcb_bundle_part_of'      => $bundle_cart_id,
                    'wcb_parent_bundle_id'    => $product_id,
                    'wcb_bundle_configuration' => $final_selections
                ];
                
                WC()->cart->add_to_cart($parent_id, $quantity, $variation_id, $attributes, $cart_item_data);
            }
        } else {
            $cart_item_data = ['wcb_bundle_selections' => $final_selections];
            WC()->cart->add_to_cart($product_id, 1, 0, [], $cart_item_data);
        }
        
        wp_send_json_success(['cart_url' => wc_get_cart_url(), 'message' => __('Prodotti aggiunti al carrello.', 'wcb-framework')]);
    }
    
public function attach_personalization_to_separate_items( $cart_item_data, $product_id, $variation_id ) {
        if ( isset( $_POST['form_data'] ) ) {
            parse_str( $_POST['form_data'], $form_data );
            $bundle_product_id = isset( $form_data['add-to-cart'] ) ? intval( $form_data['add-to-cart'] ) : 0;
            if ( !$bundle_product_id ) return $cart_item_data;

            $add_as_separate = get_post_meta( $bundle_product_id, '_bundle_add_as_separate_items', true ) === 'yes';
            if ( !$add_as_separate ) return $cart_item_data;

            $posted_personalizations = $form_data['wcb_personalization'] ?? [];
            $item_id_to_check = $variation_id > 0 ? $variation_id : $product_id;

            foreach ( $posted_personalizations as $group_index => $products ) {
                foreach ( $products as $pid => $personalizations ) {
                    $child_product = wc_get_product($pid);
                    if (!$child_product) continue;
                    
                    // Se il prodotto nel form corrisponde a quello che stiamo aggiungendo al carrello
                    if ($child_product->is_type('variable')) {
                        // Se è variabile, dobbiamo trovare la variante corretta per fare il match
                        // Questa parte può diventare complessa; per ora ci basiamo sul parent ID
                    } else {
                        if ($pid == $product_id && !empty($personalizations[0])) {
                             $bundle_groups = get_post_meta($bundle_product_id, '_bundle_groups', true);
                             $group_config = $bundle_groups[$group_index] ?? null;
                             if ($group_config && ($group_config['personalization_enabled'] ?? 'no') === 'yes') {
                                $cart_item_data['wcb_personalization'] = sanitize_text_field($personalizations[0]);
                                $cart_item_data['wcb_personalization_label'] = $group_config['personalization_label'] ?? __('Personalizzazione', 'wcb-framework');
                                return $cart_item_data;
                             }
                        }
                    }
                }
            }
        }
        return $cart_item_data;
    }
    
    private function find_variation_id_from_attributes( $product_id, $posted_attributes, &$errors_ref = null ) {
        $product = wc_get_product( $product_id );
        if ( ! $product || ! $product->is_type('variable') ) return 0;

        $attributes_to_match = [];
        if (is_array($posted_attributes)) {
            foreach ($posted_attributes as $key => $value) {
                if (strpos($key, 'attribute_') === 0) {
                    $attributes_to_match[ $key ] = $value;
                }
            }
        }

        foreach ($product->get_variation_attributes() as $attribute_name => $options) {
            $full_attribute_key = 'attribute_' . sanitize_title($attribute_name);
            if (!isset($attributes_to_match[$full_attribute_key]) || '' === $attributes_to_match[$full_attribute_key]) {
                if (is_array($errors_ref)) {
                    $error_message = sprintf(__( 'Selezione incompleta per "%1$s". Per favore, scegli un\'opzione per "%2$s".', 'wcb-framework' ), $product->get_name(), wc_attribute_label($attribute_name));
                    if (!in_array($error_message, $errors_ref)) $errors_ref[] = $error_message;
                }
                return 0;
            }
        }

        $data_store = WC_Data_Store::load( 'product' );
        $variation_id = $data_store->find_matching_product_variation( $product, $attributes_to_match );

        if ( ! $variation_id ) {
            if (is_array($errors_ref)) {
                $error_message = sprintf(__( 'La combinazione di opzioni scelta per "%s" non è disponibile o non è valida.', 'wcb-framework' ), $product->get_name());
                if (!in_array($error_message, $errors_ref)) $errors_ref[] = $error_message;
            }
            return 0;
        }
        return $variation_id;
    }
    
    public function handle_custom_product_search() {
        check_ajax_referer('search-products', 'security');
        if (!current_user_can('edit_products')) wp_die(-1);

        $term = isset($_GET['term']) ? (string) wc_clean(wp_unslash($_GET['term'])) : '';
        if (empty($term)) wp_die();

        $exclude_ids = isset($_GET['exclude']) && !empty($_GET['exclude']) ? array_map('intval', explode(',', $_GET['exclude'])) : [];
        
        $data_store = WC_Data_Store::load('product');
        $ids = $data_store->search_products($term, '', false, false, 30);
        $products = [];

        foreach ($ids as $id) {
            if (in_array($id, $exclude_ids)) continue;
            $product = wc_get_product($id);
            if (!$product || $product->is_type('variation') || $product->is_type('custom_bundle')) continue;
            
            $image_id = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : wc_placeholder_img_src('thumbnail');

            $products[] = [
                'id' => $id,
                'text' => wp_strip_all_tags($product->get_formatted_name()),
                'image_url' => $image_url,
            ];
        }
        wp_send_json($products);
    }
    
    public function add_bundle_options_panel() {
        global $post; ?>
        <div id="custom_bundle_options" class="panel woocommerce_options_panel">
            <div class="options_group">
                <div class="form-field">
                    <div style="font-size:2em;font-weight:600;padding:10px;"><?php esc_html_e('Istruzioni per il Cliente', 'wcb-framework'); ?></div>
                    <?php
                    $content = get_post_meta($post->ID, '_bundle_instructions', true);
                    $editor_id = '_bundle_instructions';
                    $settings = array(
                        'textarea_name' => '_bundle_instructions',
                        'media_buttons' => true,
                        'textarea_rows' => 10,
                        'tinymce'       => array(
                            'toolbar1' => 'bold,italic,underline,bullist,numlist,link,unlink,undo,redo,wp_adv',
                            'toolbar2' => 'formatselect,alignleft,aligncenter,alignright,strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,fullscreen',
                        ),
                    );
                    wp_editor(wp_kses_post($content), $editor_id, $settings);
                    ?>
                </div>
            </div>   
            <div class="toolbar">
                <button type="button" class="button button-primary" id="add_bundle_group"><?php esc_html_e('Aggiungi Gruppo', 'wcb-framework'); ?></button>
                <button type="button" class="button" id="expand_all_groups"><?php esc_html_e('Espandi Tutto', 'wcb-framework'); ?></button>
                <button type="button" class="button" id="collapse_all_groups"><?php esc_html_e('Chiudi Tutto', 'wcb-framework'); ?></button>
            </div>
            <div style="font-size:2em;font-weight:600;padding:10px;"><?php esc_html_e('Gruppi del Bundle', 'wcb-framework'); ?> <span class="description">(<?php esc_html_e('Trascina per riordinare', 'wcb-framework'); ?>)</span></div>
            <div class="options_group" id="bundle_groups_container">
            </div>
            <div class="toolbar">
                <button type="button" class="button button-primary" id="add_bundle_group"><?php esc_html_e('Aggiungi Gruppo', 'wcb-framework'); ?></button>
                <button type="button" class="button" id="expand_all_groups"><?php esc_html_e('Espandi Tutto', 'wcb-framework'); ?></button>
                <button type="button" class="button" id="collapse_all_groups"><?php esc_html_e('Chiudi Tutto', 'wcb-framework'); ?></button>
            </div>
            <div class="wcb-signature">
                <p><strong>WooC Bundle gIA70</strong> by <em>Gianfranco Greco</em></p>
            </div>
        </div>
        <?php
    }

    public function enqueue_admin_scripts($hook) {
        global $post;
        if ('post-new.php' == $hook || ('post.php' == $hook && isset($post->post_type) && 'product' == $post->post_type)) {
            wp_enqueue_style('wcb-admin-style', plugin_dir_url(__FILE__) . 'assets/admin.css', [], '2.0.2');
            wp_enqueue_script('wcb-admin-script', plugin_dir_url(__FILE__) . 'assets/admin.js', ['jquery', 'wc-enhanced-select', 'jquery-ui-sortable'], '2.0.2', true);
            
            $bundle_groups_data = get_post_meta($post->ID, '_bundle_groups', true);
            if (!is_array($bundle_groups_data)) $bundle_groups_data = [];

            $groups_for_js = [];
            foreach ($bundle_groups_data as $group) {
                $products_with_names = [];
                if (!empty($group['products'])) {
                    foreach ($group['products'] as $product_id) {
                        $product = wc_get_product($product_id);
                        if ($product) {
                            $image_id = $product->get_image_id();
                            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : wc_placeholder_img_src('thumbnail');
                            $products_with_names[] = [
                                'id' => $product_id, 
                                'text' => wp_strip_all_tags($product->get_formatted_name()),
                                'image_url' => $image_url
                            ];
                        }
                    }
                }
                $group['products'] = $products_with_names;
                $groups_for_js[] = $group;
            }
            wp_localize_script('wcb-admin-script', 'wcb_bundle_data', ['groups' => $groups_for_js]);
        }
    }

    public function enqueue_frontend_scripts() {
        if (is_product() && get_the_id() && wc_get_product(get_the_id())->get_type() === 'custom_bundle') {
            wp_enqueue_style('wcb-frontend-style', plugin_dir_url(__FILE__) . 'assets/frontend.css', [], '2.0.3');
            wp_enqueue_script('wcb-frontend-script', plugin_dir_url(__FILE__) . 'assets/frontend.js', ['jquery'], '2.0.3', true);
    
            $product_id = get_the_id();
            $pricing_data = [
                'type'                  => get_post_meta($product_id, '_bundle_pricing_type', true),
                'fixed_price'           => wc_get_price_to_display(wc_get_product($product_id)),
                'discount_amount'       => floatval(get_post_meta($product_id, '_bundle_discount_amount', true)),
                'discount_percentage'   => floatval(get_post_meta($product_id, '_bundle_discount_percentage', true)),
                'currency_symbol'       => get_woocommerce_currency_symbol(),
                'decimal_separator'     => wc_get_price_decimal_separator(),
                'thousand_separator'    => wc_get_price_thousand_separator(),
                'currency_position'     => get_option( 'woocommerce_currency_pos' )
            ];
    
            wp_localize_script('wcb-frontend-script', 'wcb_params', [
                'ajax_url' => admin_url('admin-ajax.php'), 
                'nonce' => wp_create_nonce('wcb-add-to-cart-nonce'),
                'pricing' => $pricing_data
            ]);
        }
    }

    public function save_bundle_meta($post_id) {
        if (isset($_POST['_bundle_instructions'])) {
            update_post_meta($post_id, '_bundle_instructions', wp_kses_post($_POST['_bundle_instructions']));
        }
        if (isset($_POST['_bundle_groups']) && is_array($_POST['_bundle_groups'])) {
            $groups_data = [];
            foreach ($_POST['_bundle_groups'] as $group) {
                $groups_data[] = [
                    'title'                    => sanitize_text_field($group['title'] ?? ''),
                    'products'                 => isset($group['products']) ? array_map('intval', $group['products']) : [],
                    'selection_mode'           => sanitize_text_field($group['selection_mode'] ?? 'multiple'),
                    'min_qty'                  => absint($group['min_qty'] ?? 1),
                    'max_qty'                  => absint($group['max_qty'] ?? 0),
                    'total_qty'                => absint($group['total_qty'] ?? 1),
                    'is_required'              => isset($group['is_required']) ? 'yes' : 'no',
                    'personalization_enabled'  => isset($group['personalization_enabled']) ? 'yes' : 'no',
                    'personalization_label'    => sanitize_text_field($group['personalization_label'] ?? ''),
                    'personalization_required' => isset($group['personalization_required']) ? 'yes' : 'no',
                ];
            }
            update_post_meta($post_id, '_bundle_groups', $groups_data);
        } else {
            delete_post_meta($post_id, '_bundle_groups');
        }

        update_post_meta($post_id, '_bundle_pricing_type', wc_clean($_POST['_bundle_pricing_type'] ?? 'fixed'));
        update_post_meta($post_id, '_bundle_discount_amount', wc_clean($_POST['_bundle_discount_amount'] ?? ''));
        update_post_meta($post_id, '_bundle_discount_percentage', wc_clean($_POST['_bundle_discount_percentage'] ?? ''));
        update_post_meta($post_id, '_bundle_add_as_separate_items', isset($_POST['_bundle_add_as_separate_items']) ? 'yes' : 'no');
    }

    public function add_bundle_pricing_fields() {
        echo '<div class="options_group show_if_custom_bundle">';

        woocommerce_wp_select([
            'id'      => '_bundle_pricing_type',
            'label'   => __('Tipo di Prezzo Bundle', 'wcb-framework'),
            'options' => [
                'fixed'      => __('Prezzo Fisso', 'wcb-framework'),
                'calculated' => __('Prezzo Calcolato', 'wcb-framework'),
            ],
            'desc_tip' => true,
            'description' => __('Scegli come calcolare il prezzo del bundle.', 'wcb-framework'),
        ]);

        echo '<div class="bundle_price_field_wrapper bundle_price_fixed_field">';
        echo '<p class="form-field"><em>' . __('Il prezzo totale del bundle deve essere inserito nel campo "Prezzo di Listino" nella tab "Generale".', 'wcb-framework') . '</em></p>';
        echo '</div>';

        echo '<div class="bundle_price_field_wrapper bundle_price_calculated_field">';
        woocommerce_wp_text_input([
            'id'          => '_bundle_discount_amount',
            'label'       => __('Sconto Fisso (€)', 'wcb-framework'),
            'data_type'   => 'price',
            'desc_tip'    => true,
            'description' => __('Applica uno sconto fisso sul totale calcolato dei prodotti.', 'wcb-framework'),
        ]);
        woocommerce_wp_text_input([
            'id'          => '_bundle_discount_percentage',
            'label'       => __('Sconto Percentuale (%)', 'wcb-framework'),
            'data_type'   => 'decimal',
            'desc_tip'    => true,
            'description' => __('Applica uno sconto percentuale sul totale calcolato dei prodotti.', 'wcb-framework'),
        ]);
        echo '</div>';
        
        woocommerce_wp_checkbox([
            'id'          => '_bundle_add_as_separate_items',
            'label'       => __('Modalità Carrello', 'wcb-framework'),
            'description' => __('Aggiungi i prodotti scelti come articoli separati nel carrello', 'wcb-framework'),
            'desc_tip'    => true,
        ]);

        echo '</div>';
    }

    public function display_bundle_options_start() {
        global $product;
        if ($product && $product->get_type() === 'custom_bundle') {
            $this->render_bundle_options_html($product->get_id());
        }
    }

    public function bundle_shortcode_callback( $atts ) {
        $atts = shortcode_atts(['id' => get_the_ID()], $atts, 'wcb_bundle_form');
        $product_id = absint($atts['id']);
        if (!$product_id) return '';

        remove_action('woocommerce_single_product_summary', [$this, 'display_bundle_options_start'], 30);

        ob_start();
        $this->render_bundle_options_html($product_id);
        return ob_get_clean();
    }
    
    public function render_bundle_options_html( $product_id ) {
        $product = wc_get_product( $product_id );
        if ( ! $product || $product->get_type() !== 'custom_bundle' ) return;

        $bundle_groups_raw = get_post_meta($product->get_id(), '_bundle_groups', true);
        if (empty($bundle_groups_raw) || !is_array($bundle_groups_raw)) {
            echo '<p>' . esc_html__('Prodotto non configurato.', 'wcb-framework') . '</p>';
            return;
        }

        $bundle_groups_filtered = [];
        foreach ($bundle_groups_raw as $group) {
            $available_products = [];
            if (!empty($group['products'])) {
                foreach ($group['products'] as $p_id) {
                    $child_product = wc_get_product($p_id);
                    if ($child_product && $child_product->is_in_stock() && $child_product->is_purchasable()) {
                        $available_products[] = $p_id;
                    }
                }
            }
            $group['products'] = $available_products;
            if (!empty($group['products'])) {
                $bundle_groups_filtered[] = $group;
            }
        }
        
        wc_get_template('single-product/add-to-cart/custom-bundle.php', [
            'product' => $product, 
            'bundle_groups' => $bundle_groups_filtered, 
            'instructions' => get_post_meta($product->get_id(), '_bundle_instructions', true),
        ], '', plugin_dir_path(__FILE__) . 'templates/');
    }

    public function display_bundle_selections_in_cart($item_data, $cart_item) {
        if (!empty($cart_item['wcb_bundle_selections'])) {
            $item_data = array_filter($item_data, function($data) {
                return !isset($data['display']) || strpos($data['display'], 'wcb-bundle-item-meta') === false;
            });

            $item_data[] = ['key' => __('Contenuto del Bundle', 'wcb-framework'), 'display' => '', 'display_class' => 'wcb-bundle-item-meta'];
            
            $display_items = [];
            foreach ($cart_item['wcb_bundle_selections'] as $group_selections) {
                foreach ($group_selections as $selection) {
                    $product = wc_get_product($selection['item_id']);
                    if ($product) {
                        $unique_key = $selection['item_id'] . '|' . ($selection['personalization'] ?? '');
                        if (!isset($display_items[$unique_key])) {
                            $display_items[$unique_key] = ['product_name' => $product->get_formatted_name(), 'personalization' => $selection['personalization'] ?? '', 'personalization_label' => $selection['personalization_label'] ?? __('Personalizzazione', 'wcb-framework'), 'quantity' => 0];
                        }
                        $display_items[$unique_key]['quantity']++;
                    }
                }
            }

            foreach ($display_items as $item) {
                 $product_line = $item['product_name'];
                 if ($item['quantity'] > 1) $product_line .= ' &times; ' . $item['quantity'];
                 $item_data[] = ['key' => '', 'display' => $product_line, 'display_class' => 'wcb-bundle-item-meta'];
                 if (!empty($item['personalization'])) {
                     $item_data[] = ['key' => '', 'display' => '<small style="padding-left: 15px;">' . esc_html($item['personalization_label']) . ': ' . esc_html($item['personalization']) . '</small>', 'display_class' => 'wcb-bundle-item-meta'];
                 }
            }
        }
        if ( ! empty( $cart_item['wcb_personalization'] ) ) {
            $label = $cart_item['wcb_personalization_label'] ?? __('Personalizzazione', 'wcb-framework');
            $item_data[] = [
                'key'     => $label,
                'display' => esc_html( $cart_item['wcb_personalization'] ),
            ];
        }
        return $item_data;
    }

    public function add_selections_to_order_items($item, $cart_item_key, $values, $order) {
        if (!empty($values['wcb_bundle_selections'])) {
            $item->add_meta_data('_wcb_bundle_selections_hidden', $values['wcb_bundle_selections'], true);
            $item->add_meta_data(__('Contenuto del Bundle', 'wcb-framework'), '', true);
    
            $display_items = [];
            foreach ($values['wcb_bundle_selections'] as $group_selections) {
                foreach ($group_selections as $selection) {
                    $product = wc_get_product($selection['item_id']);
                    if ($product) {
                        $unique_key = $selection['item_id'] . '|' . ($selection['personalization'] ?? '');
                        if (!isset($display_items[$unique_key])) {
                            $display_items[$unique_key] = ['product_name' => $product->get_formatted_name(), 'personalization' => $selection['personalization'] ?? '', 'personalization_label' => $selection['personalization_label'] ?? __('Personalizzazione', 'wcb-framework'), 'quantity' => 0];
                        }
                        $display_items[$unique_key]['quantity']++;
                    }
                }
            }
            
            foreach ($display_items as $display_item) {
                $display_line = $display_item['product_name'];
                if ($display_item['quantity'] > 1) $display_line .= ' &times; ' . $display_item['quantity'];
                if (!empty($display_item['personalization'])) $display_line .= ' | ' . esc_html($display_item['personalization_label']) . ': ' . esc_html($display_item['personalization']);
                $item->add_meta_data(':>', $display_line, false);
            }
        }
        if ( ! empty( $values['wcb_personalization'] ) ) {
            $label = $values['wcb_personalization_label'] ?? __('Personalizzazione', 'wcb-framework');
            $item->add_meta_data( $label, $values['wcb_personalization'] );
        }
    }

    public function handle_bundle_item_removed($cart_item_key, $cart) {
        $removed_item = $cart->get_removed_cart_contents()[$cart_item_key] ?? null;
        if ($removed_item && isset($removed_item['wcb_bundle_part_of'])) {
            $bundle_cart_id = $removed_item['wcb_bundle_part_of'];
            foreach ($cart->get_cart() as $key => $item) {
                if (isset($item['wcb_bundle_part_of']) && $item['wcb_bundle_part_of'] === $bundle_cart_id) {
                    unset($cart->cart_contents[$key]['wcb_bundle_part_of']);
                    unset($cart->cart_contents[$key]['wcb_parent_bundle_id']);
                    unset($cart->cart_contents[$key]['wcb_bundle_configuration']);
                }
            }
        }
    }

    public function reduce_stock_for_bundle_items($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        foreach ($order->get_items() as $item) {
            $selections = $item->get_meta('_wcb_bundle_selections_hidden');
            if ($selections && is_array($selections)) {
                $quantities_to_reduce = [];
                foreach ($selections as $group_selections) {
                    foreach ($group_selections as $selection) {
                        $quantities_to_reduce[$selection['item_id']] = ($quantities_to_reduce[$selection['item_id']] ?? 0) + 1;
                    }
                }
                foreach ($quantities_to_reduce as $product_id => $quantity) {
                    $child_product = wc_get_product($product_id);
                    if ($child_product && $child_product->managing_stock()) {
                        wc_update_product_stock($child_product, $quantity, 'decrease');
                    }
                }
            }
        }
    }

    public function calculate_bundle_price_in_cart($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['wcb_bundle_part_of'])) {
                $original_product = wc_get_product($cart_item['data']->get_id());
                if ($original_product) {
                    $cart->cart_contents[$cart_item_key]['data']->set_price($original_product->get_price());
                }
            }
        }

        $separate_bundles = [];
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['wcb_bundle_part_of'])) {
                $bundle_cart_id = $cart_item['wcb_bundle_part_of'];
                $separate_bundles[$bundle_cart_id][$cart_item_key] = $cart_item;
            }
        }

        foreach ($separate_bundles as $bundle_cart_id => $items) {
            $first_item = reset($items);
            if (!isset($first_item['wcb_parent_bundle_id']) || !isset($first_item['wcb_bundle_configuration'])) continue;
            
            $parent_bundle_id = $first_item['wcb_parent_bundle_id'];
            $bundle_recipe = $first_item['wcb_bundle_configuration'];

            $expected_items = [];
            foreach ($bundle_recipe as $group_selections) {
                foreach ($group_selections as $selection) {
                    $expected_items[$selection['item_id']] = ($expected_items[$selection['item_id']] ?? 0) + 1;
                }
            }

            $actual_items = [];
            foreach ($items as $item) {
                $actual_items[$item['data']->get_id()] = ($actual_items[$item['data']->get_id()] ?? 0) + $item['quantity'];
            }
            ksort($expected_items);
            ksort($actual_items);

            if ($expected_items != $actual_items) continue;

            $pricing_type = get_post_meta($parent_bundle_id, '_bundle_pricing_type', true);
            $bundle_final_price = 0;
            $original_subtotal = 0;
            foreach ($items as $item) {
                $original_product = wc_get_product($item['data']->get_id());
                if($original_product) $original_subtotal += floatval($original_product->get_price()) * $item['quantity'];
            }

            if ($pricing_type === 'fixed') {
                $bundle_product = wc_get_product($parent_bundle_id);
                $bundle_final_price = floatval($bundle_product->get_price());
            } else { // 'calculated'
                $subtotal = $original_subtotal;
                $discount_percentage = floatval(get_post_meta($parent_bundle_id, '_bundle_discount_percentage', true));
                $discount_amount = floatval(get_post_meta($parent_bundle_id, '_bundle_discount_amount', true));
                if ($discount_percentage > 0) $subtotal *= (1 - ($discount_percentage / 100));
                if ($discount_amount > 0) $subtotal -= $discount_amount;
                $bundle_final_price = max(0, $subtotal);
            }
            
            if ($original_subtotal > 0) {
                $discount_ratio = $bundle_final_price / $original_subtotal;
                foreach ($items as $key => $item) {
                    $original_product = wc_get_product($item['data']->get_id());
                    if($original_product) {
                        $new_price = floatval($original_product->get_price()) * $discount_ratio;
                        $cart->cart_contents[$key]['data']->set_price($new_price);
                    }
                }
            } elseif ($bundle_final_price > 0 && count($actual_items) > 0) {
                $total_quantity = array_sum($actual_items);
                if($total_quantity > 0){
                    $per_item_price = $bundle_final_price / $total_quantity;
                    foreach ($items as $key => $item) $cart->cart_contents[$key]['data']->set_price($per_item_price);
                }
            }
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if ('custom_bundle' === $cart_item['data']->get_type() && !empty($cart_item['wcb_bundle_selections'])) {
                $product_id = $cart_item['product_id'];
                $pricing_type = get_post_meta($product_id, '_bundle_pricing_type', true);
                if ('calculated' === $pricing_type) {
                    $calculated_price = 0;
                    foreach ($cart_item['wcb_bundle_selections'] as $group_selections) {
                        foreach ($group_selections as $selection) {
                            $child_product = wc_get_product($selection['item_id']);
                            if ($child_product) $calculated_price += floatval($child_product->get_price());
                        }
                    }
                    $discount_percentage = floatval(get_post_meta($product_id, '_bundle_discount_percentage', true));
                    $discount_amount = floatval(get_post_meta($product_id, '_bundle_discount_amount', true));
                    if ($discount_percentage > 0) $calculated_price *= (1 - ($discount_percentage / 100));
                    if ($discount_amount > 0) $calculated_price -= $discount_amount;
                    $cart_item['data']->set_price(max(0, $calculated_price));
                } elseif ('fixed' === $pricing_type) {
                     $bundle_product = wc_get_product($product_id);
                     if ($bundle_product) {
                        $cart_item['data']->set_price(floatval($bundle_product->get_price()));
                     }
                }
            }
        }
    }
}
WC_Custom_Bundle_Framework::get_instance();

