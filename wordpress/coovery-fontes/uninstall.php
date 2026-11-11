<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}coovery_fontes");
delete_option('coovery_fontes_settings');
delete_transient('coovery_fontes_firebase_certs');
