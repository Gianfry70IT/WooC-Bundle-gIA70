<?php
/**
 * Plugin Name:       WooC Bundle gIA70
 * Description:       Un framework per creare prodotti bundle personalizzabili, unendo un'amministrazione stabile con un frontend funzionale.
 * Version:           2.8.2
 * Author:            gIA70 - Gianfranco Greco con Codice Sorgente
 * Copyright (c) 2025 Gianfranco Greco
 * Licensed under the GNU GPL v2 or later: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wcb-framework
 * GithubRepo:        Gianfry70IT/WooC-Bundle-gIA70
 * Primary Branch:    main
 * Requires at least: 6.0
 * Tested up to:      6.9
 */

if (!defined('WCB_MODERN_THEME_DEFAULT')) {
    define('WCB_MODERN_THEME_DEFAULT', false);
}

if (!defined('ABSPATH'))
    exit;

// Se un'altra copia del plugin (es. cartella con nome diverso) è già stata caricata, questa si ferma qui
if (defined('WCB_PLUGIN_LOADED') || class_exists('WCB_Theme_Settings', false) || class_exists('WC_Custom_Bundle_Framework', false)) {
    return;
}
define('WCB_PLUGIN_LOADED', true);

if (!class_exists('WCB_Theme_Settings', false)) :
// Gestione Impostazioni & Documentazione
class WCB_Theme_Settings
{
    private static $instance;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_settings_link']);
    }

    public function register_settings()
    {
        if (!get_option('wcb_reset_style_277')) {
            update_option('wcb_style_mode', 'classic');
            update_option('wcb_enable_modern_theme', 0);
            update_option('wcb_reset_style_277', 1);
            if (function_exists('wp_cache_delete')) {
                wp_cache_delete('alloptions', 'options');
            }
        }

        $flush_cache_cb = function ($clean) {
            if (function_exists('wp_cache_delete')) {
                wp_cache_delete('alloptions', 'options');
            }
            if (function_exists('do_action')) {
                do_action('litespeed_purge_all');
            }
            return $clean;
        };

        register_setting('wcb_theme_settings', 'wcb_style_mode', [
            'type' => 'string',
            'default' => 'classic',
            'sanitize_callback' => function ($val) use ($flush_cache_cb) {
                $clean = sanitize_text_field($val);
                if (!in_array($clean, ['classic', 'modern', 'theme_native'], true)) {
                    $clean = 'classic';
                }
                update_option('wcb_enable_modern_theme', $clean === 'modern' ? 1 : 0);
                return $flush_cache_cb($clean);
            }
        ]);
        register_setting('wcb_theme_settings', 'wcb_header_style', ['type' => 'string', 'default' => 'auto', 'sanitize_callback' => function ($v) use ($flush_cache_cb) { return $flush_cache_cb(sanitize_text_field($v)); }]);
        register_setting('wcb_theme_settings', 'wcb_items_layout', ['type' => 'string', 'default' => 'list', 'sanitize_callback' => function ($v) use ($flush_cache_cb) { return $flush_cache_cb(sanitize_text_field($v)); }]);
        register_setting('wcb_theme_settings', 'wcb_grid_columns', ['type' => 'integer', 'default' => 3, 'sanitize_callback' => function ($v) use ($flush_cache_cb) { return $flush_cache_cb(absint($v)); }]);
        register_setting('wcb_theme_settings', 'wcb_summary_position', ['type' => 'string', 'default' => 'sidebar', 'sanitize_callback' => function ($v) use ($flush_cache_cb) { return $flush_cache_cb(sanitize_text_field($v)); }]);
        register_setting('wcb_theme_settings', 'wcb_bundle_display_position', ['type' => 'string', 'default' => 'after_summary', 'sanitize_callback' => function ($v) use ($flush_cache_cb) { return $flush_cache_cb(sanitize_text_field($v)); }]);
        register_setting('wcb_theme_settings', 'wcb_default_hide_override_price', ['type' => 'boolean', 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean']);
    }

    public static function get_global_style_mode()
    {
        if (!get_option('wcb_reset_style_277')) {
            update_option('wcb_style_mode', 'classic');
            update_option('wcb_enable_modern_theme', 0);
            update_option('wcb_reset_style_277', 1);
            return 'classic';
        }
        $mode = get_option('wcb_style_mode', 'classic');
        if (!in_array($mode, ['classic', 'modern', 'theme_native'], true)) {
            return 'classic';
        }
        return $mode;
    }

    public static function get_bundle_layout_settings($product_id = 0)
    {
        $global_style = self::get_global_style_mode();
        $global_header = get_option('wcb_header_style', 'auto');
        if (!in_array($global_header, ['auto', 'hero', 'wc_native'], true)) {
            $global_header = 'auto';
        }

        $global_layout = get_option('wcb_items_layout', 'list');
        if (!in_array($global_layout, ['list', 'grid'], true)) {
            $global_layout = 'list';
        }

        $global_cols = absint(get_option('wcb_grid_columns', 3));
        if ($global_cols < 2 || $global_cols > 4) {
            $global_cols = 3;
        }

        $global_summary = get_option('wcb_summary_position', 'sidebar');
        if (!in_array($global_summary, ['sidebar', 'bottom', 'bottom_fixed', 'top_fixed'], true)) {
            $global_summary = 'sidebar';
        }

        $global_display_pos = get_option('wcb_bundle_display_position', 'after_summary');
        if (!in_array($global_display_pos, ['after_summary', 'inside_summary', 'after_tabs', 'manual'], true)) {
            $global_display_pos = 'after_summary';
        }

        $style = $global_style;
        $header = $global_header;
        $layout = $global_layout;
        $cols = $global_cols;
        $summary = $global_summary;
        $display_pos = $global_display_pos;

        if ($product_id > 0) {
            $b_style = get_post_meta($product_id, '_bundle_style_mode', true);
            if (in_array($b_style, ['classic', 'modern', 'theme_native'], true)) {
                $style = $b_style;
            }

            $b_header = get_post_meta($product_id, '_bundle_header_style', true);
            if (in_array($b_header, ['auto', 'hero', 'wc_native'], true)) {
                $header = $b_header;
            }

            $b_layout = get_post_meta($product_id, '_bundle_items_layout', true);
            if (in_array($b_layout, ['list', 'grid'], true)) {
                $layout = $b_layout;
            }

            $b_cols = get_post_meta($product_id, '_bundle_grid_columns', true);
            if (in_array((string) $b_cols, ['2', '3', '4'], true)) {
                $cols = absint($b_cols);
            }

            $b_summary = get_post_meta($product_id, '_bundle_summary_position', true);
            if (in_array($b_summary, ['sidebar', 'bottom', 'bottom_fixed', 'top_fixed'], true)) {
                $summary = $b_summary;
            }

            $b_pos = get_post_meta($product_id, '_bundle_display_position', true);
            if (in_array($b_pos, ['after_summary', 'inside_summary', 'after_tabs', 'manual'], true)) {
                $display_pos = $b_pos;
            }
        }

        $is_block_theme = function_exists('wp_is_block_theme') && wp_is_block_theme();
        $resolved_header = $header;
        if ($header === 'auto') {
            if ($style === 'theme_native' || $display_pos === 'inside_summary') {
                $resolved_header = 'wc_native';
            } else {
                $resolved_header = 'hero';
            }
        }

        return [
            'style_mode' => $style,
            'header_style' => $resolved_header,
            'raw_header_style' => $header,
            'items_layout' => $layout,
            'grid_columns' => $cols,
            'summary_position' => $summary,
            'display_position' => $display_pos,
            'is_block_theme' => $is_block_theme,
        ];
    }

    public function add_settings_page()
    {
        add_submenu_page('woocommerce', __('WooC Bundle Settings', 'wcb-framework'), __('Bundle Settings', 'wcb-framework'), 'manage_woocommerce', 'wcb-theme-settings', [$this, 'render_settings_page']);
    }

    public function render_settings_page()
    {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';
?>
<div class="wrap">
    <h1>
        <?php esc_html_e('WooC Bundle gIA70 - Pannello di Controllo', 'wcb-framework'); ?>
        <span style="font-size: 13px; background: #2271b1; color: #fff; padding: 3px 10px; border-radius: 12px; vertical-align: middle; margin-left: 10px;">v2.8.2 (<?php echo esc_html(dirname(plugin_basename(__FILE__))); ?>)</span>
    </h1>

    <h2 class="nav-tab-wrapper">
        <a href="?page=wcb-theme-settings&tab=settings"
            class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Impostazioni Generali</a>
        <a href="?page=wcb-theme-settings&tab=bundles"
            class="nav-tab <?php echo $active_tab == 'bundles' ? 'nav-tab-active' : ''; ?>">Bundle Disponibili</a>
        <a href="?page=wcb-theme-settings&tab=guide"
            class="nav-tab <?php echo $active_tab == 'guide' ? 'nav-tab-active' : ''; ?>">Manuale Operativo</a>
    </h2>

    <?php if ($active_tab == 'settings'):
        $current_style = self::get_global_style_mode();
        $current_header = get_option('wcb_header_style', 'auto');
        $current_layout = get_option('wcb_items_layout', 'list');
        $current_cols = absint(get_option('wcb_grid_columns', 3));
        if ($current_cols < 2 || $current_cols > 4) {
            $current_cols = 3;
        }
        $current_summary = get_option('wcb_summary_position', 'sidebar');
        $current_display_pos = get_option('wcb_bundle_display_position', 'after_summary');
    ?>
    <form method="post" action="options.php">
        <?php settings_fields('wcb_theme_settings'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><strong><?php esc_html_e('Stile Grafico e Tema', 'wcb-framework'); ?></strong></th>
                <td>
                    <p class="description"><?php esc_html_e('Impostazioni predefinite globali. Ogni singolo Bundle può sovrascriverle nella propria scheda prodotto.', 'wcb-framework'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="wcb_style_mode">
                        <?php esc_html_e('Stile Grafico Frontend', 'wcb-framework'); ?>
                    </label></th>
                <td>
                    <select id="wcb_style_mode" name="wcb_style_mode">
                        <option value="classic" <?php selected($current_style, 'classic'); ?>>
                            <?php esc_html_e('Classico del Plugin (Pulito e neutro)', 'wcb-framework'); ?>
                        </option>
                        <option value="modern" <?php selected($current_style, 'modern'); ?>>
                            <?php esc_html_e('Moderno del Plugin (Card, Ombreggiature, Animazioni, Step)', 'wcb-framework'); ?>
                        </option>
                        <option value="theme_native" <?php selected($current_style, 'theme_native'); ?>>
                            <?php esc_html_e('Grafica Preimpostata di WooCommerce e del Tema del Sito', 'wcb-framework'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Scegliendo "Grafica Preimpostata di WooCommerce e del Tema", il bundle erediterà colori, font, pulsanti e stili form dal tema attivo (inclusi i Temi a Blocchi FSE).', 'wcb-framework'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="wcb_header_style">
                        <?php esc_html_e('Intestazione Pagina Prodotto Bundle', 'wcb-framework'); ?>
                    </label></th>
                <td>
                    <select id="wcb_header_style" name="wcb_header_style">
                        <option value="auto" <?php selected($current_header, 'auto'); ?>>
                            <?php esc_html_e('Automatico (Hero; Standard con Stile Tema o posizione "Dentro il riepilogo")', 'wcb-framework'); ?>
                        </option>
                        <option value="hero" <?php selected($current_header, 'hero'); ?>>
                            <?php esc_html_e('Hero Banner a tutta larghezza', 'wcb-framework'); ?>
                        </option>
                        <option value="wc_native" <?php selected($current_header, 'wc_native'); ?>>
                            <?php esc_html_e('Standard di WooCommerce e del Tema (Galleria e Titolo nativi)', 'wcb-framework'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Controlla se sostituire la testata prodotto standard con il banner Hero oppure mantenere il layout nativo del tema.', 'wcb-framework'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <hr><strong><?php esc_html_e('Layout Prodotti e Riepilogo', 'wcb-framework'); ?></strong>
                </th>
                <td>
                    <hr>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="wcb_items_layout">
                        <?php esc_html_e('Vista Articoli del Bundle', 'wcb-framework'); ?>
                    </label></th>
                <td>
                    <select id="wcb_items_layout" name="wcb_items_layout">
                        <option value="list" <?php selected($current_layout, 'list'); ?>>
                            <?php esc_html_e('Lista (1 colonna - layout orizzontale)', 'wcb-framework'); ?>
                        </option>
                        <option value="grid" <?php selected($current_layout, 'grid'); ?>>
                            <?php esc_html_e('Griglia (Multi-colonna - card verticali)', 'wcb-framework'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Scegli come disporre i prodotti all\'interno dei gruppi del bundle.', 'wcb-framework'); ?>
                    </p>
                </td>
            </tr>
            <tr id="wcb_grid_columns_row">
                <th scope="row"><label for="wcb_grid_columns">
                        <?php esc_html_e('Numero Colonne Griglia (Desktop)', 'wcb-framework'); ?>
                    </label></th>
                <td>
                    <select id="wcb_grid_columns" name="wcb_grid_columns">
                        <option value="2" <?php selected($current_cols, 2); ?>><?php esc_html_e('2 Colonne', 'wcb-framework'); ?></option>
                        <option value="3" <?php selected($current_cols, 3); ?>><?php esc_html_e('3 Colonne', 'wcb-framework'); ?></option>
                        <option value="4" <?php selected($current_cols, 4); ?>><?php esc_html_e('4 Colonne', 'wcb-framework'); ?></option>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Utilizzato quando la vista articoli è impostata su "Griglia". Si adatta automaticamente su tablet e smartphone.', 'wcb-framework'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="wcb_summary_position">
                        <?php esc_html_e('Posizione Riepilogo e Acquisto', 'wcb-framework'); ?>
                    </label></th>
                <td>
                    <select id="wcb_summary_position" name="wcb_summary_position">
                        <option value="sidebar" <?php selected($current_summary, 'sidebar'); ?>>
                            <?php esc_html_e('Di fianco a destra (Sticky su desktop, barra fissa su mobile)', 'wcb-framework'); ?>
                        </option>
                        <option value="bottom" <?php selected($current_summary, 'bottom'); ?>>
                            <?php esc_html_e('A fondo pagina (Standard, sotto i gruppi di prodotti)', 'wcb-framework'); ?>
                        </option>
                        <option value="bottom_fixed" <?php selected($current_summary, 'bottom_fixed'); ?>>
                            <?php esc_html_e('Fisso a fondo pagina (Barra sticky sempre visibile in basso)', 'wcb-framework'); ?>
                        </option>
                        <option value="top_fixed" <?php selected($current_summary, 'top_fixed'); ?>>
                            <?php esc_html_e('Fisso a inizio pagina (Barra sticky sempre visibile in alto)', 'wcb-framework'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Scegli dove mostrare il box del prezzo totale e il pulsante Aggiungi al Carrello.', 'wcb-framework'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="wcb_bundle_display_position">
                        <?php esc_html_e('Posizione Modulo nella Pagina Prodotto', 'wcb-framework'); ?>
                    </label></th>
                <td>
                    <select id="wcb_bundle_display_position" name="wcb_bundle_display_position">
                        <option value="after_summary" <?php selected($current_display_pos, 'after_summary'); ?>>
                            <?php esc_html_e('Sotto l\'immagine / Dopo il riepilogo (Consigliato)', 'wcb-framework'); ?>
                        </option>
                        <option value="inside_summary" <?php selected($current_display_pos, 'inside_summary'); ?>>
                            <?php esc_html_e('Dentro il riepilogo / Blocco Aggiungi al Carrello (Nativo WC & Temi a Blocchi FSE)', 'wcb-framework'); ?>
                        </option>
                        <option value="after_tabs" <?php selected($current_display_pos, 'after_tabs'); ?>>
                            <?php esc_html_e('A fondo pagina (Dopo le tab descrizione/recensioni)', 'wcb-framework'); ?>
                        </option>
                        <option value="manual" <?php selected($current_display_pos, 'manual'); ?>>
                            <?php esc_html_e('Manuale (Solo tramite Shortcode)', 'wcb-framework'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Scegli dove mostrare il modulo del bundle nella pagina prodotto single. Nei Temi a Blocchi (FSE) viene supportato automaticamente anche il blocco nativo "Aggiungi al carrello con opzioni".', 'wcb-framework'); ?><br>
                        <?php esc_html_e('Shortcode: [wcb_bundle_form]', 'wcb-framework'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <hr><strong>Comportamento Prezzi</strong>
                </th>
                <td>
                    <hr>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="wcb_default_hide_override_price">
                        <?php esc_html_e('Override Prezzi', 'wcb-framework'); ?>
                    </label></th>
                <td>
                    <label><input type="checkbox" id="wcb_default_hide_override_price"
                            name="wcb_default_hide_override_price" value="1" <?php
                            checked(get_option('wcb_default_hide_override_price', true)); ?>>
                        <?php esc_html_e('Di default, nascondi i prezzi dei singoli prodotti se il gruppo ha un prezzo imposto.', 'wcb-framework'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('Questa impostazione funge da default globale. Può essere sovrascritta nel singolo gruppo bundle.', 'wcb-framework'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var layoutSelect = document.getElementById('wcb_items_layout');
            var colsRow = document.getElementById('wcb_grid_columns_row');
            if (layoutSelect && colsRow) {
                var toggleCols = function () {
                    colsRow.style.opacity = layoutSelect.value === 'grid' ? '1' : '0.55';
                };
                layoutSelect.addEventListener('change', toggleCols);
                toggleCols();
            }
        });
    </script>
    <?php
        elseif ($active_tab == 'bundles'): ?>
    <?php $this->render_bundles_tab(); ?>
    <?php
        elseif ($active_tab == 'guide'): ?>
    <?php $this->render_guide_tab(); ?>
    <?php
        endif; ?>
</div>
<?php
    }

    private function render_guide_tab()
    {
?>
<div
    style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 4px rgba(0,0,0,0.05); margin-top: 20px; max-width: 1000px;">
    <h3>Guida Rapida alla Configurazione (v2.5)</h3>
    <p>Il plugin <strong>WooC Bundle gIA70</strong> permette di creare prodotti complessi composti da gruppi di
        articoli, con regole di selezione e prezzi avanzati.</p>

    <hr>

    <h4>1. Creazione del Bundle</h4>
    <ol>
        <li>Crea un nuovo prodotto WooCommerce.</li>
        <li>Nel box <strong>Dati Prodotto</strong>, seleziona il tipo: <code>WooC Bundle gIA70</code>.</li>
        <li>Vai alla tab verticale <strong>WooC Bundle gIA70</strong> per configurare i gruppi.</li>
    </ol>

    <hr>

    <h4>2. Configurazione dei Gruppi</h4>
    <p>Ogni bundle è composto da "Gruppi" (es. Divisa Uomo, Accessori, Gadget). Per ogni gruppo puoi definire:</p>
    <table class="widefat fixed striped" style="margin-bottom: 20px;">
        <thead>
            <tr>
                <th>Modalità</th>
                <th>Descrizione</th>
                <th>Esempio d'uso</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Singola</strong></td>
                <td>L'utente deve scegliere 1 solo prodotto (Radio Button).</td>
                <td>Scelta taglia o colore unico.</td>
            </tr>
            <tr>
                <td><strong>Multipla</strong></td>
                <td>L'utente può scegliere più prodotti (Checkbox). Usa Min/Max Qty per limitare.</td>
                <td>Scegli 2 accessori tra 5 disponibili.</td>
            </tr>
            <tr>
                <td><strong>Quantità</strong></td>
                <td>L'utente deve raggiungere una somma totale di pezzi esatta.</td>
                <td>Componi un cartone da 6 bottiglie miste.</td>
            </tr>
            <tr>
                <td><strong>Qty Multipla</strong></td>
                <td>Come Multipla, ma l'utente può inserire la quantità per ogni riga.</td>
                <td>Ordina X penne e Y matite (minimo 10 pezzi totali).</td>
            </tr>
        </tbody>
    </table>

    <h4>3. Gestione Prezzi Avanzata</h4>
    <p>Hai tre livelli di controllo sui prezzi:</p>
    <ul>
        <li><strong>Prezzo Calcolato (Standard):</strong> Somma dei prezzi dei prodotti scelti. Puoi applicare uno
            sconto globale (%) o fisso (€) nel tab "Generale".</li>
        <li><strong>Prezzo Override ITEM (Specifico):</strong> Nel backend, accanto a ogni prodotto, puoi scrivere un
            prezzo. Quel prodotto costerà quella cifra <em>solo</em> in questo bundle.</li>
        <li><strong>Prezzo Override GRUPPO (Totale):</strong> Puoi imporre che un intero gruppo costi una cifra fissa
            (es. 100€), indipendentemente dai prodotti scelti.
            <br><em>Novità v2.4:</em> Quando imposti un prezzo di gruppo, apparirà un menu per decidere se mostrare o
            nascondere i prezzi dei singoli articoli (Default/Mostra/Nascondi).
        </li>
    </ul>

    <hr>

    <h4>4. Funzioni Speciali</h4>
    <ul>
        <li><strong>Q.tà Fissa (Default Qty):</strong> Nel backend, puoi dire che selezionando la "Polo", ne vengono
            aggiunte automaticamente 2 al carrello. Apparirà un badge <code>2x</code> nel frontend.</li>
        <li><strong>Unità in Eccesso / Prezzo a Soglia (Extra €/cad):</strong> Accanto a ogni prodotto nel gruppo puoi impostare un costo unitario per le unità in eccesso (es. <code>4</code> €) e un'etichetta personalizzata (es. <em>"Dispositivi oltre il 15°"</em>). Quando il cliente spunta il prodotto, può lasciare <code>+ 0</code> (pagando solo il prezzo base a forfait fino alla soglia) oppure indicare <code>+ N</code> apparecchi in eccesso (aggiungendo automaticamente <code>N × Extra €</code> alla riga del prodotto).</li>
        <li><strong>Etichetta Sotto Prezzo:</strong> Nel tab "Generale", puoi scrivere un testo personalizzato (es.
            "Sconto 20% al checkout") che apparirà sotto il prezzo totale.</li>
        <li><strong>Personalizzazione:</strong> Puoi aggiungere campi testo (es. "Nome sulla maglia") per ogni gruppo.
        </li>
    </ul>

    <hr>

    <h4>5. Posizionamento del Bundle</h4>
    <p>Puoi scegliere dove visualizzare il form di configurazione del bundle nella pagina prodotto. Vai in
        <strong>Impostazioni → WooC Bundle Settings → Posizionamento Bundle</strong> e scegli tra:
    </p>

    <table class="widefat fixed striped" style="margin-bottom: 20px;">
        <thead>
            <tr>
                <th>Opzione</th>
                <th>Descrizione</th>
                <th>Quando usarla</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Dopo il Riepilogo</strong></td>
                <td>Il bundle appare subito dopo il box prezzo/descrizione breve, prima delle tabs recensioni.</td>
                <td>Funziona con la maggior parte dei temi. <strong>Consigliato come default</strong>.</td>
            </tr>
            <tr>
                <td><strong>Dopo le Tabs</strong></td>
                <td>Il bundle appare in fondo alla pagina, dopo le tabs descrizione/recensioni.</td>
                <td>Se vuoi dare più importanza alla descrizione del prodotto.</td>
            </tr>
            <tr>
                <td><strong>Manuale (Shortcode)</strong></td>
                <td>Il bundle NON viene visualizzato automaticamente. Devi posizionarlo tu manualmente.</td>
                <td>Per controllo totale del layout. Richiede l'uso dello shortcode.</td>
            </tr>
        </tbody>
    </table>

    <h4>6. Utilizzo dello Shortcode</h4>
    <p>Puoi utilizzare lo shortcode per visualizzare il form del bundle in qualsiasi punto del sito.</p>

    <div style="background: #f5f5f5; padding: 15px; border-left: 4px solid #2271b1; margin: 15px 0;">
        <h5 style="margin-top: 0;">📝 Shortcode Bundle:</h5>
        <p style="margin: 5px 0;"><code
                style="font-size: 1.1em; background: #fff; padding: 5px 10px; display: inline-block;">[wcb_bundle_form]</code>
            → Sulla pagina del prodotto bundle</p>
        <p style="margin: 5px 0;"><code
                style="font-size: 1.1em; background: #fff; padding: 5px 10px; display: inline-block;">[wcb_bundle_form id="35"]</code>
            → Su qualsiasi pagina o articolo</p>
    </div>

    <p><strong>Dove inserire lo shortcode:</strong></p>
    <ul>
        <li><strong>Pagina Prodotto Bundle:</strong> Usa <code>[wcb_bundle_form]</code> (senza ID) nella descrizione del
            prodotto stesso.</li>
        <li><strong>Pagine o Articoli:</strong> Usa <code>[wcb_bundle_form id="35"]</code> dove "35" è l'ID del prodotto
            bundle.
            <br>💡 <em>Vai in <strong>Impostazioni → WooC Bundle Settings → Bundle Disponibili</strong> per copiare lo
                shortcode di ogni bundle.</em>
        </li>
        <li><strong>Page Builder:</strong> Se usi Elementor, WPBakery o altri page builder, crea un elemento "Shortcode"
            e incolla il codice.</li>
        <li><strong>Template PHP:</strong> Per sviluppatori:
            <code>&lt;?php echo do_shortcode('[wcb_bundle_form id="35"]'); ?&gt;</code>
        </li>
    </ul>

    <div style="background: #fff8e5; padding: 15px; border-left: 4px solid #ffb900; margin: 15px 0;">
        <strong>⚠️ Nota Importante:</strong> Quando usi lo shortcode con ID su pagine esterne, assicurati che il
        prodotto sia pubblicato e di tipo "WooC Bundle gIA70".
    </div>

    <hr>

    <h4>7. Visualizzazione nel Carrello (v2.5)</h4>
    <p>I prodotti dei bundle vengono visualizzati nel carrello con sfondi colorati alternati per facilitare il
        riconoscimento:</p>
    <ul>
        <li>🟡 <strong>Primo bundle:</strong> Sfondo giallo chiaro</li>
        <li>🔵 <strong>Secondo bundle:</strong> Sfondo azzurro chiaro</li>
        <li>🟡 <strong>Terzo bundle:</strong> Torna al giallo (si alternano)</li>
    </ul>
    <p>Ogni bundle mostra anche un link <strong>"Modifica Bundle"</strong> per permettere all'utente di modificare la
        selezione direttamente dal carrello.</p>
</div>
<?php
    }

    private function render_bundles_tab()
    {
        // Query all bundle products
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'tax_query' => array(
                    array(
                    'taxonomy' => 'product_type',
                    'field' => 'slug',
                    'terms' => 'custom_bundle',
                ),
            ),
        );
        $bundles = get_posts($args);
?>
<div
    style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 4px rgba(0,0,0,0.05); margin-top: 20px; max-width: 1200px;">
    <h3>Bundle Disponibili - Shortcode Rapido</h3>
    <p>Copia lo shortcode di un bundle per inserirlo in qualsiasi pagina o articolo. Basta cliccare sul codice per
        copiarlo automaticamente.</p>

    <?php if (empty($bundles)): ?>
    <div style="background: #fff8e5; padding: 15px; border-left: 4px solid #ffb900; margin: 15px 0;">
        <strong>⚠️ Nessun bundle trovato.</strong> Crea il tuo primo bundle dal menu <strong>Prodotti → Aggiungi
            prodotto</strong> e seleziona il tipo "WooC Bundle gIA70".
    </div>
    <?php
        else: ?>
    <table class="widefat fixed" style="margin-top: 20px;">
        <thead>
            <tr>
                <th style="width: 50px;">ID</th>
                <th>Nome Bundle</th>
                <th style="width: 300px;">Shortcode</th>
                <th style="width: 100px;">Azioni</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bundles as $bundle): ?>
            <tr>
                <td><strong>
                        <?php echo esc_html($bundle->ID); ?>
                    </strong></td>
                <td>
                    <?php echo esc_html($bundle->post_title); ?>
                </td>
                <td>
                    <code class="wcb-shortcode-copy"
                        style="display: inline-block; background: #f0f0f1; padding: 8px 12px; border-radius: 4px; cursor: pointer; user-select: all; transition: background 0.2s;"
                        data-shortcode='[wcb_bundle_form id="<?php echo esc_attr($bundle->ID); ?>"]'
                        title="Clicca per copiare">
                            [wcb_bundle_form id="<?php echo esc_attr($bundle->ID); ?>"]
                        </code>
                </td>
                <td>
                    <a href="<?php echo esc_url(get_edit_post_link($bundle->ID)); ?>"
                        class="button button-small">Modifica</a>
                </td>
            </tr>
            <?php
            endforeach; ?>
        </tbody>
    </table>

    <div style="background: #e7f5fe; padding: 15px; border-left: 4px solid #2271b1; margin: 20px 0 0 0;">
        <h4 style="margin-top: 0;">💡 Come usare lo shortcode:</h4>
        <ul style="margin-bottom: 0;">
            <li>✅ Clicca sul codice shortcode nella tabella per copiarlo automaticamente</li>
            <li>✅ Incolla lo shortcode in qualsiasi pagina, articolo o widget di testo</li>
            <li>✅ Lo shortcode può essere usato con page builder come Elementor, inserendolo in un elemento "Shortcode"
            </li>
            <li>✅ Il modulo del bundle verrà visualizzato esattamente come nella pagina prodotto</li>
        </ul>
    </div>
    <?php
        endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.wcb-shortcode-copy').forEach(function (el) {
            el.addEventListener('click', function () {
                const shortcode = this.dataset.shortcode;
                navigator.clipboard.writeText(shortcode).then(function () {
                    // Visual feedback
                    el.style.background = '#00a32a';
                    el.style.color = '#fff';
                    setTimeout(function () {
                        el.style.background = '#f0f0f1';
                        el.style.color = '';
                    }, 500);
                });
            });

            // Hover effect
            el.addEventListener('mouseenter', function () {
                if (this.style.background !== 'rgb(0, 163, 42)') {
                    this.style.background = '#dcdcde';
                }
            });
            el.addEventListener('mouseleave', function () {
                if (this.style.background !== 'rgb(0, 163, 42)') {
                    this.style.background = '#f0f0f1';
                }
            });
        });
    });
</script>
<?php
    }

    public function add_settings_link($links)
    {
        array_unshift($links, '<a href="' . admin_url('admin.php?page=wcb-theme-settings') . '">' . __('Settings', 'wcb-framework') . '</a>');
        return $links;
    }
}
WCB_Theme_Settings::get_instance();

// Controllo Dipendenze
add_action('admin_init', 'wcb_dependency_check_on_plugins_page');
function wcb_dependency_check_on_plugins_page()
{
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        $plugin_basename = plugin_basename(__FILE__);
        add_action('after_plugin_row_' . $plugin_basename, 'wcb_show_wc_dependency_notice_row', 10, 2);
        add_filter('plugin_action_links_' . $plugin_basename, 'wcb_disable_activate_link_for_wc_dependency');
    }
}

function wcb_show_wc_dependency_notice_row($plugin_file, $plugin_data)
{
    $wp_list_table = _get_list_table('WP_Plugins_List_Table');
    $colspan = $wp_list_table->get_column_count();
?>
<tr class="plugin-update-tr active notice-error notice-alt">
    <td colspan="<?php echo $colspan; ?>" class="plugin-update colspanchange">
        <div class="update-message notice-error notice-alt inline">
            <p>
                <?php printf(esc_html__('Questo plugin richiede %s.', 'wcb-framework'), '<strong>WooCommerce</strong>'); ?>
            </p>
        </div>
    </td>
</tr>
<?php
}

function wcb_disable_activate_link_for_wc_dependency($actions)
{
    if (isset($actions['activate']))
        $actions['activate'] = '<span>' . esc_html__('Attiva', 'wcb-framework') . '</span>';
    return $actions;
}

require_once plugin_dir_path(__FILE__) . 'updater.php';
function wcb_initialize_updater()
{
    new WCB_GitHub_Updater(__FILE__);
}
add_action('plugins_loaded', 'wcb_initialize_updater');

// CLASSE PRINCIPALE FRAMEWORK
final class WC_Custom_Bundle_Framework
{
    private static $instance;
    private $rendered_bundles = [];
    private $enqueued_bundles = [];
    private $fse_columns_depth = 0;
    // Marker FSE emessi in questa richiesta: un marker scritto da altri (es. nel contenuto) viene ignorato
    private $fse_markers = [];
    // true solo mentre il form del bundle valida l'acquisto: le aggiunte dirette del bundle (link, Store API) vengono rifiutate
    private $adding_bundle_from_form = false;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('plugins_loaded', [$this, 'init']);
    }

    /**
     * URL di un asset con la versione nel percorso (es. uploads/wcb-assets/admin.1a2b3c4d5e.js).
     * Alcuni CDN (es. Cloudflare con "Ignore query string") ignorano ?ver= e continuano a servire
     * il file vecchio dopo un aggiornamento: copiando l'asset in uploads con un nome versionato,
     * ogni nuova versione ha un URL diverso. Se la copia non riesce si usa l'URL del plugin con ?ver=.
     */
    private function asset_url($file)
    {
        static $cache = [];
        if (isset($cache[$file])) {
            return $cache[$file];
        }

        $source = plugin_dir_path(__FILE__) . 'assets/' . $file;
        $hash = substr((string) @md5_file($source), 0, 10); // dal contenuto: nome file = versione esatta
        $fallback = add_query_arg('ver', $hash, plugin_dir_url(__FILE__) . 'assets/' . $file);

        $uploads = wp_upload_dir(null, false);
        // Uploads su un altro host (offload S3/CDN): la copia locale non sarebbe raggiungibile
        $same_host = wp_parse_url($uploads['baseurl'] ?? '', PHP_URL_HOST) === wp_parse_url(plugin_dir_url(__FILE__), PHP_URL_HOST);
        if (!empty($uploads['error']) || !is_readable($source) || !$same_host || !apply_filters('wcb_versioned_asset_urls', true)) {
            return $cache[$file] = $fallback;
        }

        $info = pathinfo($file);
        $versioned = $info['filename'] . '.' . $hash . '.' . $info['extension'];
        $dir = trailingslashit($uploads['basedir']) . 'wcb-assets/';
        $target = $dir . $versioned;

        if (!file_exists($target)) {
            if (!wp_mkdir_p($dir)) {
                return $cache[$file] = $fallback;
            }
            // Copia su file temporaneo + rename: una richiesta concorrente non vede mai un file a metà
            $tmp = $target . '.' . uniqid('', true) . '.tmp';
            if (!@copy($source, $tmp) || !@rename($tmp, $target)) {
                @unlink($tmp);
                if (!file_exists($target)) {
                    return $cache[$file] = $fallback;
                }
            }
            // Rimuove le copie delle versioni vecchie, tenendo l'ultima precedente:
            // le pagine ancora in cache (Cloudflare, plugin di cache) possono riferirla
            $old_copies = array_diff((array) glob($dir . $info['filename'] . '.*.' . $info['extension']), [$target]);
            usort($old_copies, function ($a, $b) {
                return @filemtime($b) <=> @filemtime($a);
            });
            foreach (array_slice($old_copies, 1) as $old) {
                @unlink($old);
            }
        }

        return $cache[$file] = set_url_scheme(trailingslashit($uploads['baseurl']) . 'wcb-assets/' . $versioned);
    }

    // Importo come testo semplice (carrello, meta d'ordine, email): senza tag né entità tipo &nbsp;&euro;
    private static function format_extra_amount($amount)
    {
        return html_entity_decode(wp_strip_all_tags(wc_price($amount)), ENT_QUOTES, 'UTF-8');
    }

    private function debug_log($message)
    {
        if (defined('WCB_DEBUG') && WCB_DEBUG) {
            error_log($message);
        }
    }

    public function init()
    {
        if (!class_exists('WooCommerce'))
            return;
        $bundle_class_file = plugin_dir_path(__FILE__) . 'includes/class-wc-product-custom-bundle.php';
        if (!file_exists($bundle_class_file))
            return;
        require_once $bundle_class_file;

        add_action('init', [$this, 'register_block_patterns']);
        add_action('admin_body_class', [$this, 'add_admin_modern_class']);
        add_filter('body_class', [$this, 'add_frontend_modern_class']);
        add_filter('product_type_selector', [$this, 'add_bundle_product_type']);
        add_filter('woocommerce_product_class', [$this, 'load_bundle_product_class'], 10, 2);
        add_filter('woocommerce_product_data_tabs', [$this, 'add_bundle_options_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'add_bundle_options_panel']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('woocommerce_product_options_general_product_data', [$this, 'add_bundle_pricing_fields']);
        add_action('woocommerce_process_product_meta_custom_bundle', [$this, 'save_bundle_meta']);
        add_action('wp_ajax_wcb_custom_product_search', [$this, 'handle_custom_product_search']);

        // Supporto nativo per hook WooCommerce add-to-cart e Temi a Blocchi (FSE)
        add_action('woocommerce_custom_bundle_add_to_cart', [$this, 'handle_wc_add_to_cart_action'], 10);
        add_filter('render_block_data', [$this, 'track_fse_columns_start'], 10, 1);
        add_filter('render_block', [$this, 'filter_fse_product_blocks'], 10, 2);

        // Supporto Esportazione / Importazione CSV nativa di WooCommerce per _bundle_groups
        add_filter('woocommerce_product_export_meta_value', [$this, 'export_bundle_meta_value'], 10, 4);
        add_action('woocommerce_product_import_inserted_product_object', [$this, 'import_bundle_meta_value'], 10, 2);

        add_action('template_redirect', [$this, 'setup_bundle_layout']);
        add_shortcode('wcb_bundle_form', [$this, 'bundle_shortcode_callback']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        add_action('wp_ajax_wcb_add_bundle_to_cart', [$this, 'wcb_add_bundle_to_cart_handler']);
        add_action('wp_ajax_nopriv_wcb_add_bundle_to_cart', [$this, 'wcb_add_bundle_to_cart_handler']);
        add_filter('woocommerce_get_item_data', [$this, 'display_bundle_selections_in_cart'], 10, 2);
        add_filter('woocommerce_add_cart_item_data', [$this, 'attach_personalization_to_separate_items'], 99, 3);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_selections_to_order_items'], 10, 4);

        // HOOK CALCOLO PREZZI CRITICO
        add_action('woocommerce_before_calculate_totals', [$this, 'calculate_bundle_price_in_cart'], 99);

        // CARRELLO: le righe di un bundle hanno quantità fissa (con prezzo fisso o di gruppo
        // cambiarle darebbe più pezzi allo stesso prezzo) e il bundle si acquista solo dal suo form
        add_filter('woocommerce_cart_item_quantity', [$this, 'lock_bundle_item_cart_quantity'], 10, 3);
        add_filter('woocommerce_cart_item_remove_link', [$this, 'hide_bundle_item_remove_link'], 10, 2);
        add_filter('woocommerce_update_cart_validation', [$this, 'validate_bundle_item_quantity_update'], 10, 4);
        add_filter('woocommerce_store_api_product_quantity_editable', [$this, 'store_api_bundle_item_quantity_editable'], 10, 3);
        add_filter('woocommerce_store_api_product_quantity_minimum', [$this, 'store_api_bundle_item_quantity_limit'], 10, 3);
        add_filter('woocommerce_store_api_product_quantity_maximum', [$this, 'store_api_bundle_item_quantity_limit'], 10, 3);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'block_direct_bundle_add_to_cart'], 10, 3);
        add_action('woocommerce_check_cart_items', [$this, 'remove_invalid_bundle_cart_items']);

        // CARRELLO A BLOCCHI: i dati del bundle (cornice, "Modifica Bundle") viaggiano nella Store API
        if (did_action('woocommerce_blocks_loaded')) {
            $this->register_store_api_cart_item_data();
        } else {
            add_action('woocommerce_blocks_loaded', [$this, 'register_store_api_cart_item_data']);
        }

        // SPEDIZIONE: override delle regole di spedizione in modalità carrello
        add_filter('woocommerce_cart_shipping_packages', [$this, 'override_bundle_shipping_packages']);
        add_filter('woocommerce_cart_contents_weight', [$this, 'override_bundle_cart_weight']);

        // COUPON: applica i coupon a tutti i prodotti del bundle (modalità carrello),
        // anche a quelli fuori dalle categorie/prodotti a cui il coupon è ristretto.
        add_filter('woocommerce_coupon_is_valid_for_product', [$this, 'force_coupon_for_bundle_items'], 10, 4);

        add_action('woocommerce_order_status_processing', [$this, 'reduce_stock_for_bundle_items']);
        add_action('woocommerce_cart_item_removed', [$this, 'handle_bundle_item_removed'], 10, 2);
        add_action('wp_ajax_wcb_get_variation_price', [$this, 'get_variation_price_handler']);
        add_action('wp_ajax_nopriv_wcb_get_variation_price', [$this, 'get_variation_price_handler']);
        add_filter('woocommerce_cart_item_name', [$this, 'add_bundle_info_to_cart_item_name'], 10, 3);
        add_filter('woocommerce_cart_item_class', [$this, 'add_bundle_class_to_cart_item'], 10, 3);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'get_cart_item_from_session'], 10, 2);
        add_filter('plugin_row_meta', [$this, 'add_plugin_meta_links'], 10, 2);
    }

    public function register_block_patterns()
    {
        if (!function_exists('register_block_pattern')) {
            return;
        }
        if (function_exists('register_block_pattern_category')) {
            register_block_pattern_category('wcb-bundles', [
                'label' => __('WooC Bundle gIA70', 'wcb-framework'),
            ]);
        }
        register_block_pattern('wcb-framework/bundle-configurator', [
            'title' => __('Configuratore Bundle Prodotto', 'wcb-framework'),
            'description' => __('Inserisce il modulo di configurazione del Bundle (compatibile con Full Site Editing e Temi a Blocchi).', 'wcb-framework'),
            'categories' => ['wcb-bundles', 'woocommerce'],
            'content' => '<!-- wp:group {"align":"wide","layout":{"type":"constrained"}} --><div class="wp-block-group alignwide"><!-- wp:shortcode -->[wcb_bundle_form]<!-- /wp:shortcode --></div><!-- /wp:group -->',
        ]);
    }

    public function setup_bundle_layout()
    {
        if (is_product()) {
            $product_id = get_the_ID();
            $product = wc_get_product($product_id);
            if ($product && $product->get_type() === 'custom_bundle') {
                $settings = WCB_Theme_Settings::get_bundle_layout_settings($product_id);

                if ($settings['header_style'] === 'hero') {
                    // Rimuove tutti gli hook da immagine e summary (inclusi widget di terze parti come PayPal Pay Later)
                    // che altrimenti rimarrebbero orfani in uno spazio bianco tra l'Hero e il Bundle.
                    remove_all_actions('woocommerce_before_single_product_summary');
                    remove_all_actions('woocommerce_single_product_summary');
                    // ...ma non i dati strutturati prodotto (JSON-LD per Google), che WooCommerce aggancia qui
                    if (function_exists('WC') && isset(WC()->structured_data)) {
                        add_action('woocommerce_single_product_summary', [WC()->structured_data, 'generate_product_data'], 60);
                    }
                    add_action('woocommerce_before_single_product', [$this, 'render_bundle_hero'], 10);
                }

                $display_pos = $settings['display_position'];
                if ($display_pos === 'after_summary') {
                    add_action('woocommerce_after_single_product_summary', [$this, 'display_bundle_options_start'], 5);
                } elseif ($display_pos === 'inside_summary') {
                    add_action('woocommerce_single_product_summary', [$this, 'display_bundle_options_start'], 30);
                } elseif ($display_pos === 'after_tabs') {
                    add_action('woocommerce_after_single_product', [$this, 'display_bundle_options_start'], 10);
                }
            }
        }
    }

    public function track_fse_columns_start($parsed_block)
    {
        if (!is_admin() && isset($parsed_block['blockName']) && $parsed_block['blockName'] === 'core/columns') {
            $this->fse_columns_depth++;
        }
        return $parsed_block;
    }

    public function filter_fse_product_blocks($block_content, $block)
    {
        $block_name = $block['blockName'] ?? '';
        if (!is_admin() && $block_name === 'core/columns' && $this->fse_columns_depth > 0) {
            $this->fse_columns_depth--;
        }

        if (is_admin() || !is_product()) {
            return $block_content;
        }

        // 1. Caso Hero su Tema a Blocchi (FSE): sostituisce il blocco a 2 colonne (galleria vuota + titolo/prezzo duplicati)
        // con il configuratore a tutta larghezza (alignwide).
        if ($block_name === 'core/columns' && preg_match('/<!--WCB_FSE_HERO_COLUMNS_MARKER:(\d+)-->/', $block_content, $matches)
            && !empty($this->fse_markers['HERO_COLUMNS'][intval($matches[1])])) {
            $product_id = intval($matches[1]);
            $settings = WCB_Theme_Settings::get_bundle_layout_settings($product_id);
            if (in_array($settings['display_position'], ['after_summary', 'inside_summary'], true)) {
                return $this->get_bundle_options_wrapper_html($product_id);
            }
            return '';
        }

        // 2. Caso Header Nativo WC + Dopo il Riepilogo su Tema a Blocchi (FSE):
        // mantiene il blocco a 2 colonne e aggiunge subito sotto il configuratore a tutta larghezza (alignwide).
        if ($block_name === 'core/columns' && preg_match('/<!--WCB_FSE_AFTER_SUMMARY_MARKER:(\d+)-->/', $block_content, $matches)
            && !empty($this->fse_markers['AFTER_SUMMARY'][intval($matches[1])])) {
            $product_id = intval($matches[1]);
            $clean_content = preg_replace('/<!--WCB_FSE_AFTER_SUMMARY_MARKER:\d+-->/', '', $block_content);
            return $clean_content . $this->get_bundle_options_wrapper_html($product_id);
        }

        // 3. Caso Dopo le Tab (after_tabs) su Tema a Blocchi (FSE):
        if ($block_name === 'woocommerce/product-details') {
            $product_id = get_the_ID();
            $product = $product_id ? wc_get_product($product_id) : null;
            if ($product && $product->get_type() === 'custom_bundle' && empty($this->rendered_bundles[$product_id])) {
                $settings = WCB_Theme_Settings::get_bundle_layout_settings($product_id);
                if ($settings['display_position'] === 'after_tabs') {
                    return $block_content . $this->get_bundle_options_wrapper_html($product_id);
                }
            }
        }

        return $block_content;
    }

    public function handle_wc_add_to_cart_action()
    {
        global $product;
        if (!$product || !is_a($product, 'WC_Product') || $product->get_type() !== 'custom_bundle') {
            return;
        }
        $product_id = $product->get_id();
        if (!empty($this->rendered_bundles[$product_id])) {
            return;
        }

        $settings = WCB_Theme_Settings::get_bundle_layout_settings($product_id);
        $display_pos = $settings['display_position'];

        // Se siamo in un Tema a Blocchi (FSE) che usa i blocchi singoli WooCommerce
        // (dove woocommerce_before_single_product_summary NON viene invocato):
        if ($settings['is_block_theme'] && !did_action('woocommerce_before_single_product_summary')) {
            if ($this->fse_columns_depth > 0 && $display_pos !== 'inside_summary') {
                if ($settings['header_style'] === 'hero') {
                    $this->fse_markers['HERO_COLUMNS'][$product_id] = true;
                    echo '<!--WCB_FSE_HERO_COLUMNS_MARKER:' . intval($product_id) . '-->';
                    return;
                }
                if ($display_pos === 'after_summary') {
                    $this->fse_markers['AFTER_SUMMARY'][$product_id] = true;
                    echo '<!--WCB_FSE_AFTER_SUMMARY_MARKER:' . intval($product_id) . '-->';
                    echo '<div class="wcb-fse-configure-cta" style="margin: 15px 0;"><a href="#wcb-bundle-configurator-' . esc_attr($product_id) . '" class="button alt wp-element-button">' . esc_html__('Configura il tuo Bundle ↓', 'wcb-framework') . '</a></div>';
                    return;
                }
                if ($display_pos === 'after_tabs') {
                    echo '<!--WCB_FSE_AFTER_TABS_MARKER:' . intval($product_id) . '-->';
                    echo '<div class="wcb-fse-configure-cta" style="margin: 15px 0;"><a href="#wcb-bundle-configurator-' . esc_attr($product_id) . '" class="button alt wp-element-button">' . esc_html__('Configura il tuo Bundle ↓', 'wcb-framework') . '</a></div>';
                    return;
                }
                if ($display_pos === 'manual') {
                    return;
                }
            }
            if ($display_pos !== 'manual') {
                $this->display_bundle_options_start();
            }
            return;
        }

        if ($display_pos === 'inside_summary') {
            $this->display_bundle_options_start();
        }
    }

    /**
     * Normalizza e sanitizza i gruppi del bundle, da qualunque fonte arrivino:
     * form admin, JSON incollato nell'import, CSV/REST di WooCommerce.
     */
    public static function sanitize_groups($groups)
    {
        if (!is_array($groups)) {
            return [];
        }
        // Checkbox del form (valore "yes", assente se non spuntata) e valori "yes"/"no" del JSON
        $flag = function ($value) {
            return in_array($value, ['yes', 'on', '1', 1, true], true) ? 'yes' : 'no';
        };
        $modes = ['single', 'multiple', 'quantity', 'multiple_quantity'];

        $clean = [];
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $personalization_fields = [];
            foreach ((array) ($group['personalization_fields'] ?? []) as $field) {
                if (is_array($field)) {
                    $personalization_fields[] = ['label' => sanitize_text_field($field['label'] ?? ''), 'required' => $flag($field['required'] ?? 'no'), 'type' => 'text'];
                }
            }
            $products_settings = [];
            foreach ((array) ($group['products_settings'] ?? []) as $pid => $settings) {
                if (!is_array($settings) || !absint($pid)) {
                    continue;
                }
                $products_settings[absint($pid)] = [
                    'min_qty' => absint($settings['min_qty'] ?? 1),
                    'step' => absint($settings['step'] ?? 1),
                    'default_qty' => absint($settings['default_qty'] ?? 1),
                    'price' => wc_format_decimal($settings['price'] ?? ''),
                    'extra_unit_price' => wc_format_decimal($settings['extra_unit_price'] ?? ''),
                    'extra_unit_label' => sanitize_text_field($settings['extra_unit_label'] ?? ''),
                ];
            }
            $selection_mode = $group['selection_mode'] ?? 'multiple';
            $clean_group = [
                'title' => sanitize_text_field($group['title'] ?? ''),
                'products' => array_values(array_filter(array_map('absint', (array) ($group['products'] ?? [])))),
                'selection_mode' => in_array($selection_mode, $modes, true) ? $selection_mode : 'multiple',
                'min_qty' => absint($group['min_qty'] ?? 1),
                'max_qty' => absint($group['max_qty'] ?? 0),
                'total_qty' => absint($group['total_qty'] ?? 1),
                'is_required' => $flag($group['is_required'] ?? 'no'),
                'personalization_enabled' => $flag($group['personalization_enabled'] ?? 'no'),
                'personalization_fields' => $personalization_fields,
                'products_settings' => $products_settings,
                'price_override' => wc_format_decimal($group['price_override'] ?? ''),
                'show_price_behavior' => sanitize_text_field($group['show_price_behavior'] ?? 'default'),
            ];
            // Mappa SKU/slug dell'export: serve a get_bundle_groups() per ritrovare i prodotti su un altro sito
            if (!empty($group['products_export_map']) && is_array($group['products_export_map'])) {
                $export_map = [];
                foreach ($group['products_export_map'] as $pid => $info) {
                    if (is_array($info) && absint($pid)) {
                        $export_map[absint($pid)] = [
                            'sku' => sanitize_text_field($info['sku'] ?? ''),
                            'slug' => sanitize_title($info['slug'] ?? ''),
                            'name' => sanitize_text_field($info['name'] ?? ''),
                        ];
                    }
                }
                $clean_group['products_export_map'] = $export_map;
            }
            $clean[] = $clean_group;
        }
        return $clean;
    }

    public static function get_bundle_groups($product_id)
    {
        $raw = get_post_meta($product_id, '_bundle_groups', true);
        if (empty($raw)) {
            return [];
        }
        if (is_string($raw)) {
            // Niente oggetti: il valore può arrivare da CSV/REST di terzi (PHP Object Injection)
            $unserialized = is_serialized($raw) ? @unserialize($raw, ['allowed_classes' => false]) : false;
            if (is_array($unserialized)) {
                $raw = $unserialized;
            } else {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $raw = $decoded;
                } else {
                    $decoded_slashed = json_decode( wp_unslash($raw), true );
                    if (is_array($decoded_slashed)) {
                        $raw = $decoded_slashed;
                    }
                }
            }
            if (is_array($raw)) {
                $raw = self::sanitize_groups($raw);
                update_post_meta($product_id, '_bundle_groups', wp_slash($raw));
            }
        }
        if (!is_array($raw)) {
            return [];
        }

        // Risolve eventuali ID prodotti su siti di staging tramite SKU o slug se salvati nell'esportazione
        $needs_resave = false;
        foreach ($raw as $g_idx => &$group) {
            if (!empty($group['products']) && is_array($group['products'])) {
                $resolved_products = [];
                $new_settings = $group['products_settings'] ?? [];
                foreach ($group['products'] as $idx => $p_id) {
                    $p_id = intval($p_id);
                    $prod_obj = $p_id > 0 ? wc_get_product($p_id) : false;
                    // Se l'ID non esiste o appartiene a un altro prodotto su staging, prova tramite metadati esportati (SKU / slug)
                    if (!$prod_obj && !empty($group['products_export_map'][$p_id])) {
                        $map = $group['products_export_map'][$p_id];
                        $found_id = 0;
                        if (!empty($map['sku'])) {
                            $found_id = wc_get_product_id_by_sku($map['sku']);
                        }
                        if (!$found_id && !empty($map['slug'])) {
                            $post_obj = get_page_by_path($map['slug'], OBJECT, 'product');
                            if ($post_obj) {
                                $found_id = $post_obj->ID;
                            }
                        }
                        if ($found_id > 0) {
                            $resolved_products[] = $found_id;
                            if (isset($new_settings[$p_id]) && !isset($new_settings[$found_id])) {
                                $new_settings[$found_id] = $new_settings[$p_id];
                            }
                            $needs_resave = true;
                            continue;
                        }
                    }
                    $resolved_products[] = $p_id;
                }
                $group['products'] = $resolved_products;
                $group['products_settings'] = $new_settings;
            }
        }
        unset($group);

        if ($needs_resave) {
            update_post_meta($product_id, '_bundle_groups', $raw);
        }

        return $raw;
    }

    public function export_bundle_meta_value($meta_value, $meta, $product, $row)
    {
        if (isset($meta->key) && $meta->key === '_bundle_groups' && is_array($meta_value)) {
            foreach ($meta_value as &$group) {
                if (!empty($group['products']) && is_array($group['products'])) {
                    $export_map = [];
                    foreach ($group['products'] as $pid) {
                        $child = wc_get_product($pid);
                        if ($child) {
                            $export_map[$pid] = [
                                'sku' => $child->get_sku(),
                                'slug' => $child->get_slug(),
                                'name' => $child->get_name(),
                            ];
                        }
                    }
                    $group['products_export_map'] = $export_map;
                }
            }
            unset($group);
            return wp_json_encode($meta_value);
        }
        return $meta_value;
    }

    public function import_bundle_meta_value($product, $data)
    {
        if ($product && is_a($product, 'WC_Product')) {
            self::get_bundle_groups($product->get_id());
        }
    }

    public function render_bundle_hero()
    {
        global $product;
        $image_url = wp_get_attachment_image_url($product->get_image_id(), 'full');
        $title = $product->get_name();
        $short_desc = $product->get_short_description();
        $bg_rule = $image_url ? 'background-image: url(' . esc_url($image_url) . ');' : '';
?>
<style id="wcb-hero-critical-css">
    .wcb-hero-section,
    .is-layout-constrained > .wcb-hero-section,
    .wp-site-blocks .wcb-hero-section {
        position: relative !important;
        left: 0 !important;
        right: auto !important;
        transform: none !important;
        width: var(--wcb-client-vw, 100vw) !important;
        max-width: var(--wcb-client-vw, 100vw) !important;
        margin-left: var(--wcb-hero-ml, calc(50% - var(--wcb-client-vw, 100vw) / 2)) !important;
        margin-right: calc(100% - var(--wcb-client-vw, 100vw) - var(--wcb-hero-ml, calc(50% - var(--wcb-client-vw, 100vw) / 2))) !important;
        box-sizing: border-box !important;
    }
</style>
<div class="wcb-hero-section alignfull" style="<?php echo $bg_rule; ?> position: relative !important; left: 0 !important; right: auto !important; transform: none !important; width: var(--wcb-client-vw, 100vw) !important; max-width: var(--wcb-client-vw, 100vw) !important; margin-left: var(--wcb-hero-ml, calc(50% - var(--wcb-client-vw, 100vw) / 2)) !important; margin-right: calc(100% - var(--wcb-client-vw, 100vw) - var(--wcb-hero-ml, calc(50% - var(--wcb-client-vw, 100vw) / 2))) !important; box-sizing: border-box !important;">
    <div class="wcb-hero-overlay"></div>
    <div class="wcb-hero-content">
        <h1 class="wcb-hero-title">
            <?php echo esc_html($title); ?>
        </h1>
        <?php if (!empty($short_desc)): ?>
        <div class="wcb-hero-description">
            <?php echo wp_kses_post($short_desc); ?>
        </div>
        <?php
        endif; ?>
    </div>
</div>
<script>
(function(){
    var alignHero = function() {
        var vw = document.documentElement.clientWidth;
        if (!vw) return;
        document.documentElement.style.setProperty('--wcb-client-vw', vw + 'px');
        var hero = document.querySelector('.wcb-hero-section');
        if (hero) {
            hero.style.setProperty('--wcb-hero-ml', '0px');
            var rect = hero.getBoundingClientRect();
            hero.style.setProperty('--wcb-hero-ml', (-rect.left) + 'px');
        }
    };
    alignHero();
    window.addEventListener('resize', alignHero);
    window.addEventListener('load', alignHero);
})();
</script>
<?php
    }

    public function add_plugin_meta_links($links, $file)
    {
        if (plugin_basename(__FILE__) === $file) {
            $row_meta = ['nerodigitale' => '<a href="https://nerodigitale.it" target="_blank" style="font-weight:bold; color:#2271b1;">Nero Digitale</a>'];
            return array_merge($links, $row_meta);
        }
        return $links;
    }

    public function get_cart_item_from_session($cart_item, $values)
    {
        // New format: all data in wcb_data key
        if (isset($values['wcb_data']) && is_array($values['wcb_data'])) {
            $cart_item['wcb_data'] = $values['wcb_data'];
            $d = $values['wcb_data'];
            // Expand to individual keys for backwards compatibility with filters
            if (isset($d['bundle_part_of']))
                $cart_item['wcb_bundle_part_of'] = $d['bundle_part_of'];
            if (isset($d['parent_bundle_id']))
                $cart_item['wcb_parent_bundle_id'] = $d['parent_bundle_id'];
            if (isset($d['bundle_configuration']))
                $cart_item['wcb_bundle_configuration'] = $d['bundle_configuration'];
            if (isset($d['source_group_index']))
                $cart_item['wcb_source_group_index'] = $d['source_group_index'];
            if (isset($d['bundle_group']))
                $cart_item['wcb_bundle_group'] = $d['bundle_group'];
            if (isset($d['is_group_single']))
                $cart_item['wcb_is_group_single'] = $d['is_group_single'];
            if (isset($d['is_group_start']))
                $cart_item['wcb_is_group_start'] = $d['is_group_start'];
            if (isset($d['is_group_end']))
                $cart_item['wcb_is_group_end'] = $d['is_group_end'];
            if (isset($d['is_group_middle']))
                $cart_item['wcb_is_group_middle'] = $d['is_group_middle'];
            if (isset($d['item_personalization']))
                $cart_item['wcb_item_personalization'] = $d['item_personalization'];
            if (isset($d['extra_units']))
                $cart_item['wcb_extra_units'] = absint($d['extra_units']);
            if (isset($d['extra_units_info']))
                $cart_item['wcb_extra_units_info'] = $d['extra_units_info'];
            $this->debug_log('WCB DEBUG - Session restored bundle_part_of: ' . ($d['bundle_part_of'] ?? 'N/A'));
        }
        // Legacy format: individual keys (keep for old cart items)
        elseif (isset($values['wcb_bundle_part_of'])) {
            $cart_item['wcb_bundle_part_of'] = $values['wcb_bundle_part_of'];
            if (isset($values['wcb_parent_bundle_id']))
                $cart_item['wcb_parent_bundle_id'] = $values['wcb_parent_bundle_id'];
            if (isset($values['wcb_bundle_configuration']))
                $cart_item['wcb_bundle_configuration'] = $values['wcb_bundle_configuration'];
            if (isset($values['wcb_source_group_index']))
                $cart_item['wcb_source_group_index'] = $values['wcb_source_group_index'];
            if (isset($values['wcb_bundle_group']))
                $cart_item['wcb_bundle_group'] = $values['wcb_bundle_group'];
            if (isset($values['wcb_is_group_single']))
                $cart_item['wcb_is_group_single'] = $values['wcb_is_group_single'];
            if (isset($values['wcb_is_group_start']))
                $cart_item['wcb_is_group_start'] = $values['wcb_is_group_start'];
            if (isset($values['wcb_is_group_end']))
                $cart_item['wcb_is_group_end'] = $values['wcb_is_group_end'];
            if (isset($values['wcb_is_group_middle']))
                $cart_item['wcb_is_group_middle'] = $values['wcb_is_group_middle'];
            if (isset($values['wcb_item_personalization']))
                $cart_item['wcb_item_personalization'] = $values['wcb_item_personalization'];
            if (isset($values['wcb_extra_units']))
                $cart_item['wcb_extra_units'] = absint($values['wcb_extra_units']);
            if (isset($values['wcb_extra_units_info']))
                $cart_item['wcb_extra_units_info'] = $values['wcb_extra_units_info'];
            $this->debug_log('WCB DEBUG - Session restored LEGACY bundle_part_of: ' . $values['wcb_bundle_part_of']);
        }
        else {
            $this->debug_log('WCB DEBUG - Session has NO wcb data. Keys: ' . implode(', ', array_keys($values)));
        }

        return $cart_item;
    }

    private function get_default_pricing_data()
    {
        return [
            'type' => 'calculated',
            'fixed_price' => 0,
            'discount_amount' => 0,
            'discount_percentage' => 0,
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'decimal_separator' => wc_get_price_decimal_separator(),
            'thousand_separator' => wc_get_price_thousand_separator(),
            'currency_position' => get_option('woocommerce_currency_pos')
        ];
    }

    public function add_bundle_class_to_cart_item($class, $cart_item, $cart_item_key)
    {
        if (isset($cart_item['wcb_bundle_part_of']) && isset($cart_item['wcb_parent_bundle_id'])) {
            $class .= ' wcb-cart-item-child';
            if (isset($cart_item['wcb_bundle_group'])) {
                $class .= ' wcb-group-' . esc_attr($cart_item['wcb_bundle_group']);
            }

            // Compute position dynamically by scanning the cart
            $bundle_id = $cart_item['wcb_bundle_part_of'];
            $bundle_keys = [];
            foreach (WC()->cart->get_cart() as $key => $item) {
                // Check both expanded key AND original wcb_data key
                $item_bid = null;
                if (isset($item['wcb_bundle_part_of'])) {
                    $item_bid = $item['wcb_bundle_part_of'];
                }
                elseif (isset($item['wcb_data']['bundle_part_of'])) {
                    $item_bid = $item['wcb_data']['bundle_part_of'];
                }
                if ($item_bid === $bundle_id) {
                    $bundle_keys[] = $key;
                }
            }

            $total = count($bundle_keys);
            $pos = array_search($cart_item_key, $bundle_keys);

            $this->debug_log("WCB CLASS - key=$cart_item_key bundle=$bundle_id total=$total pos=$pos");

            if ($total === 1) {
                $class .= ' wcb-group-single';
            }
            elseif ($pos === 0) {
                $class .= ' wcb-group-start';
            }
            elseif ($pos === $total - 1) {
                $class .= ' wcb-group-end';
            }
            else {
                $class .= ' wcb-group-middle';
            }

            // Assign alternating color based on bundle order in cart
            // Collect all unique bundle IDs from cart
            $all_bundle_ids = [];
            foreach (WC()->cart->get_cart() as $item) {
                $bid = null;
                if (isset($item['wcb_bundle_part_of'])) {
                    $bid = $item['wcb_bundle_part_of'];
                }
                elseif (isset($item['wcb_data']['bundle_part_of'])) {
                    $bid = $item['wcb_data']['bundle_part_of'];
                }
                if ($bid && !in_array($bid, $all_bundle_ids)) {
                    $all_bundle_ids[] = $bid;
                }
            }

            // Find position of this bundle in the list (0-indexed)
            $bundle_position = array_search($bundle_id, $all_bundle_ids);

            // Alternate between color 1 (yellow) and color 2 (blue)
            $color_class = ($bundle_position % 2 === 0) ? 'wcb-bundle-color-1' : 'wcb-bundle-color-2';
            $class .= ' ' . $color_class;
        }
        return $class;
    }

    public function add_bundle_info_to_cart_item_name($product_name, $cart_item, $cart_item_key)
    {
        if (isset($cart_item['wcb_bundle_part_of']) && isset($cart_item['wcb_parent_bundle_id'])) {
            $parent_bundle = wc_get_product($cart_item['wcb_parent_bundle_id']);
            if ($parent_bundle) {
                // Determine classes for internal wrapper (fallback if tr classes are ignored)
                $classes = ['wcb-cart-item-name-wrapper'];
                if (!empty($cart_item['wcb_is_group_start']))
                    $classes[] = 'wcb-group-start-internal';
                if (!empty($cart_item['wcb_is_group_end']))
                    $classes[] = 'wcb-group-end-internal';
                if (!empty($cart_item['wcb_is_group_middle']))
                    $classes[] = 'wcb-group-middle-internal';
                if (!empty($cart_item['wcb_is_group_single']))
                    $classes[] = 'wcb-group-single-internal';

                $bundle_id_attr = esc_attr($cart_item['wcb_bundle_part_of']);
                $edit_url = add_query_arg(['wcb_edit' => $cart_item['wcb_bundle_part_of'], 'v' => time()], $parent_bundle->get_permalink());

                $html = '<div class="' . implode(' ', $classes) . '">';

                // Etichetta, "Modifica Bundle" ed "Elimina bundle" sopra il primo prodotto del bundle
                if (!empty($cart_item['wcb_is_group_start']) || !empty($cart_item['wcb_is_group_single'])) {
                    $html .= '<div class="wcb-cart-bundle-info wcb-cart-bundle-info-top" data-wcb-bundle-id="' . $bundle_id_attr . '">';
                    $html .= '<small class="wcb-bundle-label">' . esc_html(sprintf(__('Bundle: %s', 'wcb-framework'), $parent_bundle->get_name())) . '</small>';
                    $html .= '<span class="wcb-bundle-actions">';
                    $html .= '<a href="' . esc_url($edit_url) . '" class="wcb-edit-bundle-link">' . esc_html__('Modifica Bundle', 'wcb-framework') . '</a>';
                    // Basta togliere una riga: handle_bundle_item_removed() toglie tutte le altre del bundle
                    $html .= '<a href="' . esc_url(wc_get_cart_remove_url($cart_item_key)) . '" class="wcb-remove-bundle-link" data-confirm="' . esc_attr(self::confirm_remove_whole_bundle_text()) . '">' . esc_html__('Elimina bundle', 'wcb-framework') . '</a>';
                    $html .= '</span>';
                    $html .= '</div>';
                }

                $html .= $product_name;
                $html .= '</div>';
                return $html;
            }
        }
        return $product_name;
    }
    public function get_variation_price_handler()
    {
        check_ajax_referer('wcb-add-to-cart-nonce', 'security');
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $attributes = isset($_POST['attributes']) ? $_POST['attributes'] : [];
        if (!$product_id) {
            wp_send_json_error(['message' => 'Product ID mancante']);
            return;
        }
        $product = wc_get_product($product_id);
        // Solo prodotti acquistabili: niente prezzi di bozze o prodotti privati agli ospiti
        if (!$product || !$product->is_type('variable') || !$product->is_purchasable()) {
            wp_send_json_error(['message' => 'Prodotto non valido']);
            return;
        }
        $data_store = WC_Data_Store::load('product');
        $variation_id = $data_store->find_matching_product_variation($product, $attributes);
        if ($variation_id) {
            $variation = wc_get_product($variation_id);
            $price = wc_get_price_to_display($variation);
            wp_send_json_success(['price' => $price]);
        }
        else {
            wp_send_json_error(['message' => 'Variante non trovata']);
        }
    }

    public function add_bundle_product_type($types)
    {
        $types['custom_bundle'] = __('WooC Bundle gIA70', 'wcb-framework');
        return $types;
    }

    public function load_bundle_product_class($classname, $product_type)
    {
        if ('custom_bundle' == $product_type)
            return 'WC_Product_Custom_Bundle';
        return $classname;
    }

    public function add_bundle_options_tab($tabs)
    {
        $tabs['custom_bundle'] = [
            'label' => __('WooC Bundle gIA70', 'wcb-framework'),
            'target' => 'custom_bundle_options',
            'class' => ['show_if_custom_bundle'],
            'priority' => 80,
        ];
        return $tabs;
    }

    public function wcb_add_bundle_to_cart_handler()
    {
        check_ajax_referer('wcb-add-to-cart-nonce', 'security');
        parse_str($_POST['form_data'], $form_data);

        $product_id = isset($form_data['add-to-cart']) ? intval($form_data['add-to-cart']) : 0;
        $product = wc_get_product($product_id);
        // Solo bundle pubblicati (o visibili a chi può modificarli) e non protetti da password:
        // un ID di un bundle in bozza, privato o nel cestino non deve dare accesso ai suoi prezzi
        if (!$product || !$product->is_type('custom_bundle') || !$product->is_purchasable() || post_password_required($product_id)) {
            wp_send_json_error(['messages' => [__('Questo bundle non è disponibile.', 'wcb-framework')]]);
            return;
        }

        $update_bundle_id = isset($form_data['wcb_update_bundle_id']) ? wc_clean($form_data['wcb_update_bundle_id']) : '';
        $bundle_groups = self::get_bundle_groups($product_id);
        $errors = [];
        $final_selections = [];

        $posted_selections = $form_data['bundle_selection'] ?? [];
        $posted_radio_selections = $form_data['bundle_selection_radio'] ?? [];
        $posted_quantities = $form_data['wcb_quantity'] ?? [];
        $posted_variation_sets_all = $form_data['wcb_variation_sets'] ?? [];
        $posted_personalizations = $form_data['wcb_personalization'] ?? [];
        $posted_extra_units = $form_data['wcb_extra_units'] ?? [];

        $get_extra_units_data = function ($group_index, $pid) use ($bundle_groups, $posted_extra_units) {
            $group_config = $bundle_groups[$group_index] ?? [];
            $p_settings = $group_config['products_settings'][$pid] ?? [];
            $extra_price = isset($p_settings['extra_unit_price']) && $p_settings['extra_unit_price'] !== '' ? floatval($p_settings['extra_unit_price']) : 0;
            if ($extra_price <= 0) {
                return ['units' => 0, 'unit_price' => 0, 'label' => ''];
            }
            $raw_units = isset($posted_extra_units[$group_index][$pid]) ? absint($posted_extra_units[$group_index][$pid]) : 0;
            $units = min(1000, $raw_units);
            $label = !empty($p_settings['extra_unit_label']) ? sanitize_text_field($p_settings['extra_unit_label']) : __('Unità in eccesso', 'wcb-framework');
            return [
                'units' => $units,
                'unit_price' => $extra_price,
                'label' => $label,
            ];
        };

        $get_personalization_data = function ($group_index, $pid, $set_index, $product_name) use ($bundle_groups, $posted_personalizations, &$errors) {
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
                    if (!empty($val))
                        $data_to_store[] = ['label' => $field['label'], 'value' => $val];
                }
            }
            return $data_to_store;
        };

        $validate_product_qty = function ($qty, $pid, $group_config, $product_name) use (&$errors) {
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

        // SICUREZZA: i product ID arrivano dal client; ogni selezione deve
        // appartenere alla lista prodotti configurata per il gruppo.
        $invalid_selection_error = function ($group_config) use (&$errors) {
            $errors[] = sprintf(__('Selezione non valida per il gruppo "%s".', 'wcb-framework'), $group_config['title'] ?? '');
        };

        foreach ($bundle_groups as $group_index => $group_config) {
            $is_required = ($group_config['is_required'] ?? 'no') === 'yes';
            $selection_mode = $group_config['selection_mode'];
            $selection_instances = [];
            $allowed_products = array_map('intval', $group_config['products'] ?? []);

            if ('single' === $selection_mode) {
                if (isset($posted_radio_selections[$group_index])) {
                    $pid = absint($posted_radio_selections[$group_index]);
                    if (!in_array($pid, $allowed_products, true)) {
                        $invalid_selection_error($group_config);
                        $pid = 0;
                    }
                    $child_product = wc_get_product($pid);
                    if ($child_product) {
                        $item_id = $pid;
                        if ($child_product->is_type('variable')) {
                            $attributes = $posted_selections[$group_index][$pid]['attributes'] ?? [];
                            $item_id = $this->find_variation_id_from_attributes($pid, $attributes, $errors);
                        }
                        if ($item_id > 0) {
                            $pers_data = $get_personalization_data($group_index, $pid, 0, $child_product->get_name());
                            $extra_data = $get_extra_units_data($group_index, $pid);
                            $selection_instances[] = [
                                'item_id' => $item_id,
                                'personalization_data' => $pers_data,
                                'extra_units' => $extra_data['units'],
                                'extra_units_info' => $extra_data['units'] > 0 ? $extra_data : null,
                            ];
                        }
                    }
                }
            }
            elseif ('multiple' === $selection_mode) {
                $group_selections = $posted_selections[$group_index] ?? [];
                foreach ($group_selections as $pid => $data) {
                    if (isset($data['selected'])) {
                        $pid = absint($pid);
                        if (!in_array($pid, $allowed_products, true)) {
                            $invalid_selection_error($group_config);
                            continue;
                        }
                        $child_product = wc_get_product($pid);
                        if ($child_product) {
                            $item_id = $pid;
                            if ($child_product->is_type('variable')) {
                                $attributes = $data['attributes'] ?? [];
                                $item_id = $this->find_variation_id_from_attributes($pid, $attributes, $errors);
                            }
                            if ($item_id > 0) {
                                $pers_data = $get_personalization_data($group_index, $pid, 0, $child_product->get_name());
                                $extra_data = $get_extra_units_data($group_index, $pid);
                                $selection_instances[] = [
                                    'item_id' => $item_id,
                                    'personalization_data' => $pers_data,
                                    'extra_units' => $extra_data['units'],
                                    'extra_units_info' => $extra_data['units'] > 0 ? $extra_data : null,
                                ];
                            }
                        }
                    }
                }
            }
            elseif ('quantity' === $selection_mode || 'multiple_quantity' === $selection_mode) {
                $quantities_in_group = $posted_quantities[$group_index] ?? [];
                foreach ($quantities_in_group as $pid => $qty) {
                    $pid = absint($pid);
                    $qty = min(absint($qty), 1000); // stesso tetto del max="1000" del form: evita cicli enormi
                    if ($qty > 0) {
                        if (!in_array($pid, $allowed_products, true)) {
                            $invalid_selection_error($group_config);
                            continue;
                        }
                        $child_product = wc_get_product($pid);
                        if ($child_product) {
                            if (!$validate_product_qty($qty, $pid, $group_config, $child_product->get_name()))
                                continue;
                            $extra_data = $get_extra_units_data($group_index, $pid);
                            if ($child_product->is_type('variable')) {
                                $variation_sets = $posted_variation_sets_all[$group_index][$pid] ?? [];
                                if (count($variation_sets) !== $qty) {
                                    $errors[] = sprintf(__('Quantità errata per "%s".', 'wcb-framework'), $child_product->get_name());
                                    continue;
                                }
                                $added_extra = false;
                                foreach ($variation_sets as $index => $attributes) {
                                    $var_id = $this->find_variation_id_from_attributes($pid, $attributes, $errors);
                                    if ($var_id > 0) {
                                        $pers_data = $get_personalization_data($group_index, $pid, $index, $child_product->get_name());
                                        $use_extra = !$added_extra ? $extra_data['units'] : 0;
                                        $selection_instances[] = [
                                            'item_id' => $var_id,
                                            'personalization_data' => $pers_data,
                                            'extra_units' => $use_extra,
                                            'extra_units_info' => ($use_extra > 0) ? $extra_data : null,
                                        ];
                                        $added_extra = true;
                                    }
                                }
                            }
                            else {
                                for ($i = 0; $i < $qty; $i++) {
                                    $pers_data = $get_personalization_data($group_index, $pid, $i, $child_product->get_name());
                                    $use_extra = ($i === 0) ? $extra_data['units'] : 0;
                                    $selection_instances[] = [
                                        'item_id' => $pid,
                                        'personalization_data' => $pers_data,
                                        'extra_units' => $use_extra,
                                        'extra_units_info' => ($use_extra > 0) ? $extra_data : null,
                                    ];
                                }
                            }
                        }
                    }
                }
            }
            if ($is_required && empty($selection_instances) && empty($errors)) {
                $errors[] = sprintf(__('Devi fare una selezione per il gruppo "%s".', 'wcb-framework'), $group_config['title']);
            }
            if (!empty($selection_instances) && empty($errors)) {
                $final_selections[$group_index] = $selection_instances;
            }
        }

        // DISPONIBILITÀ: il form disabilita i prodotti esauriti, ma il server non si fida del client
        // (pagina aperta da tempo, ultimo pezzo appena venduto, POST manipolato)
        if (empty($errors)) {
            $errors = $this->get_bundle_stock_errors($final_selections, $bundle_groups);
        }

        if (!empty($errors)) {
            wp_send_json_error(['messages' => array_unique($errors)]);
            return;
        }

        // Regole di acquisto di WooCommerce e di altri plugin (membership, prodotti riservati...)
        $this->adding_bundle_from_form = true;
        $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, 1);
        $this->adding_bundle_from_form = false;
        if (!$passed_validation) {
            wp_send_json_error(['messages' => $this->collect_cart_error_notices(__('Non è possibile acquistare questo bundle.', 'wcb-framework'))]);
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

            // Phase 1: Collect ALL items across ALL groups into a flat list
            $all_bundle_items = [];
            foreach ($final_selections as $group_idx => $group_selections) {
                $group_aggregated_items = [];
                $group_config_raw = $bundle_groups[$group_idx] ?? [];

                foreach ($group_selections as $selection) {
                    $item_id = $selection['item_id'];
                    $sel_extra_units = absint($selection['extra_units'] ?? 0);
                    $hash = $item_id . md5(json_encode($selection['personalization_data']));
                    $prod_obj = wc_get_product($item_id);
                    $parent_id_lookup = $prod_obj->is_type('variation') ? $prod_obj->get_parent_id() : $item_id;
                    $p_settings = $group_config_raw['products_settings'][$parent_id_lookup] ?? [];
                    $qty_multiplier = absint($p_settings['default_qty'] ?? 1);
                    if ($qty_multiplier < 1)
                        $qty_multiplier = 1;

                    if (!isset($group_aggregated_items[$hash])) {
                        $group_aggregated_items[$hash] = [
                            'item_id' => $item_id,
                            'quantity' => 0,
                            'personalization_data' => $selection['personalization_data'],
                            'extra_units' => 0,
                            'extra_units_info' => null,
                        ];
                    }
                    $group_aggregated_items[$hash]['quantity'] += $qty_multiplier;
                    if ($sel_extra_units > 0) {
                        $group_aggregated_items[$hash]['extra_units'] += $sel_extra_units;
                        if (!empty($selection['extra_units_info'])) {
                            $info = $selection['extra_units_info'];
                            $info['units'] = $group_aggregated_items[$hash]['extra_units'];
                            $group_aggregated_items[$hash]['extra_units_info'] = $info;
                        }
                    }
                }

                foreach ($group_aggregated_items as $item_data) {
                    $all_bundle_items[] = array_merge($item_data, ['group_idx' => $group_idx]);
                }
            }

            // Phase 2: Assign positions across the ENTIRE bundle
            $total_items = count($all_bundle_items);
            foreach ($all_bundle_items as $idx => $item_data) {
                $item_id = $item_data['item_id'];
                $quantity = $item_data['quantity'];
                $pers_data = $item_data['personalization_data'];
                $group_idx = $item_data['group_idx'];
                $product_to_add = wc_get_product($item_id);
                $variation_id = $product_to_add->is_type('variation') ? $product_to_add->get_id() : 0;
                $parent_id = $product_to_add->is_type('variation') ? $product_to_add->get_parent_id() : $product_to_add->get_id();
                $attributes = $product_to_add->is_type('variation') ? $product_to_add->get_variation_attributes() : [];

                $wcb_meta = [
                    'line_qty' => $quantity, // quantità bloccata della riga, vedi lock_bundle_item_cart_quantity()
                    'bundle_part_of' => $bundle_cart_id,
                    'parent_bundle_id' => $product_id,
                    'bundle_configuration' => $final_selections,
                    'source_group_index' => $group_idx,
                    'bundle_group' => $group_idx,
                ];

                // Position in the ENTIRE bundle (not per group)
                if ($total_items === 1) {
                    $wcb_meta['is_group_single'] = true;
                    $wcb_meta['is_group_start'] = true;
                    $wcb_meta['is_group_end'] = true;
                }
                elseif ($idx === 0) {
                    $wcb_meta['is_group_start'] = true;
                }
                elseif ($idx === $total_items - 1) {
                    $wcb_meta['is_group_end'] = true;
                }
                else {
                    $wcb_meta['is_group_middle'] = true;
                }

                if (!empty($pers_data))
                    $wcb_meta['item_personalization'] = $pers_data;
                if (!empty($item_data['extra_units'])) {
                    $wcb_meta['extra_units'] = absint($item_data['extra_units']);
                    // Quantità della riga per UN bundle: se nel carrello la riga passa da 1 a 2, gli extra raddoppiano
                    $wcb_meta['extra_units_base_qty'] = max(1, absint($quantity));
                    if (!empty($item_data['extra_units_info'])) {
                        $wcb_meta['extra_units_info'] = $item_data['extra_units_info'];
                    }
                }

                $cart_item_data = ['wcb_data' => $wcb_meta];

                $this->debug_log('WCB DEBUG - Adding item ' . ($idx + 1) . '/' . $total_items . ' position: ' . ($wcb_meta['is_group_start'] ?? false ? 'START' : ($wcb_meta['is_group_end'] ?? false ? 'END' : ($wcb_meta['is_group_middle'] ?? false ? 'MIDDLE' : 'SINGLE'))));

                if (!WC()->cart->add_to_cart($parent_id, $quantity, $variation_id, $attributes, $cart_item_data)) {
                    // Un bundle a metà non deve restare nel carrello: togliamo le righe già aggiunte
                    $this->remove_bundle_from_cart($bundle_cart_id);
                    wp_send_json_error(['messages' => $this->collect_cart_error_notices(sprintf(__('Non è stato possibile aggiungere "%s" al carrello.', 'wcb-framework'), $product_to_add->get_name()))]);
                    return;
                }
            }
        }
        else {
            $cart_item_data = ['wcb_bundle_selections' => $final_selections];
            if (!WC()->cart->add_to_cart($product_id, 1, 0, [], $cart_item_data)) {
                wp_send_json_error(['messages' => $this->collect_cart_error_notices(__('Non è stato possibile aggiungere il bundle al carrello.', 'wcb-framework'))]);
                return;
            }
        }

        $message = !empty($update_bundle_id) ? __('Bundle aggiornato con successo.', 'wcb-framework') : __('Prodotti aggiunti al carrello.', 'wcb-framework');
        wp_send_json_success(['cart_url' => wc_get_cart_url(), 'message' => $message]);
    }

    public function attach_personalization_to_separate_items($cart_item_data, $product_id, $variation_id)
    {
        // New format: wcb_data contains all bundle metadata
        if (isset($cart_item_data['wcb_data']) && is_array($cart_item_data['wcb_data'])) {
            $wcb = & $cart_item_data['wcb_data'];
            if (!isset($wcb['item_personalization']) && isset($wcb['bundle_configuration'])) {
                $config = $wcb['bundle_configuration'];
                $target_id = $variation_id > 0 ? $variation_id : $product_id;
                foreach ($config as $group_selections) {
                    foreach ($group_selections as $selection) {
                        if ($selection['item_id'] == $target_id && !empty($selection['personalization_data'])) {
                            $wcb['item_personalization'] = $selection['personalization_data'];
                            break 2;
                        }
                    }
                }
            }
            $this->debug_log('WCB DEBUG - attach_personalization: wcb_data present with keys: ' . implode(', ', array_keys($wcb)));
        }
        // Legacy format
        elseif (!isset($cart_item_data['wcb_item_personalization']) && isset($cart_item_data['wcb_bundle_configuration'])) {
            $config = $cart_item_data['wcb_bundle_configuration'];
            $target_id = $variation_id > 0 ? $variation_id : $product_id;
            foreach ($config as $group_selections) {
                foreach ($group_selections as $selection) {
                    if ($selection['item_id'] == $target_id && !empty($selection['personalization_data'])) {
                        $cart_item_data['wcb_item_personalization'] = $selection['personalization_data'];
                        break 2;
                    }
                }
            }
        }

        return $cart_item_data;
    }

    /**
     * Verifica che ogni prodotto scelto sia disponibile e in quantità sufficiente.
     * La quantità tiene conto della "quantità predefinita" (badge 2x) e dello stesso
     * prodotto scelto in più gruppi. Gli articoli in arretrato restano ordinabili.
     */
    private function get_bundle_stock_errors($final_selections, $bundle_groups)
    {
        $needed = [];
        foreach ($final_selections as $group_idx => $group_selections) {
            $group_settings = $bundle_groups[$group_idx]['products_settings'] ?? [];
            foreach ($group_selections as $selection) {
                $item = wc_get_product($selection['item_id']);
                if (!$item)
                    continue;
                $lookup_id = $item->is_type('variation') ? $item->get_parent_id() : $item->get_id();
                $multiplier = max(1, absint($group_settings[$lookup_id]['default_qty'] ?? 1));
                $needed[$item->get_id()] = ($needed[$item->get_id()] ?? 0) + $multiplier;
            }
        }

        $errors = [];
        foreach ($needed as $item_id => $qty) {
            $item = wc_get_product($item_id);
            if (!$item)
                continue;
            if (!$item->is_in_stock()) {
                $errors[] = sprintf(__('"%s" è momentaneamente non disponibile: toglilo dal bundle e scegli un altro prodotto.', 'wcb-framework'), $item->get_name());
            }
            elseif (!$item->has_enough_stock($qty)) {
                $errors[] = sprintf(__('Disponibilità insufficiente per "%1$s": ne servono %2$d, ne restano %3$s.', 'wcb-framework'), $item->get_name(), $qty, wc_format_stock_quantity_for_display($item->get_stock_quantity(), $item));
            }
        }
        return $errors;
    }

    private static function confirm_remove_whole_bundle_text()
    {
        return __('Vuoi eliminare l\'intero bundle dal carrello?', 'wcb-framework');
    }

    private static function confirm_remove_bundle_text()
    {
        return __('Attenzione: rimuovendo questo articolo verrà tolto dal carrello l\'intero bundle. Vuoi procedere?', 'wcb-framework');
    }

    /**
     * Carrello a blocchi: lo script si carica solo se la pagina carrello usa il blocco
     * (contenuto della pagina o tema a blocchi, dove il carrello è nel template).
     */
    private function enqueue_blocks_cart_script()
    {
        $cart_page_id = function_exists('wc_get_page_id') ? wc_get_page_id('cart') : 0;
        $uses_block = ($cart_page_id > 0 && has_block('woocommerce/cart', $cart_page_id))
            || (function_exists('wp_is_block_theme') && wp_is_block_theme());
        if (!$uses_block)
            return;
        $deps = [];
        foreach (['wc-blocks-checkout', 'wp-data', 'wc-blocks-data-store'] as $handle) {
            if (wp_script_is($handle, 'registered'))
                $deps[] = $handle;
        }
        wp_enqueue_script('wcb-blocks-cart', $this->asset_url('frontend-blocks-cart.js'), $deps, null, true);
        wp_localize_script('wcb-blocks-cart', 'wcb_blocks_cart', [
            'i18n' => [
                'confirm_remove_bundle' => self::confirm_remove_bundle_text(),
                'bundle_label' => __('Bundle: %s', 'wcb-framework'),
                'edit_bundle' => __('Modifica Bundle', 'wcb-framework'),
                'remove_bundle' => __('Elimina bundle', 'wcb-framework'),
                'confirm_remove_whole' => self::confirm_remove_whole_bundle_text(),
            ],
        ]);
    }

    public function register_store_api_cart_item_data()
    {
        if (!function_exists('woocommerce_store_api_register_endpoint_data') || !class_exists('\Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema'))
            return;
        woocommerce_store_api_register_endpoint_data([
            'endpoint' => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema::IDENTIFIER,
            'namespace' => 'wcb-bundle',
            'data_callback' => [$this, 'store_api_cart_item_data'],
            'schema_callback' => [$this, 'store_api_cart_item_schema'],
            'schema_type' => ARRAY_A,
        ]);
    }

    /** Stessi dati del carrello classico (posizione nel bundle, colore alternato, link di modifica). */
    public function store_api_cart_item_data($cart_item)
    {
        $data = ['bundle_id' => '', 'position' => '', 'color' => 0, 'bundle_name' => '', 'edit_url' => ''];
        $bundle_id = self::get_cart_item_bundle_id($cart_item);
        $parent_id = absint($cart_item['wcb_parent_bundle_id'] ?? ($cart_item['wcb_data']['parent_bundle_id'] ?? 0));
        if (!$bundle_id || !$parent_id || !WC()->cart)
            return $data;

        $keys = [];
        $bundle_ids = [];
        foreach (WC()->cart->get_cart() as $key => $item) {
            $bid = self::get_cart_item_bundle_id($item);
            if (!$bid)
                continue;
            if (!in_array($bid, $bundle_ids, true))
                $bundle_ids[] = $bid;
            if ($bid === $bundle_id)
                $keys[] = $key;
        }
        $total = count($keys);
        $pos = array_search($cart_item['key'] ?? '', $keys, true);
        if ($total <= 1)
            $position = 'single';
        elseif ($pos === 0)
            $position = 'start';
        elseif ($pos === $total - 1)
            $position = 'end';
        else
            $position = 'middle';

        $parent = wc_get_product($parent_id);
        $data['bundle_id'] = sanitize_html_class($bundle_id);
        $data['position'] = $position;
        $data['color'] = (array_search($bundle_id, $bundle_ids, true) % 2 === 0) ? 1 : 2;
        if ($parent) {
            $data['bundle_name'] = $parent->get_name();
            $data['edit_url'] = add_query_arg(['wcb_edit' => $bundle_id, 'v' => time()], $parent->get_permalink());
        }
        return $data;
    }

    public function store_api_cart_item_schema()
    {
        $field = function ($type, $description) {
            return ['description' => $description, 'type' => $type, 'context' => ['view', 'edit'], 'readonly' => true];
        };
        return [
            'bundle_id' => $field('string', 'ID del bundle nel carrello a cui appartiene la riga.'),
            'position' => $field('string', 'Posizione della riga nel bundle: start, middle, end o single.'),
            'color' => $field('integer', 'Colore alternato del bundle (1 o 2).'),
            'bundle_name' => $field('string', 'Nome del prodotto bundle.'),
            'edit_url' => $field('string', 'Link per modificare il bundle.'),
        ];
    }

    /** ID del bundle nel carrello a cui appartiene la riga (bundle a righe separate), oppure null. */
    private static function get_cart_item_bundle_id($cart_item)
    {
        if (!is_array($cart_item))
            return null;
        return $cart_item['wcb_bundle_part_of'] ?? ($cart_item['wcb_data']['bundle_part_of'] ?? null);
    }

    /**
     * Carrello classico: niente cestino sulle singole righe del bundle, si usa "Elimina bundle".
     * Solo nella pagina carrello: nel mini-carrello il cestino resta l'unico modo per togliere il bundle.
     */
    public function hide_bundle_item_remove_link($link, $cart_item_key)
    {
        if (!function_exists('is_cart') || !is_cart() || !WC()->cart)
            return $link;
        return self::get_cart_item_bundle_id(WC()->cart->get_cart_item($cart_item_key)) ? '' : $link;
    }

    /** Carrello classico: quantità mostrata come testo, non modificabile. */
    public function lock_bundle_item_cart_quantity($product_quantity, $cart_item_key, $cart_item = null)
    {
        if (!self::get_cart_item_bundle_id($cart_item))
            return $product_quantity;
        $qty = absint($cart_item['quantity']);
        return sprintf('%1$d <input type="hidden" name="cart[%2$s][qty]" value="%1$d" />', $qty, esc_attr($cart_item_key));
    }

    /** Carrello classico: rifiuta un aggiornamento con quantità diversa (0 = rimozione, ammessa). */
    public function validate_bundle_item_quantity_update($passed, $cart_item_key, $values, $quantity)
    {
        if ($passed && self::get_cart_item_bundle_id($values) && (int)$quantity !== 0 && (int)$quantity !== (int)($values['quantity'] ?? 0)) {
            wc_add_notice(__('Le quantità dei prodotti di un bundle non si possono cambiare dal carrello: usa "Modifica Bundle".', 'wcb-framework'), 'error');
            return false;
        }
        return $passed;
    }

    /** Carrello a blocchi (Store API): quantità non modificabile. */
    public function store_api_bundle_item_quantity_editable($editable, $product = null, $cart_item = null)
    {
        return self::get_cart_item_bundle_id($cart_item) ? false : $editable;
    }

    /** Store API: minimo e massimo pari alla quantità attuale, così il server rifiuta ogni altro valore. */
    public function store_api_bundle_item_quantity_limit($value, $product = null, $cart_item = null)
    {
        return self::get_cart_item_bundle_id($cart_item) ? absint($cart_item['quantity']) : $value;
    }

    /**
     * Il bundle si aggiunge solo dal suo form: un link ?add-to-cart=ID, la Store API o "Ordina di nuovo"
     * metterebbero nel carrello un bundle vuoto (con prezzo calcolato anche a 0 €).
     */
    public function block_direct_bundle_add_to_cart($passed, $product_id, $quantity = 1)
    {
        if (!$passed || $this->adding_bundle_from_form)
            return $passed;
        $product = wc_get_product($product_id);
        if ($product && $product->is_type('custom_bundle')) {
            wc_add_notice(sprintf(__('Per acquistare "%s" scegli i prodotti dalla pagina del bundle.', 'wcb-framework'), $product->get_name()), 'error');
            return false;
        }
        return $passed;
    }

    /**
     * Rete di sicurezza prima del checkout: toglie bundle senza prodotti scelti e righe
     * di bundle non più acquistabili (messi in bozza, privati o eliminati dopo l'aggiunta).
     */
    public function remove_invalid_bundle_cart_items()
    {
        if (!WC()->cart)
            return;
        $removed = [];
        foreach (WC()->cart->get_cart() as $key => $item) {
            if (empty($item['data']) || !is_a($item['data'], 'WC_Product'))
                continue;
            $remove = false;
            $name = $item['data']->get_name();
            if ($item['data']->is_type('custom_bundle') && empty($item['wcb_bundle_selections'])) {
                $remove = true;
            }
            elseif (self::get_cart_item_bundle_id($item)) {
                $parent_id = $item['wcb_parent_bundle_id'] ?? ($item['wcb_data']['parent_bundle_id'] ?? 0);
                $parent = $parent_id ? wc_get_product($parent_id) : null;
                if (!$parent || !$parent->is_type('custom_bundle') || !$parent->is_purchasable()) {
                    $remove = true;
                    $name = $parent ? $parent->get_name() : $name;
                }
            }
            if ($remove) {
                remove_action('woocommerce_cart_item_removed', [$this, 'handle_bundle_item_removed'], 10);
                WC()->cart->remove_cart_item($key);
                add_action('woocommerce_cart_item_removed', [$this, 'handle_bundle_item_removed'], 10, 2);
                $removed[$name] = true;
            }
        }
        foreach (array_keys($removed) as $name) {
            wc_add_notice(sprintf(__('"%s" non è più disponibile ed è stato rimosso dal carrello.', 'wcb-framework'), $name), 'error');
        }
    }

    private function remove_bundle_from_cart($bundle_cart_id)
    {
        // Senza hook: handle_bundle_item_removed rifarebbe lo stesso giro per ogni riga
        remove_action('woocommerce_cart_item_removed', [$this, 'handle_bundle_item_removed'], 10);
        foreach (WC()->cart->get_cart() as $key => $item) {
            if (($item['wcb_bundle_part_of'] ?? ($item['wcb_data']['bundle_part_of'] ?? '')) === $bundle_cart_id) {
                WC()->cart->remove_cart_item($key);
            }
        }
        add_action('woocommerce_cart_item_removed', [$this, 'handle_bundle_item_removed'], 10, 2);
    }

    /**
     * Gli errori di WC()->cart->add_to_cart() finiscono nelle notice di sessione:
     * le riportiamo nella risposta AJAX e le togliamo, altrimenti ricomparirebbero alla pagina successiva.
     */
    private function collect_cart_error_notices($fallback)
    {
        $messages = [];
        foreach (wc_get_notices('error') as $notice) {
            $text = is_array($notice) ? ($notice['notice'] ?? '') : $notice;
            $text = trim(wp_strip_all_tags((string)$text));
            if ($text !== '')
                $messages[] = $text;
        }
        wc_clear_notices();
        return !empty($messages) ? array_values(array_unique($messages)) : [$fallback];
    }

    private function find_variation_id_from_attributes($product_id, $posted_attributes, &$errors_ref = null)
    {
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable'))
            return 0;
        $attributes_to_match = [];
        if (is_array($posted_attributes)) {
            foreach ($posted_attributes as $key => $value) {
                if (strpos($key, 'attribute_') === 0) {
                    $attributes_to_match[$key] = $value;
                }
            }
        }
        foreach ($product->get_variation_attributes() as $attribute_name => $options) {
            $full_attribute_key = 'attribute_' . sanitize_title($attribute_name);
            if (!isset($attributes_to_match[$full_attribute_key]) || '' === $attributes_to_match[$full_attribute_key]) {
                if (is_array($errors_ref)) {
                    $error_message = sprintf(__('Selezione incompleta per "%1$s". Per favore, scegli un\'opzione per "%2$s".', 'wcb-framework'), $product->get_name(), wc_attribute_label($attribute_name));
                    if (!in_array($error_message, $errors_ref))
                        $errors_ref[] = $error_message;
                }
                return 0;
            }
        }
        $data_store = WC_Data_Store::load('product');
        $variation_id = $data_store->find_matching_product_variation($product, $attributes_to_match);
        if (!$variation_id) {
            if (is_array($errors_ref)) {
                $error_message = sprintf(__('La combinazione di opzioni scelta per "%s" non è disponibile o non è valida.', 'wcb-framework'), $product->get_name());
                if (!in_array($error_message, $errors_ref))
                    $errors_ref[] = $error_message;
            }
            return 0;
        }
        return $variation_id;
    }

    public function handle_custom_product_search()
    {
        check_ajax_referer('search-products', 'security');
        if (!current_user_can('edit_products'))
            wp_die(-1);
        $term = isset($_GET['term']) ? (string)wc_clean(wp_unslash($_GET['term'])) : '';
        if (empty($term))
            wp_die();
        $exclude_ids = isset($_GET['exclude']) && !empty($_GET['exclude']) ? array_map('intval', explode(',', $_GET['exclude'])) : [];
        $data_store = WC_Data_Store::load('product');
        $ids = $data_store->search_products($term, '', false, false, 30);
        $products = [];
        foreach ($ids as $id) {
            if (in_array($id, $exclude_ids))
                continue;
            $product = wc_get_product($id);
            if (!$product || $product->is_type('variation') || $product->is_type('custom_bundle'))
                continue;
            $image_id = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : wc_placeholder_img_src('thumbnail');
            $products[] = ['id' => $id, 'text' => wp_strip_all_tags($product->get_formatted_name()), 'image_url' => $image_url];
        }
        wp_send_json($products);
    }

    public function add_bundle_options_panel()
    {
        global $post; ?>
<div id="custom_bundle_options" class="panel woocommerce_options_panel">
    <div class="options_group">
        <div class="form-field">
            <div style="font-size:2em;font-weight:600;padding:10px;">
                <?php esc_html_e('Istruzioni per il Cliente', 'wcb-framework'); ?>
            </div>
            <?php
        $content = get_post_meta($post->ID, '_bundle_instructions', true);
        wp_editor(wp_kses_post($content), '_bundle_instructions', ['textarea_name' => '_bundle_instructions', 'media_buttons' => true, 'textarea_rows' => 10]);
?>
        </div>
    </div>
    <div class="toolbar">
        <button type="button" class="button button-primary" id="add_bundle_group">
            <?php esc_html_e('Aggiungi Gruppo', 'wcb-framework'); ?>
        </button>
        <button type="button" class="button" id="expand_all_groups">
            <?php esc_html_e('Espandi Tutto', 'wcb-framework'); ?>
        </button>
        <button type="button" class="button" id="collapse_all_groups">
            <?php esc_html_e('Chiudi Tutto', 'wcb-framework'); ?>
        </button>
    </div>
    <div style="font-size:2em;font-weight:600;padding:10px;">
        <?php esc_html_e('Gruppi del Bundle', 'wcb-framework'); ?> <span class="description">(
            <?php esc_html_e('Trascina per riordinare', 'wcb-framework'); ?>)
        </span>
    </div>
    <input type="hidden" name="wcb_bundle_panel_present" value="1">
    <div class="options_group" id="bundle_groups_container"></div>
    <?php
        $current_groups_export = self::get_bundle_groups($post->ID);
        foreach ($current_groups_export as &$g_exp) {
            if (!empty($g_exp['products']) && is_array($g_exp['products'])) {
                $exp_map = [];
                foreach ($g_exp['products'] as $exp_pid) {
                    $exp_child = wc_get_product($exp_pid);
                    if ($exp_child) {
                        $exp_map[$exp_pid] = [
                            'sku' => $exp_child->get_sku(),
                            'slug' => $exp_child->get_slug(),
                            'name' => $exp_child->get_name(),
                        ];
                    }
                }
                $g_exp['products_export_map'] = $exp_map;
            }
        }
        unset($g_exp);
        $json_export_str = !empty($current_groups_export) ? wp_json_encode($current_groups_export) : '';
    ?>
    <div class="options_group" style="padding: 15px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0;">
        <details>
            <summary style="cursor: pointer; font-weight: 600; color: #2271b1;">
                🔄 <?php esc_html_e('Migrazione Rapida Gruppi Bundle (Esporta / Importa JSON tra Produzione e Staging)', 'wcb-framework'); ?>
            </summary>
            <div style="margin-top: 12px;">
                <p class="description" style="margin-bottom: 8px;">
                    <?php esc_html_e('Nota: Lo strumento nativo "Prodotti → Esporta CSV" di WooCommerce nelle versioni < 2.7.3 ignora i campi array come i gruppi del bundle. Puoi incollare qui sotto il JSON dei gruppi o usare "Strumenti → Esporta → Prodotti (XML)" di WordPress.', 'wcb-framework'); ?>
                </p>
                <?php if (!empty($json_export_str)): ?>
                <p><strong><?php esc_html_e('JSON Gruppi Attuali (copia questo codice per portarlo su un altro sito):', 'wcb-framework'); ?></strong></p>
                <textarea readonly onclick="this.select();" style="width: 100%; height: 70px; font-family: monospace; font-size: 12px; background: #fff;"><?php echo esc_textarea($json_export_str); ?></textarea>
                <?php endif; ?>
                <p style="margin-top: 10px;"><strong><?php esc_html_e('Importa JSON Gruppi (incolla qui e clicca "Aggiorna" sul prodotto):', 'wcb-framework'); ?></strong></p>
                <textarea name="_wcb_import_groups_json" placeholder='[{"title":"Gruppo 1","products":[123,456],...}]' style="width: 100%; height: 70px; font-family: monospace; font-size: 12px;"></textarea>
            </div>
        </details>
    </div>
    <div class="wcb-signature">
        <p><strong>WooC Bundle gIA70</strong> by <em>Gianfranco Greco</em></p>
    </div>
</div>
<?php
    }

    public function enqueue_admin_scripts($hook)
    {
        global $post;
        if ('post-new.php' == $hook || ('post.php' == $hook && isset($post->post_type) && 'product' == $post->post_type)) {
            wp_enqueue_style('wcb-admin-style', $this->asset_url('admin.css'), [], null);
            wp_enqueue_script('wcb-admin-script', $this->asset_url('admin.js'), ['jquery', 'wc-enhanced-select', 'jquery-ui-sortable'], null, true);

            $bundle_groups_data = self::get_bundle_groups($post->ID);
            $groups_for_js = [];
            foreach ($bundle_groups_data as $group) {
                $products_with_names = [];
                if (!empty($group['products'])) {
                    foreach ($group['products'] as $product_id) {
                        $product = wc_get_product($product_id);
                        if ($product) {
                            $image_id = $product->get_image_id();
                            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : wc_placeholder_img_src('thumbnail');
                            $products_with_names[] = ['id' => $product_id, 'text' => wp_strip_all_tags($product->get_formatted_name()), 'image_url' => $image_url];
                        }
                    }
                }
                $group['products'] = $products_with_names;
                $groups_for_js[] = $group;
            }
            wp_localize_script('wcb-admin-script', 'wcb_bundle_data', ['groups' => $groups_for_js]);
            if (get_option('wcb_enable_modern_theme', WCB_MODERN_THEME_DEFAULT)) {
                wp_enqueue_style('wcb-admin-modern', $this->asset_url('admin-modern.css'), ['wcb-admin-style'], null);
                wp_enqueue_script('wcb-admin-modern', $this->asset_url('admin-modern.js'), ['wcb-admin-script'], null, true);
            }
        }
    }

    public function enqueue_bundle_assets_for_product($product_id = 0)
    {
        $cache_key = $product_id ? (int) $product_id : 0;
        if (!empty($this->enqueued_bundles[$cache_key])) {
            return;
        }
        $this->enqueued_bundles[$cache_key] = true;

        wp_enqueue_style('wcb-frontend-style', $this->asset_url('frontend.css'), [], null);
        wp_enqueue_script('wcb-frontend-script', $this->asset_url('frontend.js'), ['jquery'], null, true);

        if (!$product_id && is_product()) {
            $product_id = get_the_ID();
        }

        if ($product_id) {
            $product_obj = wc_get_product($product_id);
            if ($product_obj) {
                $pricing_data = [
                    'type' => get_post_meta($product_id, '_bundle_pricing_type', true),
                    'fixed_price' => wc_get_price_to_display($product_obj),
                    'discount_amount' => floatval(get_post_meta($product_id, '_bundle_discount_amount', true)),
                    'discount_percentage' => floatval(get_post_meta($product_id, '_bundle_discount_percentage', true)),
                    'currency_symbol' => get_woocommerce_currency_symbol(),
                    'decimal_separator' => wc_get_price_decimal_separator(),
                    'thousand_separator' => wc_get_price_thousand_separator(),
                    'currency_position' => get_option('woocommerce_currency_pos')
                ];
            } else {
                $pricing_data = $this->get_default_pricing_data();
            }
        } else {
            $pricing_data = $this->get_default_pricing_data();
        }

        $edit_config = null;
        $edit_bundle_id = null;
        $enriched_config = [];
        if (isset($_GET['wcb_edit']) && !empty($_GET['wcb_edit']) && WC()->cart) {
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
                            foreach ($product->get_attributes() as $name => $value) {
                                $key = 'attribute_' . sanitize_title($name);
                                $attributes[$key] = $value;
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

        $layout_settings = WCB_Theme_Settings::get_bundle_layout_settings($product_id);

        wp_localize_script('wcb-frontend-script', 'wcb_params', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcb-add-to-cart-nonce'),
            'pricing' => $pricing_data,
            'layout' => $layout_settings,
            'edit_mode' => ['active' => !empty($edit_config), 'bundle_id' => $edit_bundle_id, 'config' => $enriched_config],
            'i18n' => [
                'confirm_remove_bundle' => self::confirm_remove_bundle_text(),
                'update_bundle_btn' => __('Aggiorna Bundle', 'wcb-framework')
            ]
        ]);

        if ($layout_settings['style_mode'] === 'modern') {
            wp_enqueue_style('wcb-frontend-modern', $this->asset_url('frontend-modern.css'), ['wcb-frontend-style'], null);
            wp_enqueue_script('wcb-frontend-modern', $this->asset_url('frontend-modern.js'), ['wcb-frontend-script'], null, true);
        }
    }

    public function enqueue_frontend_scripts()
    {
        global $post;
        $should_enqueue = false;
        $product_id = 0;

        if (is_product() && get_the_ID() && wc_get_product(get_the_ID()) && wc_get_product(get_the_ID())->get_type() === 'custom_bundle') {
            $should_enqueue = true;
            $product_id = get_the_ID();
        }
        elseif (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'wcb_bundle_form')) {
            $should_enqueue = true;
            if (preg_match('/\[wcb_bundle_form\s+.*?id=[\'"]?(\d+)[\'"]?.*?\]/', $post->post_content, $matches)) {
                $product_id = intval($matches[1]);
            }
        }

        if ($should_enqueue) {
            $this->enqueue_bundle_assets_for_product($product_id);
        }

        if (is_cart()) {
            $this->enqueue_blocks_cart_script();
            wp_enqueue_style('wcb-frontend-style', $this->asset_url('frontend.css'), [], null);
            wp_enqueue_script('wcb-frontend-script', $this->asset_url('frontend.js'), ['jquery'], null, true);
            wp_localize_script('wcb-frontend-script', 'wcb_params', [
                'i18n' => ['confirm_remove_bundle' => self::confirm_remove_bundle_text()]
            ]);
            if (WCB_Theme_Settings::get_global_style_mode() === 'modern') {
                wp_enqueue_style('wcb-frontend-modern', $this->asset_url('frontend-modern.css'), ['wcb-frontend-style'], null);
                wp_enqueue_script('wcb-frontend-modern', $this->asset_url('frontend-modern.js'), ['wcb-frontend-script'], null, true);
            }
        }
    }

    public function save_bundle_meta($post_id)
    {
        if (isset($_POST['_bundle_instructions']))
            update_post_meta($post_id, '_bundle_instructions', wp_kses_post($_POST['_bundle_instructions']));

        $imported_groups = null;
        if (!empty($_POST['_wcb_import_groups_json'])) {
            $imported_groups = json_decode(wp_unslash($_POST['_wcb_import_groups_json']), true);
            // JSON non valido: si salva comunque quanto c'è nel pannello, avvisando l'utente
            if ((!is_array($imported_groups) || empty($imported_groups)) && class_exists('WC_Admin_Meta_Boxes')) {
                WC_Admin_Meta_Boxes::add_error(__('Import gruppi: il JSON incollato non è valido ed è stato ignorato.', 'wcb-framework'));
            }
        }
        if (is_array($imported_groups) && !empty($imported_groups)) {
            update_post_meta($post_id, '_bundle_groups', wp_slash(self::sanitize_groups($imported_groups)));
            self::get_bundle_groups($post_id);
        } elseif (isset($_POST['_bundle_groups']) && is_array($_POST['_bundle_groups'])) {
            $groups_data = self::sanitize_groups($_POST['_bundle_groups']);
            update_post_meta($post_id, '_bundle_groups', $groups_data);
        } elseif (!empty($_POST['wcb_bundle_panel_present'])) {
            delete_post_meta($post_id, '_bundle_groups');
        }

        update_post_meta($post_id, '_bundle_pricing_type', wc_clean($_POST['_bundle_pricing_type'] ?? 'fixed'));
        update_post_meta($post_id, '_bundle_discount_amount', wc_clean($_POST['_bundle_discount_amount'] ?? ''));
        update_post_meta($post_id, '_bundle_discount_percentage', wc_clean($_POST['_bundle_discount_percentage'] ?? ''));
        update_post_meta($post_id, '_bundle_price_sublabel', wp_kses_post($_POST['_bundle_price_sublabel'] ?? ''));
        update_post_meta($post_id, '_bundle_add_as_separate_items', isset($_POST['_bundle_add_as_separate_items']) ? 'yes' : 'no');
        update_post_meta($post_id, '_bundle_shipping_override', isset($_POST['_bundle_shipping_override']) ? 'yes' : 'no');
        update_post_meta($post_id, '_bundle_coupon_override', isset($_POST['_bundle_coupon_override']) ? 'yes' : 'no');

        // Impostazioni Layout & Visualizzazione per singolo Bundle
        update_post_meta($post_id, '_bundle_style_mode', sanitize_text_field($_POST['_bundle_style_mode'] ?? 'default'));
        update_post_meta($post_id, '_bundle_header_style', sanitize_text_field($_POST['_bundle_header_style'] ?? 'default'));
        update_post_meta($post_id, '_bundle_items_layout', sanitize_text_field($_POST['_bundle_items_layout'] ?? 'default'));
        update_post_meta($post_id, '_bundle_grid_columns', sanitize_text_field($_POST['_bundle_grid_columns'] ?? 'default'));
        update_post_meta($post_id, '_bundle_summary_position', sanitize_text_field($_POST['_bundle_summary_position'] ?? 'default'));
        update_post_meta($post_id, '_bundle_display_position', sanitize_text_field($_POST['_bundle_display_position'] ?? 'default'));
    }

    public function add_bundle_pricing_fields()
    {
        echo '<div class="options_group show_if_custom_bundle">';
        woocommerce_wp_select(['id' => '_bundle_pricing_type', 'label' => __('Tipo di Prezzo Bundle', 'wcb-framework'), 'options' => ['fixed' => __('Prezzo Fisso', 'wcb-framework'), 'calculated' => __('Prezzo Calcolato', 'wcb-framework')], 'desc_tip' => true, 'description' => __('Scegli come calcolare il prezzo del bundle.', 'wcb-framework')]);
        echo '<div class="bundle_price_field_wrapper bundle_price_fixed_field"><p class="form-field"><em>' . __('Il prezzo totale del bundle deve essere inserito nel campo "Prezzo di Listino" nella tab "Generale".', 'wcb-framework') . '</em></p></div>';
        echo '<div class="bundle_price_field_wrapper bundle_price_calculated_field">';
        woocommerce_wp_text_input(['id' => '_bundle_discount_amount', 'label' => __('Sconto Fisso (€)', 'wcb-framework'), 'data_type' => 'price', 'desc_tip' => true, 'description' => __('Applica uno sconto fisso sul totale calcolato dei prodotti.', 'wcb-framework')]);
        woocommerce_wp_text_input(['id' => '_bundle_discount_percentage', 'label' => __('Sconto Percentuale (%)', 'wcb-framework'), 'data_type' => 'decimal', 'desc_tip' => true, 'description' => __('Applica uno sconto percentuale sul totale calcolato dei prodotti.', 'wcb-framework')]);
        echo '</div>';
        woocommerce_wp_checkbox(['id' => '_bundle_add_as_separate_items', 'label' => __('Modalità Carrello', 'wcb-framework'), 'description' => __('Aggiungi i prodotti scelti come articoli separati nel carrello', 'wcb-framework'), 'desc_tip' => true]);
        woocommerce_wp_checkbox(['id' => '_bundle_shipping_override', 'label' => __('Spedizione Bundle', 'wcb-framework'), 'description' => __('In modalità carrello, calcola la spedizione con peso, dimensioni e classe del bundle (tab "Spedizione") invece che con quelli dei singoli prodotti. Richiede peso o classe di spedizione impostati sul bundle.', 'wcb-framework'), 'desc_tip' => true]);
        woocommerce_wp_checkbox(['id' => '_bundle_coupon_override', 'label' => __('Coupon su tutto il Bundle', 'wcb-framework'), 'description' => __('In modalità carrello, applica i coupon a tutti i prodotti del bundle, anche a quelli fuori dalle categorie/prodotti a cui il coupon è ristretto.', 'wcb-framework'), 'desc_tip' => true]);
        echo '<div class="options_group">';
        woocommerce_wp_textarea_input([
            'id' => '_bundle_price_sublabel',
            'label' => __('Etichetta Sotto Prezzo', 'wcb-framework'),
            'desc_tip' => true,
            'description' => __('Testo personalizzato da mostrare sotto il prezzo totale nel frontend (es. info sconti, codici promo).', 'wcb-framework'),
            'style' => 'height: 50px;',
            'placeholder' => '(es. Sconto 20% applicabile al checkout)'
        ]);
        echo '</div>';

        echo '<div class="options_group">';
        echo '<p class="form-field"><strong>' . esc_html__('Layout e Visualizzazione Frontend (Override Singolo Bundle)', 'wcb-framework') . '</strong></p>';
        woocommerce_wp_select([
            'id' => '_bundle_style_mode',
            'label' => __('Stile Grafico', 'wcb-framework'),
            'options' => [
                'default' => __('Usa impostazione generale (Default)', 'wcb-framework'),
                'classic' => __('Classico del Plugin', 'wcb-framework'),
                'modern' => __('Moderno del Plugin (Card, Ombreggiature, Step)', 'wcb-framework'),
                'theme_native' => __('Grafica Preimpostata di WooCommerce e del Tema', 'wcb-framework'),
            ],
            'desc_tip' => true,
            'description' => __('Scegli lo stile grafico specifico per questo bundle oppure eredita quello globale.', 'wcb-framework'),
        ]);
        woocommerce_wp_select([
            'id' => '_bundle_header_style',
            'label' => __('Intestazione Pagina', 'wcb-framework'),
            'options' => [
                'default' => __('Usa impostazione generale (Default)', 'wcb-framework'),
                'auto' => __('Automatico (Hero; Standard con Stile Tema o "Dentro il riepilogo")', 'wcb-framework'),
                'hero' => __('Hero Banner a tutta larghezza', 'wcb-framework'),
                'wc_native' => __('Standard di WooCommerce e del Tema', 'wcb-framework'),
            ],
            'desc_tip' => true,
            'description' => __('Controlla se mostrare il banner Hero o la galleria/titolo standard di WooCommerce/Tema.', 'wcb-framework'),
        ]);
        woocommerce_wp_select([
            'id' => '_bundle_items_layout',
            'label' => __('Vista Prodotti', 'wcb-framework'),
            'options' => [
                'default' => __('Usa impostazione generale (Default)', 'wcb-framework'),
                'list' => __('Lista (1 colonna - orizzontale)', 'wcb-framework'),
                'grid' => __('Griglia (Multi-colonna - card verticali)', 'wcb-framework'),
            ],
            'desc_tip' => true,
            'description' => __('Mostra gli articoli dei gruppi in lista a 1 colonna oppure in griglia.', 'wcb-framework'),
        ]);
        woocommerce_wp_select([
            'id' => '_bundle_grid_columns',
            'label' => __('Colonne Griglia (Desktop)', 'wcb-framework'),
            'options' => [
                'default' => __('Usa impostazione generale (Default)', 'wcb-framework'),
                '2' => __('2 Colonne', 'wcb-framework'),
                '3' => __('3 Colonne', 'wcb-framework'),
                '4' => __('4 Colonne', 'wcb-framework'),
            ],
            'desc_tip' => true,
            'description' => __('Numero di colonne su desktop quando la Vista Prodotti è impostata su Griglia.', 'wcb-framework'),
        ]);
        woocommerce_wp_select([
            'id' => '_bundle_summary_position',
            'label' => __('Posizione Riepilogo', 'wcb-framework'),
            'options' => [
                'default' => __('Usa impostazione generale (Default)', 'wcb-framework'),
                'sidebar' => __('Di fianco a destra (Sticky su desktop, barra fissa su mobile)', 'wcb-framework'),
                'bottom' => __('A fondo pagina (Standard, sotto i gruppi)', 'wcb-framework'),
                'bottom_fixed' => __('Fisso a fondo pagina (Sticky Bottom sempre visibile)', 'wcb-framework'),
                'top_fixed' => __('Fisso a inizio pagina (Sticky Top sempre visibile)', 'wcb-framework'),
            ],
            'desc_tip' => true,
            'description' => __('Scegli dove posizionare il riepilogo del prezzo totale e il pulsante di acquisto.', 'wcb-framework'),
        ]);
        woocommerce_wp_select([
            'id' => '_bundle_display_position',
            'label' => __('Posizione Modulo in Pagina', 'wcb-framework'),
            'options' => [
                'default' => __('Usa impostazione generale (Default)', 'wcb-framework'),
                'after_summary' => __('Sotto l\'immagine / Dopo il riepilogo', 'wcb-framework'),
                'inside_summary' => __('Dentro il riepilogo / Blocco Aggiungi al Carrello (FSE)', 'wcb-framework'),
                'after_tabs' => __('A fondo pagina (Dopo le tab)', 'wcb-framework'),
                'manual' => __('Manuale (Solo Shortcode)', 'wcb-framework'),
            ],
            'desc_tip' => true,
            'description' => __('Dove agganciare il configuratore nella pagina prodotto.', 'wcb-framework'),
        ]);
        echo '</div>';
        echo '</div>';
    }

    public function get_bundle_options_wrapper_html($product_id, $force_position = '')
    {
        if (!empty($this->rendered_bundles[$product_id])) {
            return '';
        }
        $this->rendered_bundles[$product_id] = true;

        $settings = WCB_Theme_Settings::get_bundle_layout_settings($product_id);
        $pos = $force_position ?: $settings['display_position'];
        $wrapper_classes = [
            'wcb-wrapper',
            'wcb-position-' . esc_attr($pos),
            'wcb-style-' . esc_attr($settings['style_mode']),
            'wcb-view-' . esc_attr($settings['items_layout']),
            'wcb-cols-' . esc_attr($settings['grid_columns']),
            'wcb-summary-pos-' . esc_attr($settings['summary_position']),
        ];
        if ($pos !== 'inside_summary') {
            $wrapper_classes[] = 'alignwide';
        }

        ob_start();
        ?>
        <style id="wcb-layout-critical-css-<?php echo esc_attr($product_id); ?>">
            .wcb-view-grid .wcb-group-products {
                display: grid !important;
                grid-template-columns: repeat(var(--wcb-grid-cols, <?php echo intval($settings['grid_columns']); ?>), minmax(0, 1fr)) !important;
                gap: 20px !important;
                align-items: stretch !important;
            }
            @media (max-width: 991px) {
                .wcb-view-grid .wcb-group-products {
                    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                }
            }
            @media (max-width: 575px) {
                .wcb-view-grid .wcb-group-products {
                    grid-template-columns: minmax(0, 1fr) !important;
                }
            }
            .wcb-view-grid .wcb-product-item {
                margin-bottom: 0 !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: flex-start !important;
                height: 100% !important;
                box-sizing: border-box !important;
                position: relative !important;
                padding: 16px !important;
                min-width: 0 !important;
                overflow: clip !important;
            }
            .wcb-view-grid .wcb-product-item > label {
                display: flex !important;
                flex-direction: column !important;
                align-items: stretch !important;
                text-align: center !important;
                width: 100% !important;
                position: relative !important;
            }
            .wcb-view-grid .wcb-product-item input[type="radio"],
            .wcb-view-grid .wcb-product-item input[type="checkbox"] {
                position: absolute !important;
                top: 10px !important;
                left: 10px !important;
                z-index: 95 !important;
                margin: 0 !important;
            }
            .wcb-view-grid .wcb-product-thumbnail,
            .wcb-view-grid .wcb-product-thumbnail.wcb-gallery-wrapper {
                width: 100% !important;
                height: auto !important;
                aspect-ratio: 1 / 1 !important;
                margin: 0 0 15px 0 !important;
                order: 0 !important;
            }
            .wcb-view-grid .wcb-product-thumbnail img {
                width: 100% !important;
                height: 100% !important;
                max-width: none !important;
                aspect-ratio: 1 / 1 !important;
                object-fit: cover !important;
            }
            .wcb-view-grid .wcb-product-info {
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                text-align: center !important;
                width: 100% !important;
                flex: 1 !important;
                padding-top: 0 !important;
            }
            .wcb-view-grid .wcb-qty-wrapper {
                width: 100% !important;
                max-width: 120px !important;
                margin: 8px auto 0 !important;
            }
            .wcb-view-grid .wcb-variation-container,
            .wcb-view-grid .wcb-variation-sets-container,
            .wcb-view-grid .wcb-personalization-field-container,
            .wcb-view-grid .wcb-extra-units-container {
                padding: 12px !important;
                margin-top: 12px !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }
            .wcb-view-grid .wcb-variation-field,
            .wcb-view-grid .wcb-personalization-field {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 6px !important;
                text-align: left !important;
            }
            .wcb-view-grid .wcb-variation-field > label,
            .wcb-view-grid .wcb-personalization-field > label {
                min-width: 0 !important;
                margin-right: 0 !important;
            }
            .wcb-view-grid .wcb-variation-field select,
            .wcb-view-grid .wcb-personalization-field input[type="text"] {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                box-sizing: border-box !important;
            }
            body.wcb-modern-theme .wcb-product-price span.woocommerce-Price-amount,
            body.wcb-modern-theme .wcb-product-price span.woocommerce-Price-currencySymbol {
                font-size: 1em !important;
                font-weight: 800 !important;
                color: var(--wcb-m-primary, #4361ee) !important;
            }
            body.wcb-modern-theme .wcb-product-price small.woocommerce-price-suffix,
            body.wcb-modern-theme .wcb-product-price .wcb-included-badge {
                font-size: 0.65em !important;
                font-weight: 600 !important;
                color: #888 !important;
                -webkit-text-fill-color: #888 !important;
            }
            .wcb-product-excerpt {
                font-size: 0.9em;
                line-height: 1.45;
                color: #555;
                margin: 2px 0 10px;
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
            .wcb-view-grid .wcb-product-excerpt {
                align-items: center !important;
                text-align: center !important;
            }
            .wcb-open-desc-modal {
                appearance: none;
                background: none !important;
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
                font-size: 0.92em !important;
                font-weight: 600 !important;
                color: #0073aa !important;
                text-decoration: underline !important;
                cursor: pointer !important;
                box-shadow: none !important;
                line-height: 1.3 !important;
                min-height: 0 !important;
                text-transform: none !important;
                letter-spacing: normal !important;
            }
            dialog.wcb-desc-modal {
                padding: 0;
                border: none;
                border-radius: 12px;
                max-width: 680px;
                width: calc(100% - 32px);
                max-height: 85vh;
                background: #fff;
                color: #23282d;
                box-shadow: 0 24px 60px rgba(0, 0, 0, 0.28);
                margin: auto;
                overflow: hidden;
            }
            dialog.wcb-desc-modal::backdrop {
                background: rgba(15, 23, 42, 0.65);
                backdrop-filter: blur(4px);
            }
            .wcb-desc-modal-inner {
                display: flex;
                flex-direction: column;
                max-height: 85vh;
            }
            .wcb-desc-modal-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                padding: 18px 24px;
                border-bottom: 1px solid #e5e7eb;
                background: #f8fafc;
            }
            .wcb-desc-modal-title {
                margin: 0 !important;
                font-size: 1.25em !important;
                font-weight: 700 !important;
                color: #1e293b !important;
            }
            .wcb-desc-modal-close {
                appearance: none;
                background: transparent !important;
                border: none !important;
                font-size: 28px !important;
                line-height: 1 !important;
                color: #64748b !important;
                cursor: pointer !important;
                padding: 4px 8px !important;
                margin: 0 !important;
                box-shadow: none !important;
            }
            .wcb-desc-modal-body {
                padding: 24px;
                overflow-y: auto;
                font-size: 1em;
                line-height: 1.65;
                color: #334155;
            }
        </style>
        <script>
        document.addEventListener("click", function(e) {
            var openBtn = e.target.closest ? e.target.closest(".wcb-open-desc-modal") : null;
            if (openBtn) {
                e.preventDefault();
                e.stopPropagation();
                var item = openBtn.closest(".wcb-product-item");
                if (!item) return;
                var tpl = item.querySelector(".wcb-full-description-template");
                var modal = document.getElementById("wcb-desc-modal");
                if (!tpl || !modal) return;
                var titleEl = modal.querySelector(".wcb-desc-modal-title");
                var bodyEl = modal.querySelector(".wcb-desc-modal-body");
                if (titleEl) titleEl.textContent = tpl.getAttribute("data-product-title") || "";
                if (bodyEl) bodyEl.innerHTML = tpl.innerHTML;
                if (typeof modal.showModal === "function") {
                    if (!modal.open) modal.showModal();
                } else {
                    modal.setAttribute("open", "open");
                }
                return;
            }
            var modalEl = document.getElementById("wcb-desc-modal");
            if (modalEl && modalEl.open) {
                var closeBtn = e.target.closest ? e.target.closest(".wcb-desc-modal-close") : null;
                if (closeBtn || e.target === modalEl) {
                    if (typeof modalEl.close === "function") modalEl.close();
                    else modalEl.removeAttribute("open");
                }
            }
        }, true);
        </script>
        <?php
        echo '<div id="wcb-bundle-configurator-' . esc_attr($product_id) . '" class="' . implode(' ', $wrapper_classes) . '" style="--wcb-grid-cols: ' . esc_attr($settings['grid_columns']) . ';">';
        $this->render_bundle_options_html($product_id);
        echo '</div>';
        return ob_get_clean();
    }

    public function display_bundle_options_start()
    {
        global $product;
        if ($product && $product->get_type() === 'custom_bundle') {
            $product_id = $product->get_id();
            echo $this->get_bundle_options_wrapper_html($product_id);
        }
    }

    public function bundle_shortcode_callback($atts)
    {
        $atts = shortcode_atts(['id' => 0], $atts, 'wcb_bundle_form');
        $product_id = absint($atts['id']);

        if (!$product_id && is_product()) {
            $product_id = get_the_ID();
        }

        if (!$product_id)
            return '';

        // Rimuovi hook automatici se presenti, per evitare duplicati
        remove_action('woocommerce_after_single_product_summary', [$this, 'display_bundle_options_start'], 5);
        remove_action('woocommerce_single_product_summary', [$this, 'display_bundle_options_start'], 30);
        remove_action('woocommerce_after_single_product', [$this, 'display_bundle_options_start'], 10);

        return $this->get_bundle_options_wrapper_html($product_id, 'manual');
    }

    public function render_bundle_options_html($product_id)
    {
        $product = wc_get_product($product_id);
        if (!$product || $product->get_type() !== 'custom_bundle')
            return;

        // Garantisce che CSS/JS siano caricati anche se il bundle è renderizzato via Blocchi FSE o Page Builder
        $this->enqueue_bundle_assets_for_product($product_id);

        $bundle_groups_raw = self::get_bundle_groups($product->get_id());
        if (empty($bundle_groups_raw) || !is_array($bundle_groups_raw)) {
            if (current_user_can('edit_products')) {
                $edit_url = get_edit_post_link($product->get_id());
                echo '<div class="woocommerce-info" style="margin: 20px 0; padding: 20px; border-left: 4px solid #2271b1;">';
                echo '<strong>' . esc_html__('Prodotto Bundle non ancora configurato.', 'wcb-framework') . '</strong><br>';
                echo esc_html__('Nessun gruppo di prodotti trovato per questo bundle. Nota: se hai importato il prodotto tramite "Prodotti → Esporta CSV" di WooCommerce da una versione precedente alla 2.7.3, WooCommerce non esportava i campi array dei gruppi.', 'wcb-framework');
                if ($edit_url) {
                    echo '<br><a href="' . esc_url($edit_url) . '" class="button" style="margin-top: 10px;">✏️ ' . esc_html__('Configura o Importa i Gruppi del Bundle', 'wcb-framework') . '</a>';
                }
                echo '</div>';
            } else {
                echo '<p>' . esc_html__('Prodotto non configurato.', 'wcb-framework') . '</p>';
            }
            return;
        }
        $bundle_groups_filtered = [];
        $missing_ids = [];
        foreach ($bundle_groups_raw as $group_index => $group) {
            $available_products = [];
            if (!empty($group['products'])) {
                foreach ($group['products'] as $p_id) {
                    $child_product = wc_get_product($p_id);
                    if ($child_product) {
                        $available_products[] = $p_id;
                    } else {
                        $missing_ids[] = intval($p_id);
                    }
                }
            }
            $group['products'] = $available_products;
            if (!empty($group['products']))
                $bundle_groups_filtered[$group_index] = $group;
        }

        if (empty($bundle_groups_filtered)) {
            if (current_user_can('edit_products')) {
                $edit_url = get_edit_post_link($product->get_id());
                echo '<div class="woocommerce-error" style="margin: 20px 0; padding: 20px;">';
                echo '<strong>' . esc_html__('Attenzione Admin: I prodotti associati ai gruppi di questo bundle non esistono su questo sito.', 'wcb-framework') . '</strong><br>';
                if (!empty($missing_ids)) {
                    echo esc_html(sprintf(__('ID prodotti mancanti o cambiati dopo importazione: %s.', 'wcb-framework'), implode(', ', array_unique($missing_ids)))) . '<br>';
                }
                if ($edit_url) {
                    echo '<a href="' . esc_url($edit_url) . '" class="button" style="margin-top: 10px;">✏️ ' . esc_html__('Ricollega i prodotti nella tab Prodotti Bundle', 'wcb-framework') . '</a>';
                }
                echo '</div>';
            } else {
                echo '<p>' . esc_html__('Prodotto non configurato.', 'wcb-framework') . '</p>';
            }
            return;
        }

        $layout_settings = WCB_Theme_Settings::get_bundle_layout_settings($product_id);

        global $product;
        $original_product = $product;
        $product = wc_get_product($product_id);

        wc_get_template(
            'single-product/add-to-cart/custom-bundle.php',
            [
                'product' => $product,
                'bundle_groups' => $bundle_groups_filtered,
                'instructions' => get_post_meta($product->get_id(), '_bundle_instructions', true),
                'layout_settings' => $layout_settings,
            ],
            '',
            plugin_dir_path(__FILE__) . 'templates/'
        );

        $product = $original_product;
    }
    public function display_bundle_selections_in_cart($item_data, $cart_item)
    {
        if (!empty($cart_item['wcb_bundle_selections'])) {
            $item_data = array_filter($item_data, function ($data) {
                return !isset($data['display']) || strpos($data['display'], 'wcb-bundle-item-meta') === false;
            });
            $item_data[] = ['key' => __('Contenuto del Bundle', 'wcb-framework'), 'display' => '', 'display_class' => 'wcb-bundle-item-meta'];
            foreach ($cart_item['wcb_bundle_selections'] as $group_selections) {
                foreach ($group_selections as $selection) {
                    $product = wc_get_product($selection['item_id']);
                    if ($product) {
                        $product_line = $product->get_formatted_name();
                        $item_data[] = ['key' => '', 'display' => $product_line, 'display_class' => 'wcb-bundle-item-meta'];
                        if (!empty($selection['extra_units_info']) && !empty($selection['extra_units_info']['units'])) {
                            $eu_info = $selection['extra_units_info'];
                            $eu_total = floatval($eu_info['units']) * floatval($eu_info['unit_price']);
                            $eu_text = sprintf(
                                '+%d (+%s)',
                                intval($eu_info['units']),
                                self::format_extra_amount($eu_total)
                            );
                            $item_data[] = [
                                'key' => '',
                                'display' => '<small style="padding-left: 15px;">' . esc_html($eu_info['label']) . ': ' . esc_html($eu_text) . '</small>',
                                'display_class' => 'wcb-bundle-item-meta'
                            ];
                        }
                        if (!empty($selection['personalization_data']) && is_array($selection['personalization_data'])) {
                            foreach ($selection['personalization_data'] as $field) {
                                if (!empty($field['value']))
                                    $item_data[] = ['key' => '', 'display' => '<small style="padding-left: 15px;">' . esc_html($field['label']) . ': ' . esc_html($field['value']) . '</small>', 'display_class' => 'wcb-bundle-item-meta'];
                            }
                        }
                    }
                }
            }
        }
        $extra_info = $cart_item['wcb_extra_units_info'] ?? ($cart_item['wcb_data']['extra_units_info'] ?? null);
        if (!empty($extra_info) && !empty($extra_info['units'])) {
            $eu_total = floatval($extra_info['units']) * floatval($extra_info['unit_price']);
            $item_data[] = [
                'key' => $extra_info['label'] ?: __('Unità in eccesso', 'wcb-framework'),
                'display' => esc_html(sprintf('+%d (+%s)', intval($extra_info['units']), self::format_extra_amount($eu_total)))
            ];
        }
        if (!empty($cart_item['wcb_item_personalization'])) {
            foreach ($cart_item['wcb_item_personalization'] as $field) {
                if (!empty($field['value']))
                    $item_data[] = ['key' => $field['label'], 'display' => esc_html($field['value'])];
            }
        }
        return $item_data;
    }

    public function add_selections_to_order_items($item, $cart_item_key, $values, $order)
    {
        if (!empty($values['wcb_bundle_selections'])) {
            $item->add_meta_data('_wcb_bundle_selections_hidden', $values['wcb_bundle_selections'], true);
            $item->add_meta_data(__('Contenuto del Bundle', 'wcb-framework'), '', true);
            $display_items = [];
            foreach ($values['wcb_bundle_selections'] as $group_selections) {
                foreach ($group_selections as $selection) {
                    $product = wc_get_product($selection['item_id']);
                    if ($product) {
                        $unique_key = $selection['item_id'] . json_encode($selection['personalization_data'] ?? []);
                        if (!isset($display_items[$unique_key])) {
                            $display_items[$unique_key] = [
                                'product_name' => $product->get_formatted_name(),
                                'personalization_data' => $selection['personalization_data'] ?? [],
                                'extra_units_info' => $selection['extra_units_info'] ?? null,
                                'quantity' => 0
                            ];
                        }
                        $display_items[$unique_key]['quantity']++;
                        if (empty($display_items[$unique_key]['extra_units_info']) && !empty($selection['extra_units_info'])) {
                            $display_items[$unique_key]['extra_units_info'] = $selection['extra_units_info'];
                        }
                    }
                }
            }
            foreach ($display_items as $display_item) {
                $display_line = $display_item['product_name'];
                if ($display_item['quantity'] > 1)
                    $display_line .= ' &times; ' . $display_item['quantity'];
                $details = [];
                if (!empty($display_item['extra_units_info']) && !empty($display_item['extra_units_info']['units'])) {
                    $eu_info = $display_item['extra_units_info'];
                    $eu_total = floatval($eu_info['units']) * floatval($eu_info['unit_price']);
                    $details[] = ($eu_info['label'] ?: __('Unità in eccesso', 'wcb-framework')) . ': +' . intval($eu_info['units']) . ' (+' . self::format_extra_amount($eu_total) . ')';
                }
                if (!empty($display_item['personalization_data']) && is_array($display_item['personalization_data'])) {
                    foreach ($display_item['personalization_data'] as $field) {
                        if (!empty($field['value']))
                            $details[] = $field['label'] . ': ' . $field['value'];
                    }
                }
                if (!empty($details))
                    $display_line .= ' | ' . implode(', ', $details);
                $item->add_meta_data(':>', $display_line, false);
            }
        }
        $extra_info = $values['wcb_extra_units_info'] ?? ($values['wcb_data']['extra_units_info'] ?? null);
        if (!empty($extra_info) && !empty($extra_info['units'])) {
            $eu_total = floatval($extra_info['units']) * floatval($extra_info['unit_price']);
            $item->add_meta_data(
                $extra_info['label'] ?: __('Unità in eccesso', 'wcb-framework'),
                sprintf('+%d (+%s)', intval($extra_info['units']), self::format_extra_amount($eu_total))
            );
        }
        if (!empty($values['wcb_item_personalization']) && is_array($values['wcb_item_personalization'])) {
            foreach ($values['wcb_item_personalization'] as $field) {
                if (!empty($field['value']))
                    $item->add_meta_data($field['label'], $field['value']);
            }
        }
    }

    public function handle_bundle_item_removed($cart_item_key, $cart)
    {
        // Recupera l'elemento appena rimosso dai "removed_cart_contents"
        $removed_item = $cart->get_removed_cart_contents()[$cart_item_key] ?? null;

        // Se l'elemento rimosso faceva parte di un bundle
        if ($removed_item && isset($removed_item['wcb_bundle_part_of'])) {
            $target_bundle_id = $removed_item['wcb_bundle_part_of'];

            // CRITICO: Rimuoviamo temporaneamente l'azione per evitare un loop infinito
            // (altrimenti rimuovere il figlio A rimuoverebbe il figlio B, che proverebbe a rimuovere A...)
            remove_action('woocommerce_cart_item_removed', [$this, 'handle_bundle_item_removed'], 10);

            // Cicla attraverso il carrello attuale per trovare i fratelli
            foreach ($cart->get_cart() as $key => $item) {
                if (isset($item['wcb_bundle_part_of']) && $item['wcb_bundle_part_of'] === $target_bundle_id) {
                    // Rimuovi dal carrello
                    $cart->remove_cart_item($key);
                }
            }

            // Ripristina l'azione per future rimozioni
            add_action('woocommerce_cart_item_removed', [$this, 'handle_bundle_item_removed'], 10, 2);
        }
    }

    public function reduce_stock_for_bundle_items($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order)
            return;
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
                    if ($child_product && $child_product->managing_stock())
                        wc_update_product_stock($child_product, $quantity, 'decrease');
                }
            }
        }
    }

    // Funzioni per tema moderno (Spostate QUI dentro la classe)
    public function add_admin_modern_class($classes)
    {
        if (get_option('wcb_enable_modern_theme', WCB_MODERN_THEME_DEFAULT)) {
            $classes .= ' wcb-modern-theme';
        }
        return $classes;
    }

    public function add_frontend_modern_class($classes)
    {
        if (is_product() && get_the_ID()) {
            $product = wc_get_product(get_the_ID());
            if ($product && $product->get_type() === 'custom_bundle') {
                $settings = WCB_Theme_Settings::get_bundle_layout_settings($product->get_id());
                if ($settings['style_mode'] === 'modern') {
                    $classes[] = 'wcb-modern-theme';
                } elseif ($settings['style_mode'] === 'theme_native') {
                    $classes[] = 'wcb-theme-native';
                }
                if ($settings['header_style'] === 'hero') {
                    $classes[] = 'wcb-header-hero';
                } else {
                    $classes[] = 'wcb-header-wc-native';
                }
                if ($settings['summary_position'] === 'bottom_fixed') {
                    $classes[] = 'wcb-has-bottom-bar';
                } elseif ($settings['summary_position'] === 'top_fixed') {
                    $classes[] = 'wcb-has-top-bar';
                } elseif ($settings['summary_position'] === 'sidebar') {
                    $classes[] = 'wcb-has-mobile-bottom-bar';
                }
            }
        }
        return $classes;
    }

    // SPEDIZIONE BUNDLE (modalità carrello / articoli separati)
    // Se il bundle ha l'override attivo, ai fini del calcolo spedizione i
    // figli vengono sostituiti da una riga sintetica con i dati di
    // spedizione (peso, dimensioni, classe) del prodotto bundle.
    private function get_shipping_override_bundle($cart_item)
    {
        $bundle_id = $cart_item['wcb_bundle_part_of'] ?? ($cart_item['wcb_data']['bundle_part_of'] ?? null);
        $parent_id = $cart_item['wcb_parent_bundle_id'] ?? ($cart_item['wcb_data']['parent_bundle_id'] ?? null);
        if (!$bundle_id || !$parent_id)
            return null;
        if (get_post_meta($parent_id, '_bundle_shipping_override', true) !== 'yes')
            return null;
        $bundle_product = wc_get_product($parent_id);
        if (!$bundle_product || !$bundle_product->needs_shipping())
            return null;
        // Senza peso né classe di spedizione sul bundle l'override produrrebbe
        // spedizioni "a vuoto": in quel caso restano le regole dei singoli prodotti.
        if ('' === $bundle_product->get_weight() && !$bundle_product->get_shipping_class_id())
            return null;
        return ['bundle_id' => $bundle_id, 'product' => $bundle_product];
    }

    public function override_bundle_shipping_packages($packages)
    {
        foreach ($packages as &$package) {
            if (empty($package['contents']) || !is_array($package['contents']))
                continue;

            $synthetic = [];
            foreach ($package['contents'] as $key => $item) {
                $override = $this->get_shipping_override_bundle($item);
                if (!$override)
                    continue;
                $bid = $override['bundle_id'];
                if (!isset($synthetic[$bid])) {
                    $synthetic[$bid] = [
                        'key' => 'wcb_shipping_' . $bid,
                        'product_id' => $override['product']->get_id(),
                        'variation_id' => 0,
                        'variation' => [],
                        'quantity' => 1,
                        'data' => $override['product'],
                        'line_total' => 0,
                        'line_tax' => 0,
                        'line_subtotal' => 0,
                        'line_subtotal_tax' => 0,
                    ];
                }
                // I totali dei figli confluiscono nella riga sintetica, così le
                // regole basate sul prezzo (es. spedizione gratuita sopra soglia)
                // continuano a vedere il valore reale del bundle.
                $synthetic[$bid]['line_total'] += floatval($item['line_total'] ?? 0);
                $synthetic[$bid]['line_tax'] += floatval($item['line_tax'] ?? 0);
                $synthetic[$bid]['line_subtotal'] += floatval($item['line_subtotal'] ?? 0);
                $synthetic[$bid]['line_subtotal_tax'] += floatval($item['line_subtotal_tax'] ?? 0);
                unset($package['contents'][$key]);
            }

            if (!empty($synthetic)) {
                foreach ($synthetic as $line) {
                    $package['contents'][$line['key']] = $line;
                }
                $package['contents_cost'] = 0;
                foreach ($package['contents'] as $item) {
                    $package['contents_cost'] += floatval($item['line_total'] ?? 0);
                }
            }
        }
        return $packages;
    }

    public function override_bundle_cart_weight($weight)
    {
        if (!WC()->cart)
            return $weight;
        $seen_bundles = [];
        foreach (WC()->cart->get_cart() as $cart_item) {
            $override = $this->get_shipping_override_bundle($cart_item);
            if (!$override)
                continue;
            // Togli il peso dei figli e conta quello del bundle una volta sola
            $weight -= floatval($cart_item['data']->get_weight()) * $cart_item['quantity'];
            if (!isset($seen_bundles[$override['bundle_id']])) {
                $weight += floatval($override['product']->get_weight());
                $seen_bundles[$override['bundle_id']] = true;
            }
        }
        return max(0, $weight);
    }

    // COUPON BUNDLE (modalità carrello / articoli separati)
    // Forza la validità del coupon sui figli di un bundle che ha l'override
    // attivo, così lo sconto si applica anche ai prodotti che non rientrano
    // nelle categorie/prodotti a cui il coupon sarebbe ristretto.
    public function force_coupon_for_bundle_items($valid, $product, $coupon, $values)
    {
        // Già valido per le regole standard: niente da forzare.
        if ($valid)
            return true;
        if (!is_array($values))
            return $valid;
        $bundle_id = $values['wcb_bundle_part_of'] ?? ($values['wcb_data']['bundle_part_of'] ?? null);
        $parent_id = $values['wcb_parent_bundle_id'] ?? ($values['wcb_data']['parent_bundle_id'] ?? null);
        if (!$bundle_id || !$parent_id)
            return $valid;
        if (get_post_meta($parent_id, '_bundle_coupon_override', true) !== 'yes')
            return $valid;
        return true;
    }

    // LOGICA PREZZI AVANZATA (ARCHITECT FIX)
    public function calculate_bundle_price_in_cart($cart)
    {
        if (is_admin() && !defined('DOING_AJAX'))
            return;

        // FASE 0: quantità bloccate. Una riga di bundle modificata per altre vie (API, plugin terzi)
        // torna alla quantità con cui è stata aggiunta prima di calcolare i prezzi
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $locked_qty = absint($cart_item['wcb_data']['line_qty'] ?? 0);
            if ($locked_qty > 0 && self::get_cart_item_bundle_id($cart_item) && absint($cart_item['quantity']) !== $locked_qty) {
                $cart->cart_contents[$cart_item_key]['quantity'] = $locked_qty;
            }
        }

        // FASE 1: Pre-calcolo Totali Gruppi (Regolare)
        $bundle_group_totals = [];
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['wcb_bundle_part_of'])) {
                $product_id = $cart_item['data']->get_id();
                $original_product = wc_get_product($product_id);
                if ($original_product) {
                    $raw_reg = $original_product->get_regular_price();
                    $reg_price = floatval($raw_reg !== '' ? $raw_reg : $original_product->get_price());
                    $cart->cart_contents[$cart_item_key]['data']->set_price($reg_price);

                    $bundle_id = $cart_item['wcb_bundle_part_of'];
                    $group_idx = $cart_item['wcb_source_group_index'] ?? null;
                    if ($group_idx !== null) {
                        if (!isset($bundle_group_totals[$bundle_id][$group_idx]))
                            $bundle_group_totals[$bundle_id][$group_idx] = 0;
                        $bundle_group_totals[$bundle_id][$group_idx] += $reg_price * $cart_item['quantity'];
                    }
                }
            }
        }

        // FASE 2: Applicazione Override Distribuito
        $separate_bundles = [];
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['wcb_bundle_part_of']))
                $separate_bundles[$cart_item['wcb_bundle_part_of']][$cart_item_key] = $cart_item;
        }

        foreach ($separate_bundles as $bundle_cart_id => $items) {
            $first_item = reset($items);
            if (!isset($first_item['wcb_parent_bundle_id']))
                continue;
            $parent_bundle_id = $first_item['wcb_parent_bundle_id'];
            // Bundle non più acquistabile (bozza, privato, cestino): niente prezzi del bundle, restano quelli di listino.
            // remove_invalid_bundle_cart_items() toglie poi le righe dal carrello.
            $parent_bundle_obj = wc_get_product($parent_bundle_id);
            if (!$parent_bundle_obj || !$parent_bundle_obj->is_type('custom_bundle') || !$parent_bundle_obj->is_purchasable())
                continue;
            $bundle_groups = self::get_bundle_groups($parent_bundle_id);

            foreach ($items as $cart_item_key => $cart_item_data) {
                $product_id = $cart_item_data['data']->get_id();
                $parent_product_id = $cart_item_data['data']->is_type('variation') ? $cart_item_data['data']->get_parent_id() : $product_id;
                $source_group_idx = $cart_item_data['wcb_source_group_index'] ?? null;

                if ($source_group_idx !== null && isset($bundle_groups[$source_group_idx])) {
                    $group_config = $bundle_groups[$source_group_idx];
                    $group_mode = $group_config['selection_mode'] ?? 'multiple';
                    $item_settings = $group_config['products_settings'][$parent_product_id] ?? ($group_config['products_settings'][$product_id] ?? []);

                    if (isset($item_settings['price']) && $item_settings['price'] !== '') {
                        $cart->cart_contents[$cart_item_key]['data']->set_price(floatval($item_settings['price']));
                    }
                    elseif (in_array($group_mode, ['single', 'multiple'], true) && isset($group_config['price_override']) && $group_config['price_override'] !== '') {
                        $target_total = floatval($group_config['price_override']);
                        $actual_total = $bundle_group_totals[$bundle_cart_id][$source_group_idx] ?? 0;

                        if ($actual_total > 0) {
                            $ratio = $target_total / $actual_total;
                            $raw_reg_item = $cart_item_data['data']->get_regular_price();
                            $regular_price_this_item = floatval($raw_reg_item !== '' ? $raw_reg_item : $cart_item_data['data']->get_price());
                            $new_price = $regular_price_this_item * $ratio;
                            $cart->cart_contents[$cart_item_key]['data']->set_price($new_price);
                        }
                    }
                }
            }

            // FASE 2B: Sconti Globali Bundle
            $pricing_type = get_post_meta($parent_bundle_id, '_bundle_pricing_type', true);
            $current_bundle_subtotal = 0;
            foreach ($items as $cart_item_key => $item) {
                $current_bundle_subtotal += floatval($cart->cart_contents[$cart_item_key]['data']->get_price()) * $item['quantity'];
            }

            $bundle_product = wc_get_product($parent_bundle_id);
            $fixed_bundle_price = $bundle_product ? floatval($bundle_product->get_price()) : 0;

            if ($pricing_type === 'fixed' && $fixed_bundle_price > 0) {
                $bundle_final_price = $fixed_bundle_price;
                if ($current_bundle_subtotal > 0) {
                    $discount_ratio = $bundle_final_price / $current_bundle_subtotal;
                    foreach ($items as $key => $item) {
                        $current_price = floatval($cart->cart_contents[$key]['data']->get_price());
                        $cart->cart_contents[$key]['data']->set_price($current_price * $discount_ratio);
                    }
                }
            }
            else {
                $discount_percentage = floatval(get_post_meta($parent_bundle_id, '_bundle_discount_percentage', true));
                $discount_amount = floatval(get_post_meta($parent_bundle_id, '_bundle_discount_amount', true));
                $subtotal = $current_bundle_subtotal;
                if ($discount_percentage > 0)
                    $subtotal *= (1 - ($discount_percentage / 100));
                if ($discount_amount > 0)
                    $subtotal -= $discount_amount;
                $bundle_final_price = max(0, $subtotal);

                if ($current_bundle_subtotal > 0 && $bundle_final_price != $current_bundle_subtotal) {
                    $discount_ratio = $bundle_final_price / $current_bundle_subtotal;
                    foreach ($items as $key => $item) {
                        $current_price = floatval($cart->cart_contents[$key]['data']->get_price());
                        $cart->cart_contents[$key]['data']->set_price($current_price * $discount_ratio);
                    }
                }
            }

            // FASE 2C: Supplemento Unità in Eccesso (+N × Extra €/cad) sulla singola riga
            // Applicato dopo eventuali override/sconti di gruppo o bundle per garantire
            // che ogni unità extra aggiunga esattamente l'importo unitario configurato.
            foreach ($items as $cart_item_key => $cart_item_data) {
                $extra_units = absint($cart_item_data['wcb_extra_units'] ?? ($cart_item_data['wcb_data']['extra_units'] ?? 0));
                if ($extra_units <= 0) {
                    continue;
                }
                $product_id = $cart_item_data['data']->get_id();
                $parent_product_id = $cart_item_data['data']->is_type('variation') ? $cart_item_data['data']->get_parent_id() : $product_id;
                $source_group_idx = $cart_item_data['wcb_source_group_index'] ?? ($cart_item_data['wcb_data']['source_group_index'] ?? null);
                if ($source_group_idx !== null && isset($bundle_groups[$source_group_idx])) {
                    $group_config = $bundle_groups[$source_group_idx];
                    $item_settings = $group_config['products_settings'][$parent_product_id] ?? ($group_config['products_settings'][$product_id] ?? []);
                    $extra_unit_price = isset($item_settings['extra_unit_price']) && $item_settings['extra_unit_price'] !== '' ? floatval($item_settings['extra_unit_price']) : 0;
                    // Carrello e ordine devono mostrare l'importo addebitato ora (config attuale), non quello salvato all'aggiunta
                    $live_info = $extra_unit_price > 0 ? array_merge((array) ($cart_item_data['wcb_extra_units_info'] ?? ($cart_item_data['wcb_data']['extra_units_info'] ?? [])), ['units' => $extra_units, 'unit_price' => $extra_unit_price]) : null;
                    $cart->cart_contents[$cart_item_key]['wcb_extra_units_info'] = $live_info;
                    if (isset($cart->cart_contents[$cart_item_key]['wcb_data'])) {
                        $cart->cart_contents[$cart_item_key]['wcb_data']['extra_units_info'] = $live_info;
                    }
                    if ($extra_unit_price > 0) {
                        // Extra per bundle: ripartito sulla quantità della riga in UN bundle, così scala con la quantità
                        // (righe aggiunte prima di questa versione: quantità attuale, extra fisso come prima)
                        $base_qty = max(1, intval($cart_item_data['wcb_data']['extra_units_base_qty'] ?? ($cart_item_data['quantity'] ?? 1)));
                        $current_unit_price = floatval($cart->cart_contents[$cart_item_key]['data']->get_price());
                        $extra_per_item_qty = ($extra_units * $extra_unit_price) / $base_qty;
                        $cart->cart_contents[$cart_item_key]['data']->set_price($current_unit_price + $extra_per_item_qty);
                    }
                }
            }
        }

        // FASE 3: Bundle Unico (Non Separato)
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if ('custom_bundle' === $cart_item['data']->get_type() && !empty($cart_item['wcb_bundle_selections'])) {
                $product_id = $cart_item['product_id'];
                $pricing_type = get_post_meta($product_id, '_bundle_pricing_type', true);
                $bundle_product = wc_get_product($product_id);
                $fixed_bundle_price = $bundle_product ? floatval($bundle_product->get_price()) : 0;
                $bundle_groups = self::get_bundle_groups($product_id);

                // Calcola eventuale supplemento totale per unità in eccesso (+N × Extra €/cad)
                $total_extra_surcharge = 0;
                foreach ($cart_item['wcb_bundle_selections'] as $group_index => $group_selections) {
                    $group_config = $bundle_groups[$group_index] ?? [];
                    foreach ($group_selections as $sel_index => $selection) {
                        $sel_extra_units = absint($selection['extra_units'] ?? 0);
                        if ($sel_extra_units <= 0) {
                            continue;
                        }
                        $child_product = wc_get_product($selection['item_id']);
                        if (!$child_product) {
                            continue;
                        }
                        $lookup_id = $child_product->is_type('variation') ? $child_product->get_parent_id() : $selection['item_id'];
                        $prod_settings = $group_config['products_settings'][$lookup_id] ?? ($group_config['products_settings'][$selection['item_id']] ?? []);
                        $extra_unit_price = isset($prod_settings['extra_unit_price']) && $prod_settings['extra_unit_price'] !== '' ? floatval($prod_settings['extra_unit_price']) : 0;
                        if ($extra_unit_price > 0) {
                            $total_extra_surcharge += $sel_extra_units * $extra_unit_price;
                        }
                        // Allinea il riepilogo in carrello/ordine all'importo effettivamente addebitato
                        $cart->cart_contents[$cart_item_key]['wcb_bundle_selections'][$group_index][$sel_index]['extra_units_info'] = $extra_unit_price > 0
                            ? array_merge((array) ($selection['extra_units_info'] ?? []), ['units' => $sel_extra_units, 'unit_price' => $extra_unit_price])
                            : null;
                    }
                }

                if ('fixed' === $pricing_type && $fixed_bundle_price > 0) {
                    $cart_item['data']->set_price($fixed_bundle_price + $total_extra_surcharge);
                }
                else {
                    $calculated_price = 0;
                    foreach ($cart_item['wcb_bundle_selections'] as $group_index => $group_selections) {
                        $group_config = $bundle_groups[$group_index] ?? [];
                        $group_mode = $group_config['selection_mode'] ?? 'multiple';
                        $group_price_override = (in_array($group_mode, ['single', 'multiple'], true) && isset($group_config['price_override']) && $group_config['price_override'] !== '') ? floatval($group_config['price_override']) : null;

                        if ($group_price_override !== null) {
                            $calculated_price += $group_price_override;
                        }
                        else {
                            foreach ($group_selections as $selection) {
                                $child_product = wc_get_product($selection['item_id']);
                                if (!$child_product)
                                    continue;
                                $lookup_id = $child_product->is_type('variation') ? $child_product->get_parent_id() : $selection['item_id'];
                                $prod_settings = $group_config['products_settings'][$lookup_id] ?? ($group_config['products_settings'][$selection['item_id']] ?? []);
                                $item_price_override = isset($prod_settings['price']) && $prod_settings['price'] !== '' ? floatval($prod_settings['price']) : null;
                                $sel_qty = isset($selection['quantity']) ? max(1, intval($selection['quantity'])) : 1;
                                $unit_price = ($item_price_override !== null) ? $item_price_override : floatval($child_product->get_price());
                                $calculated_price += $unit_price * $sel_qty;
                            }
                        }
                    }
                    $discount_percentage = floatval(get_post_meta($product_id, '_bundle_discount_percentage', true));
                    $discount_amount = floatval(get_post_meta($product_id, '_bundle_discount_amount', true));
                    if ($discount_percentage > 0)
                        $calculated_price *= (1 - ($discount_percentage / 100));
                    if ($discount_amount > 0)
                        $calculated_price -= $discount_amount;
                    $cart_item['data']->set_price(max(0, $calculated_price) + $total_extra_surcharge);
                }
            }
        }
    }
}
WC_Custom_Bundle_Framework::get_instance();
endif;
