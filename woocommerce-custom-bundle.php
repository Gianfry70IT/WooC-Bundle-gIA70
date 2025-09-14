<?php
/**
 * Plugin Name: WooC Bundle gIA70
 * Description: Un framework per creare prodotti bundle personalizzabili, unendo un'amministrazione stabile con un frontend funzionale.
 * Version: 0.8.7
 * Author: gIA70 - Gianfranco Greco
 * Copyright (c) 2025 Gianfranco Greco
 * Licensed under the GNU GPL v2 or later: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wcb-framework
 * GithubRepo: Gianfry70IT/WooC-Bundle-gIA70
 * Primary Branch:	main
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Accesso negato
}

// Includi e inizializza l'updater
require_once plugin_dir_path( __FILE__ ) . 'updater.php';
new WCB_GitHub_Updater( __FILE__ );

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

        // ADMIN
        add_filter( 'product_type_selector', [ $this, 'add_bundle_product_type' ] );
        add_filter( 'woocommerce_product_class', [ $this, 'load_bundle_product_class' ], 10, 2 );
        add_filter( 'woocommerce_product_data_tabs', [ $this, 'add_bundle_options_tab' ] );
        add_action( 'woocommerce_product_data_panels', [ $this, 'add_bundle_options_panel' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_action( 'woocommerce_process_product_meta_custom_bundle', [ $this, 'save_bundle_options' ] );
        add_action( 'woocommerce_product_options_general_product_data', [ $this, 'add_bundle_pricing_fields' ] );
        add_action( 'woocommerce_process_product_meta_custom_bundle', [ $this, 'save_bundle_pricing_fields' ] );
        add_action( 'wp_ajax_wcb_custom_product_search', [ $this, 'handle_custom_product_search' ] );
        
        // FRONTEND
        add_action( 'woocommerce_single_product_summary', [ $this, 'display_bundle_options_start' ], 30 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );

        // GESTORE AJAX PER AGGIUNTA AL CARRELLO
        add_action( 'wp_ajax_wcb_add_bundle_to_cart', [ $this, 'wcb_add_bundle_to_cart_handler' ] );
        add_action( 'wp_ajax_nopriv_wcb_add_bundle_to_cart', [ $this, 'wcb_add_bundle_to_cart_handler' ] );

        // Ganci per carrello e ordini
        add_filter( 'woocommerce_get_item_data', [ $this, 'display_bundle_selections_in_cart' ], 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'add_selections_to_order_items' ], 10, 4 );
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'calculate_bundle_price_in_cart' ], 99 );
        add_action( 'woocommerce_order_status_processing', [ $this, 'reduce_stock_for_bundle_items' ] );
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
            } elseif ('quantity' === $selection_mode) {
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

        $cart_item_data = ['wcb_bundle_selections' => $final_selections];
        WC()->cart->add_to_cart($product_id, 1, 0, [], $cart_item_data);
        wp_send_json_success(['cart_url' => wc_get_cart_url(), 'message' => __('Prodotto aggiunto al carrello.', 'wcb-framework')]);
    }
    
    private function find_variation_id_from_attributes( $product_id, $posted_attributes, &$errors_ref = null ) {
        $product = wc_get_product( $product_id );
        if ( ! $product || ! $product->is_type('variable') ) return 0;
        $attributes_to_match = [];
        if (is_array($posted_attributes)) {
            foreach ($posted_attributes as $key => $value) {
                if (strpos($key, 'attribute_') === 0) $attributes_to_match[ $key ] = $value;
            }
        }
        $variation_attributes = $product->get_variation_attributes();
        foreach ($variation_attributes as $attribute_name => $options) {
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
                $debug_info = [];
                foreach($attributes_to_match as $key => $val) { 
                    $clean_key = str_replace('attribute_', '', $key);
                    $debug_info[] = "'{$clean_key}' => '{$val}'"; 
                }
                $errors_ref[] = sprintf( 'DEBUG: Nessuna variante trovata per "%s" con gli attributi [%s].', $product->get_name(), implode(', ', $debug_info));
            }
            return 0;
        }
        return $variation_id;
    }
    
    public function handle_custom_product_search() {
        check_ajax_referer('search-products', 'security');
        if (!current_user_can('edit_products')) wp_die(-1);
        $term = isset($_GET['term']) ? (string) wc_clean(wp_unslash($_GET['term'])) : '';
        $exclude_ids = isset($_GET['exclude']) && !empty($_GET['exclude']) ? array_map('intval', explode(',', $_GET['exclude'])) : [];
        if (empty($term)) wp_die();
        $data_store = WC_Data_Store::load('product');
        $ids = $data_store->search_products($term, '', false, false, 30);
        $products = [];
        foreach ($ids as $id) {
            if (in_array($id, $exclude_ids)) continue;
            $product = wc_get_product($id);
            if (!$product || $product->is_type('variation')) continue;
            $products[$id] = $product->get_formatted_name();
        }
        wp_send_json($products);
    }

    public function add_bundle_product_type($types) {
        $types['custom_bundle'] = __('WooC Bundle gIA70', 'wcb-framework');
        return $types;
    }

    public function load_bundle_product_class($classname, $product_type) {
        if ('custom_bundle' == $product_type) return 'WC_Product_Custom_Bundle';
        return $classname;
    }

    public function add_bundle_options_tab($tabs) {
        $tabs['custom_bundle'] = ['label' => __('WooC Bundle gIA70', 'wcb-framework'),'target' => 'custom_bundle_options','class' => ['show_if_custom_bundle'],'priority' => 80,];
        return $tabs;
    }

    public function add_bundle_options_panel() {
        global $post; ?>
        <div id="custom_bundle_options" class="panel woocommerce_options_panel">
            <div class="options_group"><p class="form-field"><label for="bundle_instructions"><?php esc_html_e('Istruzioni per il Cliente', 'wcb-framework'); ?></label><textarea class="short" style="height: 80px;" name="_bundle_instructions" id="bundle_instructions"><?php echo esc_textarea(get_post_meta($post->ID, '_bundle_instructions', true)); ?></textarea></p></div>
            <div class="options_group" id="bundle_groups_container"><h2><?php esc_html_e('Gruppi del Bundle', 'wcb-framework'); ?> <span class="description">(<?php esc_html_e('Trascina per riordinare', 'wcb-framework'); ?>)</span></h2></div>
            <div class="toolbar"><button type="button" class="button button-primary" id="add_bundle_group"><?php esc_html_e('Aggiungi Gruppo', 'wcb-framework'); ?></button></div>
        </div>
        <div class="wcb-signature">
            <p><strong>WooC Bundle gIA70</strong> by <em>Gianfranco Greco</em></p>
        </div>
        <?php
    }

    public function enqueue_admin_scripts($hook) {
        global $post;
        if ('post-new.php' == $hook || ('post.php' == $hook && isset($post->post_type) && 'product' == $post->post_type)) {
            wp_enqueue_style('wcb-admin-style', plugin_dir_url(__FILE__) . 'assets/admin.css', [], '10.8.2');
            wp_enqueue_script('wcb-admin-script', plugin_dir_url(__FILE__) . 'assets/admin.js', ['jquery', 'wc-enhanced-select', 'jquery-ui-sortable'], '10.8.2', true);
            $bundle_groups_data = get_post_meta($post->ID, '_bundle_groups', true);
            if (!is_array($bundle_groups_data)) $bundle_groups_data = [];
            $groups_for_js = [];
            foreach ($bundle_groups_data as $group) {
                $products_with_names = [];
                if (!empty($group['products'])) {
                    foreach ($group['products'] as $product_id) {
                        $product = wc_get_product($product_id);
                        if ($product) $products_with_names[] = ['id' => $product_id, 'text' => wp_strip_all_tags($product->get_formatted_name())];
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
            wp_enqueue_style('wcb-frontend-style', plugin_dir_url(__FILE__) . 'assets/frontend.css', [], '10.8.2');
            wp_enqueue_script('wcb-frontend-script', plugin_dir_url(__FILE__) . 'assets/frontend.js', ['jquery'], '10.8.2', true);
            wp_localize_script('wcb-frontend-script', 'wcb_params', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('wcb-add-to-cart-nonce')]);
        }
    }

    public function save_bundle_options($post_id) {
        if (isset($_POST['_bundle_instructions'])) {
            update_post_meta($post_id, '_bundle_instructions', sanitize_textarea_field($_POST['_bundle_instructions']));
        }
        if (isset($_POST['_bundle_groups']) && is_array($_POST['_bundle_groups'])) {
            $groups_data = [];
            foreach ($_POST['_bundle_groups'] as $group) {
                $groups_data[] = [
                    'title'                    => sanitize_text_field($group['title'] ?? ''),
                    'products'                 => isset($group['products']) ? array_map('intval', $group['products']) : [],
                    'selection_mode'           => sanitize_text_field($group['selection_mode'] ?? 'multiple'),
                    'min_qty'                  => absint($group['min_qty'] ?? 1),
                    'max_qty'                  => absint($group['max_qty'] ?? 1),
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
    }

    public function add_bundle_pricing_fields() {
        echo '<div class="options_group show_if_custom_bundle">';
        woocommerce_wp_select(['id' => '_bundle_pricing_type', 'label' => __('Tipo di Prezzo Bundle', 'wcb-framework'), 'options' => ['fixed' => __('Prezzo Fisso', 'wcb-framework'), 'calculated' => __('Prezzo Calcolato', 'wcb-framework')]]);
        woocommerce_wp_text_input(['id' => '_bundle_discount_amount', 'label' => __('Sconto Fisso (€)', 'wcb-framework'), 'data_type' => 'price']);
        woocommerce_wp_text_input(['id' => '_bundle_discount_percentage', 'label' => __('Sconto Percentuale (%)', 'wcb-framework'), 'data_type' => 'decimal']);
        echo '</div>';
    }

    public function save_bundle_pricing_fields($post_id) {
        update_post_meta($post_id, '_bundle_pricing_type', wc_clean($_POST['_bundle_pricing_type'] ?? 'fixed'));
        update_post_meta($post_id, '_bundle_discount_amount', wc_clean($_POST['_bundle_discount_amount'] ?? ''));
        update_post_meta($post_id, '_bundle_discount_percentage', wc_clean($_POST['_bundle_discount_percentage'] ?? ''));
    }

    public function display_bundle_options_start() {
        global $product;
        if (!is_product() || !$product || $product->get_type() !== 'custom_bundle') return;
        $bundle_groups = get_post_meta($product->get_id(), '_bundle_groups', true);
        $instructions = get_post_meta($product->get_id(), '_bundle_instructions', true);
        if (empty($bundle_groups)) {
            echo '<p>' . esc_html__('Prodotto non configurato.', 'wcb-framework') . '</p>';
            return;
        }
        wc_get_template('single-product/add-to-cart/custom-bundle.php', ['product' => $product, 'bundle_groups' => $bundle_groups, 'instructions' => $instructions], '', plugin_dir_path(__FILE__) . 'templates/');
    }

    public function display_bundle_selections_in_cart($item_data, $cart_item) {
        if (!empty($cart_item['wcb_bundle_selections'])) {
            // Rimuoviamo eventuali metadati duplicati prima di aggiungerne di nuovi
            $item_data = array_filter($item_data, function($data) {
                return !isset($data['display_class']) || $data['display_class'] !== 'wcb-bundle-item-meta';
            });

            $item_data[] = ['key' => __('Contenuto del Bundle', 'wcb-framework'), 'display' => '', 'display_class' => 'wcb-bundle-item-meta'];
            
            $display_items = [];
            foreach ($cart_item['wcb_bundle_selections'] as $group_selections) {
                foreach ($group_selections as $selection) {
                    $product = wc_get_product($selection['item_id']);
                    if ($product) {
                        $unique_key = $selection['item_id'] . '|' . $selection['personalization'];
                        
                        if (!isset($display_items[$unique_key])) {
                            $display_items[$unique_key] = [
                                'product_name' => $product->get_formatted_name(),
                                'personalization' => $selection['personalization'],
                                'personalization_label' => $selection['personalization_label'] ?? __('Personalizzazione', 'wcb-framework'),
                                'quantity' => 0
                            ];
                        }
                        $display_items[$unique_key]['quantity']++;
                    }
                }
            }

            foreach ($display_items as $item) {
                 $product_line = $item['product_name'];
                 if ($item['quantity'] > 1) {
                     $product_line .= ' &times; ' . $item['quantity'];
                 }
                 
                 $item_data[] = [
                    'key' => '', // Chiave vuota per non avere un titolo in grassetto
                    'display' => $product_line,
                    'display_class' => 'wcb-bundle-item-meta'
                 ];

                 if (!empty($item['personalization'])) {
                     $item_data[] = [
                        'key' => '', // Chiave vuota anche qui
                        'display' => '<small style="padding-left: 15px;">' . esc_html($item['personalization_label']) . ': ' . esc_html($item['personalization']) . '</small>',
                        'display_class' => 'wcb-bundle-item-meta'
                    ];
                 }
            }
        }
        return $item_data;
    }

    public function add_selections_to_order_items($item, $cart_item_key, $values, $order) {
        if (!empty($values['wcb_bundle_selections'])) {
            // Salvataggio dei dati grezzi per la gestione interna (es. magazzino)
            $item->add_meta_data('_wcb_bundle_selections_hidden', $values['wcb_bundle_selections'], true);
            
            // Titolo visibile
            $item->add_meta_data(__('Contenuto del Bundle', 'wcb-framework'), '', true);
    
            // Aggreghiamo i prodotti per raggruppare le quantità
            $display_items = [];
            foreach ($values['wcb_bundle_selections'] as $group_selections) {
                foreach ($group_selections as $selection) {
                    $product = wc_get_product($selection['item_id']);
                    if ($product) {
                        $unique_key = $selection['item_id'] . '|' . ($selection['personalization'] ?? '');
                        
                        if (!isset($display_items[$unique_key])) {
                            $display_items[$unique_key] = [
                                'product_name' => $product->get_formatted_name(),
                                'personalization' => $selection['personalization'] ?? '',
                                'personalization_label' => $selection['personalization_label'] ?? __('Personalizzazione', 'wcb-framework'),
                                'quantity' => 0
                            ];
                        }
                        $display_items[$unique_key]['quantity']++;
                    }
                }
            }
            
            // NUOVA LOGICA: Costruiamo una singola stringa per ogni item e la salviamo.
            foreach ($display_items as $display_item) {
                // Partiamo dal nome del prodotto
                $display_line = $display_item['product_name'];
    
                // Aggiungiamo la quantità se maggiore di 1
                if ($display_item['quantity'] > 1) {
                    $display_line .= ' &times; ' . $display_item['quantity'];
                }
                
                // Aggiungiamo la personalizzazione, se presente
                if (!empty($display_item['personalization'])) {
                    // Usiamo un separatore per mantenere la leggibilità
                    $display_line .= ' | ' . esc_html($display_item['personalization_label']) . ': ' . esc_html($display_item['personalization']);
                }
                
                // Salviamo la riga completa. Usiamo un trattino come chiave per tutti, 
                // così WooCommerce mostrerà solo il valore, che è la nostra riga completa.
                $item->add_meta_data(':>', $display_line, false);
            }
        }
    }

    public function reduce_stock_for_bundle_items($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        foreach ($order->get_items() as $item) {
            $selections = $item->get_meta('_wcb_bundle_selections_hidden');
            if ($selections) {
                $quantities_to_reduce = [];
                foreach ($selections as $group_selections) {
                    foreach ($group_selections as $selection) {
                        $quantities_to_reduce[$selection['item_id']] = ($quantities_to_reduce[$selection['item_id']] ?? 0) + 1;
                    }
                }
                foreach ($quantities_to_reduce as $product_id => $quantity) {
                    $child_product = wc_get_product($product_id);
                    if ($child_product && $child_product->managing_stock()) wc_update_product_stock($child_product, $quantity, 'decrease');
                }
            }
        }
    }

    public function calculate_bundle_price_in_cart($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        foreach ($cart->get_cart() as $cart_item) {
            if ('custom_bundle' === $cart_item['data']->get_type() && !empty($cart_item['wcb_bundle_selections'])) {
                $product_id = $cart_item['product_id'];
                if ('calculated' === get_post_meta($product_id, '_bundle_pricing_type', true)) {
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
                }
            }
        }
    }
}

WC_Custom_Bundle_Framework::get_instance();
