<?php
/*
Plugin Name: Simple Lead Tracker PRO
Description: Rastreo avanzado con Dashboard Estadístico para Marketing y Administración.
Version: 26.186
Author: LDG Felipe de Jesús Carrera Rendón
*/

if (!defined('ABSPATH')) exit;

define('SLT_PATH', plugin_dir_path(__FILE__));
define('SLT_URL', plugin_dir_url(__FILE__));
define('SLT_VERSION', '26.186'); 

$files = [
    SLT_PATH . 'includes/database.php',
    SLT_PATH . 'includes/rest-api.php',
    SLT_PATH . 'includes/admin-ui.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

register_activation_hook(__FILE__, 'slt_run_activation');
function slt_run_activation() {
    if (function_exists('slt_crear_tabla')) {
        slt_crear_tabla();
    }
}

add_action('admin_init', function() {
    register_setting('slt_settings_group', 'slt_master_switch');
    register_setting('slt_settings_group', 'slt_track_logged');
    register_setting('slt_settings_group', 'slt_show_admin_bar');
    register_setting('slt_settings_group', 'slt_hierarchical_tracking');
    register_setting('slt_settings_group', 'slt_strict_mode');
    register_setting('slt_settings_group', 'slt_db_limit'); // NUEVO: Límite de registros
    register_setting('slt_settings_group', 'slt_clickup_token');
    register_setting('slt_settings_group', 'slt_clickup_list');
    register_setting('slt_settings_group', 'slt_blocked_ips');
    
});


//// envia datos de prueba para clickup

add_action('wp_ajax_slt_test_clickup_conn', function() {
    $token = get_option('slt_clickup_token');
    $list_id = get_option('slt_clickup_list');
    $test_msg = isset($_POST['test_message']) ? sanitize_text_field($_POST['test_message']) : 'Prueba vacía';

    if (!$token || !$list_id) {
        wp_send_json_error('Configuración incompleta (Token o List ID).');
    }

    $url = "https://api.clickup.com/api/v2/list/{$list_id}/task";

    $body = json_encode([
        'name' => $test_msg, // El texto que escribiste en el input
        'description' => 'Enviado manualmente desde el panel de Simple Lead Tracker.',
        'status' => 'to do'
    ]);

    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => $token,
            'Content-Type'  => 'application/json'
        ],
        'body' => $body
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code === 200 || $code === 201) {
        wp_send_json_success();
    } else {
        $error_body = wp_remote_retrieve_body($response);
        wp_send_json_error("Error $code: $error_body");
    }
});

// BARRA SUPERIOR CON CONTADOR Y COLORES
add_action('admin_bar_menu', function($admin_bar) {
    if (get_option('slt_show_admin_bar', '1') === '0') return;

    global $wpdb;
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}slt_eventos");
    $limit = (int)get_option('slt_db_limit', 5000);
    $percent = ($count / $limit) * 100;

    $color = '#00a32a'; // Verde
    if ($percent > 90) $color = '#d63638'; // Rojo
    elseif ($percent > 70) $color = '#ffb900'; // Naranja

    $admin_bar->add_node([
        'id'    => 'slt-status',
        'title' => '<span style="color:'.$color.';">●</span> SLT: ' . number_format($count) . ' / ' . number_format($limit),
        'href'  => admin_url('admin.php?page=slt-settings'),
        'meta'  => ['title' => 'Estado de la base de datos SLT']
    ]);
}, 100);

add_action('wp_enqueue_scripts', function() {
    if (get_option('slt_master_switch', '1') === '0') return;
    if (get_option('slt_track_logged', '1') === '0' && is_user_logged_in()) return;

    $tracking_data = get_option('slt_hierarchical_tracking', []);
    if (is_string($tracking_data)) $tracking_data = json_decode($tracking_data, true);
    $strict_mode = get_option('slt_strict_mode', '0');
    $current_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    if ($current_path === '') $current_path = 'home_root';

    if ($strict_mode === '1') {
        $should_load = false;
        if (!empty($tracking_data)) {
            foreach ($tracking_data as $page) {
                if ($page['url'] === '*') { $should_load = true; break; }
                $target_path = trim(parse_url($page['url'], PHP_URL_PATH), '/');
                if ($target_path === '') $target_path = 'home_root';
                if ($current_path === $target_path) { $should_load = true; break; }
            }
        }
        if (!$should_load) return;
    }

    $php_referer = wp_get_referer();
    $origin_info = "Directo";
    if ($php_referer) {
        $ref_host = parse_url($php_referer, PHP_URL_HOST);
        if ($ref_host === $_SERVER['HTTP_HOST']) {
            $origin_info = "Desde: " . parse_url($php_referer, PHP_URL_PATH);
        } else {
            $origin_info = "Origen: " . $ref_host;
        }
    }

    wp_enqueue_script('slt-tracker', SLT_URL . 'assets/tracker.js', [], SLT_VERSION, true);
    wp_localize_script('slt-tracker', 'slt_vars', [
        'rest_url' => esc_url_raw(rest_url('slt/v1/push/')),
        'tracking_rules' => $tracking_data,
        'strict_mode' => $strict_mode,
        'current_clean_path' => $current_path,
        'php_origin' => $origin_info
    ]);
});

add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'slt-settings') !== false) {
        wp_enqueue_script('slt-admin-settings', SLT_URL . 'assets/admin-settings.js', ['jquery'], SLT_VERSION, true);
    }
});

// En tu archivo simple-lead-tracker.php
add_action('wp_head', function() {
    ?>
    <script>
    (function() {
        // 1. Capturar Referer Externo Original
        var extRef = document.referrer;
        if (extRef && !extRef.includes(window.location.hostname)) {
            if (!sessionStorage.getItem('slt_original_ref')) {
                sessionStorage.setItem('slt_original_ref', extRef);
            }
        }
        // 2. Persistir ruta actual para el siguiente salto
        var currentPath = window.location.pathname + window.location.search;
        var lastStored = sessionStorage.getItem('slt_current_page');
        if (lastStored && lastStored !== currentPath) {
            sessionStorage.setItem('slt_last_page', lastStored);
        }
        sessionStorage.setItem('slt_current_page', currentPath);
    })();
    </script>
    <?php
}, 1); // Prioridad 1 para que sea lo primero en ejecutarse