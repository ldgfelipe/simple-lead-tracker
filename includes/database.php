<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('slt_crear_tabla')) {
    function slt_crear_tabla() {
        global $wpdb;
        $tabla = $wpdb->prefix . 'slt_eventos';
        $charset = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $tabla (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            fecha datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            url_pagina varchar(255) NOT NULL,
            elemento_id varchar(100) NOT NULL,
            session_id varchar(255) NOT NULL,
            user_name varchar(100) DEFAULT NULL,
            user_email varchar(100) DEFAULT NULL, -- Columna específica para Email
            user_phone varchar(50) DEFAULT NULL,  -- Columna específica para Teléfono
            user_ip varchar(45) DEFAULT NULL,     -- Columna para la IP (Bloqueos)
            observaciones text DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

if (!function_exists('slt_exportar_excel_y_limpiar')) {
    function slt_exportar_excel_y_limpiar() {
        global $wpdb;
        $tabla = $wpdb->prefix . 'slt_eventos';
        $results = $wpdb->get_results("SELECT * FROM $tabla ORDER BY fecha ASC", ARRAY_A);
        if (!$results) return;

        $filename = 'slt_backup_' . date('Y-m-d_H-i') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        $output = fopen('php://output', 'w');
        
        // Actualizamos la cabecera y el loop para incluir Observaciones en el CSV
        fputcsv($output, array('ID', 'Fecha', 'Pagina', 'Evento', 'Sesion', 'Usuario', 'Observaciones'));
        foreach ($results as $row) { fputcsv($output, $row); }
        fclose($output);
        $wpdb->query("TRUNCATE TABLE $tabla");
        exit;
    }
}

if (!function_exists('slt_is_ip_blocked')) {
    function slt_is_ip_blocked() {
        // 1. Obtener la IP del visitante de forma robusta
        $user_ip = $_SERVER['REMOTE_ADDR'];
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $user_ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        $user_ip = trim($user_ip);

        // 2. Obtener la lista negra
        $blocked_ips_raw = get_option('slt_blocked_ips', '');
        if (empty(trim($blocked_ips_raw))) return false;

        // 3. Limpiar la lista: convertir a array, quitar espacios y eliminar líneas vacías
        $blocked_ips = array_filter(array_map('trim', explode("\n", str_replace("\r", "", $blocked_ips_raw))));
        
        // 4. Comparación exacta
        return in_array($user_ip, $blocked_ips);
    }
}



// Gestión de acciones de base de datos
add_action('admin_init', function() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'slt_eventos';

    // Verificación de integridad: Asegura que la columna observaciones exista si se actualiza el plugin
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $tabla LIKE 'observaciones'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $tabla ADD COLUMN observaciones text DEFAULT NULL");
    }

    // 1. Acción EXPORTAR Y LIMPIAR
    if (isset($_POST['slt_export_clear']) && check_admin_referer('slt_export_action', 'slt_nonce')) {
        slt_exportar_excel_y_limpiar();
    }

    // 2. Acción LIMPIAR DIRECTO (SIN EXPORTAR)
    if (isset($_POST['slt_clear_db']) && check_admin_referer('slt_clear_action', 'slt_nonce')) {
        $wpdb->query("TRUNCATE TABLE $tabla");
        wp_redirect(admin_url('admin.php?page=slt-settings&status=cleared'));
        exit;
    }

    // 3. Acción SWITCH BARRA SUPERIOR
    if (isset($_GET['slt_toggle_switch'])) {
        $current = get_option('slt_master_switch', '1');
        update_option('slt_master_switch', $current === '1' ? '0' : '1');
        wp_redirect(remove_query_arg('slt_toggle_switch'));
        exit;
    }
});