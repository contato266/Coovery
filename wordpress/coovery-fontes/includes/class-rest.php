<?php

if (!defined('ABSPATH')) {
    exit;
}

class Coovery_Fontes_Rest {
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register'));
    }

    public static function register() {
        register_rest_route('coovery/v1', '/stream', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'stream'),
            'permission_callback' => array(__CLASS__, 'authorize'),
        ));
    }

    /**
     * @param WP_REST_Request $request
     * @return true|WP_Error
     */
    public static function authorize($request) {
        $header = (string) $request->get_header('authorization');
        if (stripos($header, 'Bearer ') !== 0) {
            return new WP_Error('coovery_fontes_token', 'Entre com uma conta do Firebase.', array('status' => 401));
        }
        $token = trim(substr($header, 7));
        $settings = Coovery_Fontes_Install::settings();
        $claims = Coovery_Fontes_Firebase::verify($token, $settings['firebase_project_id']);
        if (is_wp_error($claims)) {
            return $claims;
        }
        $request->set_param('_coovery_uid', $claims['sub']);
        return true;
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function stream($request) {
        $type = sanitize_key((string) $request->get_param('type'));
        if ($type === 'tv' || $type === 'episode') {
            $type = 'series';
        }
        $tmdb_id = absint($request->get_param('tmdb_id'));
        if (!in_array($type, array('movie', 'series'), true) || $tmdb_id < 1) {
            return new WP_Error('coovery_fontes_query', 'Informe o tipo e o id do TMDB.', array('status' => 400));
        }

        $season = null;
        $episode = null;
        if ($type === 'series') {
            $season = absint($request->get_param('season'));
            $episode = absint($request->get_param('episode'));
            if ($season < 1 || $episode < 1) {
                return new WP_Error('coovery_fontes_query', 'Informe a temporada e o episódio.', array('status' => 400));
            }
        }

        global $wpdb;
        $table = Coovery_Fontes_Install::table_name();
        if ($type === 'movie') {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, title, video_url FROM {$table} WHERE media_type = %s AND tmdb_id = %d ORDER BY id ASC",
                'movie',
                $tmdb_id
            ));
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, title, video_url FROM {$table} WHERE media_type = %s AND tmdb_id = %d AND season = %d AND episode = %d ORDER BY id ASC",
                'series',
                $tmdb_id,
                $season,
                $episode
            ));
        }

        $streams = array();
        foreach ((array) $rows as $row) {
            $url = isset($row->video_url) ? (string) $row->video_url : '';
            if ($url === '') {
                continue;
            }
            $streams[] = array(
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'url' => $url,
            );
        }

        return new WP_REST_Response(array(
            'type' => $type,
            'tmdb_id' => $tmdb_id,
            'season' => $season,
            'episode' => $episode,
            'streams' => $streams,
        ), 200);
    }
}
