<?php
if (!defined('ABSPATH')) exit;

/**
 * LÓGICA DE PROCESAMIENTO GLOBAL (admin_init)
 * Mantenemos la estructura de hooks de WordPress para asegurar estabilidad
 */
add_action('admin_init', function() {
    // Verificamos que estemos en la página correcta o sea una petición de nuestro plugin
    if (!isset($_GET['page']) || $_GET['page'] !== 'slt-logs') {
        if (!isset($_POST['action']) || $_POST['action'] !== 'slt_get_maxmind_geo') return;
    }

    global $wpdb;
    $tabla = $wpdb->prefix . 'slt_eventos';

    // 1. ELIMINAR SESIÓN (Mantenido igual al original)
    if (isset($_GET['action']) && $_GET['action'] === 'delete_session' && isset($_GET['s_id'])) {
        $session_id_to_delete = sanitize_text_field($_GET['s_id']);
        check_admin_referer('slt_delete_session_' . $session_id_to_delete);
        $wpdb->delete($tabla, array('session_id' => $session_id_to_delete));
        wp_safe_redirect(admin_url('admin.php?page=slt-logs&status=deleted'));
        exit;
    }

    // 2. OBTENER UBICACIÓN AJAX (Con limpieza de prefijo "IP: ")
    if (isset($_POST['action']) && $_POST['action'] === 'slt_get_maxmind_geo') {
        check_admin_referer('slt_note_nonce');
        
        // Limpiamos la variable para que las APIs no den error
        $raw_ip = sanitize_text_field($_POST['ip']);
        $ip = trim(str_replace('IP:', '', $raw_ip)); 
        
        $location_text = '';

        // Intento 1: Plugin MaxMind/GeoIP Detect
        if (function_exists('geoip_detect2_get_info_from_ip')) {
            $info = geoip_detect2_get_info_from_ip($ip);
            if ($info && isset($info->country->name)) {
                $location_text = "[GeoIP] " . implode(', ', array_filter([$info->city->name, $info->country->name]));
            }
        }

        // Intento 2: API Externa Gratuita (Fallback)
        if (empty($location_text)) {
            $response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=status,country,city");
            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response));
                if ($body && $body->status === 'success') {
                    $location_text = "[IP-API] " . $body->city . ", " . $body->country;
                }
            }
        }

        wp_send_json_success($location_text ?: "Ubicación no encontrada");
        exit;
    }

    // 3. GUARDAR NOTAS (Actualiza todas las filas de la sesión para mantener integridad)
    if (isset($_POST['action']) && $_POST['action'] === 'slt_save_note') {
        check_admin_referer('slt_note_nonce');
        $s_id = sanitize_text_field($_POST['session_id']);
        $note = sanitize_textarea_field($_POST['note']);
        
        // Actualizamos todas las entradas de esa sesión para que la nota sea global
        $wpdb->update($tabla, array('observaciones' => $note), array('session_id' => $s_id));
        wp_send_json_success();
        exit;
    }
});

/**
 * Función principal de visualización
 */
function slt_mostrar_logs() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'slt_eventos';

    // Ver detalle (Journey)
    if (isset($_GET['view_session'])) {
        slt_render_detalle_sesion(sanitize_text_field($_GET['view_session']));
        return;
    }

    $filtro = isset($_GET['f_pais']) ? sanitize_text_field($_GET['f_pais']) : '';
    $where = $filtro ? $wpdb->prepare("WHERE session_id LIKE %s", '%' . $filtro . '%') : "WHERE 1=1";

    // Query agrupada (Respetando tu estructura original)
    $query = "SELECT session_id, MAX(fecha) as ultima_actividad, MAX(user_name) as nombre_usuario, MAX(observaciones) as notas, COUNT(*) as movimientos 
              FROM $tabla $where GROUP BY session_id ORDER BY ultima_actividad DESC LIMIT 50";
    $sesiones = $wpdb->get_results($query);

    wp_nonce_field('slt_note_nonce', 'slt_note_nonce_field');
    ?>

    <div class="wrap">
        <h1>🔍 Registro de Sesiones (Simple Lead Tracker)</h1>

        <div class="tablenav top">
            <form method="get">
                <input type="hidden" name="page" value="slt-logs">
                <input type="text" name="f_pais" placeholder="Filtrar IP o ID..." value="<?php echo esc_attr($filtro); ?>">
                <input type="submit" class="button" value="Filtrar">
            </form>
        </div>

        <table class="widefat striped" style="margin-top:20px;">
            <thead>
                <tr>
                    <th>Última Actividad</th>
                    <th>Identidad</th>
                    <th>Ubicación / IP</th>
                    <th>Eventos</th>
                    <th>Notas de Seguimiento</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if($sesiones): foreach ($sesiones as $s): 
                    $partes = explode('|', $s->session_id);
                    $ip_display = isset($partes[2]) ? trim($partes[2]) : 'N/A';
                ?>
                <tr>
                    <td><?php echo $s->ultima_actividad; ?></td>
                    <td><strong><?php echo esc_html($s->nombre_usuario ?: 'Anónimo'); ?></strong></td>
                    <td>
                        <?php echo esc_html($partes[1] ?? 'N/A'); ?><br>
                        <small style="color:#888;"><?php echo esc_html($ip_display); ?></small>
                    </td>
                    <td><span class="badge-count" style="background:#eee; padding:2px 5px; border-radius:5px;"><?php echo $s->movimientos; ?> actos</span></td>
                    <td>
                        <input type="text" class="slt-quick-note" data-sid="<?php echo esc_attr($s->session_id); ?>" value="<?php echo esc_attr($s->notas); ?>" placeholder="..." style="width:100%;">
                    </td>
                    <td>
                        <div style="display:flex; gap:4px;">
                            <button type="button" class="slt-get-geo button button-small" data-ip="<?php echo esc_attr($ip_display); ?>" data-sid="<?php echo esc_attr($s->session_id); ?>" title="Localizar IP">
                                <span class="dashicons dashicons-admin-site"></span>
                            </button>
                            <a href="?page=slt-logs&view_session=<?php echo urlencode($s->session_id); ?>" class="button button-small"><span class="dashicons dashicons-visibility"></span></a>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=slt-logs&action=delete_session&s_id=' . urlencode($s->session_id)), 'slt_delete_session_' . $s->session_id); ?>" class="button button-small" style="color:red;" onclick="return confirm('¿Borrar?');"><span class="dashicons dashicons-trash"></span></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6">Sin resultados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
    jQuery(document).ready(function($) {
        const nonce = $('#slt_note_nonce_field').val();

        // Función unificada para guardar
        function remoteSave($el) {
            $el.css('background', '#fff9e6');
            $.post(window.location.href, {
                action: 'slt_save_note',
                session_id: $el.data('sid'),
                note: $el.val(),
                _wpnonce: nonce
            }, function() {
                $el.css('background', '#fff').css('border-color', '#00a32a');
            });
        }

        $(document).on('blur', '.slt-quick-note', function() {
            remoteSave($(this));
        });

        // Lógica del botón de localización
        $('.slt-get-geo').on('click', function(e) {
            e.preventDefault();
            const btn = $(this);
            const target = $('input[data-sid="' + btn.data('sid') + '"]');
            
            btn.find('.dashicons').addClass('spin-animation');
            
            $.post(window.location.href, {
                action: 'slt_get_maxmind_geo',
                ip: btn.data('ip'),
                _wpnonce: nonce
            }, function(res) {
                if(res.success) {
                    const current = target.val();
                    const updated = (current ? current + " | " : "") + res.data;
                    target.val(updated);
                    remoteSave(target);
                }
                btn.find('.dashicons').removeClass('spin-animation');
            });
        });
    });
    </script>
    <style>.spin-animation { animation: slt-spin 1s infinite linear; display:inline-block; } @keyframes slt-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>
    <?php
}

/**
 * Journey Detalle (Respetando el diseño estable)
 */
function slt_render_detalle_sesion($sid) {
    global $wpdb;
    $tabla = $wpdb->prefix . 'slt_eventos';
    $movimientos = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tabla WHERE session_id = %s ORDER BY fecha ASC", $sid));
    if (!$movimientos) return;
    ?>
    <div class="wrap">
        <h1><a href="?page=slt-logs" class="button">Volver</a> Detalle de Sesión</h1>
        <div class="card" style="margin-top:20px; padding:20px; background:#fff; border:1px solid #ccd0d4;">
            <strong>Notas del Lead:</strong><br>
            <textarea class="slt-full-note" data-sid="<?php echo esc_attr($sid); ?>" style="width:100%; height:80px; margin-top:10px;"><?php echo esc_textarea($movimientos[0]->observaciones); ?></textarea>
        </div>
        <div style="margin-top:20px; border-left: 2px solid #2271b1; padding-left:15px;">
            <?php foreach($movimientos as $m): ?>
                <div style="margin-bottom:15px;">
                    <small><?php echo $m->fecha; ?></small><br>
                    <strong><?php echo esc_html($m->elemento_id); ?></strong><br>
                    <span style="font-size:11px; color:#666;"><?php echo esc_html($m->url_pagina); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
    jQuery(document).ready(function($) {
        $('.slt-full-note').on('blur', function() {
            $.post(window.location.href, {
                action: 'slt_save_note',
                session_id: $(this).data('sid'),
                note: $(this).val(),
                _wpnonce: $('#slt_note_nonce_field').val()
            });
        });
    });
    </script>
    <?php
}