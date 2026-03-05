<?php
if (!defined('ABSPATH')) exit;

function slt_mostrar_intenciones() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'slt_eventos';

    $filtro_fecha = isset($_GET['rango_fecha']) ? sanitize_text_field($_GET['rango_fecha']) : '7';
    $where = "WHERE fecha >= DATE_SUB(NOW(), INTERVAL $filtro_fecha DAY)";
    
    if (isset($_POST['slt_export_stats'])) {
        slt_exportar_estadisticas($where);
    }

    $stats_scoring = $wpdb->get_results("SELECT 
        SUM(CASE WHEN elemento_id LIKE '%Click:%' OR elemento_id LIKE '%Submit:%' THEN 1 ELSE 0 END) as prospectos,
        SUM(CASE WHEN elemento_id LIKE 'Entrada%' OR elemento_id LIKE 'Salida%' THEN 1 ELSE 0 END) as navegantes
        FROM $tabla $where");

    $prospectos = $stats_scoring[0]->prospectos ?: 0;
    $navegantes = $stats_scoring[0]->navegantes ?: 0;
    $total = $navegantes + $prospectos;
    $porcentaje_prospecto = $total > 0 ? round(($prospectos / $total) * 100, 2) : 0;

    $origenes = $wpdb->get_results("SELECT 
        CASE 
            WHEN elemento_id LIKE '%[Origen: %' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(elemento_id, '[Origen: ', -1), ']', 1)
            ELSE 'Directo / Desconocido'
        END as fuente,
        COUNT(*) as cantidad 
        FROM $tabla $where AND elemento_id LIKE '%Entrada%'
        GROUP BY fuente ORDER BY cantidad DESC LIMIT 5");

    $results = $wpdb->get_results("SELECT url_pagina, elemento_id, COUNT(*) as cantidad 
        FROM $tabla $where 
        GROUP BY url_pagina, elemento_id ORDER BY cantidad DESC LIMIT 20");

    ?>
    <style>
        .slt-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 20px; }
        .slt-stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-top: 4px solid #2271b1; }
        .slt-stat-card h3 { margin-top: 0; color: #50575e; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; }
        .slt-big-num { font-size: 32px; font-weight: bold; color: #1d2327; display: block; margin: 10px 0; }
        .slt-progress { background: #f0f0f1; border-radius: 10px; height: 12px; position: relative; margin-top: 10px; overflow: hidden; }
        .slt-bar { background: #00a32a; height: 100%; border-radius: 10px; transition: width 0.6s ease; }
        .slt-guide-box { background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #72aee6; }
        .slt-impact-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .slt-high { background: #e7f6ed; color: #00a32a; }
        .slt-info { background: #f0f6fb; color: #2271b1; }
        .help-section h4 { margin: 0 0 10px 0; color: #2271b1; }
        .help-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 782px) { .help-grid { grid-template-columns: 1fr; } }
    </style>

    <div class="wrap">
        <h1>📊 Estadísticas de Conversión y Comportamiento</h1>

        <div class="slt-guide-box">
            <div class="help-section">
                <h4>📘 Guía de Interpretación de Datos (Metodología Lead Scoring)</h4>
                <div class="help-grid">
                    <div>
                        <p><strong>Niveles de Impacto:</strong></p>
                        <p><span class="slt-impact-badge slt-high">Impacto Alto:</span> Acciones de <strong>intención activa</strong>. Clics en botones, envíos de formularios y elementos estratégicos definidos en reglas. Representan un cliente potencial (Lead).</p>
                        <p><span class="slt-impact-badge slt-info">Informativo:</span> Acciones de <strong>navegación pasiva</strong>. Entradas, salidas o actualizaciones. Indican volumen de tráfico pero no necesariamente interés comercial.</p>
                    </div>
                    <div>
                        <p><strong>¿Cómo se calcula la Calidad de Tráfico?</strong></p>
                        <p>Es la relación entre navegantes pasivos y usuarios activos. Si tienes un <strong>10%</strong>, significa que de cada 100 visitas, 10 interactuaron con elementos clave. Un porcentaje alto valida que el diseño y los textos están funcionando.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="tablenav top">
            <form method="get" style="display:inline-block;">
                <input type="hidden" name="page" value="slt-intentions">
                <select name="rango_fecha">
                    <option value="1" <?php selected($filtro_fecha, '1'); ?>>Hoy</option>
                    <option value="7" <?php selected($filtro_fecha, '7'); ?>>Últimos 7 días</option>
                    <option value="30" <?php selected($filtro_fecha, '30'); ?>>Últimos 30 días</option>
                </select>
                <input type="submit" class="button" value="Filtrar">
            </form>
            <form method="post" style="display:inline-block; margin-left: 10px;">
                <input type="submit" name="slt_export_stats" class="button button-primary" value="📥 Exportar Informe para Marketing">
            </form>
        </div>

        <div class="slt-stat-grid">
            <div class="slt-stat-card">
                <h3>Calidad de Conversión</h3>
                <span class="slt-big-num"><?php echo $porcentaje_prospecto; ?>%</span>
                <p>Efectividad de la página para convertir visitantes en prospectos activos.</p>
                <div class="slt-progress"><div class="slt-bar" style="width:<?php echo $porcentaje_prospecto; ?>%"></div></div>
            </div>

            <div class="slt-stat-card">
                <h3>Fuentes de Adquisición</h3>
                <ul style="margin: 10px 0 0 0; padding:0; list-style:none;">
                    <?php foreach($origenes as $o): ?>
                        <li style="font-size:13px; margin-bottom:8px; display:flex; justify-content:space-between; border-bottom: 1px solid #f0f0f1; padding-bottom: 4px;">
                            <span>🌐 <?php echo esc_html($o->fuente); ?></span>
                            <strong><?php echo $o->cantidad; ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="slt-stat-card">
                <h3>Interacciones Totales</h3>
                <span class="slt-big-num"><?php echo $total; ?></span>
                <p>Eventos registrados (Entradas + Clics + Salidas) en el rango seleccionado.</p>
            </div>
        </div>

        <h2 style="margin-top:40px;">🧠 Mapa de Intenciones y Rendimiento</h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Página Destino</th>
                    <th>Acción del Usuario</th>
                    <th>Frecuencia</th>
                    <th>Valor Estratégico</th>
                </tr>
            </thead>
            <tbody>
                <?php if($results): foreach ($results as $row): 
                    $es_prospecto = (strpos($row->elemento_id, 'Click:') !== false || strpos($row->elemento_id, 'Submit:') !== false);
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($row->url_pagina); ?></strong></td>
                        <td><code><?php echo esc_html($row->elemento_id); ?></code></td>
                        <td><?php echo $row->cantidad; ?> interacciones</td>
                        <td>
                            <?php if($es_prospecto): ?>
                                <span class="slt-impact-badge slt-high">Alto Impacto</span>
                            <?php else: ?>
                                <span class="slt-impact-badge slt-info">Informativo</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="4">Sin actividad registrada en este periodo.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function slt_exportar_estadisticas($where) {
    global $wpdb;
    $tabla = $wpdb->prefix . 'slt_eventos';
    $results = $wpdb->get_results("SELECT url_pagina, elemento_id, COUNT(*) as clics 
        FROM $tabla $where GROUP BY url_pagina, elemento_id ORDER BY clics DESC", ARRAY_A);

    if (!$results) return;

    $filename = 'reporte_analitico_leads_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    $output = fopen('php://output', 'w');
    fputcsv($output, array('URL Pagina', 'Accion / Intencion', 'Total Eventos'));

    foreach ($results as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}