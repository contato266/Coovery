<?php

if (!defined('ABSPATH')) {
    exit;
}

class Coovery_Fontes_Admin {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_coovery_fontes_save', array(__CLASS__, 'save'));
        add_action('admin_post_coovery_fontes_delete', array(__CLASS__, 'delete'));
        add_action('admin_post_coovery_fontes_settings', array(__CLASS__, 'save_settings'));
    }

    public static function menu() {
        add_menu_page(
            'Fontes Coovery',
            'Fontes Coovery',
            'manage_options',
            'coovery-fontes',
            array(__CLASS__, 'render'),
            'dashicons-video-alt2',
            58
        );
    }

    public static function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Você não pode cadastrar fontes.', 'coovery-fontes'));
        }

        $action = isset($_GET['fonte']) ? sanitize_key(wp_unslash($_GET['fonte'])) : 'list';
        echo '<div class="wrap">';
        echo '<h1>Fontes Coovery</h1>';
        self::notice();

        if ($action === 'edit' || $action === 'new') {
            self::render_form();
        } else {
            self::render_list();
            self::render_settings();
        }
        echo '</div>';
    }

    private static function notice() {
        if (empty($_GET['coovery_msg'])) {
            return;
        }
        $messages = array(
            'saved' => 'Fonte salva.',
            'deleted' => 'Fonte excluída.',
            'settings' => 'Configuração salva.',
            'certs' => 'As chaves do Firebase foram encontradas.',
            'certs_error' => 'Não foi possível buscar as chaves do Firebase. Confira se o site alcança o Google.',
        );
        $key = sanitize_key(wp_unslash($_GET['coovery_msg']));
        if (!isset($messages[$key])) {
            return;
        }
        $class = $key === 'certs_error' ? 'notice-error' : 'notice-success';
        echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($messages[$key]) . '</p></div>';
    }

    private static function render_list() {
        global $wpdb;
        $table = Coovery_Fontes_Install::table_name();
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $page = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
        $per_page = 50;
        $offset = ($page - 1) * $per_page;

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            if (ctype_digit($search)) {
                $total = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE title LIKE %s OR tmdb_id = %d",
                    $like,
                    (int) $search
                ));
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$table} WHERE title LIKE %s OR tmdb_id = %d ORDER BY updated_at DESC LIMIT %d OFFSET %d",
                    $like,
                    (int) $search,
                    $per_page,
                    $offset
                ));
            } else {
                $total = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE title LIKE %s",
                    $like
                ));
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$table} WHERE title LIKE %s ORDER BY updated_at DESC LIMIT %d OFFSET %d",
                    $like,
                    $per_page,
                    $offset
                ));
            }
        } else {
            $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY updated_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ));
        }

        $new_url = admin_url('admin.php?page=coovery-fontes&fonte=new');
        echo '<p>Cadastre a URL do arquivo. O app só recebe essa URL quando a pessoa está logada no Firebase. Quem não entrou continua na lista de IPTV.</p>';
        echo '<a class="button button-primary" href="' . esc_url($new_url) . '">Adicionar fonte</a>';

        echo '<form method="get" style="margin:16px 0">';
        echo '<input type="hidden" name="page" value="coovery-fontes" />';
        echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Título ou id TMDB" /> ';
        echo '<button class="button">Buscar</button>';
        echo '</form>';

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>Título</th><th>TMDB</th><th>Tipo</th><th>Temp.</th><th>Ep.</th><th>URL</th><th></th>';
        echo '</tr></thead><tbody>';
        if (!$rows) {
            echo '<tr><td colspan="7">Nenhuma fonte cadastrada.</td></tr>';
        }
        foreach ((array) $rows as $row) {
            $edit = admin_url('admin.php?page=coovery-fontes&fonte=edit&id=' . (int) $row->id);
            echo '<tr>';
            echo '<td><a href="' . esc_url($edit) . '">' . esc_html($row->title) . '</a></td>';
            echo '<td>' . esc_html((string) $row->tmdb_id) . '</td>';
            echo '<td>' . esc_html($row->media_type === 'series' ? 'Série' : 'Filme') . '</td>';
            echo '<td>' . esc_html($row->season === null ? '—' : (string) $row->season) . '</td>';
            echo '<td>' . esc_html($row->episode === null ? '—' : (string) $row->episode) . '</td>';
            echo '<td><code>' . esc_html(self::short_url($row->video_url)) . '</code></td>';
            echo '<td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline" onsubmit="return confirm(\'Excluir esta fonte?\')">';
            echo '<input type="hidden" name="action" value="coovery_fontes_delete" />';
            echo '<input type="hidden" name="id" value="' . (int) $row->id . '" />';
            wp_nonce_field('coovery_fontes_delete_' . (int) $row->id);
            echo '<button class="button-link-delete" type="submit">Excluir</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        $pages = (int) ceil($total / $per_page);
        if ($pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            for ($i = 1; $i <= $pages; $i++) {
                $url = add_query_arg(array('page' => 'coovery-fontes', 'paged' => $i, 's' => $search), admin_url('admin.php'));
                $style = $i === $page ? 'font-weight:700' : '';
                echo '<a style="margin-right:8px;' . esc_attr($style) . '" href="' . esc_url($url) . '">' . (int) $i . '</a>';
            }
            echo '</div></div>';
        }
    }

    private static function render_form() {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $row = $id > 0 ? self::find($id) : null;
        if ($id > 0 && !$row) {
            echo '<p>Fonte não encontrada.</p>';
            return;
        }

        $title = $row ? $row->title : '';
        $tmdb = $row ? (string) $row->tmdb_id : '';
        $type = $row ? $row->media_type : 'movie';
        $season = $row && $row->season !== null ? (string) $row->season : '';
        $episode = $row && $row->episode !== null ? (string) $row->episode : '';
        $url = $row ? $row->video_url : '';

        echo '<p><a href="' . esc_url(admin_url('admin.php?page=coovery-fontes')) . '">Voltar</a></p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:720px">';
        echo '<input type="hidden" name="action" value="coovery_fontes_save" />';
        echo '<input type="hidden" name="id" value="' . (int) $id . '" />';
        wp_nonce_field('coovery_fontes_save');
        echo '<table class="form-table"><tbody>';
        self::text_row('title', 'Título', $title, 'O nome que aparece neste cadastro.');
        self::text_row('tmdb_id', 'ID TMDB', $tmdb, 'O número do filme ou da série no TMDB.');
        echo '<tr><th scope="row"><label for="media_type">Tipo</label></th><td>';
        echo '<select name="media_type" id="media_type">';
        echo '<option value="movie"' . selected($type, 'movie', false) . '>Filme</option>';
        echo '<option value="series"' . selected($type, 'series', false) . '>Série</option>';
        echo '</select></td></tr>';
        self::text_row('season', 'Temporada', $season, 'Obrigatória para série.');
        self::text_row('episode', 'Episódio', $episode, 'Obrigatório para série.');
        self::text_row('video_url', 'URL do vídeo', $url, 'O endereço do arquivo. Pode ser de qualquer conta do R2.');
        echo '</tbody></table>';
        submit_button($id > 0 ? 'Salvar fonte' : 'Adicionar fonte');
        echo '</form>';
    }

    private static function text_row($name, $label, $value, $help) {
        echo '<tr><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input class="regular-text" style="width:100%" type="text" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . esc_html($help) . '</p></td></tr>';
    }

    private static function render_settings() {
        $settings = Coovery_Fontes_Install::settings();
        echo '<hr /><h2>Firebase</h2>';
        echo '<p>O id do projeto é <code>coovery</code>. Só um token desse projeto libera a URL.</p>';
        echo '<p>Consulta do app: <code>' . esc_html(rest_url('coovery/v1/stream')) . '</code></p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="coovery_fontes_settings" />';
        wp_nonce_field('coovery_fontes_settings');
        echo '<table class="form-table"><tbody>';
        self::text_row('firebase_project_id', 'ID do projeto Firebase', $settings['firebase_project_id'], 'Deixe coovery, a menos que o app use outro projeto.');
        echo '</tbody></table>';
        submit_button('Salvar Firebase', 'secondary', 'submit', false);
        echo ' <button class="button" name="check_certs" value="1">Buscar chaves do Firebase</button>';
        echo '</form>';
    }

    public static function save() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Você não pode cadastrar fontes.', 'coovery-fontes'));
        }
        check_admin_referer('coovery_fontes_save');

        $parsed = self::parse_source($_POST);
        if (is_wp_error($parsed)) {
            wp_die(esc_html($parsed->get_error_message()));
        }

        global $wpdb;
        $table = Coovery_Fontes_Install::table_name();
        $id = absint($_POST['id'] ?? 0);
        $now = current_time('mysql');
        $data = array(
            'title' => $parsed['title'],
            'tmdb_id' => $parsed['tmdb_id'],
            'media_type' => $parsed['media_type'],
            'season' => $parsed['season'],
            'episode' => $parsed['episode'],
            'video_url' => $parsed['video_url'],
            'updated_at' => $now,
        );
        if ($id > 0 && self::find($id)) {
            if ($parsed['media_type'] === 'movie') {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table} SET title = %s, tmdb_id = %d, media_type = %s, season = NULL, episode = NULL, video_url = %s, updated_at = %s WHERE id = %d",
                    $parsed['title'],
                    $parsed['tmdb_id'],
                    'movie',
                    $parsed['video_url'],
                    $now,
                    $id
                ));
            } else {
                $wpdb->update($table, $data, array('id' => $id), array('%s', '%d', '%s', '%d', '%d', '%s', '%s'), array('%d'));
            }
        } else {
            $data['created_at'] = $now;
            if ($parsed['media_type'] === 'movie') {
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$table} (title, tmdb_id, media_type, season, episode, video_url, created_at, updated_at) VALUES (%s, %d, %s, NULL, NULL, %s, %s, %s)",
                    $parsed['title'],
                    $parsed['tmdb_id'],
                    'movie',
                    $parsed['video_url'],
                    $now,
                    $now
                ));
            } else {
                $wpdb->insert($table, $data, array('%s', '%d', '%s', '%d', '%d', '%s', '%s', '%s'));
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=coovery-fontes&coovery_msg=saved'));
        exit;
    }

    public static function delete() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Você não pode cadastrar fontes.', 'coovery-fontes'));
        }
        $id = absint($_POST['id'] ?? 0);
        check_admin_referer('coovery_fontes_delete_' . $id);
        global $wpdb;
        $wpdb->delete(Coovery_Fontes_Install::table_name(), array('id' => $id), array('%d'));
        wp_safe_redirect(admin_url('admin.php?page=coovery-fontes&coovery_msg=deleted'));
        exit;
    }

    public static function save_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Você não pode alterar esta configuração.', 'coovery-fontes'));
        }
        check_admin_referer('coovery_fontes_settings');
        $project = sanitize_text_field(wp_unslash($_POST['firebase_project_id'] ?? 'coovery'));
        if (!preg_match('/^[A-Za-z0-9-]{4,60}$/', $project)) {
            $project = 'coovery';
        }
        update_option('coovery_fontes_settings', array(
            'firebase_project_id' => strtolower($project),
        ));

        $msg = 'settings';
        if (!empty($_POST['check_certs'])) {
            delete_transient('coovery_fontes_firebase_certs');
            $certs = Coovery_Fontes_Firebase::certificates(true);
            $msg = is_wp_error($certs) ? 'certs_error' : 'certs';
        }
        wp_safe_redirect(admin_url('admin.php?page=coovery-fontes&coovery_msg=' . $msg));
        exit;
    }

    /**
     * @param array $input
     * @return array|WP_Error
     */
    private static function parse_source($input) {
        $title = sanitize_text_field(wp_unslash($input['title'] ?? ''));
        $tmdb_id = absint($input['tmdb_id'] ?? 0);
        $type = sanitize_key(wp_unslash($input['media_type'] ?? ''));
        $url = esc_url_raw(trim((string) wp_unslash($input['video_url'] ?? '')));

        if ($title === '') {
            return new WP_Error('title', 'Informe o título.');
        }
        if ($tmdb_id < 1) {
            return new WP_Error('tmdb', 'Informe o id do TMDB.');
        }
        if (!in_array($type, array('movie', 'series'), true)) {
            return new WP_Error('type', 'Escolha filme ou série.');
        }
        if ($url === '' || !wp_http_validate_url($url)) {
            return new WP_Error('url', 'Informe uma URL http ou https.');
        }

        $season = null;
        $episode = null;
        if ($type === 'series') {
            $season = absint($input['season'] ?? 0);
            $episode = absint($input['episode'] ?? 0);
            if ($season < 1 || $episode < 1 || $season > 200 || $episode > 5000) {
                return new WP_Error('episode', 'Informe a temporada e o episódio.');
            }
        }

        return array(
            'title' => $title,
            'tmdb_id' => $tmdb_id,
            'media_type' => $type,
            'season' => $season,
            'episode' => $episode,
            'video_url' => $url,
        );
    }

    private static function find($id) {
        global $wpdb;
        $table = Coovery_Fontes_Install::table_name();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    private static function short_url($url) {
        $url = (string) $url;
        if (strlen($url) <= 64) {
            return $url;
        }
        return substr($url, 0, 61) . '...';
    }
}
