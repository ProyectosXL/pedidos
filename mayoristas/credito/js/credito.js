// credito.js - Funcionalidad para el módulo de crédito mayoristas

document.addEventListener('DOMContentLoaded', function() {
    initSearchFilter();
    setupColumnSorting();
});

/**
 * Configura el ordenamiento por columnas
 */
function setupColumnSorting() {
    const sortableHeaders = document.querySelectorAll('.credito-table thead th.sortable');
    
    sortableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const sortBy = this.getAttribute('data-sort');
            const tbody = document.querySelector('.credito-table tbody');
            const rows = Array.from(tbody.querySelectorAll('tr:not(.totals-row):not(.no-results-message)'));
            const totalsRow = tbody.querySelector('.totals-row');
            
            // Determinar dirección de ordenamiento
            let isAsc = true;
            if(this.classList.contains('sort-asc')) {
                isAsc = false;
            }
            
            // Limpiar todas las clases de ordenamiento
            sortableHeaders.forEach(h => {
                h.classList.remove('sort-asc', 'sort-desc');
            });
            
            // Agregar clase correspondiente
            this.classList.add(isAsc ? 'sort-asc' : 'sort-desc');
            
            // Ordenar filas
            rows.sort((a, b) => {
                let aVal, bVal;
                
                switch(sortBy) {
                    case 'cliente':
                    case 'razon':
                        aVal = a.getAttribute(`data-${sortBy}`) || '';
                        bVal = b.getAttribute(`data-${sortBy}`) || '';
                        return isAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                        
                    case 'ppp':
                    case 'pedidos':
                        aVal = parseInt(a.getAttribute(`data-${sortBy}`)) || 0;
                        bVal = parseInt(b.getAttribute(`data-${sortBy}`)) || 0;
                        return isAsc ? aVal - bVal : bVal - aVal;
                        
                    case 'cupo':
                    case 'saldo':
                    case 'vencidas':
                    case 'cheques':
                    case 'cheques10':
                    case 'deuda':
                    case 'disponible':
                        aVal = parseFloat(a.getAttribute(`data-${sortBy}`)) || 0;
                        bVal = parseFloat(b.getAttribute(`data-${sortBy}`)) || 0;
                        return isAsc ? aVal - bVal : bVal - aVal;
                        
                    default:
                        return 0;
                }
            });
            
            // Reordenar DOM
            rows.forEach(row => tbody.appendChild(row));
            
            // Asegurar que la fila de totales esté al final
            if(totalsRow) {
                tbody.appendChild(totalsRow);
            }
        });
    });
}

/**
 * Inicializa el filtro de búsqueda de clientes
 */
function initSearchFilter() {
    const searchInput = document.getElementById('searchClient');
    
    if (!searchInput) return;
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        filterClients(searchTerm);
    });
}

/**
 * Filtra las filas de clientes según el término de búsqueda
 * @param {string} searchTerm - Término de búsqueda
 */
function filterClients(searchTerm) {
    const table = document.querySelector('.credito-table tbody');
    if (!table) return;
    
    const rows = table.querySelectorAll('tr:not(.totals-row)');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const clientData = row.getAttribute('data-client');
        
        if (!clientData) return;
        
        if (searchTerm === '' || clientData.includes(searchTerm)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Mostrar mensaje si no hay resultados
    showNoResults(visibleCount === 0);
}

/**
 * Muestra u oculta el mensaje de "sin resultados"
 * @param {boolean} show - Si debe mostrar el mensaje
 */
function showNoResults(show) {
    const existingMessage = document.querySelector('.no-results-message');
    
    if (show && !existingMessage) {
        const table = document.querySelector('.credito-table tbody');
        const emptyRow = document.createElement('tr');
        emptyRow.className = 'no-results-message';
        emptyRow.innerHTML = `
            <td colspan="11" class="table-empty">
                <i class="fas fa-search"></i>
                <h3>No se encontraron resultados</h3>
                <p>Intenta con otro término de búsqueda</p>
            </td>
        `;
        table.insertBefore(emptyRow, table.querySelector('.totals-row'));
    } else if (!show && existingMessage) {
        existingMessage.remove();
    }
}
