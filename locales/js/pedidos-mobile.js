/**
 * pedidos-mobile.js — Comportamiento exclusivo de la vista celular de pedidos.php.
 * No cambia la lógica del pedido: reubica nodos existentes, esconde el encabezado
 * al scrollear y maneja el menú ⋮, el "Ver más" de cada card y el toque en destacados.
 * Depende de pedidos.js (MQ_CELULAR, refrescarCarruselPorVista, scrollToProduct, exportarExcel).
 */
(function () {
    const mq = window.matchMedia(MQ_CELULAR);

    const header      = document.querySelector('.header-container');
    const footer      = document.querySelector('.fixed-footer');
    const filtersBar  = document.querySelector('.filters-bar');
    const filtersGrid = filtersBar ? filtersBar.querySelector('.filters-grid') : null;
    const rubroSelect = document.getElementById('filterRubro');
    const sortSelect  = document.getElementById('sortProducts');
    const menuBtn     = document.getElementById('mMenuBtn');
    const menu        = document.getElementById('mMenu');

    if (!header) return;

    // Lugar original de cada nodo que se mueve, para devolverlo exacto al volver a escritorio.
    // Se guarda el hermano siguiente (puede ser un nodo de texto) y no un índice.
    const origenFiltros = filtersBar  ? { padre: filtersBar.parentNode,  siguiente: filtersBar.nextSibling }  : null;
    const origenRubro   = rubroSelect ? { padre: rubroSelect.parentNode, siguiente: rubroSelect.nextSibling } : null;

    const UMBRAL_OCULTAR = 120; // px de scroll a partir de los cuales el encabezado puede esconderse
    const UMBRAL_TEMBLOR = 6;   // px mínimos de movimiento para cambiar de estado (evita parpadeo)

    // ── Alturas para scroll-margin, espacio inferior y posición del aviso de stock ──
    function medirAlturas() {
        const root = document.documentElement;
        root.style.setProperty('--m-header-h', header.offsetHeight + 'px');
        if (footer) root.style.setProperty('--m-footer-h', footer.offsetHeight + 'px');
    }

    // ── Reubicación de filtros ──────────────────────────────────────────────────
    // En el celular el buscador, el rubro y el orden van dentro del bloque fijo del encabezado.
    // Mover el nodo (y no duplicarlo) mantiene ids únicos y los listeners ya registrados.
    function aplicarVista(enCelular) {
        if (enCelular) {
            if (filtersBar) header.appendChild(filtersBar);
            if (rubroSelect && filtersGrid) filtersGrid.insertBefore(rubroSelect, sortSelect || null);
        } else {
            if (origenRubro)   origenRubro.padre.insertBefore(rubroSelect, origenRubro.siguiente);
            if (origenFiltros) origenFiltros.padre.insertBefore(filtersBar, origenFiltros.siguiente);
            mostrarHeader();
            cerrarMenu();
        }
        medirAlturas();
    }

    // ── Encabezado que se esconde al bajar ──────────────────────────────────────
    let ultimoY = Math.max(0, window.scrollY);
    let rafPendiente = false;

    function mostrarHeader() { header.classList.remove('m-header-hidden'); }

    function ocultarHeader() {
        header.classList.add('m-header-hidden');
        cerrarMenu();
    }

    // Mientras se escribe en el buscador o se elige en un select, el bloque no se va
    function focoEnFiltros() {
        const el = document.activeElement;
        return !!el && header.contains(el) && el.matches('input, select');
    }

    function evaluarScroll() {
        rafPendiente = false;
        if (!mq.matches) return;
        const y = Math.max(0, window.scrollY); // iOS puede dar negativos en el rebote
        if (y <= UMBRAL_OCULTAR) {
            mostrarHeader();
            ultimoY = y;
            return;
        }
        const delta = y - ultimoY;
        if (Math.abs(delta) < UMBRAL_TEMBLOR) return;
        if (delta > 0) {
            if (!focoEnFiltros() && (!menu || menu.hidden)) ocultarHeader();
        } else {
            mostrarHeader();
        }
        ultimoY = y;
    }

    window.addEventListener('scroll', () => {
        if (rafPendiente) return;
        rafPendiente = true;
        requestAnimationFrame(evaluarScroll);
    }, { passive: true });

    // Si el foco entra al bloque (p. ej. con teclado), se muestra
    header.addEventListener('focusin', () => { if (mq.matches) mostrarHeader(); });

    // ── Menú ⋮ ──────────────────────────────────────────────────────────────────
    function abrirMenu() {
        if (!menu) return;
        menu.hidden = false;
        menuBtn.setAttribute('aria-expanded', 'true');
    }

    function cerrarMenu() {
        if (!menu || menu.hidden) return;
        menu.hidden = true;
        menuBtn.setAttribute('aria-expanded', 'false');
    }

    if (menuBtn && menu) {
        menuBtn.addEventListener('click', () => {
            if (menu.hidden) abrirMenu(); else cerrarMenu();
        });

        // Cierra al tocar afuera del menú y de su botón
        document.addEventListener('click', e => {
            if (menu.hidden) return;
            if (menu.contains(e.target) || menuBtn.contains(e.target)) return;
            cerrarMenu();
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && !menu.hidden) {
                cerrarMenu();
                menuBtn.focus();
            }
        });

        menu.addEventListener('click', e => {
            const item = e.target.closest('[data-m-accion]');
            if (!item) return;
            cerrarMenu();
            if (item.dataset.mAccion === 'exportar') {
                exportarExcel();
            } else if (item.dataset.mAccion === 'limpiar-filtros') {
                // Reusa el botón de escritorio para no duplicar su lógica
                const btnClear = document.getElementById('btnClear');
                if (btnClear) btnClear.click();
            }
        });
    }

    // ── "Ver más / Ver menos" de cada card ──────────────────────────────────────
    // Delegado en el tbody: ordenar mueve los <tr> y filtrar solo cambia su display,
    // así que la clase is-expanded del <tr> sobrevive a ambos.
    const tbody = document.querySelector('.product-table tbody');
    if (tbody) {
        tbody.addEventListener('click', e => {
            const btn = e.target.closest('.m-ver-mas');
            if (!btn) return;
            const row = btn.closest('tr');
            const abierto = row.classList.toggle('is-expanded');
            btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
            const texto = btn.querySelector('.m-ver-mas-texto');
            if (texto) texto.textContent = abierto ? 'Ver menos' : 'Ver más';
        });
    }

    // ── Destacados: tocar cualquier parte de la tarjeta lleva al artículo ───────
    // La imagen y el nombre ya tienen su onclick; acá se cubre el resto de la tarjeta.
    document.querySelectorAll('.suggestions-carousel').forEach(carrusel => {
        carrusel.addEventListener('click', e => {
            if (!mq.matches) return;
            const item = e.target.closest('.carousel-item');
            if (!item || e.target.closest('img, .product-name, .add-btn')) return;
            const codigo = item.querySelector('.product-code');
            if (codigo) scrollToProduct(codigo.textContent.trim());
        });
    });

    // ── Inicio y cambios de ancho/orientación ───────────────────────────────────
    // En escritorio no se toca el DOM al cargar: todo queda donde lo dejó el HTML
    if (mq.matches) aplicarVista(true);
    else medirAlturas();

    const alCambiarVista = e => {
        aplicarVista(e.matches);
        // Recalcula el top del thead sticky de escritorio (pedidos.js y envio.js escuchan resize)
        window.dispatchEvent(new Event('resize'));
        if (typeof refrescarCarruselPorVista === 'function') refrescarCarruselPorVista();
    };
    // Safari < 14 solo tiene addListener
    if (mq.addEventListener) mq.addEventListener('change', alCambiarVista);
    else mq.addListener(alCambiarVista);

    // Las alturas cambian con fuentes, chips o al rotar
    if ('ResizeObserver' in window) {
        const ro = new ResizeObserver(medirAlturas);
        ro.observe(header);
        if (footer) ro.observe(footer);
    } else {
        window.addEventListener('resize', medirAlturas);
    }
})();
