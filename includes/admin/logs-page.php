<?php
if (!defined('ABSPATH')) exit;

/**
 * LÓGICA DE PROCESAMIENTO (Antes de renderizar HTML)
 */
add_action('admin_init', function() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'slt-logs') return;

    global $wpdb;
    $tabla = $wpdb->prefix . 'slt_eventos';

    // --- LÓGICA DE ELIMINACIÓN DE SESIÓN ---
    if (isset($_GET['action']) && $_GET['action'] === 'delete_session' && isset($_GET['s_id'])) {
        $session_id_to_delete = sanitize_text_field($_GET['s_id']);
        check_admin_referer('slt_delete_session_' . $session_id_to_delete);
        $wpdb->delete($tabla, array('session_id' => $session_id_to_delete));
        wp_safe_redirect(admin_url('admin.php?page=slt-logs&status=deleted'));
        exit;
    }
});

/**
 * Función principal para mostrar la lista de sesiones
 */
function slt_mostrar_logs() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'slt_eventos';

    // --- NUEVA LÓGICA AJAX: OBTENER UBICACIÓN MAXMIND ---
    if (isset($_POST['action']) && $_POST['action'] === 'slt_get_maxmind_geo') {
        check_admin_referer('slt_note_nonce');
        $ip = sanitize_text_field($_POST['ip']);
        
        $location_text = 'Ubicación no encontrada';
        
        // Verificamos si la función del plugin Geolocalization IP Detector está disponible
        if (function_exists('geoip_detect2_get_info_from_ip')) {
            $info = geoip_detect2_get_info_from_ip($ip);
            if ($info) {
                $city = $info->city->name;
                $state = $info->mostSpecificSubdivision->name;
                $country = $info->country->name;
                $location_text = "📍 Ubicación Exacta: " . implode(', ', array_filter([$city, $state, $country]));
            }
        }
        wp_send_json_success($location_text);
    }

    // --- LÓGICA DE GUARDADO AJAX (Observaciones) ---
    if (isset($_POST['action']) && $_POST['action'] === 'slt_save_note') {
        check_admin_referer('slt_note_nonce');
        $s_id = sanitize_text_field($_POST['session_id']);
        $note = sanitize_textarea_field($_POST['note']);
        $wpdb->update($tabla, array('observaciones' => $note), array('session_id' => $s_id));
        wp_send_json_success();
    }

    // Si estamos viendo el detalle de una sesión
    $session_detalle = isset($_GET['view_session']) ? sanitize_text_field($_GET['view_session']) : null;
    if ($session_detalle) {
        slt_render_detalle_sesion($session_detalle);
        return;
    }

    // Filtros de búsqueda
    $filtro_pais = isset($_GET['f_pais']) ? sanitize_text_field($_GET['f_pais']) : '';
    $where = "WHERE 1=1";
    if ($filtro_pais) $where .= $wpdb->prepare(" AND session_id LIKE %s", '%' . $filtro_pais . '%');

    // Query agrupada por sesión
    $query = "SELECT 
                session_id, 
                MAX(fecha) as ultima_actividad, 
                MIN(fecha) as inicio,
                MAX(user_name) as nombre_usuario,
                MAX(observaciones) as notas,
                COUNT(*) as movimientos,
                GROUP_CONCAT(DISTINCT url_pagina SEPARATOR ', ') as paginas_visitadas
              FROM $tabla 
              $where
              GROUP BY session_id 
              ORDER BY ultima_actividad DESC 
              LIMIT 50";

    $sesiones = $wpdb->get_results($query);
    wp_nonce_field('slt_note_nonce', 'slt_note_nonce_field');
    ?>

    <div class="wrap">
        <h1>🔍 Registro de Sesiones (Lead Tracker)</h1>
        
        <?php if (isset($_GET['status']) && $_GET['status'] === 'deleted'): ?>
            <div class="notice notice-success is-dismissible"><p>Sesión y todo su historial eliminados correctamente.</p></div>
        <?php endif; ?>

        <div class="tablenav top">
            <form method="get">
                <input type="hidden" name="page" value="slt-logs">
                <input type="text" name="f_pais" placeholder="Filtrar por País o IP..." value="<?php echo esc_attr($filtro_pais); ?>">
                <input type="submit" class="button" value="Filtrar">
                <?php if($filtro_pais): ?>
                    <a href="admin.php?page=slt-logs" class="button">Limpiar</a>
                <?php endif; ?>
            </form>
        </div>

        <table class="widefat striped" style="margin-top:20px;">
            <thead>
                <tr>
                    <th>Última Actividad</th>
                    <th>Lead / Identidad</th>
                    <th>Ubicación / IP</th>
                    <th>Eventos</th>
                    <th>Etiquetas / Notas (Autoguardado)</th>
                    <th>Opciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if($sesiones): foreach ($sesiones as $s): 
                    $partes = explode('|', $s->session_id);
                    $ubicacion = isset($partes[1]) ? trim($partes[1]) : 'Desconocida';
                    $ip = isset($partes[2]) ? trim($partes[2]) : 'N/A';
                    
                    $delete_url = wp_nonce_url(
                        admin_url('admin.php?page=slt-logs&action=delete_session&s_id=' . urlencode($s->session_id)), 
                        'slt_delete_session_' . $s->session_id
                    );
                ?>
                    <tr>
                        <td><?php echo date('d/m/Y H:i:s', strtotime($s->ultima_actividad)); ?></td>
                        <td>
                            <strong><?php echo esc_html($s->nombre_usuario ?: 'Anónimo'); ?></strong>
                        </td>
                        <td>
                            <span class="dashicons dashicons-location" style="font-size:17px;"></span> <?php echo esc_html($ubicacion); ?><br>
                            <div style="display:flex; align-items:center; gap:5px; margin-top:3px;">
                                <small style="color:#666;"><?php echo esc_html($ip); ?></small>
                                <?php if ($ip !== 'N/A'): ?>
                                <button type="button" class="slt-get-geo button button-small" 
                                        data-ip="<?php echo esc_attr($ip); ?>" 
                                        data-sid="<?php echo esc_attr($s->session_id); ?>"
                                        title="Obtener ubicación exacta con MaxMind"
                                        style="height:18px; line-height:16px; padding:0 4px; min-width:auto;">
                                    <span class="dashicons dashicons-radar" style="font-size:14px; width:14px; height:14px; margin-top:1px;"></span>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge-count" style="background:#d1e8ff; color:#00458b; padding:2px 8px; border-radius:10px; font-weight:bold; font-size:11px;">
                                <?php echo $s->movimientos; ?> actos
                            </span>
                        </td>
                        <td>
                            <input type="text" 
                                   class="slt-quick-note" 
                                   id="note-<?php echo md5($s->session_id); ?>"
                                   data-sid="<?php echo esc_attr($s->session_id); ?>" 
                                   value="<?php echo esc_attr($s->notas); ?>" 
                                   placeholder="Click para anotar..." 
                                   style="width:100%; font-size:12px; border:1px solid #ddd; background:#f9f9f9;">
                        </td>
                        <td>
                            <div style="display:flex; gap:5px;">
                                <a href="?page=slt-logs&view_session=<?php echo urlencode($s->session_id); ?>" class="button button-small" title="Ver Journey">
                                    <span class="dashicons dashicons-visibility" style="margin-top:4px;"></span>
                                </a>
                                <a href="<?php echo $delete_url; ?>" 
                                   class="button button-small" 
                                   style="color: #d63638; border-color: #d63638;" 
                                   onclick="return confirm('¿Eliminar TODO el historial de este usuario? Esta acción no se puede deshacer.');"
                                   title="Eliminar Sesión">
                                    <span class="dashicons dashicons-trash" style="margin-top:4px;"></span>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6">No hay datos para mostrar con los filtros actuales.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Guardar notas al perder foco
        $('.slt-quick-note, .slt-full-note').on('blur', function() {
            saveNote($(this));
        });

        function saveNote($el) {
            const data = {
                action: 'slt_save_note',
                session_id: $el.data('sid'),
                note: $el.val(),
                _wpnonce: $('#slt_note_nonce_field').val()
            };
            $el.css('opacity', '0.5');
            $.post(window.location.href, data, function() {
                $el.css('opacity', '1').css('border-color', '#00a32a');
                setTimeout(() => { $el.css('border-color', '#ddd'); }, 1000);
            });
        }

        // Obtener Geolocalización Exacta
        $('.slt-get-geo').on('click', function() {
            const btn = $(this);
            const ip = btn.data('ip');
            const sid = btn.data('sid');
            const noteInput = $('#note-' + btoa(sid).replace(/=/g, "").substring(0, 16)); // Selector simplificado o usar md5
            const targetInput = $('input[data-sid="' + sid + '"]');

            btn.find('.dashicons').addClass('spin-animation');
            
            $.post(window.location.href, {
                action: 'slt_get_maxmind_geo',
                ip: ip,
                _wpnonce: $('#slt_note_nonce_field').val()
            }, function(response) {
                if (response.success) {
                    let currentVal = targetInput.val();
                    let newVal = currentVal ? currentVal + " | " + response.data : response.data;
                    targetInput.val(newVal);
                    saveNote(targetInput); // Guardamos automáticamente
                }
                btn.find('.dashicons').removeClass('spin-animation');
            });
        });
    });
    </script>
    <style>
        .spin-animation { animation: slt-spin 1s infinite linear; }
        @keyframes slt-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    </style>
    <?php
}

/**
 * Función para renderizar el flujo cronológico de una sesión (Journey)
 */
function slt_render_detalle_sesion($session_id) {
    global $wpdb;
    $tabla = $wpdb->prefix . 'slt_eventos';
    
    $movimientos = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $tabla WHERE session_id = %s ORDER BY fecha ASC", $session_id
    ));

    if (!$movimientos) {
        echo '<div class="notice notice-error"><p>No se encontraron datos para esta sesión.</p></div>';
        return;
    }

    $partes = explode('|', $session_id);
    wp_nonce_field('slt_note_nonce', 'slt_note_nonce_field');
    ?>
    <div class="wrap">
        <h1><a href="?page=slt-logs" class="page-title-action" style="margin-right:15px;">← Volver</a> Detalle del Journey</h1>
        
        <div style="display: flex; gap: 20px; margin-top:20px;">
            <div class="card" style="flex: 1; padding:15px; background:#fff; border:1px solid #ccd0d4;">
                <p><strong>Identidad Detectada:</strong> <span style="color:#2271b1; font-weight:bold;"><?php echo esc_html($movimientos[0]->user_name ?: 'Anónimo'); ?></span></p>
                <p><strong>Ubicación:</strong> <?php echo esc_html($partes[1] ?? 'N/A'); ?> | <strong>IP:</strong> <?php echo esc_html($partes[2] ?? 'N/A'); ?></p>
            </div>
            
            <div class="card" style="flex: 1; padding:15px; border-left: 4px solid #ffb900; background:#fff; border-top:1px solid #ccd0d4; border-right:1px solid #ccd0d4; border-bottom:1px solid #ccd0d4;">
                <strong>📝 Notas del Lead:</strong><br>
                <textarea class="slt-full-note" 
                          data-sid="<?php echo esc_attr($session_id); ?>" 
                          style="width:100%; margin-top:10px; height:60px; font-size:13px;" 
                          placeholder="Escribe aquí el seguimiento de este lead..."><?php echo esc_textarea($movimientos[0]->observaciones); ?></textarea>
            </div>
        </div>

        <h2 style="margin-top:30px;">Línea de Tiempo</h2>
        
        <div class="slt-timeline" style="border-left: 3px solid #2271b1; padding-left: 20px; margin-left: 10px; margin-top:20px;">
            <?php foreach($movimientos as $m): 
                if (strpos($m->elemento_id, 'Copiado') !== false) {
                    $color = '#2271b1'; 
                } elseif (strpos($m->elemento_id, 'Pipedrive: Formulario Completado') !== false) {
                    $color = '#d4af37';
                } elseif (strpos($m->elemento_id, 'Click') !== false || strpos($m->elemento_id, 'Pipedrive') !== false) {
                    $color = '#00a32a'; 
                } elseif (strpos($m->elemento_id, 'Salida') !== false) {
                    $color = '#d63638'; 
                } else {
                    $color = '#646970'; 
                }
            ?>
                <div class="slt-event" style="margin-bottom:20px; position:relative;">
                    <div style="position:absolute; left:-27px; top:5px; background:#fff; border:2px solid <?php echo $color; ?>; border-radius:50%; width:10px; height:10px;"></div>
                    <small style="color:#666;"><?php echo date('H:i:s', strtotime($m->fecha)); ?></small><br>
                    <strong style="color:<?php echo $color; ?>; font-size:14px;"><?php echo esc_html($m->elemento_id); ?></strong>
                    <p style="margin:2px 0; font-size:12px; color:#50575e;">Página: <em><?php echo esc_html($m->url_pagina); ?></em></p>
                    
                    <?php if($m->user_name && strpos($m->elemento_id, 'Pipedrive') !== false): ?>
                        <div style="background: #fff9e6; border-left: 3px solid #d4af37; padding: 5px 10px; margin-top: 5px; font-size: 12px;">
                            <strong>Dato capturado:</strong> <?php echo esc_html($m->user_name); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('.slt-full-note').on('blur', function() {
            const $el = $(this);
            const data = {
                action: 'slt_save_note',
                session_id: $el.data('sid'),
                note: $el.val(),
                _wpnonce: $('#slt_note_nonce_field').val()
            };
            $el.css('background', '#fff9e6');
            $.post(window.location.href, data, function() {
                $el.css('background', '#fff');
            });
        });
    });
    </script>
    <?php
}