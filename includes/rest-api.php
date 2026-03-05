<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
    register_rest_route('slt/v1', '/push/', [
        'methods' => 'POST',
        'callback' => 'slt_rest_handler',
        'permission_callback' => '__return_true'
    ]);
});

if (!function_exists('slt_rest_handler')) {
    function slt_rest_handler($request) {
        if (get_option('slt_master_switch', '1') === '0') return new WP_REST_Response(['status' => 'tracker_off'], 200);

        // --- 1. BLOQUEO POR IP (CON VERIFICACIÓN DE EXISTENCIA) ---
        // Solo bloqueamos si la función existe Y si la opción de IPs no está vacía
        if (function_exists('slt_is_ip_blocked')) {
            $lista_negra = get_option('slt_blocked_ips', '');
            if (!empty($lista_negra) && slt_is_ip_blocked()) {
                return new WP_REST_Response(['status' => 'ip_blocked'], 403);
            }
        }


        // --- VALIDACIÓN DE MODO ESTRICTO EN SERVIDOR ---
        $tracking_data = get_option('slt_hierarchical_tracking', []);
        if (is_string($tracking_data)) $tracking_data = json_decode($tracking_data, true);

        if (get_option('slt_strict_mode', '0') === '1') {
            $incoming_url = isset($request->get_params()['url']) ? trim($request->get_params()['url'], '/') : '';
            if ($incoming_url === '') $incoming_url = 'home_root';
            
            $allowed = false;
            foreach ($tracking_data as $page) {
                $target = trim(parse_url($page['url'], PHP_URL_PATH), '/');
                if ($target === '') $target = 'home_root';
                if ($page['url'] === '*' || $incoming_url === $target) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) return new WP_REST_Response(['status' => 'blocked_by_strict_mode'], 200);
        }

        $referer = $request->get_header('referer');
        if (!$referer || strpos($referer, str_replace(['http://', 'https://'], '', home_url())) === false) {
            return new WP_REST_Response(['status' => 'unauthorized_origin'], 403);
        }

        if (get_option('slt_track_logged', '1') === '0' && wp_validate_auth_cookie() !== false) {
            return new WP_REST_Response(['status' => 'ignored'], 200);
        }

        global $wpdb;
        $tabla = $wpdb->prefix . 'slt_eventos';
        $params = $request->get_params();

        $elemento = isset($params['e']) ? sanitize_text_field($params['e']) : '';
        if (stripos($elemento, 'Scroll') !== false) {
            return new WP_REST_Response(['status' => 'blocked_scroll'], 200);
        }

        $ip = $_SERVER['REMOTE_ADDR'];
        $geo_response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=status,country");
        $country = "Unknown";
        if (!is_wp_error($geo_response)) {
            $geo_data = json_decode(wp_remote_retrieve_body($geo_response));
            if ($geo_data && $geo_data->status === 'success') { $country = $geo_data->country; }
        }

        $session = (isset($params['s']) ? sanitize_text_field($params['s']) : 'anon') . " | " . $country . " | IP: " . $ip;
        
        if (isset($params['ref']) && $elemento === 'Entrada') {
            $ref = sanitize_text_field($params['ref']);
            $elemento_con_ref = $elemento . " [Origen: $ref]";
        } else {
            $elemento_con_ref = $elemento;
        }

        // --- LÓGICA DE DISPARO CLICKUP ---
        if ($elemento === 'Entrada' && !empty($tracking_data)) {
            $current_url = isset($params['url']) ? trim($params['url'], '/') : '';
            if ($current_url === '') $current_url = 'home_root';

            foreach ($tracking_data as $page) {
                $target_rule = trim(parse_url($page['url'], PHP_URL_PATH), '/');
                if ($target_rule === '') $target_rule = 'home_root';

                if (($page['url'] === '*' || $current_url === $target_rule) && !empty($page['send_clickup'])) {
                    slt_create_clickup_task($params, $country, $ip);
                    break;
                }
            }
        }

        if (!empty($params['u'])) {
            $wpdb->update($tabla, ['user_name' => sanitize_text_field($params['u'])], ['session_id' => $session]);
        } else {
            $existing_name = $wpdb->get_var($wpdb->prepare("SELECT user_name FROM $tabla WHERE session_id = %s AND user_name IS NOT NULL LIMIT 1", $session));
            $wpdb->insert($tabla, [
                'url_pagina'  => isset($params['url']) ? sanitize_text_field($params['url']) : '',
                'elemento_id' => $elemento_con_ref,
                'session_id'  => $session,
                'user_name'   => $existing_name
            ]);
        }
        return new WP_REST_Response(['status' => 'ok'], 200);
    }
}


/**
 * Función para enviar la tarea a ClickUp
 */function slt_create_clickup_task($params, $country, $ip) {
    global $wpdb;
    $tabla = $wpdb->prefix . 'slt_eventos';

    $token = get_option('slt_clickup_token');
    $list_id = get_option('slt_clickup_list');

    // RECONSTRUCCIÓN IDÉNTICA AL LOG:
    // El log usa el ID completo. Aquí lo reconstruimos igual a como se guarda en la DB:
    $s_id_raiz = isset($params['s']) ? sanitize_text_field($params['s']) : 'anon';
    $session_id_completo = $s_id_raiz . " | " . $country . " | IP: " . $ip;

    if (empty($token) || empty($list_id)) return;

    // CONSULTA IGUAL A log-page.php (Función slt_render_detalle_sesion)
    $movimientos = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $tabla WHERE session_id = %s ORDER BY fecha ASC", 
        $session_id_completo
    ));

    $historial_texto = "";
    $user_ident = "Anónimo";

    if ($movimientos) {
        foreach ($movimientos as $index => $m) {
            $hora = date('H:i:s', strtotime($m->fecha));
            $historial_texto .= ($index + 1) . ". [$hora] {$m->elemento_id} -> {$m->url_pagina}\n";
            
            // Si algún registro tiene nombre, lo capturamos
            if (!empty($m->user_name)) $user_ident = $m->user_name;
        }
    } else {
        // Si no hay movimientos, enviamos el error detallado para saber qué falló
        $historial_texto = "No se encontraron movimientos para: " . $session_id_completo;
    }

    // ENVÍO A CLICKUP
    $body = [
        'name' => "LEAD: " . $user_ident . " (Desde " . ($params['url'] ?? 'Web') . ")",
        'description' => "👤 Identidad: " . $user_ident . "\n" .
                         "🌎 Ubicación: " . $country . " (IP: " . $ip . ")\n\n" .
                         "📜 HISTORIAL (Basado en Log):\n" .
                         "------------------------------------------\n" .
                         $historial_texto . 
                         "\n------------------------------------------",
        'status' => 'to do',
        'priority' => 3
    ];

    wp_remote_post("https://api.clickup.com/api/v2/list/{$list_id}/task", [
        'headers' => [
            'Authorization' => $token,
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode($body),
        'blocking' => false,
    ]);
}