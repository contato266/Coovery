<?php
/**
 * Plugin Name: Coovery Fontes
 * Description: Cadastra a URL de cada filme e episódio e entrega essa URL só para quem entrou com uma conta do Firebase.
 * Version: 1.1.0
 * Author: Coovery
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: coovery-fontes
 */

if (!defined('ABSPATH')) {
    exit;
}

define('COOVERY_FONTES_VERSION', '1.1.0');
define('COOVERY_FONTES_FILE', __FILE__);
define('COOVERY_FONTES_DIR', plugin_dir_path(__FILE__));

require_once COOVERY_FONTES_DIR . 'includes/class-install.php';
require_once COOVERY_FONTES_DIR . 'includes/class-firebase.php';
require_once COOVERY_FONTES_DIR . 'includes/class-admin.php';
require_once COOVERY_FONTES_DIR . 'includes/class-rest.php';

register_activation_hook(COOVERY_FONTES_FILE, array('Coovery_Fontes_Install', 'activate'));

add_action('plugins_loaded', static function () {
    Coovery_Fontes_Admin::init();
    Coovery_Fontes_Rest::init();
});
