<?php
/*
 * updater.php - Versione 2.4.10
 * Author: Gianfranco Greco con Codice Sorgente
 * Copyright (c) 2025 Gianfranco Greco
 * Licensed under the GNU GPL v2 or later: https://www.gnu.org/licenses/gpl-2.0.html
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
                
                $package_url = '';
                if ( ! empty( $this->github_response->assets ) ) {
                    foreach ( $this->github_response->assets as $asset ) {
                        if ( '.zip' === substr( $asset->name, -4 ) ) {
                            $package_url = $asset->browser_download_url;
                            break;
                        }
                    }
                }

                if ( empty( $package_url ) ) {
                    $package_url = $this->github_response->zipball_url;
                }

                $obj = new stdClass();
                $obj->slug        = $this->basename;
                $obj->new_version = $this->github_response->tag_name;
                $obj->url         = $this->plugin_data["PluginURI"] ?? 'https://github.com/' . $this->github_repo;
                $obj->package     = $package_url;
                
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
    }
}