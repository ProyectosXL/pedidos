
// Loading overlay
function showLoading() {
    document.getElementById('loadingOverlay').classList.add('active');
}

// Search functionality (solo para vendedores)
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    
    // Si no existe el searchInput, es porque es cliente directo, salir
    if (!searchInput) return;
    
    const clearSearch = document.getElementById('clearSearch');
    const clientsGrid = document.getElementById('clientsGrid');
    const emptySearch = document.getElementById('emptySearch');
    const clientCards = document.querySelectorAll('.client-card');

    // Search input handler
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        
        // Show/hide clear button
        clearSearch.style.display = searchTerm ? 'block' : 'none';
        
        let visibleCount = 0;
        
        clientCards.forEach(card => {
            const code = card.getAttribute('data-code');
            const name = card.getAttribute('data-name');
            const matches = code.includes(searchTerm) || name.includes(searchTerm);
            
            if(matches) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        // Show empty state if no results
        if(visibleCount === 0 && searchTerm !== '') {
            clientsGrid.style.display = 'none';
            emptySearch.style.display = 'block';
        } else {
            clientsGrid.style.display = 'grid';
            emptySearch.style.display = 'none';
        }
    });

    // Clear search button
    clearSearch.addEventListener('click', function() {
        searchInput.value = '';
        searchInput.focus();
        clearSearch.style.display = 'none';
        
        clientCards.forEach(card => {
            card.style.display = 'block';
        });
        
        clientsGrid.style.display = 'grid';
        emptySearch.style.display = 'none';
    });

    // Focus search on load
    searchInput.focus();

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + K to focus search
        if((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput.focus();
            searchInput.select();
        }
        
        // Escape to clear search
        if(e.key === 'Escape' && searchInput.value) {
            clearSearch.click();
        }
    });
});