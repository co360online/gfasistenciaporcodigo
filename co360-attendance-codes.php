<?php
/**
 * Plugin Name: CO360 Asistencia por Código (MVP)
 * Description: Valida códigos de asistencia en Gravity Forms y registra su uso por proyecto.
 * Version: 0.1.0
 * Author: CO360
 * Text Domain: co360-attendance-codes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CO360_ATTENDANCE_VERSION', '0.1.0' );
define( 'CO360_ATTENDANCE_PATH', plugin_dir_path( __FILE__ ) );
define( 'CO360_ATTENDANCE_URL', plugin_dir_url( __FILE__ ) );

require_once CO360_ATTENDANCE_PATH . 'includes/helpers.php';
require_once CO360_ATTENDANCE_PATH . 'includes/class-db.php';
require_once CO360_ATTENDANCE_PATH . 'includes/class-csv.php';
require_once CO360_ATTENDANCE_PATH . 'includes/class-admin.php';
require_once CO360_ATTENDANCE_PATH . 'includes/class-gf-hooks.php';

register_activation_hook( __FILE__, array( 'CO360_DB', 'activate' ) );

add_action(
    'plugins_loaded',
    function () {
        if ( is_admin() ) {
            new CO360_Admin();
        }
        new CO360_GF_Hooks();
    }
);
