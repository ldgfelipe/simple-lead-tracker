=== Simple Lead Tracker PRO ===
Contributors: LDG Felipe de Jesús Carrera Rendón
Tags: tracking, leads, analytics, behavior, rest-api
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 25.0
License: GPLv2 or later

== Description ==

Simple Lead Tracker PRO es una herramienta de rastreo avanzada diseñada para capturar el comportamiento de los usuarios sin afectar el rendimiento del sitio. Utiliza la tecnología sendBeacon y la REST API de WordPress para máxima compatibilidad con sistemas de caché.

== Features ==

* **Arquitectura Modular**: Código separado por funciones para mayor estabilidad.
* **Control Jerárquico**: Define reglas de rastreo específicas por página o globales.
* **Monitoreo de DB**: Vigilancia del tamaño de la base de datos en tiempo real.
* **Exportación Inteligente**: Genera backups en Excel (CSV) y limpia la tabla automáticamente.
* **Geolocalización**: Captura automática del país e IP del visitante.
* **Detección de Intenciones**: Panel visual para interpretar qué elementos interesan más a los usuarios.
* **Interruptor Maestro**: Activa o desactiva el rastreo con un clic desde la barra superior.

== Installation ==

1. Sube la carpeta `simple-lead-tracker` al directorio `/wp-content/plugins/`.
2. Activa el plugin a través del menú 'Plugins' en WordPress.
3. Ve a 'Leads Tracker > Configuración' para definir tus reglas iniciales.

== Frequently Asked Questions ==

= ¿Cómo agrego un selector de atributo? =
Usa la sintaxis CSS estándar: `[title="Nombre"]` o `[arial-label="Boton"]`.

= ¿El rastreo de logeados afecta a los administradores? =
Si desactivas "Trackear Logeados", el plugin ignorará tus propias acciones si tienes la sesión iniciada.

== Changelog ==

= 25.0 =
* Reestructuración modular por carpetas.
* Mejora en la lógica de selectores de atributos.
* Implementación de exportación Excel y limpieza automática.