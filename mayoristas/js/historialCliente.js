
// Filtrado por período
let currentPeriod = 'mes'; // Default

// Cambiar período
function cambiarPeriodo(periodo) {
    currentPeriod = periodo;
    
    // Actualizar botones activos
    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Filtrar tabla
    filtrarPorPeriodo(periodo);
    
    // Actualizar estadísticas
    actualizarEstadisticas();
}

// Filtrar filas por período
function filtrarPorPeriodo(periodo) {
    const rows = document.querySelectorAll('.history-table tbody tr');
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    
    let visibleCount = 0;
    
    rows.forEach(row => {
        const fechaStr = row.getAttribute('data-fecha');
        if(!fechaStr) {
            row.style.display = 'none';
            return;
        }
        
        // Parsear fecha (formato yyyy-mm-dd)
        const partes = fechaStr.split('-');
        const fecha = new Date(partes[0], partes[1] - 1, partes[2]);
        fecha.setHours(0, 0, 0, 0);
        
        let mostrar = false;
        
        switch(periodo) {
            case 'hoy':
                mostrar = fecha.getTime() === hoy.getTime();
                break;
                
            case 'semana':
                const inicioSemana = new Date(hoy);
                inicioSemana.setDate(hoy.getDate() - 7);
                mostrar = fecha >= inicioSemana && fecha <= hoy;
                break;
                
            case 'mes':
                const inicioMes = new Date(hoy);
                inicioMes.setDate(hoy.getDate() - 30);
                mostrar = fecha >= inicioMes && fecha <= hoy;
                break;
                
            case 'trimestre':
                const inicioTrimestre = new Date(hoy);
                inicioTrimestre.setDate(hoy.getDate() - 90);
                mostrar = fecha >= inicioTrimestre && fecha <= hoy;
                break;
                
            case 'todos':
                mostrar = true;
                break;
        }
        
        if(mostrar) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Mostrar mensaje si no hay resultados
    verificarResultados(visibleCount);
}

// Verificar si hay resultados
function verificarResultados(count) {
    const tbody = document.querySelector('.history-table tbody');
    const emptyRow = document.getElementById('emptyRow');
    
    if(count === 0 && emptyRow) {
        emptyRow.style.display = '';
    } else if(emptyRow) {
        emptyRow.style.display = 'none';
    }
}

// Actualizar estadísticas según período
function actualizarEstadisticas() {
    const rows = document.querySelectorAll('.history-table tbody tr:not(#emptyRow)');
    
    let totalPedidos = 0;
    let totalArticulos = 0;
    
    rows.forEach(row => {
        if(row.style.display !== 'none') {
            totalPedidos++;
            const cantidad = parseInt(row.getAttribute('data-cantidad')) || 0;
            totalArticulos += cantidad;
        }
    });
    
    // Actualizar displays
    const totalPedidosEl = document.getElementById('totalPedidos');
    const totalArticulosEl = document.getElementById('totalArticulos');
    
    if(totalPedidosEl) {
        totalPedidosEl.textContent = totalPedidos;
    }
    
    if(totalArticulosEl) {
        totalArticulosEl.textContent = totalArticulos;
    }
}

// Búsqueda en tabla
function buscarEnTabla() {
    const searchTerm = document.getElementById('searchHistory')?.value.toLowerCase() || '';
    const rows = document.querySelectorAll('.history-table tbody tr:not(#emptyRow)');
    
    let visibleCount = 0;
    
    rows.forEach(row => {
        // Solo buscar en filas del período actual
        if(row.style.display === 'none') return;
        
        const pedido = row.getAttribute('data-pedido')?.toLowerCase() || '';
        const observaciones = row.getAttribute('data-observaciones')?.toLowerCase() || '';
        
        const matches = pedido.includes(searchTerm) || observaciones.includes(searchTerm);
        
        if(matches || searchTerm === '') {
            row.classList.remove('hidden-by-search');
            visibleCount++;
        } else {
            row.classList.add('hidden-by-search');
        }
    });
    
    verificarResultados(visibleCount);
}

// Ver detalle del pedido
function verDetalle(nroPedido, codCliente, tipo) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '../pedidos/detallePed.php';
    
    const inputs = {
        'accion': 'ver_detalle',
        'numero_pedido': nroPedido,
        'sucursal': codCliente,
        'tipo': tipo,
        'origen': 'vendedor'
    };
    
    for (const [key, value] of Object.entries(inputs)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
    }
    
    document.body.appendChild(form);
    form.submit();
}

// Imprimir historial
function imprimirHistorial() {
    window.print();
}

// Exportar a Excel (simple)
function exportarExcel() {
    const rows = document.querySelectorAll('.history-table tbody tr:not(#emptyRow)');
    let csv = 'Fecha,Número de Pedido,Observaciones,Cantidad\n';
    
    rows.forEach(row => {
        if(row.style.display !== 'none' && !row.classList.contains('hidden-by-search')) {
            const fecha = row.querySelector('td:nth-child(1)')?.textContent || '';
            const pedido = row.getAttribute('data-pedido') || '';
            const obs = row.getAttribute('data-observaciones') || '';
            const cantidad = row.getAttribute('data-cantidad') || '';
            
            csv += `"${fecha}","${pedido}","${obs}","${cantidad}"\n`;
        }
    });
    
    // Crear y descargar archivo
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', `historial_pedidos_${new Date().getTime()}.csv`);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    // Aplicar filtro inicial (mes)
    filtrarPorPeriodo('mes');
    
    // Event listener para búsqueda
    const searchInput = document.getElementById('searchHistory');
    if(searchInput) {
        searchInput.addEventListener('input', buscarEnTabla);
    }
    
    // Animación de filas
    const rows = document.querySelectorAll('.history-table tbody tr:not(#emptyRow)');
    rows.forEach((row, index) => {
        row.style.animationDelay = `${index * 0.03}s`;
    });
    
    // Estadísticas iniciales
    actualizarEstadisticas();
});

// Atajos de teclado
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + F para buscar
    if((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        const searchInput = document.getElementById('searchHistory');
        if(searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }
    
    // Escape para limpiar búsqueda
    if(e.key === 'Escape') {
        const searchInput = document.getElementById('searchHistory');
        if(searchInput && searchInput.value) {
            searchInput.value = '';
            buscarEnTabla();
        }
    }
    
    // Ctrl/Cmd + P para imprimir
    if((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        imprimirHistorial();
    }
});

// Helper: Formatear fecha
function formatearFecha(fecha) {
    const opciones = { year: 'numeric', month: '2-digit', day: '2-digit' };
    return fecha.toLocaleDateString('es-AR', opciones);
}

// Helper: Scroll suave a elemento
function scrollToElement(elementId) {
    const element = document.getElementById(elementId);
    if(element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}