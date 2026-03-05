<?php
if (!defined('ABSPATH')) exit;

// Registro de Menús con Alerta de Capacidad
add_action('admin_menu', function() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'slt_eventos';
    
    // Consultamos cantidad actual y límite configurado
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $tabla");
    $limit = (int)get_option('slt_db_limit', 5000);
    $notif = '';

    // Si llegamos al 90% o más, inyectamos el globo rojo de WordPress
    if ($count >= ($limit * 0.9)) {
        $notif = ' <span class="update-plugins count-1"><span class="plugin-count">!</span></span>';
    }

    add_menu_page('Leads Tracker', 'Leads Tracker' . $notif, 'manage_options', 'slt-logs', 'slt_mostrar_logs', 'dashicons-chart-line');
    add_submenu_page('slt-logs', 'Configuración', 'Configuración', 'manage_options', 'slt-settings', 'slt_mostrar_settings');
    add_submenu_page('slt-logs', 'Estadísticas', 'Estadísticas', 'manage_options', 'slt-intentions', 'slt_mostrar_intenciones');
});

// Nodo en la Barra Superior (Admin Bar) con Monitor de Capacidad
add_action('admin_bar_menu', function($wp_admin_bar) {
    if (get_option('slt_show_admin_bar', '1') === '1') {
        global $wpdb;
        $tabla = $wpdb->prefix . 'slt_eventos';
        
        $is_on = get_option('slt_master_switch', '1') === '1';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $tabla");
        $limit = (int)get_option('slt_db_limit', 5000);
        $percent = ($count / $limit) * 100;

        // Lógica de colores por salud de base de datos
        $status_color = $is_on ? '#00ff00' : '#ff0000'; // Color del icono (ON/OFF)
        
        // Color del texto del contador según capacidad
        $cap_color = '#ffffff'; 
        if ($percent >= 90) $cap_color = '#ffa0a0'; // Rojo suave para resaltar en barra negra
        elseif ($percent >= 70) $cap_color = '#ffb900'; // Naranja

        $wp_admin_bar->add_node([
            'id'    => 'slt-logs-bar',
            'title' => '<span class="ab-icon dashicons-chart-line" style="color:'.$status_color.'"></span> Tracker <span style="color:'.$cap_color.';">['.number_format($count).'/'.number_format($limit).']</span>',
            'href'  => admin_url('admin.php?page=slt-logs')
        ]);
        
        $wp_admin_bar->add_node([
            'id' => 'slt-toggle-btn',
            'parent' => 'slt-logs-bar',
            'title' => $is_on ? '❌ Desactivar Tracker' : '✅ Activar Tracker',
            'href' => add_query_arg('slt_toggle_switch', '1')
        ]);

        $wp_admin_bar->add_node([
            'id' => 'slt-db-status',
            'parent' => 'slt-logs-bar',
            'title' => '📊 Capacidad: ' . round($percent, 1) . '%',
            'href' => admin_url('admin.php?page=slt-settings')
        ]);
    }
}, 999);