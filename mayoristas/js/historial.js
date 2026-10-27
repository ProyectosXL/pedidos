
// Filter and search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const clientFilter = document.getElementById('clientFilter');
    const dateFilter = document.getElementById('dateFilter');
    const tableRows = document.querySelectorAll('.modern-table tbody tr');
    const emptyState = document.getElementById('emptyState');
    const tableBody = document.querySelector('.modern-table tbody');
    
    // Collect all unique clients for filter
    const clients = new Set();
    tableRows.forEach(row => {
        const clientCode = row.getAttribute('data-client-code');
        if (clientCode) {
            clients.add(clientCode);
        }
    });
    
    // Update stats
    function updateStats() {
        const visibleRows = Array.from(tableRows).filter(row => row.style.display !== 'none');
        
        document.getElementById('totalPedidos').textContent = visibleRows.length;
        
        const totalArticulos = visibleRows.reduce((sum, row) => {
            return sum + parseInt(row.getAttribute('data-cantidad') || 0);
        }, 0);
        
        document.getElementById('totalArticulos').textContent = totalArticulos;
        
        // Get unique clients from visible rows
        const uniqueClients = new Set();
        visibleRows.forEach(row => {
            const clientCode = row.getAttribute('data-client-code');
            if (clientCode) {
                uniqueClients.add(clientCode);
            }
        });
        
        document.getElementById('totalClientes').textContent = uniqueClients.size;
    }
    
    // Filter function
    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const selectedClient = clientFilter.value;
        const selectedDate = dateFilter.value;
        
        let visibleCount = 0;
        
        tableRows.forEach(row => {
            const clientName = row.getAttribute('data-client-name').toLowerCase();
            const clientCode = row.getAttribute('data-client-code').toLowerCase();
            const orderNumber = row.getAttribute('data-order-number').toLowerCase();
            const observations = row.getAttribute('data-observations').toLowerCase();
            const date = row.getAttribute('data-date');
            
            // Search filter
            const matchesSearch = !searchTerm || 
                clientName.includes(searchTerm) || 
                clientCode.includes(searchTerm) ||
                orderNumber.includes(searchTerm) ||
                observations.includes(searchTerm);
            
            // Client filter
            const matchesClient = !selectedClient || clientCode === selectedClient.toLowerCase();
            
            // Date filter
            let matchesDate = true;
            if (selectedDate) {
                const today = new Date();
                const rowDate = new Date(date);
                
                switch(selectedDate) {
                    case 'today':
                        matchesDate = rowDate.toDateString() === today.toDateString();
                        break;
                    case 'week':
                        const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
                        matchesDate = rowDate >= weekAgo;
                        break;
                    case 'month':
                        const monthAgo = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);
                        matchesDate = rowDate >= monthAgo;
                        break;
                    case 'all':
                        matchesDate = true;
                        break;
                }
            }
            
            const isVisible = matchesSearch && matchesClient && matchesDate;
            
            if (isVisible) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Show/hide empty state
        if (visibleCount === 0) {
            tableBody.style.display = 'none';
            emptyState.style.display = 'table-row';
        } else {
            tableBody.style.display = '';
            emptyState.style.display = 'none';
        }
        
        // Update stats
        updateStats();
    }
    
    // Event listeners
    if (searchInput) {
        searchInput.addEventListener('input', filterTable);
    }
    
    if (clientFilter) {
        clientFilter.addEventListener('change', filterTable);
    }
    
    if (dateFilter) {
        dateFilter.addEventListener('change', filterTable);
    }
    
    // Clear filters button
    const clearFiltersBtn = document.getElementById('clearFilters');
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function() {
            searchInput.value = '';
            clientFilter.value = '';
            dateFilter.value = 'all';
            filterTable();
        });
    }
    
    // Initial stats calculation
    updateStats();
    
    // Export functionality (if needed in future)
    const exportBtn = document.getElementById('exportBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            // Export to CSV or Excel functionality
            console.log('Export functionality');
        });
    }
    
    // Row click animation
    tableRows.forEach(row => {
        const orderLink = row.querySelector('.order-number');
        if (orderLink) {
            orderLink.addEventListener('click', function(e) {
                // Add visual feedback
                row.style.backgroundColor = 'rgba(52, 152, 219, 0.1)';
                setTimeout(() => {
                    row.style.backgroundColor = '';
                }, 300);
            });
        }
    });
    
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
        
        // Escape to clear filters
        if (e.key === 'Escape') {
            if (clearFiltersBtn) {
                clearFiltersBtn.click();
            }
        }
    });
});

// Show loading state
function showLoading() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
        loadingOverlay.classList.add('active');
    }
}

// Format date nicely
function formatDate(dateString) {
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('es-AR', options);
}

// Highlight search terms
function highlightText(text, term) {
    if (!term) return text;
    const regex = new RegExp(`(${term})`, 'gi');
    return text.replace(regex, '<mark>$1</mark>');
}