<?php
/**
 * WCB GitHub Updater (Versione Finale Definitiva)
 * @version 1.5.0
 */

if ( ! class_exists( 'WCB_GitHub_Updater' ) ) {
    class WCB_GitHub_Updater {
        private $file;
        private $basename;
        private $plugin_data;
        private $github_repo;
        private $github_response;

        public function __construct( $file ) {
            $this->file = $file;
            add_action( 'admin_init', [ $this, 'set_plugin_properties' ] );
            add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ], 10, 1 );
            add_filter( 'upgrader_source_selection', [ $this, 'rename_github_zip' ], 10, 3 );
        }

        public function set_plugin_properties() {
            $this->basename    = plugin_basename( $this->file );
            $this->plugin_data = get_plugin_data( $this->file );
            $this->github_repo = $this->get_github_repo_from_header();
        }

        private function get_github_repo_from_header() {
            $plugin_file_content = file_get_contents( $this->file );
            if ( preg_match( '/^[\s\*#@]*GithubRepo:(.*)$/mi', $plugin_file_content, $matches ) ) {
                return trim( $matches[1] );
            }
            return '';
        }

        private function get_repo_release_info() {
            if ( ! empty( $this->github_response ) || empty( $this->github_repo ) ) { return; }
            $url = "https://api.github.com/repos/{$this->github_repo}/releases/latest";
            $response = wp_remote_get( $url );
            if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) { return; }
            $this->github_response = json_decode( wp_remote_retrieve_body( $response ) );
        }

        public function check_for_update( $transient ) {
            if ( empty( $transient->checked ) || empty( $transient->checked[ $this->basename ] ) ) {
                return $transient;
            }

            $this->get_repo_release_info();
            
            if ( ! $this->github_response || ! isset( $this->github_response->tag_name ) ) {
                return $transient;
            }
            
            $installed_version = $transient->checked[ $this->basename ];
            $github_version = trim( ltrim( $this->github_response->tag_name, 'vV' ) );

            if ( version_compare( $github_version, $installed_version, '>' ) ) {
                $obj = new stdClass();
                $obj->slug        = $this->basename;
                $obj->new_version = $this->github_response->tag_name;
                $obj->url         = $this->plugin_data["PluginURI"] ?? 'https://github.com/' . $this->github_repo;
                $obj->package     = $this->github_response->zipball_url;
                
                if ( ! empty( $this->plugin_data['TestedUpto'] ) ) {
                    $obj->tested = $this->plugin_data['TestedUpto'];
                }
                if ( ! empty( $this->plugin_data['RequiresWP'] ) ) {
                    $obj->requires = $this->plugin_data['RequiresWP'];
                }
                
                $transient->response[ $this->basename ] = $obj;
            }

            return $transient;
        }

        /**
         * Rinomina la cartella del plugin estratta dallo zip di GitHub per
         * corrispondere alla slug corretta del plugin.
         */
        public function rename_github_zip( $source, $remote_source, $upgrader ) {
            // Controlla se l'aggiornamento è per il nostro plugin
            if ( isset( $upgrader->skin->plugin_info ) && $upgrader->skin->plugin_info['Name'] === $this->plugin_data['Name'] ) {
                
                // Il percorso della cartella appena estratta (es. .../upgrade/Gianfry70IT-...)
                $unzipped_folder = $source;
                
                // Il nome corretto della cartella del nostro plugin (es. 'wooc-bundle-gia70')
                $correct_folder_name = dirname( $this->basename );

                // Il percorso completo della cartella di destinazione rinominata
                $destination_folder = trailingslashit( dirname( $unzipped_folder ) ) . $correct_folder_name;

                // Rinomina la cartella
                rename( $unzipped_folder, $destination_folder );

                // Restituisce il nuovo percorso a WordPress per continuare l'installazione
                return $destination_folder;
            }

            return $source;
        }
    }
}