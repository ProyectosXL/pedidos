# Locales - Sistema Modernizado de Pedidos

## Descripción
Sistema de pedidos con diseño moderno basado en la plataforma de mayoristas.

## Estructura del Proyecto
```
locales/
├── pedidos.php               # Página principal de pedidos
├── historial.php             # Historial de pedidos (ya modernizado)
├── detallePed.php           # Detalle de pedidos (ya modernizado)
├── style/
│   └── pedidos.css          # Estilos modernos
├── js/
│   └── pedidos.js           # Funcionalidades JavaScript
├── class/
│   └── pedido.php           # Clase backend para manejo de pedidos
└── controller/
    └── procesarPedido.php   # Controlador para envío de pedidos
```

## Características Implementadas

### Diseño
- ✅ Header con logo y acciones rápidas
- ✅ Barra de filtros con búsqueda y selección de rubro
- ✅ Tabla de productos con imágenes
- ✅ Badges animados para productos en SALE
- ✅ Footer fijo con totales (SKU, Unidades, Precio)
- ✅ Responsive design
- ✅ Modal para ver imágenes en grande

### Funcionalidades
- ✅ Cálculo automático de totales
- ✅ Botones +/- para incrementar/decrementar cantidades
- ✅ Filtros por código, descripción y rubro
- ✅ Validación de stock
- ✅ Habilitación/deshabilitación automática de botón enviar
- ✅ Limpiar cantidades con confirmación
- ✅ Soporte para mayoristas y franquicias
- ✅ Integración con sesiones PHP
- ✅ Diferentes tipos de pedidos (General, Accesorios, Outlet)

### Backend
- ✅ Clase Pedido con método listarPedido()
- ✅ Soporte para stored procedures (SJ_TIPO_PEDIDO_1, SJ_TIPO_PEDIDO_2, SJ_TIPO_PEDIDO_3)
- ✅ Controlador para procesamiento de pedidos
- ✅ Validación de datos del formulario
- ✅ Filtrado de artículos con cantidad > 0

## Uso

### Acceso
```
http://localhost/pedidos/locales/pedidos.php?tipo=1
```

Parámetros:
- `tipo=1` - Pedido General
- `tipo=2` - Pedido Accesorios
- `tipo=3` - Pedido Outlet

### Variables de Sesión Requeridas
```php
$_SESSION['username']      // Usuario autenticado
$_SESSION['numsuc']        // Número de sucursal
$_SESSION['tipo']          // MAYORISTA o FRANQUICIA
$_SESSION['esOutlet']      // Flag outlet
$_SESSION['usuarioUy']     // Usuario Uruguay
$_SESSION['cupoCredi']     // Límite de crédito
$_SESSION['razonSocial']   // Nombre del cliente
```

## Integración con Sistema Existente

### Redirección desde menu
Actualizar los links en el menú principal para apuntar a:
```php
<a href="../pedidos/locales/pedidos.php?tipo=1">Pedido General</a>
<a href="../pedidos/locales/pedidos.php?tipo=2">Pedido Accesorios</a>
<a href="../pedidos/locales/pedidos.php?tipo=3">Pedido Outlet</a>
```

## Pendientes de Implementación

### Funcionalidades adicionales del sistema original
- ⚠️ Exportar a Excel (jquery.table2excel.js)
- ⚠️ Grabar pedido (guardar borrador)
- ⚠️ Cargar pedido guardado
- ⚠️ Verificación de límite de crédito con alertas
- ⚠️ Stock local para sucursales < 100
- ⚠️ Ventas 30 días para sucursales < 100
- ⚠️ Integración con SweetAlert2 para notificaciones

### Migraciones necesarias
Si se requieren las funciones adicionales del sistema original:

1. **Exportar a Excel**
   - Copiar `js/jquery.table2excel.js`
   - Agregar jQuery si es necesario
   - Implementar función exportarExcel()

2. **Grabar/Cargar Pedido**
   - Migrar lógica AJAX de `js/main.js`
   - Crear endpoints en controller/
   - Agregar localStorage para persistencia

3. **Verificación de Crédito**
   - Implementar verificarCredito() en JS
   - Agregar alertas visuales en frontend
   - Integrar con cupo_credito de sesión

4. **Stock Local y Ventas**
   - Agregar AJAX para cargar datos adicionales
   - Crear stored procedures si no existen
   - Actualizar interfaz con los datos

## Notas Técnicas

### Compatibilidad
- PHP 7.x / 8.x
- SQL Server con extensión sqlsrv
- Navegadores modernos (Chrome, Firefox, Edge)
- Font Awesome 6.4.0

### Performance
- Carga lazy de imágenes
- Filtros en cliente para respuesta inmediata
- Cálculos optimizados en JavaScript

### Seguridad
- Validación de sesión en cada página
- Sanitización de inputs en backend
- Prevención de SQL injection mediante stored procedures

## Contacto y Soporte
Para dudas o mejoras, consultar con el equipo de desarrollo.
