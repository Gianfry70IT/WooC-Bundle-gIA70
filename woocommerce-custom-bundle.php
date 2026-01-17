<?php
/**
 * Plugin Name:       WooC Bundle gIA70
 * Description:       Un framework per creare prodotti bundle personalizzabili, unendo un'amministrazione stabile con un frontend funzionale.
 * Version:           2.3.6
 * Author:            gIA70 - Gianfranco Greco
 * Copyright (c) 2025 Gianfranco Greco
 * Licensed under the GNU GPL v2 or later: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wcb-framework
 * GithubRepo:        Gianfry70IT/WooC-Bundle-gIA70
 * Primary Branch:    main
 * Requires at least: 6.0
 * Tested up to:      6.8.2
 */

if ( ! defined( 'ABSPATH' ) ) exit;


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
        
        add_action( 'woocommerce_after_single_product_summary', [ $this, 'display_bundle_options_start' ], 5 );
        add_action( 'template_redirect', [ $this, 'setup_bundle_layout' ] );
        
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
        
        add_filter( 'woocommerce_cart_item_name', [ $this, 'add_bundle_info_to_cart_item_name' ], 10, 3 );
        add_filter( 'plugin_row_meta', [ $this, 'add_plugin_meta_links' ], 10, 2 );
    }
    
    public function setup_bundle_layout() {
        if ( is_product() ) {
            $product = wc_get_product( get_the_ID() );
            if ( $product && $product->get_type() === 'custom_bundle' ) {
                remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );
                remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
                remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
                remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
                remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
                
                add_action( 'woocommerce_before_single_product', [ $this, 'render_bundle_hero' ], 10 );
            }
        }
    }

    public function render_bundle_hero() {
        global $product;
        $image_url = wp_get_attachment_image_url( $product->get_image_id(), 'full' );
        $title = $product->get_name();
        $short_desc = $product->get_short_description();
        ?>
        <div class="wcb-hero-section" style="background-image: url('<?php echo esc_url($image_url); ?>');">
            <div class="wcb-hero-overlay"></div>
            <div class="wcb-hero-content">
                <h1 class="wcb-hero-title"><?php echo esc_html($title); ?></h1>
                <?php if ( ! empty( $short_desc ) ) : ?>
                    <div class="wcb-hero-description"><?php echo wp_kses_post($short_desc); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    public function add_plugin_meta_links( $links, $file ) {
        if ( plugin_basename( __FILE__ ) === $file ) {
            $row_meta = [
                'nerodigitale' => '<a href="https://nerodigitale.it" target="_blank" style="font-weight:bold; color:#2271b1;">Nero Digitale</a>',
                'view_details' => '<a href="https://github.com/Gianfry70IT/WooC-Bundle-gIA70" target="_blank" aria-label="' . esc_attr__( 'Visualizza dettagli su GitHub', 'wcb-framework' ) . '">' . esc_html__( 'View details', 'wcb-framework' ) . '</a>',
            ];
            return array_merge( $links, $row_meta );
        }
        return $links;
    }
    
    public function add_bundle_info_to_cart_item_name( $product_name, $cart_item, $cart_item_key ) {
        if ( isset( $cart_item['wcb_bundle_part_of'] ) && isset( $cart_item['wcb_parent_bundle_id'] ) ) {
            $parent_bundle = wc_get_product( $cart_item['wcb_parent_bundle_id'] );
            if ( $parent_bundle ) {
                $bundle_id_attr = esc_attr( $cart_item['wcb_bundle_part_of'] );
                
                $edit_url = add_query_arg( [
                    'wcb_edit' => $cart_item['wcb_bundle_part_of'], 
                    'v'        => time() 
                ], $parent_bundle->get_permalink() );

                $html = $product_name;
                $html .= '<div class="wcb-cart-bundle-info" data-wcb-bundle-id="' . $bundle_id_attr . '">';
                $html .= '<span class="wcb-bundle-label">' . sprintf( __( 'Bundle: %s', 'wcb-framework' ), $parent_bundle->get_name() ) . '</span>';
                $html .= ' <a href="' . esc_url( $edit_url ) . '" class="wcb-edit-bundle-link">' . __( 'Modifica Bundle', 'wcb-framework' ) . '</a>';
                $html .= '</div>';
                
                return $html;
            }
        }
        return $product_name;
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

        $update_bundle_id = isset($form_data['wcb_update_bundle_id']) ? wc_clean($form_data['wcb_update_bundle_id']) : '';
        
        $bundle_groups = $product->get_meta('_bundle_groups', true);
        if(!is_array($bundle_groups)) $bundle_groups = [];
        $errors = [];
        $final_selections = [];

        $posted_selections = $form_data['bundle_selection'] ?? [];
        $posted_radio_selections = $form_data['bundle_selection_radio'] ?? [];
        $posted_quantities = $form_data['wcb_quantity'] ?? [];
        $posted_variation_sets_all = $form_data['wcb_variation_sets'] ?? [];
        $posted_personalizations = $form_data['wcb_personalization'] ?? [];

        $get_personalization_data = function($group_index, $pid, $set_index, $product_name) use ($bundle_groups, $posted_personalizations, &$errors) {
            $group_config = $bundle_groups[$group_index];
            $enabled = ($group_config['personalization_enabled'] ?? 'no') === 'yes';
            $fields = $group_config['personalization_fields'] ?? [];
            $data_to_store = [];

            if ($enabled && !empty($fields)) {
                foreach ($fields as $fIndex => $field) {
                    $val = sanitize_text_field($posted_personalizations[$group_index][$pid][$set_index][$fIndex] ?? '');
                    if (($field['required'] ?? 'no') === 'yes' && empty($val)) {
                        $errors[] = sprintf(__('Il campo "%1$s" per %2$s è obbligatorio.', 'wcb-framework'), $field['label'], $product_name);
                    }
                    if (!empty($val)) {
                        $data_to_store[] = ['label' => $field['label'], 'value' => $val];
                    }
                }
            }
            return $data_to_store;
        };

        // Helper per validazione Min/Step
        $validate_product_qty = function($qty, $pid, $group_config, $product_name) use (&$errors) {
            $products_settings = $group_config['products_settings'] ?? [];
            $settings = $products_settings[$pid] ?? [];
            
            $min = absint($settings['min_qty'] ?? 1);
            $step = absint($settings['step'] ?? 1);

            if ($qty < $min) {
                $errors[] = sprintf(__('La quantità minima per "%s" è %d.', 'wcb-framework'), $product_name, $min);
                return false;
            }
            if ($qty % $step !== 0) {
                $errors[] = sprintf(__('La quantità per "%s" deve essere un multiplo di %d.', 'wcb-framework'), $product_name, $step);
                return false;
            }
            return true;
        };

        foreach ($bundle_groups as $group_index => $group_config) {
            $is_required = ($group_config['is_required'] ?? 'no') === 'yes';
            $selection_mode = $group_config['selection_mode'];
            $selection_instances = [];

            if ('single' === $selection_mode) {
                if (isset($posted_radio_selections[$group_index])) {
                    $pid = absint($posted_radio_selections[$group_index]);
                    $child_product = wc_get_product($pid);
                    if ($child_product) {
                        // FIX: NON chiamiamo validate_product_qty qui. 
                        // In modalità singola (radio), la quantità è sempre 1, ignoriamo min/step accidentali.

                        $item_id = $pid;
                        if ($child_product->is_type('variable')) {
                            $attributes = $posted_selections[$group_index][$pid]['attributes'] ?? [];
                            $item_id = $this->find_variation_id_from_attributes($pid, $attributes, $errors);
                        }
                        if ($item_id > 0) {
                            $pers_data = $get_personalization_data($group_index, $pid, 0, $child_product->get_name());
                            $selection_instances[] = ['item_id' => $item_id, 'personalization_data' => $pers_data];
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
                             // FIX: NON chiamiamo validate_product_qty qui. 
                             // In modalità multipla (checkbox), la selezione vale 1.

                            $item_id = $pid;
                            if ($child_product->is_type('variable')) {
                                $attributes = $data['attributes'] ?? [];
                                $item_id = $this->find_variation_id_from_attributes($pid, $attributes, $errors);
                            }
                             if ($item_id > 0) {
                                $pers_data = $get_personalization_data($group_index, $pid, 0, $child_product->get_name());
                                $selection_instances[] = ['item_id' => $item_id, 'personalization_data' => $pers_data];
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
                            // Qui MANTENIAMO la validazione perché l'utente inserisce un numero
                            if(!$validate_product_qty($qty, $pid, $group_config, $child_product->get_name())) continue;

                            if ($child_product->is_type('variable')) {
                                $variation_sets = $posted_variation_sets_all[$group_index][$pid] ?? [];
                                if (count($variation_sets) !== $qty) {
                                    $errors[] = sprintf(__('Quantità errata per "%s".', 'wcb-framework'), $child_product->get_name());
                                    continue;
                                }
                                foreach ($variation_sets as $index => $attributes) {
                                    $var_id = $this->find_variation_id_from_attributes($pid, $attributes, $errors);
                                    if ($var_id > 0) {
                                        $pers_data = $get_personalization_data($group_index, $pid, $index, $child_product->get_name());
                                        $selection_instances[] = ['item_id' => $var_id, 'personalization_data' => $pers_data];
                                    }
                                }
                            } else {
                                for ($i = 0; $i < $qty; $i++) {
                                    $pers_data = $get_personalization_data($group_index, $pid, $i, $child_product->get_name());
                                    $selection_instances[] = ['item_id' => $pid, 'personalization_data' => $pers_data];
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
                $final_selections[$group_index] = $selection_instances;
            }
        }

        if (!empty($errors)) {
            wp_send_json_error(['messages' => array_unique($errors)]);
            return;
        }

        if (!empty($update_bundle_id)) {
            $cart = WC()->cart;
            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                if (isset($cart_item['wcb_bundle_part_of']) && $cart_item['wcb_bundle_part_of'] === $update_bundle_id) {
                    $cart->remove_cart_item($cart_item_key);
                }
            }
        }

        $add_as_separate = get_post_meta($product_id, '_bundle_add_as_separate_items', true) === 'yes';

        if ($add_as_separate) {
            $bundle_cart_id = uniqid('wcb_'); 
            
            // CORREZIONE CRITICA: Iteriamo per GRUPPO, non aggreghiamo globalmente per ID.
            foreach ($final_selections as $group_idx => $group_selections) {
                // Aggreghiamo SOLO all'interno dello stesso gruppo per evitare troppe righe duplicate se identiche
                $group_aggregated_items = [];

                foreach ($group_selections as $selection) {
                    $item_id = $selection['item_id'];
                    // Usiamo un hash unico per aggregare solo se item_id E personalizzazioni sono identici
                    $hash = $item_id . md5(json_encode($selection['personalization_data']));
                    
                    if (!isset($group_aggregated_items[$hash])) {
                        $group_aggregated_items[$hash] = [
                            'item_id' => $item_id,
                            'quantity' => 0,
                            'personalization_data' => $selection['personalization_data']
                        ];
                    }
                    $group_aggregated_items[$hash]['quantity']++;
                }

                foreach ($group_aggregated_items as $item_data) {
                    $item_id = $item_data['item_id'];
                    $quantity = $item_data['quantity'];
                    $pers_data = $item_data['personalization_data'];

                    $product_to_add = wc_get_product($item_id);
                    $variation_id = $product_to_add->is_type('variation') ? $product_to_add->get_id() : 0;
                    $parent_id = $product_to_add->is_type('variation') ? $product_to_add->get_parent_id() : $product_to_add->get_id();
                    $attributes = $product_to_add->is_type('variation') ? $product_to_add->get_variation_attributes() : [];
                    
                    $cart_item_data = [
                        'wcb_bundle_part_of'       => $bundle_cart_id,
                        'wcb_parent_bundle_id'     => $product_id,
                        'wcb_bundle_configuration' => $final_selections,
                        // NUOVO: Salviamo l'indice del gruppo sorgente per applicare il prezzo corretto
                        'wcb_source_group_index'   => $group_idx 
                    ];
                    
                    if(!empty($pers_data)) {
                        $cart_item_data['wcb_item_personalization'] = $pers_data;
                    }

                    WC()->cart->add_to_cart($parent_id, $quantity, $variation_id, $attributes, $cart_item_data);
                }
            }
        } else {
            $cart_item_data = ['wcb_bundle_selections' => $final_selections];
            WC()->cart->add_to_cart($product_id, 1, 0, [], $cart_item_data);
        }
        
        $message = !empty($update_bundle_id) ? __('Bundle aggiornato con successo.', 'wcb-framework') : __('Prodotti aggiunti al carrello.', 'wcb-framework');
        wp_send_json_success(['cart_url' => wc_get_cart_url(), 'message' => $message]);
    }
    
    // --- FIX CRITICO PER CARRELLO "SEPARATE ITEMS" ---
    public function attach_personalization_to_separate_items( $cart_item_data, $product_id, $variation_id ) {
        if ( isset($cart_item_data['wcb_item_personalization']) ) {
            return $cart_item_data;
        }

        if ( isset( $cart_item_data['wcb_bundle_configuration'] ) ) {
            $config = $cart_item_data['wcb_bundle_configuration'];
            $target_id = $variation_id > 0 ? $variation_id : $product_id;

            foreach ($config as $group_selections) {
                foreach ($group_selections as $selection) {
                    if ($selection['item_id'] == $target_id && !empty($selection['personalization_data'])) {
                        $cart_item_data['wcb_item_personalization'] = $selection['personalization_data'];
                        return $cart_item_data;
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
            <div class="options_group" id="bundle_groups_container"></div>
            <div class="wcb-signature">
                <p><strong>WooC Bundle gIA70</strong> by <em>Gianfranco Greco</em></p>
            </div>
        </div>
        <?php
    }

    public function enqueue_admin_scripts($hook) {
        global $post;
        if ('post-new.php' == $hook || ('post.php' == $hook && isset($post->post_type) && 'product' == $post->post_type)) {
            // VERSIONE 2.3.6: Forza refresh cache
            wp_enqueue_style('wcb-admin-style', plugin_dir_url(__FILE__) . 'assets/admin.css', [], '2.3.6');
            wp_enqueue_script('wcb-admin-script', plugin_dir_url(__FILE__) . 'assets/admin.js', ['jquery', 'wc-enhanced-select', 'jquery-ui-sortable'], '2.3.6', true);
            
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
            // VERSIONE 2.3.6
            wp_enqueue_style('wcb-frontend-style', plugin_dir_url(__FILE__) . 'assets/frontend.css', [], '2.3.6');
            wp_enqueue_script('wcb-frontend-script', plugin_dir_url(__FILE__) . 'assets/frontend.js', ['jquery'], '2.3.6', true);
    
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
            
            $edit_config = null;
            $edit_bundle_id = null;
            $enriched_config = [];

            if (isset($_GET['wcb_edit']) && !empty($_GET['wcb_edit'])) {
                $edit_bundle_id = sanitize_text_field($_GET['wcb_edit']);
                $cart = WC()->cart->get_cart();
                foreach ($cart as $key => $item) {
                    if (isset($item['wcb_bundle_part_of']) && $item['wcb_bundle_part_of'] === $edit_bundle_id) {
                        if (isset($item['wcb_bundle_configuration'])) {
                            $edit_config = $item['wcb_bundle_configuration'];
                            break; 
                        }
                    }
                }

                if ($edit_config) {
                    foreach ($edit_config as $group_idx => $group_selections) {
                        foreach ($group_selections as $selection) {
                            $item_id = $selection['item_id'];
                            $product = wc_get_product($item_id);
                            
                            $item_data = $selection;
                            
                            if ($product && $product->is_type('variation')) {
                                $item_data['is_variation'] = true;
                                $item_data['parent_id'] = $product->get_parent_id();
                                
                                $attributes = [];
                                foreach ( $product->get_attributes() as $name => $value ) {
                                    $key = 'attribute_' . sanitize_title( $name );
                                    $attributes[ $key ] = $value;
                                }
                                $item_data['attributes'] = $attributes; 
                            } else {
                                $item_data['is_variation'] = false;
                                $item_data['parent_id'] = $item_id;
                            }
                            $enriched_config[$group_idx][] = $item_data;
                        }
                    }
                }
            }

            wp_localize_script('wcb-frontend-script', 'wcb_params', [
                'ajax_url' => admin_url('admin-ajax.php'), 
                'nonce' => wp_create_nonce('wcb-add-to-cart-nonce'),
                'pricing' => $pricing_data,
                'edit_mode' => [
                    'active' => !empty($edit_config),
                    'bundle_id' => $edit_bundle_id,
                    'config' => $enriched_config 
                ],
                'i18n' => [
                    'confirm_remove_bundle' => __('Attenzione: Rimuovendo questo articolo, perderai lo sconto bundle e i prezzi degli altri articoli torneranno al listino originale. Vuoi procedere?', 'wcb-framework'),
                    'update_bundle_btn' => __('Aggiorna Bundle', 'wcb-framework')
                ]
            ]);
        }
        
        if (is_cart()) {
             wp_enqueue_style('wcb-frontend-style', plugin_dir_url(__FILE__) . 'assets/frontend.css', [], '2.3.6');
             wp_enqueue_script('wcb-frontend-script', plugin_dir_url(__FILE__) . 'assets/frontend.js', ['jquery'], '2.3.6', true);
             wp_localize_script('wcb-frontend-script', 'wcb_params', [
                'i18n' => [
                    'confirm_remove_bundle' => __('Attenzione: Rimuovendo questo articolo, perderai lo sconto bundle e i prezzi degli altri articoli torneranno al listino originale. Vuoi procedere?', 'wcb-framework'),
                ]
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
                
                // Campi Personalizzazione
                $personalization_fields = [];
                if (isset($group['personalization_fields']) && is_array($group['personalization_fields'])) {
                    foreach ($group['personalization_fields'] as $field) {
                        $personalization_fields[] = [
                            'label'    => sanitize_text_field($field['label'] ?? ''),
                            'required' => sanitize_text_field($field['required'] ?? 'no'),
                            'type'     => 'text' 
                        ];
                    }
                }

                // NUOVO: Settings per Prodotto (Min, Step, Price)
                $products_settings = [];
                if (isset($group['products_settings']) && is_array($group['products_settings'])) {
                    foreach ($group['products_settings'] as $pid => $settings) {
                        $products_settings[$pid] = [
                            'min_qty' => absint($settings['min_qty'] ?? 1),
                            'step'    => absint($settings['step'] ?? 1),
                            'price'   => wc_format_decimal($settings['price'] ?? '')
                        ];
                    }
                }

                $groups_data[] = [
                    'title'                    => sanitize_text_field($group['title'] ?? ''),
                    'products'                 => isset($group['products']) ? array_map('intval', $group['products']) : [],
                    'selection_mode'           => sanitize_text_field($group['selection_mode'] ?? 'multiple'),
                    'min_qty'                  => absint($group['min_qty'] ?? 1),
                    'max_qty'                  => absint($group['max_qty'] ?? 0),
                    'total_qty'                => absint($group['total_qty'] ?? 1),
                    'is_required'              => isset($group['is_required']) ? 'yes' : 'no',
                    'personalization_enabled'  => isset($group['personalization_enabled']) ? 'yes' : 'no',
                    'personalization_fields'   => $personalization_fields, 
                    'products_settings'        => $products_settings, // SALVA I SETTINGS
                    'price_override'           => wc_format_decimal($group['price_override'] ?? ''), 
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
                    $child_product = wc_get_product( $p_id );
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

    // --- FIX VISUALIZZAZIONE CARRELLO (Supporto Multi-Campo + Separate Items) ---
    public function display_bundle_selections_in_cart($item_data, $cart_item) {
        // Logica per Bundle Unico
        if (!empty($cart_item['wcb_bundle_selections'])) {
            $item_data = array_filter($item_data, function($data) {
                return !isset($data['display']) || strpos($data['display'], 'wcb-bundle-item-meta') === false;
            });

            $item_data[] = ['key' => __('Contenuto del Bundle', 'wcb-framework'), 'display' => '', 'display_class' => 'wcb-bundle-item-meta'];
            
            foreach ($cart_item['wcb_bundle_selections'] as $group_selections) {
                foreach ($group_selections as $selection) {
                    $product = wc_get_product($selection['item_id']);
                    if ($product) {
                        $product_line = $product->get_formatted_name();
                        $item_data[] = ['key' => '', 'display' => $product_line, 'display_class' => 'wcb-bundle-item-meta'];
                        
                        if (!empty($selection['personalization_data']) && is_array($selection['personalization_data'])) {
                            foreach($selection['personalization_data'] as $field) {
                                if(!empty($field['value'])) {
                                    $item_data[] = ['key' => '', 'display' => '<small style="padding-left: 15px;">' . esc_html($field['label']) . ': ' . esc_html($field['value']) . '</small>', 'display_class' => 'wcb-bundle-item-meta'];
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // NUOVO: Logica per Articoli Separati
        if (!empty($cart_item['wcb_item_personalization'])) {
            foreach($cart_item['wcb_item_personalization'] as $field) {
                if(!empty($field['value'])) {
                    $item_data[] = [
                        'key'     => $field['label'],
                        'display' => esc_html($field['value']),
                    ];
                }
            }
        }
        
        return $item_data;
    }

    // --- FIX VISUALIZZAZIONE ORDINE (Supporto Multi-Campo + Separate Items) ---
    public function add_selections_to_order_items($item, $cart_item_key, $values, $order) {
        // Logica Bundle Unico
        if (!empty($values['wcb_bundle_selections'])) {
            $item->add_meta_data('_wcb_bundle_selections_hidden', $values['wcb_bundle_selections'], true);
            $item->add_meta_data(__('Contenuto del Bundle', 'wcb-framework'), '', true);
    
            $display_items = [];
            foreach ($values['wcb_bundle_selections'] as $group_selections) {
                foreach ($group_selections as $selection) {
                    $product = wc_get_product($selection['item_id']);
                    if ($product) {
                        // Creazione chiave unica basata su ID + Dati Personalizzati
                        $unique_key = $selection['item_id'] . json_encode($selection['personalization_data'] ?? []);
                        if (!isset($display_items[$unique_key])) {
                            $display_items[$unique_key] = [
                                'product_name' => $product->get_formatted_name(), 
                                'personalization_data' => $selection['personalization_data'] ?? [],
                                'quantity' => 0
                            ];
                        }
                        $display_items[$unique_key]['quantity']++;
                    }
                }
            }
            
            foreach ($display_items as $display_item) {
                $display_line = $display_item['product_name'];
                if ($display_item['quantity'] > 1) $display_line .= ' &times; ' . $display_item['quantity'];
                
                if (!empty($display_item['personalization_data']) && is_array($display_item['personalization_data'])) {
                    $details = [];
                    foreach($display_item['personalization_data'] as $field) {
                        if(!empty($field['value'])) {
                            $details[] = $field['label'] . ': ' . $field['value'];
                        }
                    }
                    if(!empty($details)) $display_line .= ' | ' . implode(', ', $details);
                }
                
                $item->add_meta_data(':>', $display_line, false);
            }
        }
        
        // NUOVO: Logica Articoli Separati
        if ( ! empty( $values['wcb_item_personalization'] ) && is_array($values['wcb_item_personalization']) ) {
            foreach($values['wcb_item_personalization'] as $field) {
                if(!empty($field['value'])) {
                    $item->add_meta_data( $field['label'], $field['value'] );
                }
            }
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
                    unset($cart->cart_contents[$key]['wcb_source_group_index']); // Clean up new meta
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

        // Reset prezzi base
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['wcb_bundle_part_of'])) {
                $original_product = wc_get_product($cart_item['data']->get_id());
                if ($original_product) $cart->cart_contents[$cart_item_key]['data']->set_price($original_product->get_price());
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
            if (!isset($first_item['wcb_parent_bundle_id'])) continue;
            
            $parent_bundle_id = $first_item['wcb_parent_bundle_id'];
            $bundle_groups = get_post_meta($parent_bundle_id, '_bundle_groups', true); 

            // Applica Prezzi Override (Item > Group)
            foreach ($items as $cart_item_key => $cart_item_data) {
                $product_id = $cart_item_data['data']->get_id();
                
                // CORREZIONE CRITICA: Usiamo wcb_source_group_index se disponibile per individuare
                // esattamente da quale gruppo proviene l'articolo.
                $source_group_idx = $cart_item_data['wcb_source_group_index'] ?? null;
                
                if ($source_group_idx !== null && isset($bundle_groups[$source_group_idx])) {
                    // Logica precisa per "Separate Items" con il nuovo metadato
                    $group_config = $bundle_groups[$source_group_idx];
                    $item_settings = $group_config['products_settings'][$product_id] ?? [];
                    
                    if (isset($item_settings['price']) && $item_settings['price'] !== '') {
                        $cart->cart_contents[$cart_item_key]['data']->set_price(floatval($item_settings['price']));
                    } elseif (isset($group_config['price_override']) && $group_config['price_override'] !== '') {
                        $cart->cart_contents[$cart_item_key]['data']->set_price(floatval($group_config['price_override']));
                    }

                } else {
                    // Logica Fallback (Vecchia/Incompleta) - cerca a caso nella ricetta.
                    // Mantenuta solo se wcb_source_group_index non esiste per qualche motivo.
                    $bundle_recipe = $cart_item_data['wcb_bundle_configuration'] ?? [];
                    foreach ($bundle_recipe as $group_idx => $selections) {
                        $found = false;
                        foreach ($selections as $sel) {
                            if ($sel['item_id'] == $product_id) {
                                $group_config = $bundle_groups[$group_idx] ?? [];
                                $item_settings = $group_config['products_settings'][$product_id] ?? [];
                                
                                if (isset($item_settings['price']) && $item_settings['price'] !== '') {
                                    $cart->cart_contents[$cart_item_key]['data']->set_price(floatval($item_settings['price']));
                                    $found = true;
                                } elseif (isset($group_config['price_override']) && $group_config['price_override'] !== '') {
                                    $cart->cart_contents[$cart_item_key]['data']->set_price(floatval($group_config['price_override']));
                                    $found = true;
                                }
                                break;
                            }
                        }
                        if ($found) break; 
                    }
                }
            }

            $pricing_type = get_post_meta($parent_bundle_id, '_bundle_pricing_type', true);
            
            $current_bundle_subtotal = 0;
            foreach ($items as $cart_item_key => $item) {
                 $current_bundle_subtotal += floatval($cart->cart_contents[$cart_item_key]['data']->get_price()) * $item['quantity'];
            }

            $bundle_final_price = 0;

            if ($pricing_type === 'fixed') {
                $bundle_product = wc_get_product($parent_bundle_id);
                $bundle_final_price = floatval($bundle_product->get_price());
                
                if ($current_bundle_subtotal > 0) {
                    $discount_ratio = $bundle_final_price / $current_bundle_subtotal;
                    foreach ($items as $key => $item) {
                        $current_price = floatval($cart->cart_contents[$key]['data']->get_price());
                        $cart->cart_contents[$key]['data']->set_price($current_price * $discount_ratio);
                    }
                }
            } else { 
                $discount_percentage = floatval(get_post_meta($parent_bundle_id, '_bundle_discount_percentage', true));
                $discount_amount = floatval(get_post_meta($parent_bundle_id, '_bundle_discount_amount', true));
                
                $subtotal = $current_bundle_subtotal;
                
                if ($discount_percentage > 0) $subtotal *= (1 - ($discount_percentage / 100));
                if ($discount_amount > 0) $subtotal -= $discount_amount;
                $bundle_final_price = max(0, $subtotal);

                if ($current_bundle_subtotal > 0 && $bundle_final_price != $current_bundle_subtotal) {
                     $discount_ratio = $bundle_final_price / $current_bundle_subtotal;
                     foreach ($items as $key => $item) {
                        $current_price = floatval($cart->cart_contents[$key]['data']->get_price());
                        $cart->cart_contents[$key]['data']->set_price($current_price * $discount_ratio);
                    }
                }
            }
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if ('custom_bundle' === $cart_item['data']->get_type() && !empty($cart_item['wcb_bundle_selections'])) {
                $product_id = $cart_item['product_id'];
                $pricing_type = get_post_meta($product_id, '_bundle_pricing_type', true);
                
                if ('calculated' === $pricing_type) {
                    $calculated_price = 0;
                    $bundle_groups = get_post_meta($product_id, '_bundle_groups', true);
                    
                    foreach ($cart_item['wcb_bundle_selections'] as $group_index => $group_selections) {
                        $group_config = $bundle_groups[$group_index] ?? [];
                        
                        // FIX: Applicazione override prezzo (Group level) anche per Bundle "Unico"
                        $group_price_override = isset($group_config['price_override']) && $group_config['price_override'] !== '' ? floatval($group_config['price_override']) : null;

                        foreach ($group_selections as $selection) {
                            $child_product = wc_get_product($selection['item_id']);
                            if (!$child_product) continue;
                            
                            // FIX: Check Override Prodotto Specifico
                            $prod_settings = $group_config['products_settings'][$selection['item_id']] ?? [];
                            $item_price_override = isset($prod_settings['price']) && $prod_settings['price'] !== '' ? floatval($prod_settings['price']) : null;

                            if ($item_price_override !== null) {
                                $calculated_price += $item_price_override;
                            } elseif ($group_price_override !== null) {
                                $calculated_price += $group_price_override;
                            } else {
                                $calculated_price += floatval($child_product->get_price());
                            }
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