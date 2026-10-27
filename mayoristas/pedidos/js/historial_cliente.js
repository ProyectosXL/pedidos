// Client History JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchHistory');
    const tableRows = document.querySelectorAll('.history-table tbody tr:not(#emptyState)');
    const emptyState = document.getElementById('emptyState');
    
    // Update stats
    function updateStats() {
        const visibleRows = Array.from(tableRows).filter(row => row.style.display !== 'none');
        
        const totalPedidosEl = document.getElementById('totalPedidos');
        if (totalPedidosEl) {
            totalPedidosEl.textContent = visibleRows.length;
        }
        
        const totalArticulos = visibleRows.reduce((sum, row) => {
            return sum + parseInt(row.getAttribute('data-cantidad') || 0);
        }, 0);
        
        const totalArticulosEl = document.getElementById('totalArticulos');
        if (totalArticulosEl) {
            totalArticulosEl.textContent = totalArticulos;
        }
    }
    
    // Filter function
    function filterTable() {
        if (!searchInput) return;
        
        const searchTerm = searchInput.value.toLowerCase().trim();
        let visibleCount = 0;
        
        tableRows.forEach(row => {
            const pedido = (row.getAttribute('data-pedido') || '').toLowerCase();
            const observaciones = (row.getAttribute('data-observaciones') || '').toLowerCase();
            
            // Search filter
            const matchesSearch = !searchTerm || 
                                pedido.includes(searchTerm) || 
                                observaciones.includes(searchTerm);
            
            if (matchesSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Show/hide empty state
        if (emptyState) {
            emptyState.style.display = visibleCount === 0 ? '' : 'none';
        }
        
        // Update stats
        updateStats();
    }
    
    // Search input listener
    if (searchInput) {
        searchInput.addEventListener('input', filterTable);
        searchInput.addEventListener('keyup', function(e) {
            if (e.key === 'Escape') {
                searchInput.value = '';
                filterTable();
            }
        });
    }
    
    // Initial stats
    updateStats();
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + F to focus search
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
    });
});

// Period filter function
function cambiarPeriodo(periodo) {
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    
    let fechaLimite;
    
    switch(periodo) {
        case 'hoy':
            fechaLimite = hoy;
            break;
        case 'semana':
            fechaLimite = new Date(hoy);
            fechaLimite.setDate(hoy.getDate() - 7);
            break;
        case 'mes':
            fechaLimite = new Date(hoy);
            fechaLimite.setMonth(hoy.getMonth() - 1);
            break;
        case 'trimestre':
            fechaLimite = new Date(hoy);
            fechaLimite.setMonth(hoy.getMonth() - 3);
            break;
        case 'todos':
            fechaLimite = null;
            break;
    }
    
    const tableRows = document.querySelectorAll('.history-table tbody tr:not(#emptyState)');
    const emptyState = document.getElementById('emptyState');
    let visibleCount = 0;
    
    tableRows.forEach(row => {
        const fechaStr = row.getAttribute('data-fecha');
        
        if (!fechaStr) {
            row.style.display = 'none';
            return;
        }
        
        if (!fechaLimite) {
            // Show all
            row.style.display = '';
            visibleCount++;
        } else {
            const fechaPedido = new Date(fechaStr);
            if (fechaPedido >= fechaLimite) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        }
    });
    
    // Show/hide empty state
    if (emptyState) {
        emptyState.style.display = visibleCount === 0 ? '' : 'none';
    }
    
    // Update active button
    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.closest('.period-btn').classList.add('active');
    
    // Update stats after filtering
    updateStats();
}

// Show loading overlay
function showLoading() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
        loadingOverlay.classList.add('active');
    }
}

// Export to Excel function
function exportarExcel() {
    alert('Función de exportación en desarrollo');
}

// Update stats helper (exposed globally for period filtering)
function updateStats() {
    const tableRows = document.querySelectorAll('.history-table tbody tr:not(#emptyState)');
    const visibleRows = Array.from(tableRows).filter(row => row.style.display !== 'none');
    
    const totalPedidosEl = document.getElementById('totalPedidos');
    if (totalPedidosEl) {
        totalPedidosEl.textContent = visibleRows.length;
    }
    
    const totalArticulos = visibleRows.reduce((sum, row) => {
        return sum + parseInt(row.getAttribute('data-cantidad') || 0);
    }, 0);
    
    const totalArticulosEl = document.getElementById('totalArticulos');
    if (totalArticulosEl) {
        totalArticulosEl.textContent = totalArticulos;
    }
}
