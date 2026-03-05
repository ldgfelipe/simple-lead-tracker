<?php
if (!defined('ABSPATH')) exit;

function slt_mostrar_settings() {
    $tracking_val = get_option('slt_hierarchical_tracking', '[]');
    if (is_array($tracking_val)) $tracking_val = json_encode($tracking_val);
    global $wpdb;
    $tabla = $wpdb->prefix . 'slt_eventos';
    
    // Cálculos de salud de la DB
    $db_info = $wpdb->get_row("SELECT ROUND((data_length + index_length) / 1024 / 1024, 2) AS size FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name = '$tabla'");
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $tabla");
    $limit = (int)get_option('slt_db_limit', 5000);
    $percent = min(($count / $limit) * 100, 100);
    
    $color = '#00a32a'; 
    if ($percent > 90) $color = '#d63638';
    elseif ($percent > 70) $color = '#ffb900';

    $strict_mode_val = get_option('slt_strict_mode', '0');

    // Recuperamos la opción existente slt_track_logged
    $track_logged_val = get_option('slt_track_logged', '1');
    ?>
<style>
.slt-page-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.slt-page-header {
    display: flex;
    gap: 10px;
    align-items: center;
    margin-bottom: 5px;
    background: #f0f0f1;
    padding: 10px;
    border-radius: 3px;
    border-left: 4px solid #2271b1;
}

.slt-rules-list {
    margin-left: 20px;
    border-left: 2px dashed #ccd0d4;
    padding-left: 20px;
}

.slt-rule-item {
    display: flex;
    gap: 8px;
    margin-bottom: 10px;
    align-items: center;
    background: #fafafa;
    padding: 8px;
    border-radius: 3px;
    border: 1px solid #e5e5e5;
}

.slt-rule-item input {
    flex: 1;
}

.slt-delete {
    color: #d63638;
    cursor: pointer;
    font-size: 12px;
    text-decoration: underline;
}

.slt-delete:hover {
    color: #b32d2e;
}

.slt-example-box {
    background: #f0f6fb;
    border-left: 4px solid #72aee6;
    padding: 12px;
    margin-bottom: 20px;
}

.slt-master-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.slt-health-bar {
    background: #eee;
    border-radius: 10px;
    height: 12px;
    width: 100%;
    margin: 10px 0;
    overflow: hidden;
}

.slt-health-fill {
    height: 100%;
    transition: width 0.5s ease;
}

.slt-clickup-toggle {
    background: #7b68ee;
    color: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.slt-clickup-toggle.inactive {
    background: #ccc;
    color: #666;
}

.slt-clickup-toggle:hover {
    opacity: 0.9;
    transform: scale(1.02);
}

.slt-hint {
    font-size: 11px;
    color: #666;
    font-style: italic;
    margin-top: 5px;
}

.slt-page-controls {
    margin-left: 10px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.slt-clickup-check-label {
    font-size: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    color: #7b68ee;
}

.slt-clickup-check-label input {
    margin: 0;
    cursor: pointer;
}
</style>

<div class="wrap">
    <h1>⚙️ Configuración del Tracker</h1>

    <?php if ($percent >= 100): ?>
    <div class="notice notice-error">
        <p>⚠️ <strong>¡Atención!</strong> Has alcanzado el límite de registros configurado. Vacía la base de datos para
            seguir rastreando.</p>
    </div>
    <?php endif; ?>

    <div class="slt-master-card">
        <h3>📊 Estado del Sistema y Base de Datos</h3>
        <p>Registros actuales: <strong><?php echo number_format($count); ?></strong> de
            <strong><?php echo number_format($limit); ?></strong> permitidos.
        </p>
        <div class="slt-health-bar">
            <div class="slt-health-fill"
                style="width:<?php echo (float)$percent; ?>%; background:<?php echo esc_attr($color); ?>;"></div>
        </div>
        <p>Peso total: <strong><?php echo $db_info ? $db_info->size : 0; ?> MB</strong></p>

        <div style="display:flex; gap:10px; margin-top:15px;">
            <form method="post" action="">
                <?php wp_nonce_field('slt_export_action', 'slt_nonce'); ?>
                <input type="submit" name="slt_export_clear" class="button button-primary"
                    value="📥 Exportar Excel y Limpiar DB">
            </form>
            <form method="post" action="" onsubmit="return confirm('¿Estás seguro de vaciar todos los registros?');">
                <?php wp_nonce_field('slt_clear_action', 'slt_nonce'); ?>
                <input type="submit" name="slt_clear_db" class="button button-secondary" value="🗑️ Vaciar DB">
            </form>
        </div>
    </div>

    <form method="post" action="options.php">
        <?php 
                settings_fields('slt_settings_group'); 
                do_settings_sections('slt_settings_group');
            ?>

        <div class="slt-master-card">
            <table class="form-table">
                <tr>
                    <th>Límite de Registros</th>
                    <td>
                        <input type="number" name="slt_db_limit"
                            value="<?php echo esc_attr(get_option('slt_db_limit', 5000)); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th>Interuptor General</th>
                    <td>
                        <select name="slt_master_switch">
                            <option value="1" <?php selected(get_option('slt_master_switch','1'),'1');?>>✅ Rastreo
                                Activado</option>
                            <option value="0" <?php selected(get_option('slt_master_switch','1'),'0');?>>❌ Rastreo
                                Pausado</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Trackear Usuarios Logueados</th>
                    <td>
                        <select name="slt_track_logged">
                            <option value="1" <?php selected($track_logged_val, '1'); ?>>✅ Sí, rastrear a todos</option>
                            <option value="0" <?php selected($track_logged_val, '0'); ?>>❌ No, solo visitantes</option>
                        </select>
                        <p class="description">Si se desactiva, no se guardarán datos de usuarios que hayan iniciado sesión (Administradores, Editores, etc).</p>
                    </td>
                </tr>
                <tr>
                    <th>Modo de Aplicación</th>
                    <td>
                        <select name="slt_strict_mode">
                            <option value="0" <?php selected($strict_mode_val, '0'); ?>>Global (Todo el sitio)</option>
                            <option value="1" <?php selected($strict_mode_val, '1'); ?>>Estricto (Solo páginas listadas
                                abajo)</option>
                        </select>
                    </td>
                    <tr>
    <th>🚫 Bloquear IPs</th>
    <td>
        <textarea name="slt_blocked_ips" rows="3" class="large-text" placeholder="Una IP por línea (ej: 192.168.1.1)"><?php echo esc_textarea(get_option('slt_blocked_ips')); ?></textarea>
        <p class="description">Las IPs listadas aquí no generarán registros en la base de datos ni se enviarán a ClickUp.</p>
    </td>
</tr>
                </tr>
            </table>
        </div>

        <div class="slt-master-card" style="border-left: 5px solid #7b68ee;">
            <h3>🚀 Integración con ClickUp</h3>
            <p class="description">Habilite el envío a ClickUp individualmente en cada regla de página abajo.</p>
            <table class="form-table">
                <tr>
                    <th>API Token</th>
                    <td><input type="password" name="slt_clickup_token"
                            value="<?php echo esc_attr(get_option('slt_clickup_token')); ?>" class="regular-text"
                            placeholder="pk_..."></td>
                </tr>
                <tr>
                    <th>List ID</th>
                    <td><input type="text" name="slt_clickup_list"
                            value="<?php echo esc_attr(get_option('slt_clickup_list')); ?>" class="regular-text"
                            placeholder="901500..."></td>
                </tr>
                <tr>
                    <th>Enviar Prueba</th>
                    <td>
                        <div style="display:flex; gap:10px; align-items:center;">
                            <input type="text" id="slt-test-msg" class="regular-text"
                                placeholder="Escribe un mensaje de prueba...">
                            <button type="button" id="slt-test-clickup" class="button button-secondary">🚀 Enviar a
                                ClickUp</button>
                        </div>
                        <span id="slt-test-result"
                            style="display:block; margin-top:5px; font-weight:bold; font-size:12px;"></span>
                    </td>
                </tr>
            </table>
        </div>

        <h2>🗺️ Jerarquía de Páginas y Reglas de Envío</h2>
        <button type="button" class="button button-large" id="add-page-btn" style="margin-bottom:20px;">+ Añadir Nueva
            Página</button>
        <div id="slt-pages-container"></div>

        <textarea name="slt_hierarchical_tracking" id="slt_hierarchical_tracking_input"
            style="display:none;"><?php echo esc_attr($tracking_val); ?></textarea>
        <?php submit_button('💾 Guardar Toda la Configuración', 'primary', 'submit', true, ['style'=>'width:100%;']); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    let pages = [];
    try {
        pages = JSON.parse($('#slt_hierarchical_tracking_input').val() || '[]');
    } catch (e) {
        console.error("Error parseando JSON:", e);
        pages = [];
    }

    function renderPages() {
        const container = $('#slt-pages-container').empty();

        if (pages.length === 0) {
            container.append(
                '<p id="slt-empty-msg" style="padding:20px; background:#f9f9f9; border:1px dashed #ccc; text-align:center;">No hay reglas configuradas. Añada una página para comenzar.</p>'
            );
            return;
        }

        pages.forEach((page, pIdx) => {
            const clickupActive = (page.send_clickup === true) ? 'checked' : '';

            let rulesListHtml = '';
            if (page.rules && page.rules.length > 0) {
                page.rules.forEach((rule, rIdx) => {
                    rulesListHtml += `
                            <div class="slt-rule-item">
                                <input type="text" value="${rule.selector || ''}" placeholder="Selector CSS" onchange="updateRule('${pIdx}', '${rIdx}', 'selector', this.value)">
                                <input type="text" value="${rule.name || ''}" placeholder="Nombre" onchange="updateRule('${pIdx}', '${rIdx}', 'name', this.value)">
                                <span class="slt-delete" onclick="removeRule('${pIdx}', '${rIdx}')">×</span>
                            </div>`;
                });
            }

            const boxHtml = `
                    <div class="slt-page-box">
                        <div class="slt-page-header">
                            <strong>URL:</strong>
                            <input type="text" value="${page.url || ''}" class="slt-page-url" data-idx="${pIdx}" placeholder="/ejemplo o *">
                            <span class="slt-delete slt-delete-page" data-idx="${pIdx}">Eliminar Página</span>
                        </div>
                        <div class="slt-page-controls">
                            <label class="slt-clickup-check-label">
                                <input type="checkbox" class="slt-clickup-checkbox" data-idx="${pIdx}" ${clickupActive}>
                                <span class="dashicons dashicons-cloud" style="font-size:16px; width:16px; height:16px; margin-top: -2px;"></span>
                                Enviar esta página a ClickUp
                            </label>
                            <div class="slt-hint" style="margin-top:0;">
                                ${page.url === '*' ? '(Aplica a todo el sitio)' : ''}
                            </div>
                        </div>
                        <div class="slt-rules-list">
                            ${rulesListHtml}
                            <button type="button" class="button button-small" onclick="addRule('${pIdx}')">+ Añadir Regla de Click</button>
                        </div>
                    </div>`;

            container.append(boxHtml);
        });
    }

    // --- MANEJADORES DE EVENTOS ---

    $(document).on('click', '#add-page-btn', function(e) {
        e.preventDefault();
        pages.push({
            url: '',
            send_clickup: false,
            rules: []
        });
        updateInput();
        renderPages();
    });

    $(document).on('click', '.slt-delete-page', function() {
        if (confirm('¿Eliminar esta página y sus reglas?')) {
            const idx = $(this).data('idx');
            pages.splice(idx, 1);
            updateInput();
            renderPages();
        }
    });

    $(document).on('change', '.slt-page-url', function() {
        const idx = $(this).data('idx');
        pages[idx].url = $(this).val();
        updateInput();
        // No renderizamos aquí para no perder el foco mientras el usuario escribe
    });

    $(document).on('change', '.slt-clickup-checkbox', function() {
        const idx = $(this).data('idx');
        pages[idx].send_clickup = $(this).is(':checked');
        updateInput();
        // No hace falta renderPages(), el check ya cambió visualmente
    });

    window.addRule = (pIdx) => {
        if (!pages[pIdx].rules) pages[pIdx].rules = [];
        pages[pIdx].rules.push({
            selector: '',
            name: ''
        });
        updateInput();
        renderPages();
    };

    window.removeRule = (pIdx, rIdx) => {
        pages[pIdx].rules.splice(rIdx, 1);
        updateInput();
        renderPages();
    };

    window.updateRule = (pIdx, rIdx, key, val) => {
        if (!pages[pIdx].rules[rIdx]) return;
        pages[pIdx].rules[rIdx][key] = val;
        updateInput();
    };

    function updateInput() {
        $('#slt_hierarchical_tracking_input').val(JSON.stringify(pages));
    }

   $('#slt-test-clickup').on('click', function() {
    const btn = $(this);
    const resultMsg = $('#slt-test-result');
    const message = $('#slt-test-msg').val(); // Capturamos el texto
    
    if(!message) {
        resultMsg.text('⚠️ Por favor escribe un mensaje.').css('color', '#d63638');
        return;
    }

    btn.prop('disabled', true).text('Enviando...');
    resultMsg.text('').css('color', 'black');

    $.ajax({
        url: ajaxurl,
        method: 'POST',
        data: {
            action: 'slt_test_clickup_conn',
            test_message: message // Enviamos el mensaje al servidor
        },
        success: function(response) {
            if (response.success) {
                resultMsg.text('✅ Tarea creada con éxito.').css('color', '#00a32a');
                $('#slt-test-msg').val(''); // Limpiamos el campo
            } else {
                resultMsg.text('❌ Error: ' + response.data).css('color', '#d63638');
            }
            btn.prop('disabled', false).text('🚀 Enviar a ClickUp');
        }
    });
});

    setTimeout(() => {
        renderPages()
    }, 600)

});
</script>
<?php
}