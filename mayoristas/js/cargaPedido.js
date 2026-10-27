// Totales y contadores
let totalArticulos = 0;
let totalCantidad = 0;
let totalPrecio = 0;

// Calcular totales
function calcularTotales() {
    totalArticulos = 0;
    totalCantidad = 0;
    totalPrecio = 0;
    
    const inputs = document.querySelectorAll('.qty-input');
    inputs.forEach(input => {
        const cant = parseInt(input.value) || 0;
        if(cant > 0) {
            totalArticulos++;
            totalCantidad += cant;
            
            // Obtener precio del producto desde data-attribute
            const row = input.closest('tr');
            const precio = parseFloat(row.getAttribute('data-precio')) || 0;
            totalPrecio += (precio * cant);
            
            input.classList.add('has-value');
        } else {
            input.classList.remove('has-value');
        }
    });
    
    document.getElementById('totalArticulos').textContent = totalArticulos;
    document.getElementById('totalCantidad').textContent = totalCantidad;
    
    // Actualizar total en precio formateado
    const totalPrecioElement = document.getElementById('totalPrecio');
    if(totalPrecioElement) {
        totalPrecioElement.textContent = '$' + parseInt(totalPrecio).toLocaleString('es-AR');
    }
    
    // Habilitar/deshabilitar botón submit
    const submitBtn = document.getElementById('submitBtn');
    if(submitBtn) {
        submitBtn.disabled = totalArticulos === 0;
    }
}

// Quick actions - botones +/-
function setupQuickActions() {
    const rows = document.querySelectorAll('.product-table tbody tr');
    
    rows.forEach(row => {
        const input = row.querySelector('.qty-input');
        const btnMinus = row.querySelector('.btn-minus');
        const btnPlus = row.querySelector('.btn-plus');
        
        if(btnMinus) {
            btnMinus.addEventListener('click', () => {
                const currentValue = parseInt(input.value) || 0;
                if(currentValue > 0) {
                    input.value = currentValue - 1;
                    calcularTotales();
                }
            });
        }
        
        if(btnPlus) {
            btnPlus.addEventListener('click', () => {
                const currentValue = parseInt(input.value) || 0;
                const stock = parseInt(input.getAttribute('data-stock')) || 999;
                if(currentValue < stock) {
                    input.value = currentValue + 1;
                    calcularTotales();
                }
            });
        }
    });
}

// Toast de stock insuficiente
function mostrarToastStock(input, stock) {
    const existente = document.getElementById('toast-stock');
    if (existente) existente.remove();

    const row = input.closest('tr');
    const codigo = row ? (row.querySelector('input[name="codArt[]"]')?.value || '') : '';

    const toast = document.createElement('div');
    toast.id = 'toast-stock';
    toast.style.cssText = [
        'position:fixed', 'bottom:90px', 'right:24px', 'z-index:9999',
        'background:#fff', 'border-radius:12px', 'box-shadow:0 8px 32px rgba(0,0,0,0.18)',
        'border-left:5px solid #f59e0b', 'padding:16px 20px',
        'display:flex', 'align-items:flex-start', 'gap:14px',
        'min-width:280px', 'max-width:360px',
        'animation:slideInToast .25s ease'
    ].join(';');

    toast.innerHTML = `
        <div style="font-size:1.6rem; color:#f59e0b; margin-top:2px;"><i class="fas fa-exclamation-triangle"></i></div>
        <div style="flex:1;">
            <div style="font-weight:700; color:#92400e; margin-bottom:4px;">Stock insuficiente</div>
            ${codigo ? `<div style="font-size:.78rem; color:#6b7280; margin-bottom:6px; font-family:monospace;">${codigo}</div>` : ''}
            <div style="font-size:.88rem; color:#374151;">
                Stock disponible: <strong style="color:#10b981;">${stock} u.</strong><br>
                Se ajustó la cantidad automáticamente.
            </div>
        </div>
        <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:1rem;padding:0;"><i class="fas fa-times"></i></button>
    `;

    if (!document.getElementById('toast-keyframes')) {
        const style = document.createElement('style');
        style.id = 'toast-keyframes';
        style.textContent = '@keyframes slideInToast{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:translateX(0)}}';
        document.head.appendChild(style);
    }

    document.body.appendChild(toast);
    setTimeout(() => { if (toast.parentElement) toast.remove(); }, 4000);
}

// Verificar stock
function verificarStock(input) {
    const cantidad = parseInt(input.value) || 0;
    const stock = parseInt(input.getAttribute('data-stock')) || 0;

    if(cantidad > stock) {
        input.classList.add('error');
        input.value = stock;
        mostrarToastStock(input, stock);
    } else {
        input.classList.remove('error');
    }
}

// Variables globales del carrusel
let carouselPositions = {
    top: 0,
    tendencia: 0,
    nuevo: 0
};

// Variables para autoplay
let carouselIntervals = {
    top: null,
    tendencia: null,
    nuevo: null
};

let carouselPaused = {
    top: false,
    tendencia: false,
    nuevo: false
};

// Iniciar autoplay del carrusel
function startCarouselAutoplay(tipo) {
    // Detener autoplay previo si existe
    stopCarouselAutoplay(tipo);
    
    carouselIntervals[tipo] = setInterval(() => {
        if(!carouselPaused[tipo]) {
            const carousel = document.getElementById(`carousel-${tipo}`);
            if(!carousel) return;
            
            const cards = carousel.querySelectorAll('.suggestion-card');
            const totalCards = cards.length;
            const cardsPerPage = 7;
            const totalPages = Math.ceil(totalCards / cardsPerPage);
            
            // Avanzar a la siguiente página o volver al inicio
            if(carouselPositions[tipo] < totalPages - 1) {
                moveCarousel(tipo, 1);
            } else {
                // Volver al inicio
                carouselPositions[tipo] = -1;
                moveCarousel(tipo, 1);
            }
        }
    }, 6000); // 6 segundos
}

// Detener autoplay del carrusel
function stopCarouselAutoplay(tipo) {
    if(carouselIntervals[tipo]) {
        clearInterval(carouselIntervals[tipo]);
        carouselIntervals[tipo] = null;
    }
}

// Pausar autoplay (mantiene el intervalo pero no avanza)
function pauseCarouselAutoplay(tipo) {
    carouselPaused[tipo] = true;
}

// Reanudar autoplay
function resumeCarouselAutoplay(tipo) {
    carouselPaused[tipo] = false;
}

// Variables para filtros activos
let activeFilters = {
    search: '',
    rubro: '',
    sort: 'default'
};

// Debounce para búsqueda
let searchDebounceTimer = null;

// Actualizar chips de filtros activos
function updateActiveFilters() {
    const chipsContainer = document.getElementById('chipsContainer');
    const activeFiltersSection = document.getElementById('activeFiltersChips');
    
    if(!chipsContainer || !activeFiltersSection) return;
    
    chipsContainer.innerHTML = '';
    let hasActiveFilters = false;
    
    // Chip de búsqueda
    if(activeFilters.search) {
        hasActiveFilters = true;
        const chip = createFilterChip('Búsqueda', activeFilters.search, 'search');
        chipsContainer.appendChild(chip);
    }
    
    // Chip de rubro
    if(activeFilters.rubro) {
        hasActiveFilters = true;
        const chip = createFilterChip('Rubro', activeFilters.rubro, 'rubro');
        chipsContainer.appendChild(chip);
    }
    
    // Chip de ordenamiento
    if(activeFilters.sort && activeFilters.sort !== 'default') {
        hasActiveFilters = true;
        const sortSelect = document.getElementById('sortProducts');
        const sortText = sortSelect?.options[sortSelect.selectedIndex]?.text || activeFilters.sort;
        const chip = createFilterChip('Orden', sortText, 'sort');
        chipsContainer.appendChild(chip);
    }
    
    activeFiltersSection.style.display = hasActiveFilters ? 'flex' : 'none';
}

// Crear chip de filtro
function createFilterChip(label, value, type) {
    const chip = document.createElement('div');
    chip.className = 'filter-chip';
    chip.innerHTML = `
        <span class="chip-label">${label}:</span>
        <span>${value}</span>
        <button class="chip-remove" onclick="removeFilter('${type}')" aria-label="Eliminar filtro ${label}">
            <i class="fas fa-times"></i>
        </button>
    `;
    return chip;
}

// Remover filtro específico
function removeFilter(type) {
    switch(type) {
        case 'search':
            const searchInput = document.getElementById('searchProduct');
            if(searchInput) {
                searchInput.value = '';
                activeFilters.search = '';
                document.getElementById('clearSearchBtn').style.display = 'none';
            }
            break;
        case 'rubro':
            const rubroSelect = document.getElementById('filterRubro');
            if(rubroSelect) {
                rubroSelect.value = '';
                activeFilters.rubro = '';
            }
            break;
        case 'sort':
            const sortSelect = document.getElementById('sortProducts');
            if(sortSelect) {
                sortSelect.value = 'default';
                activeFilters.sort = 'default';
            }
            break;
    }
    
    updateActiveFilters();
    filtrarProductos();
    updateResultsCounter();
}

// Limpiar todos los filtros (excepto rubro)
function clearAllFilters() {
    const searchInput = document.getElementById('searchProduct');
    const sortSelect = document.getElementById('sortProducts');
    
    if(searchInput) {
        searchInput.value = '';
        activeFilters.search = '';
        document.getElementById('clearSearchBtn').style.display = 'none';
    }
    
    if(sortSelect) {
        sortSelect.value = 'default';
        activeFilters.sort = 'default';
    }
    
    updateActiveFilters();
    filtrarProductos();
    updateResultsCounter();
}

// Actualizar contador de resultados
function updateResultsCounter() {
    const counter = document.getElementById('resultsCounter');
    if(!counter) return;
    
    const rows = document.querySelectorAll('.product-table tbody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        if(row.style.display !== 'none') {
            visibleCount++;
        }
    });
    
    const totalProducts = rows.length;
    
    if(visibleCount === totalProducts) {
        counter.innerHTML = `<i class="fas fa-box-open"></i> ${totalProducts} productos disponibles`;
    } else {
        counter.innerHTML = `<i class="fas fa-filter"></i> ${visibleCount} de ${totalProducts} productos`;
    }
}

// Filtrar productos
function filtrarProductos() {
    const searchTerm = document.getElementById('searchProduct')?.value.toLowerCase() || '';
    const rubroFilter = document.getElementById('filterRubro')?.value || '';
    
    // Actualizar filtros activos
    activeFilters.search = searchTerm;
    activeFilters.rubro = rubroFilter;
    
    // Mostrar/ocultar botón clear search
    const clearBtn = document.getElementById('clearSearchBtn');
    if(clearBtn) {
        clearBtn.style.display = searchTerm ? 'block' : 'none';
    }
    
    // Filtrar tabla de productos
    const rows = document.querySelectorAll('.product-table tbody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const code = row.getAttribute('data-code')?.toLowerCase() || '';
        const description = row.getAttribute('data-description')?.toLowerCase() || '';
        const rubro = row.getAttribute('data-rubro') || '';
        
        const matchSearch = code.includes(searchTerm) || description.includes(searchTerm);
        const matchRubro = !rubroFilter || rubro === rubroFilter;
        
        if(matchSearch && matchRubro) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Actualizar carruseles de sugerencias
    if(typeof allTopProducts !== 'undefined') {
        actualizarCarousel('top', allTopProducts, rubroFilter);
    }
    if(typeof allTendenciaProducts !== 'undefined') {
        actualizarCarousel('tendencia', allTendenciaProducts, rubroFilter);
    }
    if(typeof allNuevosProducts !== 'undefined') {
        actualizarCarousel('nuevo', allNuevosProducts, rubroFilter);
    }
    
    // Actualizar chips y contador
    updateActiveFilters();
    updateResultsCounter();
    
    // Guardar en sessionStorage
    sessionStorage.setItem('productFilters', JSON.stringify(activeFilters));
}

// Limpiar cantidades
function limpiarCantidades() {
    if(confirm('¿Desea limpiar todas las cantidades ingresadas?')) {
        const inputs = document.querySelectorAll('.qty-input');
        inputs.forEach(input => {
            input.value = 0;
            input.classList.remove('has-value');
        });
        calcularTotales();
    }
}

// Modal de imagen
function mostrarImagen(src) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    if(modal && modalImg) {
        modalImg.src = src;
        modal.offsetHeight;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function cerrarModal(e) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    
    if(e && e.target === modalImg) {
        return;
    }
    
    if(modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        setTimeout(() => {
            if(modalImg && !modal.classList.contains('active')) {
                modalImg.src = '';
            }
        }, 400);
    }
}

// Validar antes de enviar
function validarPedido() {
    if(totalArticulos === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Pedido vacío',
            text: 'Debe ingresar al menos un artículo con cantidad',
            confirmButtonColor: '#3c4043',
            confirmButtonText: 'Entendido'
        });
        return false;
    }
    
    // Formatear importe total
    const importeFormateado = new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(totalPrecio);
    
    // Mostrar confirmación mejorada
    Swal.fire({
        title: '¿Confirmar pedido?',
        html: `
            <div style="text-align: left; padding: 10px 20px;">
                <p style="margin-bottom: 15px; font-size: 15px;">Va a realizar un pedido con:</p>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <li style="padding: 8px 0; border-bottom: 1px solid #eee;">
                        <i class="fas fa-box" style="width: 25px; color: #3c4043;"></i>
                        <strong>${totalArticulos}</strong> artículo${totalArticulos !== 1 ? 's' : ''}
                    </li>
                    <li style="padding: 8px 0; border-bottom: 1px solid #eee;">
                        <i class="fas fa-cubes" style="width: 25px; color: #3c4043;"></i>
                        <strong>${totalCantidad}</strong> unidad${totalCantidad !== 1 ? 'es' : ''} totales
                    </li>
                    <li style="padding: 8px 0;">
                        <i class="fas fa-money-bill-wave" style="width: 25px; color: #28a745;"></i>
                        <strong style="color: #28a745; font-size: 18px;">${importeFormateado}</strong>
                    </li>
                </ul>
                <p style="margin-top: 15px; font-size: 13px; color: #666;">
                    <i class="fas fa-info-circle"></i> El pedido será procesado y confirmado por el sistema
                </p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#dc3545',
        confirmButtonText: '<i class="fas fa-paper-plane"></i> Sí, enviar pedido',
        cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
        customClass: {
            confirmButton: 'btn-confirm-pedido',
            cancelButton: 'btn-cancel-pedido',
            actions: 'swal-actions-spacing'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            // Mostrar spinner de carga
            Swal.fire({
                title: 'Procesando pedido...',
                html: '<div style="padding: 20px;"><i class="fas fa-spinner fa-spin fa-3x" style="color: #3c4043;"></i><p style="margin-top: 15px; color: #666;">Por favor espere mientras se procesa su pedido</p></div>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    // Filtrar items sin cantidad
                    const form = document.getElementById('formPedido');
                    const inputs = form.querySelectorAll('.qty-input');
                    
                    inputs.forEach(input => {
                        const cant = parseInt(input.value) || 0;
                        const row = input.closest('tr');
                        
                        if(cant === 0) {
                            const hiddenInputs = row.querySelectorAll('input[type="hidden"]');
                            hiddenInputs.forEach(hidden => hidden.remove());
                            input.remove();
                        }
                    });
                    
                    // Enviar formulario
                    setTimeout(() => {
                        form.submit();
                    }, 500);
                }
            });
        }
    });
    
    return false; // Prevenir envío automático
}

// Agregar producto rápido desde sugerencias
function quickAddProduct(codigo, cantidad) {
    const rows = document.querySelectorAll('.product-table tbody tr');
    
    rows.forEach(row => {
        const rowCode = row.getAttribute('data-code');
        if(rowCode === codigo.toLowerCase()) {
            const input = row.querySelector('.qty-input');
            if(input) {
                input.value = cantidad;
                input.classList.add('has-value');
                
                // Scroll a la fila
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Highlight temporal
                row.classList.add('highlight');
                setTimeout(() => row.classList.remove('highlight'), 2000);
                
                // Actualizar totales
                calcularTotales();
            }
        }
    });
}

// Ordenar tabla por columnas
function setupColumnSorting() {
    const sortableHeaders = document.querySelectorAll('.product-table th.sortable');
    
    sortableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const sortBy = this.getAttribute('data-sort');
            const tbody = document.querySelector('.product-table tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
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
                    case 'codigo':
                        aVal = a.getAttribute('data-code') || '';
                        bVal = b.getAttribute('data-code') || '';
                        return isAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                        
                    case 'descripcion':
                        aVal = a.getAttribute('data-description') || '';
                        bVal = b.getAttribute('data-description') || '';
                        return isAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                        
                    case 'rubro':
                        aVal = a.getAttribute('data-rubro') || '';
                        bVal = b.getAttribute('data-rubro') || '';
                        return isAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                        
                    case 'stock':
                        aVal = parseInt(a.getAttribute('data-stock-num')) || 0;
                        bVal = parseInt(b.getAttribute('data-stock-num')) || 0;
                        return isAsc ? aVal - bVal : bVal - aVal;
                        
                    case 'precio':
                        aVal = parseFloat(a.getAttribute('data-precio')) || 0;
                        bVal = parseFloat(b.getAttribute('data-precio')) || 0;
                        return isAsc ? aVal - bVal : bVal - aVal;
                        
                    default:
                        return 0;
                }
            });
            
            // Reordenar DOM
            rows.forEach(row => tbody.appendChild(row));
        });
    });
}

// Ordenar productos
function setupSorting() {
    const sortSelect = document.getElementById('sortProducts');
    if(!sortSelect) return;
    
    sortSelect.addEventListener('change', function() {
        const sortType = this.value;
        activeFilters.sort = sortType;
        
        const tbody = document.querySelector('.product-table tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        if(sortType === 'default') {
            updateActiveFilters();
            return;
        }
        
        rows.sort((a, b) => {
            switch(sortType) {
                case 'ventas-desc':
                    const ventasA = parseInt(a.getAttribute('data-ventas')) || 0;
                    const ventasB = parseInt(b.getAttribute('data-ventas')) || 0;
                    return ventasB - ventasA;
                    
                case 'ventas-asc':
                    const ventasA2 = parseInt(a.getAttribute('data-ventas')) || 0;
                    const ventasB2 = parseInt(b.getAttribute('data-ventas')) || 0;
                    return ventasA2 - ventasB2;
                    
                case 'nuevos':
                    const esNuevoA = parseInt(a.getAttribute('data-es-nuevo')) || 0;
                    const esNuevoB = parseInt(b.getAttribute('data-es-nuevo')) || 0;
                    if(esNuevoA === 1 && esNuevoB !== 1) return -1;
                    if(esNuevoA !== 1 && esNuevoB === 1) return 1;
                    return 0;
                    
                case 'precio-asc':
                    const precioA1 = parseFloat(a.getAttribute('data-precio')) || 0;
                    const precioB1 = parseFloat(b.getAttribute('data-precio')) || 0;
                    return precioA1 - precioB1;
                    
                case 'precio-desc':
                    const precioA2 = parseFloat(a.getAttribute('data-precio')) || 0;
                    const precioB2 = parseFloat(b.getAttribute('data-precio')) || 0;
                    return precioB2 - precioA2;
                    
                case 'stock-desc':
                    const stockA = parseInt(a.getAttribute('data-stock-num')) || 0;
                    const stockB = parseInt(b.getAttribute('data-stock-num')) || 0;
                    return stockB - stockA;
                    
                case 'stock-asc':
                    const stockA2 = parseInt(a.getAttribute('data-stock-num')) || 0;
                    const stockB2 = parseInt(b.getAttribute('data-stock-num')) || 0;
                    return stockA2 - stockB2;
                    
                case 'az':
                    const descA = a.getAttribute('data-description')?.toLowerCase() || '';
                    const descB = b.getAttribute('data-description')?.toLowerCase() || '';
                    return descA.localeCompare(descB);
                    
                case 'za':
                    const descA2 = a.getAttribute('data-description')?.toLowerCase() || '';
                    const descB2 = b.getAttribute('data-description')?.toLowerCase() || '';
                    return descB2.localeCompare(descA2);
                    
                default:
                    return 0;
            }
        });
        
        rows.forEach(row => tbody.appendChild(row));
        updateActiveFilters();
        
        // Guardar en sessionStorage
        sessionStorage.setItem('productFilters', JSON.stringify(activeFilters));
    });
}

// Renderizar tarjeta de producto
function renderProductCard(prod, tipo, index) {
    const codigo = prod.COD_ARTICU.trim();
    let descripcion = prod.DESCRIPCIO.trim();
    const precio = parseFloat(prod.PRECIO_MAYO);
    const rubro = prod.RUBRO.trim();
    const foto = codigo.substring(0, 13) + '.jpg';
    const unidades30d = prod.UNIDADES_30D || 0;
    
    // Detectar si es producto en liquidación (SALE)
    const enLiquidacion = descripcion.indexOf('-- SALE! --') !== -1;
    if(enLiquidacion) {
        descripcion = descripcion.replace('-- SALE! --', '').trim();
    }
    
    const descCorta = descripcion.length > 40 ? descripcion.substring(0, 40) + '...' : descripcion;
    
    // Determinar si debe estar visible (solo primeros 7)
    const displayStyle = index < 7 ? '' : 'style="display: none;"';
    
    let badge = '';
    if(tipo === 'top') {
        badge = `<span class="suggestion-badge top">🔥 Más Vendidos</span>`;
    } else if(tipo === 'tendencia') {
        badge = `<span class="suggestion-badge trending">📈 En Tendencia</span>`;
    } else if(tipo === 'nuevo') {
        badge = `<span class="suggestion-badge new">✨ Novedad</span>`;
    }
    
    // Badge SALE si corresponde
    const saleBadge = enLiquidacion ? '<span class="badge-sale">SALE</span>' : '';
    
    return `
        <div class="suggestion-card" data-codigo="${codigo.toLowerCase()}" data-rubro="${rubro}" ${displayStyle}>
            <div class="suggestion-image">
                <img src="https://app.xl.com.ar/imagenes/${foto}" alt="${descripcion}">
                ${badge}
                ${saleBadge}
            </div>
            <div class="suggestion-info">
                <span class="suggestion-code">${codigo}</span>
                <p class="suggestion-desc">${descCorta}</p>
                <div class="suggestion-footer">
                    <span class="suggestion-price">$${parseInt(precio).toLocaleString('es-AR')}</span>
                    <button class="btn-quick-add" onclick="quickAddProduct('${codigo}', 6)">
                        <i class="fas fa-plus"></i> Agregar 6
                    </button>
                </div>
            </div>
        </div>
    `;
}

// Actualizar carrusel según filtro de rubro
function actualizarCarousel(tipo, productos, rubroFilter) {
    const carousel = document.getElementById(`carousel-${tipo}`);
    if(!carousel) return;
    
    // Filtrar productos por rubro
    let productosFiltrados = productos;
    if(rubroFilter) {
        productosFiltrados = productos.filter(p => p.RUBRO.trim() === rubroFilter);
    }
    
    // Tomar los primeros 21 ordenados por ventas (3 páginas de 7)
    const productosDestacados = productosFiltrados.slice(0, 21);
    
    // Renderizar productos con índice para control de visibilidad
    carousel.innerHTML = productosDestacados.map((prod, index) => renderProductCard(prod, tipo, index)).join('');
    
    // Resetear posición del carrusel
    carouselPositions[tipo] = 0;
    
    // Actualizar indicadores
    actualizarIndicadores(tipo, productosDestacados.length);
    
    // Actualizar botones
    actualizarBotonesCarousel(tipo, productosDestacados.length);
}

// Mover carrusel
function moveCarousel(tipo, direccion, manual = false) {
    const carousel = document.getElementById(`carousel-${tipo}`);
    if(!carousel) return;
    
    const cards = carousel.querySelectorAll('.suggestion-card');
    const totalCards = cards.length;
    const cardsPerPage = 7;
    const totalPages = Math.ceil(totalCards / cardsPerPage);
    
    carouselPositions[tipo] += direccion;
    
    // Limitar posición
    if(carouselPositions[tipo] < 0) carouselPositions[tipo] = 0;
    if(carouselPositions[tipo] >= totalPages) carouselPositions[tipo] = totalPages - 1;
    
    // Si es interacción manual, reiniciar autoplay
    if(manual) {
        startCarouselAutoplay(tipo);
    }
    
    // Fade out actual
    carousel.style.opacity = '0';
    carousel.style.transform = 'translateY(10px)';
    
    setTimeout(() => {
        // Mostrar/ocultar tarjetas
        const startIndex = carouselPositions[tipo] * cardsPerPage;
        const endIndex = startIndex + cardsPerPage;
        
        cards.forEach((card, index) => {
            if(index >= startIndex && index < endIndex) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
        
        // Fade in
        carousel.style.opacity = '1';
        carousel.style.transform = 'translateY(0)';
        
        // Actualizar indicadores
        actualizarIndicadores(tipo, totalCards);
        actualizarBotonesCarousel(tipo, totalCards);
    }, 600);
}

// Actualizar indicadores del carrusel
function actualizarIndicadores(tipo, totalCards) {
    const indicatorsContainer = document.getElementById(`indicators-${tipo}`);
    if(!indicatorsContainer) return;
    
    const cardsPerPage = 7;
    const totalPages = Math.ceil(totalCards / cardsPerPage);
    
    if(totalPages <= 1) {
        indicatorsContainer.innerHTML = '';
        return;
    }
    
    let html = '';
    for(let i = 0; i < totalPages; i++) {
        const activeClass = i === carouselPositions[tipo] ? 'active' : '';
        html += `<div class="carousel-indicator ${activeClass}" onclick="goToPage('${tipo}', ${i})"></div>`;
    }
    indicatorsContainer.innerHTML = html;
}

// Ir a página específica
function goToPage(tipo, page) {
    const carousel = document.getElementById(`carousel-${tipo}`);
    if(!carousel) return;
    
    const cards = carousel.querySelectorAll('.suggestion-card');
    const totalCards = cards.length;
    const cardsPerPage = 7;
    const totalPages = Math.ceil(totalCards / cardsPerPage);
    
    if(page < 0 || page >= totalPages) return;
    if(page === carouselPositions[tipo]) return; // Ya estamos en esa página
    
    carouselPositions[tipo] = page;
    
    // Reiniciar autoplay por interacción manual
    startCarouselAutoplay(tipo);
    
    // Fade out actual
    carousel.style.opacity = '0';
    carousel.style.transform = 'translateY(10px)';
    
    setTimeout(() => {
        const startIndex = page * cardsPerPage;
        const endIndex = startIndex + cardsPerPage;
        
        cards.forEach((card, index) => {
            if(index >= startIndex && index < endIndex) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
        
        // Fade in
        carousel.style.opacity = '1';
        carousel.style.transform = 'translateY(0)';
        
        actualizarIndicadores(tipo, totalCards);
        actualizarBotonesCarousel(tipo, totalCards);
    }, 600);
}

// Actualizar botones del carrusel
function actualizarBotonesCarousel(tipo, totalCards) {
    const wrapper = document.querySelector(`[data-content="${tipo}"]`);
    if(!wrapper) return;
    
    const prevBtn = wrapper.querySelector('.carousel-btn.prev');
    const nextBtn = wrapper.querySelector('.carousel-btn.next');
    
    const cardsPerPage = 7;
    const totalPages = Math.ceil(totalCards / cardsPerPage);
    
    if(prevBtn) {
        prevBtn.disabled = carouselPositions[tipo] === 0;
    }
    
    if(nextBtn) {
        nextBtn.disabled = carouselPositions[tipo] >= totalPages - 1;
    }
}

// Inicializar tooltips con datos
function inicializarTooltips() {
    const badges = document.querySelectorAll('.sales-badge');
    badges.forEach(badge => {
        const vendidos = badge.getAttribute('data-vendidos');
        const clientes = badge.getAttribute('data-clientes');
        
        if(vendidos && clientes) {
            const tooltipText = `${parseInt(vendidos).toLocaleString('es-AR')} vendidos | ${clientes} clientes`;
            badge.setAttribute('data-tooltip', tooltipText);
        } else if(vendidos) {
            const tooltipText = `${parseInt(vendidos).toLocaleString('es-AR')} unidades vendidas`;
            badge.setAttribute('data-tooltip', tooltipText);
        }
    });
}

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips
    inicializarTooltips();
    
    // Debug: verificar datos
    console.log('TOP Products:', typeof allTopProducts !== 'undefined' ? allTopProducts.length : 'undefined');
    console.log('TENDENCIA Products:', typeof allTendenciaProducts !== 'undefined' ? allTendenciaProducts.length : 'undefined');
    console.log('NUEVO Products:', typeof allNuevosProducts !== 'undefined' ? allNuevosProducts.length : 'undefined');
    
    // Inicializar carruseles
    if(typeof allTopProducts !== 'undefined' && allTopProducts.length > 0) {
        actualizarCarousel('top', allTopProducts, '');
        startCarouselAutoplay('top');
    }
    if(typeof allTendenciaProducts !== 'undefined' && allTendenciaProducts.length > 0) {
        actualizarCarousel('tendencia', allTendenciaProducts, '');
        startCarouselAutoplay('tendencia');
    }
    if(typeof allNuevosProducts !== 'undefined' && allNuevosProducts.length > 0) {
        actualizarCarousel('nuevo', allNuevosProducts, '');
        startCarouselAutoplay('nuevo');
    }
    
    // Event listeners para pause/resume en hover de carruseles
    ['top', 'tendencia', 'nuevo'].forEach(tipo => {
        const wrapper = document.querySelector(`[data-content="${tipo}"]`);
        if(wrapper) {
            wrapper.addEventListener('mouseenter', () => {
                pauseCarouselAutoplay(tipo);
            });
            
            wrapper.addEventListener('mouseleave', () => {
                resumeCarouselAutoplay(tipo);
            });
            
            // Event listeners para botones de navegación (marcados como manuales)
            const prevBtn = wrapper.querySelector('.carousel-btn.prev');
            const nextBtn = wrapper.querySelector('.carousel-btn.next');
            
            if(prevBtn) {
                prevBtn.addEventListener('click', () => {
                    moveCarousel(tipo, -1, true);
                });
            }
            
            if(nextBtn) {
                nextBtn.addEventListener('click', () => {
                    moveCarousel(tipo, 1, true);
                });
            }
        }
    });
    
    // Setup tabs de sugerencias
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.suggestions-carousel-wrapper');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tab = this.getAttribute('data-tab');
            
            // Desactivar todos
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            // Activar seleccionado
            this.classList.add('active');
            const wrapper = document.querySelector(`[data-content="${tab}"]`);
            if(wrapper) {
                wrapper.classList.add('active');
            }
        });
    });
    
    // Setup quick actions
    setupQuickActions();
    
    // Setup column sorting
    setupColumnSorting();
    
    // Setup sorting
    setupSorting();
    
    // Event listeners para inputs de cantidad
    const inputs = document.querySelectorAll('.qty-input');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            calcularTotales();
        });
        
        input.addEventListener('blur', function() {
            verificarStock(this);
            calcularTotales();
        });
        
        input.addEventListener('keypress', function(e) {
            if(!/[0-9]/.test(e.key) && e.key !== 'Enter') {
                e.preventDefault();
            }
        });
    });
    
    // Event listeners para filtros
    const searchProduct = document.getElementById('searchProduct');
    if(searchProduct) {
        searchProduct.addEventListener('input', function() {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => {
                filtrarProductos();
            }, 300);
        });
    }
    
    const filterRubro = document.getElementById('filterRubro');
    if(filterRubro) {
        filterRubro.addEventListener('change', filtrarProductos);
    }
    
    // Botón limpiar búsqueda
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    if(clearSearchBtn) {
        clearSearchBtn.addEventListener('click', function() {
            const searchInput = document.getElementById('searchProduct');
            if(searchInput) {
                searchInput.value = '';
                activeFilters.search = '';
                this.style.display = 'none';
                filtrarProductos();
            }
        });
    }
    
    // Botón limpiar todos los filtros
    const btnClearAllFilters = document.getElementById('btnClearAllFilters');
    if(btnClearAllFilters) {
        btnClearAllFilters.addEventListener('click', clearAllFilters);
    }
    
    // Restaurar filtros desde sessionStorage
    const savedFilters = sessionStorage.getItem('productFilters');
    if(savedFilters) {
        try {
            const filters = JSON.parse(savedFilters);
            if(filters.search && searchProduct) {
                searchProduct.value = filters.search;
            }
            if(filters.sort) {
                const sortSelect = document.getElementById('sortProducts');
                if(sortSelect) sortSelect.value = filters.sort;
            }
            activeFilters = filters;
            filtrarProductos();
        } catch(e) {
            console.error('Error al restaurar filtros:', e);
        }
    }
    
    // Event listener para el botón limpiar
    const btnClear = document.getElementById('btnClear');
    if(btnClear) {
        btnClear.addEventListener('click', limpiarCantidades);
    }
    
    // Modal de imagen
    const modal = document.getElementById('imageModal');
    if(modal) {
        modal.addEventListener('click', cerrarModal);
    }
    
    // Calcular totales iniciales
    calcularTotales();
    
    // Animación de filas
    const rows = document.querySelectorAll('.product-table tbody tr');
    rows.forEach((row, index) => {
        row.style.animationDelay = `${index * 0.02}s`;
    });
});

// Atajos de teclado
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + F para buscar
    if((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        const searchInput = document.getElementById('searchProduct');
        if(searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }
    
    // Escape para limpiar búsqueda
    if(e.key === 'Escape') {
        const searchInput = document.getElementById('searchProduct');
        if(searchInput && searchInput.value) {
            searchInput.value = '';
            filtrarProductos();
        }
    }
});