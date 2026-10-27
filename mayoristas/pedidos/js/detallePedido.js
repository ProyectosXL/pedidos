// Detalle Pedido JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Highlight search functionality
    highlightFromUrl();
    
    // Add keyboard shortcuts
    setupKeyboardShortcuts();
});

// Highlight product if coming from search
function highlightFromUrl() {
    const urlParams = new URLSearchParams(window.location.search);
    const highlightCode = urlParams.get('highlight');
    
    if (highlightCode) {
        const rows = document.querySelectorAll('.detail-table tbody tr');
        rows.forEach(row => {
            const codeCell = row.querySelector('.codigo-producto');
            if (codeCell && codeCell.textContent.trim() === highlightCode) {
                row.style.background = 'rgba(52, 152, 219, 0.1)';
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }
}

// Export to Excel
function exportarPedido() {
    // Get pedido info from URL
    const urlParams = new URLSearchParams(window.location.search);
    const pedido = urlParams.get('pedido');
    const suc = urlParams.get('suc');
    
    // Get table data
    const table = document.querySelector('.detail-table');
    if (!table) {
        alert('No hay datos para exportar');
        return;
    }
    
    let csv = '\uFEFF'; // BOM for Excel UTF-8
    csv += 'Código,Descripción,Cantidad\n';
    
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 3) {
            const codigo = cells[0].textContent.trim();
            const descripcion = cells[1].textContent.trim().replace(/,/g, ';'); // Replace commas
            const cantidad = cells[2].textContent.trim();
            csv += `${codigo},${descripcion},${cantidad}\n`;
        }
    });
    
    // Create download
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', `Pedido_${pedido}_${suc}.csv`);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Keyboard shortcuts
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + P to print
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
        
        // Escape to go back
        if (e.key === 'Escape') {
            window.location.href = 'historial.php';
        }
        
        // Ctrl/Cmd + E to export
        if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
            e.preventDefault();
            exportarPedido();
        }
    });
}

// Table filtering (if needed for large orders)
function filterTable(searchTerm) {
    const rows = document.querySelectorAll('.detail-table tbody tr');
    const term = searchTerm.toLowerCase();
    let visibleCount = 0;
    
    rows.forEach(row => {
        const codigo = row.querySelector('.codigo-producto').textContent.toLowerCase();
        const descripcion = row.querySelectorAll('td')[1].textContent.toLowerCase();
        
        if (codigo.includes(term) || descripcion.includes(term)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    return visibleCount;
}

// Print preparation
window.addEventListener('beforeprint', function() {
    document.body.classList.add('printing');
});

window.addEventListener('afterprint', function() {
    document.body.classList.remove('printing');
});
