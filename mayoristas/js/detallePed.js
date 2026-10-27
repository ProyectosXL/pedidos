
// Print functionality
function printOrder() {
    window.print();
}

// Search within order items
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchItems');
    
    if (searchInput) {
        const tableRows = document.querySelectorAll('.detail-table tbody tr');
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            let visibleCount = 0;
            
            tableRows.forEach(row => {
                const code = row.getAttribute('data-code').toLowerCase();
                const description = row.getAttribute('data-description').toLowerCase();
                
                const matches = code.includes(searchTerm) || description.includes(searchTerm);
                
                if (matches) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Update item count
            const itemCount = document.getElementById('itemCount');
            if (itemCount) {
                itemCount.textContent = visibleCount;
            }
            
            // Update total quantity
            updateTotals();
        });
    }
    
    // Calculate totals on load
    updateTotals();
    
    // Add animation to rows
    const rows = document.querySelectorAll('.detail-table tbody tr');
    rows.forEach((row, index) => {
        row.style.animationDelay = `${index * 0.05}s`;
    });
});

// Update totals based on visible rows
function updateTotals() {
    const visibleRows = Array.from(document.querySelectorAll('.detail-table tbody tr')).filter(
        row => row.style.display !== 'none'
    );
    
    const totalItems = visibleRows.length;
    const totalQuantity = visibleRows.reduce((sum, row) => {
        return sum + parseInt(row.getAttribute('data-quantity') || 0);
    }, 0);
    
    // Update displays
    const totalItemsEl = document.getElementById('totalItems');
    const totalQuantityEl = document.getElementById('totalQuantity');
    
    if (totalItemsEl) {
        totalItemsEl.textContent = totalItems;
    }
    
    if (totalQuantityEl) {
        totalQuantityEl.textContent = totalQuantity;
    }
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + P for print
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        printOrder();
    }
    
    // Ctrl/Cmd + F for search
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        const searchInput = document.getElementById('searchItems');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }
});

// Highlight row on click
document.querySelectorAll('.detail-table tbody tr').forEach(row => {
    row.addEventListener('click', function() {
        // Remove previous highlights
        document.querySelectorAll('.detail-table tbody tr').forEach(r => {
            r.style.backgroundColor = '';
        });
        
        // Highlight clicked row temporarily
        this.style.backgroundColor = 'rgba(52, 152, 219, 0.1)';
        
        setTimeout(() => {
            this.style.backgroundColor = '';
        }, 1000);
    });
});