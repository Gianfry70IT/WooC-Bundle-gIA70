<?php
/*
 * updater.php - Versione 2.8.4
 * Author: Gianfranco Greco con Codice Sorgente
 * Copyright (c) 2025 Gianfranco Greco
 * Licensed under the GNU GPL v2 or later: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Aggiornamenti dalle release GitHub del repository indicato nell'header "GithubRepo".
 * - La risposta dell'API GitHub è in cache (6 ore; 30 minuti se la richiesta fallisce):
 *   l'API senza autenticazione concede 60 richieste l'ora per IP, poche su un hosting condiviso.
 * - Si installa solo lo zip allegato alla release (quello creato dal workflow), mai lo zip dei sorgenti,
 *   e solo se scaricato dal percorso releases/download del repository.
 * - Prima dell'installazione lo zip viene confrontato con l'impronta SHA-256 che GitHub pubblica per l'allegato.
 * - La cartella estratta prende il nome della cartella già installata: un nome diverso nello zip
 *   cambierebbe il percorso del plugin e WordPress lo disattiverebbe.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WCB_GitHub_Updater')) {
    class WCB_GitHub_Updater
    {
        const CACHE_TTL = 6 * HOUR_IN_SECONDS;
        const ERROR_TTL = 30 * MINUTE_IN_SECONDS;

        private $file;
        private $basename;
        private $github_repo;
        private $release; // risposta /releases/latest già letta in questa richiesta

        public function __construct($file)
        {
            $this->file = $file;
            // Subito, non su admin_init: il controllo aggiornamenti gira anche da cron, dove admin_init non scatta
            $this->basename = plugin_basename($file);
            $this->github_repo = $this->get_github_repo_from_header();
            if (empty($this->github_repo)) {
                return;
            }
            add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update'], 10, 1);
            add_filter('upgrader_pre_download', [$this, 'download_and_verify_package'], 10, 4);
            add_filter('upgrader_source_selection', [$this, 'keep_plugin_folder_name'], 10, 4);
        }

        private function get_github_repo_from_header()
        {
            $headers = get_file_data($this->file, ['repo' => 'GithubRepo']);
            $repo = trim((string) ($headers['repo'] ?? ''));
            // Solo "utente/repository": il valore finisce in un URL
            return preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo) ? $repo : '';
        }

        private function cache_key()
        {
            return 'wcb_gh_release_' . md5($this->github_repo);
        }

        private function get_release()
        {
            if (null !== $this->release) {
                return $this->release;
            }
            // "Controlla di nuovo" in Bacheca → Aggiornamenti salta la cache
            $force = is_admin() && isset($_GET['force-check']) && current_user_can('update_plugins');
            $cached = $force ? false : get_site_transient($this->cache_key());
            if (false !== $cached) {
                return $this->release = is_array($cached) ? $cached : [];
            }

            $response = wp_remote_get("https://api.github.com/repos/{$this->github_repo}/releases/latest", [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'WooC-Bundle-gIA70-Updater',
                ],
            ]);
            if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
                set_site_transient($this->cache_key(), 'error', self::ERROR_TTL);
                return $this->release = [];
            }
            $data = json_decode(wp_remote_retrieve_body($response), true);
            $release = $this->parse_release(is_array($data) ? $data : []);
            set_site_transient($this->cache_key(), $release, $release ? self::CACHE_TTL : self::ERROR_TTL);
            return $this->release = $release;
        }

        /** Tiene solo ciò che serve: versione, URL dello zip allegato e la sua impronta SHA-256. */
        private function parse_release($data)
        {
            if (empty($data['tag_name']) || !empty($data['draft']) || !empty($data['prerelease'])) {
                return [];
            }
            $prefix = "https://github.com/{$this->github_repo}/releases/download/";
            foreach ((array) ($data['assets'] ?? []) as $asset) {
                $url = (string) ($asset['browser_download_url'] ?? '');
                $name = (string) ($asset['name'] ?? '');
                if ('.zip' !== substr($name, -4) || 0 !== strpos($url, $prefix)) {
                    continue;
                }
                $digest = (string) ($asset['digest'] ?? '');
                return [
                    'version' => trim(ltrim((string) $data['tag_name'], 'vV')),
                    'package' => $url,
                    'sha256' => preg_match('/^sha256:([a-f0-9]{64})$/i', $digest, $m) ? strtolower($m[1]) : '',
                    'url' => (string) ($data['html_url'] ?? ''),
                ];
            }
            return []; // Release senza zip allegato: nessun aggiornamento (lo zip dei sorgenti ha la cartella sbagliata)
        }

        public function check_for_update($transient)
        {
            if (!is_object($transient) || empty($transient->checked) || empty($transient->checked[$this->basename])) {
                return $transient;
            }
            $release = $this->get_release();
            if (empty($release['version']) || empty($release['package'])) {
                return $transient;
            }
            if (version_compare($release['version'], $transient->checked[$this->basename], '>')) {
                $obj = new stdClass();
                $obj->slug = dirname($this->basename);
                $obj->plugin = $this->basename;
                $obj->new_version = $release['version'];
                $obj->url = $release['url'] ?: 'https://github.com/' . $this->github_repo;
                $obj->package = $release['package'];
                $plugin_data = get_file_data($this->file, ['tested' => 'Tested up to', 'requires' => 'Requires at least', 'requires_php' => 'Requires PHP']);
                foreach ($plugin_data as $key => $value) {
                    if ('' !== $value) {
                        $obj->$key = $value;
                    }
                }
                $transient->response[$this->basename] = $obj;
            }
            return $transient;
        }

        /**
         * Scarica lo zip della release e lo confronta con l'impronta SHA-256 pubblicata da GitHub.
         * Protegge da download corrotti o alterati lungo il percorso; non da un account GitHub
         * compromesso (chi carica lo zip ne pubblica anche l'impronta): per quello serve la verifica in due passaggi.
         */
        public function download_and_verify_package($reply, $package, $upgrader = null, $hook_extra = [])
        {
            if (false !== $reply) {
                return $reply;
            }
            $release = $this->get_release();
            if (empty($release['package']) || $package !== $release['package']) {
                return $reply; // Non è il nostro pacchetto
            }
            if (empty($release['sha256'])) {
                return $reply; // Nessuna impronta pubblicata: download normale di WordPress
            }
            if (!function_exists('download_url')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            $file = download_url($package, 300);
            if (is_wp_error($file)) {
                return $file;
            }
            $hash = hash_file('sha256', $file);
            if (!$hash || !hash_equals($release['sha256'], strtolower($hash))) {
                @unlink($file);
                // La cache potrebbe riferirsi a una release sostituita: alla prossima verifica si rilegge GitHub
                delete_site_transient($this->cache_key());
                return new WP_Error('wcb_package_checksum', __('Aggiornamento WooC Bundle annullato: lo zip scaricato non corrisponde all\'impronta SHA-256 pubblicata su GitHub.', 'wcb-framework'));
            }
            return $file;
        }

        /** Rinomina la cartella estratta come quella installata, così il plugin resta attivo dopo l'aggiornamento. */
        public function keep_plugin_folder_name($source, $remote_source, $upgrader = null, $hook_extra = [])
        {
            if (is_wp_error($source) || empty($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->basename) {
                return $source;
            }
            $folder = dirname($this->basename);
            if ('.' === $folder || '' === $folder) {
                return $source; // Plugin a file singolo: niente da fare
            }
            $desired = trailingslashit($remote_source) . $folder;
            if (untrailingslashit($source) === untrailingslashit($desired)) {
                return $source;
            }
            global $wp_filesystem;
            if (!$wp_filesystem || !$wp_filesystem->move(untrailingslashit($source), $desired, true)) {
                return new WP_Error('wcb_rename_folder', __('Aggiornamento WooC Bundle: impossibile rinominare la cartella del plugin.', 'wcb-framework'));
            }
            return trailingslashit($desired);
        }
    }
}
