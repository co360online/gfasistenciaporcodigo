# CO360 Asistencia por Código (MVP)

Plugin para validar códigos de asistencia en Gravity Forms y registrar su uso por proyecto.

## Instalación

1. Copia la carpeta del plugin en `wp-content/plugins/co360-attendance-codes`.
2. Activa **CO360 Asistencia por Código (MVP)** desde el panel de WordPress.

## Flujo recomendado

1. **Crear proyecto**
   - Menú **CO360 Asistencia → Proyectos**.
2. **Generar códigos**
   - Menú **CO360 Asistencia → Códigos**.
   - Selecciona el proyecto, define cantidad, longitud y prefijo.
3. **Configurar mapeo Gravity Forms**
   - Menú **CO360 Asistencia → Ajustes**.
   - Añade una fila con `form_id`, `field_id` del campo de código y `project_id`.
4. **Probar envío**
   - Envía el formulario con un código válido y verifica que el uso se registre.
5. **Exportar CSV**
   - Desde **Proyectos** o **Códigos** usa **Exportar CSV** para descargar los códigos.

## Notas

- La validación se realiza 100% en servidor.
- El registro de usos queda en **CO360 Asistencia → Usos**.
