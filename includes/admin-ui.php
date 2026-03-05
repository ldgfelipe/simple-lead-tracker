<?php
if (!defined('ABSPATH')) exit;

/**
 * Orquestador de la Interfaz Administrativa
 * Carga los módulos de la carpeta /admin/
 */

require_once SLT_PATH . 'includes/admin/menu-bar.php';
require_once SLT_PATH . 'includes/admin/settings-page.php';
require_once SLT_PATH . 'includes/admin/stats-page.php';
require_once SLT_PATH . 'includes/admin/logs-page.php';

add_shortcode('slt_simulador_pipedrive', function() {
    // Esta forma es más segura para encontrar la carpeta 'tests' en la raíz del plugin
    $url = plugins_url('tests/mock-pipedrive.php', dirname(__FILE__)) . '?source=pipedrive.com';
    
    return '<div style="background:#f9f9f9; .border:2px dashed #08a742;">
                <iframe src="'.esc_url($url).'" style="width:100%; height:650px; border:none;"></iframe>
            </div>';
});