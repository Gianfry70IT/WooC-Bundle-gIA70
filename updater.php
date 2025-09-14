<?php
// file: updater.php

if ( ! class_exists( 'WCB_GitHub_Updater' ) ) {
    class WCB_GitHub_Updater {
        private $file;
        private $plugin;
        private $basename;
        private $plugin_data;
        private $github_repo;
        private $github_response;

        public function __construct( $file ) {
            $this->file = $file;
            add_action( 'admin_init', [ $this, 'set_plugin_properties' ] );
            add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
        }

        public function set_plugin_properties() {
            $this->plugin      = get_plugin_data( $this->file );
            $this->basename    = plugin_basename( $this->file );
            $this->plugin_data = get_plugin_data( $this->file );
            $this->github_repo = $this->plugin_data['GitHub Plugin URI'];
        }

        private function get_repo_release_info() {
            if ( ! empty( $this->github_response ) || empty($this->github_repo) ) {
                return;
            }

            $url = "https://api.github.com/repos/{$this->github_repo}/releases/latest";
            $response = wp_remote_get( $url );

            if ( is_wp_error( $response ) ) {
                return false;
            }

            $this->github_response = json_decode( wp_remote_retrieve_body( $response ) );
        }

        public function check_for_update( $transient ) {
            if ( empty( $transient->checked ) ) {
                return $transient;
            }

            $this->get_repo_release_info();
            
            if ( $this->github_response && version_compare( $this->github_response->tag_name, $transient->checked[ $this->basename ], '>' ) ) {
                $package_url = $this->github_response->zipball_url;

                $obj = new stdClass();
                $obj->slug = $this->basename;
                $obj->new_version = $this->github_response->tag_name;
                $obj->url = $this->plugin["PluginURI"];
                $obj->package = $package_url;
                $transient->response[ $this->basename ] = $obj;
            }

            return $transient;
        }
    }
}