<?php

if (!defined('ABSPATH')) {
    exit;
}

class Coovery_Fontes_Install {
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'coovery_fontes';
    }

    public static function activate() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::table_name();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(180) NOT NULL DEFAULT '',
            tmdb_id bigint(20) unsigned NOT NULL DEFAULT 0,
            media_type varchar(16) NOT NULL DEFAULT '',
            season smallint(5) unsigned NULL,
            episode smallint(5) unsigned NULL,
            video_url text NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY tmdb_lookup (media_type, tmdb_id, season, episode)
        ) {$charset};";

        dbDelta($sql);

        if (get_option('coovery_fontes_settings', null) === null) {
            add_option('coovery_fontes_settings', array(
                'firebase_project_id' => 'coovery',
            ));
        }
    }

    public static function settings() {
        $stored = get_option('coovery_fontes_settings', array());
        if (!is_array($stored)) {
            $stored = array();
        }
        return array(
            'firebase_project_id' => isset($stored['firebase_project_id']) && $stored['firebase_project_id'] !== ''
                ? (string) $stored['firebase_project_id']
                : 'coovery',
        );
    }
}
