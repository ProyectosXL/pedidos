const form = document.getElementById("formulario");
let b = 0;
let stock;
let stockModificado = [];
let indice;
let preloader=document.querySelector('.container');
let documentTitle=document.title;//será usado para identificar si se deberá traer stock de art generales o accesorios en traerStock.php

let totalArticulos = document.getElementById("total");
let totalPrecio = document.getElementById("totalPrecio");

let artCargados = [];
let filasResaltadas = []; // Para guardar las filas resaltadas por problemas de stock
let valoresInicialesSesion = new WeakMap();

function normalizarValorInput(valor) {
  return String(parseInt(valor, 10) || 0);
}

function marcarCasilleroModificadoEnSesion(input) {
  if (!input || input.dataset.modificadoSesion === "1") return;
  input.dataset.modificadoSesion = "1";
  input.classList.add("pedido-input-session-modified");
  if (input.parentElement) {
    input.parentElement.classList.add("pedido-cell-session-modified");
    // Estilos inline para asegurar visibilidad sobre cualquier estilo de tabla/tema.
    input.parentElement.style.backgroundColor = "#f0fdf4";
  }
  input.style.backgroundColor = "#dcfce7";
  input.style.border = "2px solid #16a34a";
  input.style.fontWeight = "700";
}

function registrarCambioSesion(input) {
  if (!input) return;
  if (typeof valoresInicialesSesion.get(input) === "undefined") {
    valoresInicialesSesion.set(input, normalizarValorInput(input.value));
  }
  const valorActual = parseInt(input.value, 10) || 0;
  // Si el usuario cargó una cantidad, marcar inmediatamente el casillero.
  if (valorActual > 0) {
    marcarCasilleroModificadoEnSesion(input);
    return;
  }
  const valorInicial = valoresInicialesSesion.get(input);
  if (typeof valorInicial === "undefined") return;
  if (normalizarValorInput(input.value) !== valorInicial) {
    marcarCasilleroModificadoEnSesion(input);
  }
}

function total() {
  var suma = 0;
  var ids = typeof sucursalesIds !== 'undefined' ? sucursalesIds : [];
  var porSuc = {};
  ids.forEach(function(id) { porSuc[id] = 0; });

  var inputs = document.querySelectorAll("#id_tabla input[name^='cantPed_']");
  for (var i = 0; i < inputs.length; i++) {
    var val = parseInt(0 + inputs[i].value, 10) || 0;
    suma += val;
    var name = inputs[i].name;
    var match = name.match(/cantPed_(\d+)\[\]/);
    if (match && porSuc.hasOwnProperty(match[1])) {
      porSuc[match[1]] += val;
    }
  }

  var totalEl = document.getElementById("total");
  if (totalEl) totalEl.value = suma;

  ids.forEach(function(id) {
    var cell = document.getElementById("totalSuc_" + id);
    if (cell) cell.textContent = porSuc[id] || 0;
  });

  var filas = document.querySelectorAll("#id_tabla tbody tr");
  var skuCount = 0;
  for (var r = 0; r < filas.length; r++) {
    var rowInputs = filas[r].querySelectorAll("input[name^='cantPed_']");
    var rowSum = 0;
    for (var j = 0; j < rowInputs.length; j++) {
      rowSum += parseInt(0 + rowInputs[j].value, 10) || 0;
    }
    if (rowSum > 0) skuCount++;
  }
  var skuEl = document.getElementById("totalSKU");
  if (skuEl) skuEl.value = skuCount;
}

function precioTotal() {
  var precioTodos = 0;
  var filas = document.querySelectorAll("#id_tabla tbody tr");
  for (var r = 0; r < filas.length; r++) {
    var precioCell = filas[r].querySelector("#precio");
    var precio = parseInt(0 + (precioCell ? precioCell.textContent : 0), 10) || 0;
    var rowInputs = filas[r].querySelectorAll("input[name^='cantPed_']");
    var rowSum = 0;
    for (var j = 0; j < rowInputs.length; j++) {
      rowSum += parseInt(0 + rowInputs[j].value, 10) || 0;
    }
    precioTodos += precio * rowSum;
  }
  var totalPrecioEl = document.getElementById("totalPrecio");
  if (totalPrecioEl) totalPrecioEl.value = precioTodos;
  var totalPrecioFooter = document.getElementById("totalPrecioFooter");
  if (totalPrecioFooter) totalPrecioFooter.textContent = precioTodos;
}

function pulsar(e) {
  tecla = document.all ? e.keyCode : e.which;
  return tecla != 13;
}

/************************************************************************************************************************************************************ */

/**
 * Valida que el input de cantidad sea un número entero positivo o cero
 * @param {HTMLInputElement} input - El input a validar
 * @returns {boolean} - true si es válido, false si no
 */
function validarInputCantidad(input) {
    let valor = input.value.trim();
    
    // Si está vacío, establecer a 0
    if (valor === '' || valor === null || valor === undefined) {
        input.value = 0;
        return true;
    }
    
    // Validar que sea numérico
    if (!/^\d+$/.test(valor)) {
        Swal.fire({
            icon: 'warning',
            title: 'Cantidad inválida',
            text: 'Debe ingresar solo números enteros mayores o iguales a cero',
            timer: 3000,
            showConfirmButton: false
        });
        input.value = 0;
        input.focus();
        return false;
    }
    
    // Convertir a número entero
    let cantidad = parseInt(valor, 10);
    
    // Validar que sea mayor o igual a cero
    if (cantidad < 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Cantidad inválida',
            text: 'La cantidad debe ser mayor o igual a cero',
            timer: 3000,
            showConfirmButton: false
        });
        input.value = 0;
        input.focus();
        return false;
    }
    
    // Validar que sea un número entero (no decimal)
    if (cantidad != parseFloat(valor)) {
        Swal.fire({
            icon: 'warning',
            title: 'Cantidad inválida',
            text: 'Debe ingresar solo números enteros (sin decimales)',
            timer: 3000,
            showConfirmButton: false
        });
        input.value = Math.floor(parseFloat(valor));
        input.focus();
        return false;
    }
    
    // Establecer el valor como entero
    input.value = cantidad;
    return true;
}

/************************************************************************************************************************************************************ */

/**
 * Guarda un borrador del pedido en localStorage
 */
function guardarBorrador() {
    try {
        let borrador = {
            fecha: new Date().toISOString(),
            articulos: []
        };
        
        // Recopilar todos los inputs con cantidades > 0
        document.querySelectorAll('input[name^="cantPed_"]').forEach(input => {
            let cantidad = parseInt(input.value) || 0;
            if (cantidad > 0) {
                // Buscar el código del artículo en la misma fila
                let fila = input.closest('tr');
                let codigoInput = fila.querySelector('input[name="codArt[]"]');
                let codigo = codigoInput ? codigoInput.value : '';
                
                borrador.articulos.push({
                    nombre: input.name,
                    indice: Array.from(input.closest('tbody').querySelectorAll(`input[name="${input.name}"]`)).indexOf(input),
                    valor: cantidad,
                    codigo: codigo
                });
            }
        });
        
        // Guardar también los totales
        borrador.totales = {
            totalArticulos: document.getElementById("total") ? document.getElementById("total").value : 0,
            totalPrecio: document.getElementById("totalPrecio") ? document.getElementById("totalPrecio").value : 0
        };
        
        localStorage.setItem('pedido_borrador_' + documentTitle, JSON.stringify(borrador));
        console.log('Borrador guardado:', borrador.articulos.length, 'artículos');
    } catch (error) {
        console.error('Error al guardar borrador:', error);
    }
}

/**
 * Restaura el borrador desde localStorage
 */
function restaurarBorrador() {
    try {
        let borrador = JSON.parse(localStorage.getItem('pedido_borrador_' + documentTitle));
        
        if (!borrador || !borrador.articulos || borrador.articulos.length === 0) {
            return false;
        }
        
        let restaurados = 0;
        let codigosRestaurados = [];
        
        borrador.articulos.forEach(art => {
            // Buscar el input por nombre e índice
            let inputs = document.querySelectorAll(`input[name="${art.nombre}"]`);
            if (inputs[art.indice] && inputs[art.indice].value != art.valor) {
                inputs[art.indice].value = art.valor;
                restaurados++;
                if (art.codigo && !codigosRestaurados.includes(art.codigo)) {
                    codigosRestaurados.push(art.codigo);
                }
            }
        });
        
        // Restaurar totales
        if (borrador.totales) {
            if (document.getElementById("total")) {
                document.getElementById("total").value = borrador.totales.totalArticulos;
            }
            if (document.getElementById("totalPrecio")) {
                document.getElementById("totalPrecio").value = borrador.totales.totalPrecio;
            }
        }
        
        // Recalcular totales y actualizar artCargados
        if (typeof total === 'function') total();
        if (typeof precioTotal === 'function') precioTotal();
        
        // Actualizar artCargados con los artículos restaurados
        artCargados = [];
        document.querySelectorAll('input[name^="cantPed_"]').forEach(input => {
            let cantidad = parseInt(input.value) || 0;
            if (cantidad > 0) {
                let fila = input.closest('tr');
                let codigoInput = fila.querySelector('input[name="codArt[]"]');
                let codigo = codigoInput ? codigoInput.value.trim() : '';
                if (codigo) {
                    let totalFila = 0;
                    fila.querySelectorAll("input[type=number][name^='cantPed_']").forEach(el => {
                        totalFila += parseInt(el.value || 0);
                    });
                    artCargados.push({ codigo: codigo, cantidad: totalFila });
                }
            }
        });
        
        console.log('Borrador restaurado:', restaurados, 'artículos');
        return restaurados > 0;
    } catch (error) {
        console.error('Error al restaurar borrador:', error);
        return false;
    }
}

// Hacer la función disponible globalmente
window.restaurarBorrador = restaurarBorrador;

/**
 * Limpia el resaltado de las filas con problemas de stock
 */
function limpiarResaltado() {
    filasResaltadas.forEach(fila => {
        if (fila && fila.style) {
            fila.style.backgroundColor = '';
            fila.style.borderLeft = '';
        }
    });
    filasResaltadas = [];
}

/**
 * Resalta las filas de artículos con problemas de stock
 */
function resaltarArticulosProblema(codigos) {
    limpiarResaltado();
    
    codigos.forEach(codigo => {
        // Buscar todas las filas que contengan el código en la columna CODIGO
        let filas = Array.from(document.querySelectorAll('#id_tabla tbody tr')).filter(fila => {
            // Buscar el input hidden con el código del artículo
            let codigoInput = fila.querySelector('input[name="codArt[]"]');
            if (codigoInput) {
                return codigoInput.value.trim() === codigo.trim();
            }
            // Fallback: buscar en la segunda celda (columna CODIGO)
            let codigoCell = fila.querySelector('td:nth-child(2)');
            return codigoCell && codigoCell.textContent.trim() === codigo.trim();
        });
        
        filas.forEach(fila => {
            fila.style.backgroundColor = '#ffcccc';
            fila.style.borderLeft = '4px solid #dc3545';
            fila.style.transition = 'background-color 0.3s ease';
            fila.classList.add('articulo-problema');
            filasResaltadas.push(fila);
            
            // Agregar listener para quitar resaltado cuando se corrija
            let inputs = fila.querySelectorAll('input[name^="cantPed_"]');
            inputs.forEach(input => {
                input.addEventListener('change', function() {
                    // Verificar si todas las cantidades de la fila son 0 o válidas
                    let totalFila = 0;
                    fila.querySelectorAll('input[name^="cantPed_"]').forEach(inp => {
                        totalFila += parseInt(inp.value || 0);
                    });
                    
                    // Si todas las cantidades son 0, quitar resaltado
                    if (totalFila === 0) {
                        fila.style.backgroundColor = '';
                        fila.style.borderLeft = '';
                        fila.classList.remove('articulo-problema');
                        let index = filasResaltadas.indexOf(fila);
                        if (index > -1) {
                            filasResaltadas.splice(index, 1);
                        }
                    }
                }, { once: false });
            });
        });
    });
    
    // Scroll a la primera fila con problema
    if (filasResaltadas.length > 0) {
        setTimeout(() => {
            filasResaltadas[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 500);
    }
}

/************************************************************************************************************************************************************ */

//guardo el array con todos los input number (cantidad de pedido), del cual se escuchará el evento change para detectar la fila donde se está cambiando el valor
let inputCantidad = document.querySelectorAll("input[type=number][name^='cantPed_']");

inputCantidad.forEach((el) => {
    valoresInicialesSesion.set(el, normalizarValorInput(el.value));
    el.addEventListener("change", function(e) {
        registrarCambioSesion(el);
        total();
        precioTotal();
        verificarTotal(e || {target: el});
        guardarBorrador(); // Guardar borrador automáticamente al cambiar
    });
    el.addEventListener("blur", function() {
        validarInputCantidad(this);
        registrarCambioSesion(this);
        total();
        precioTotal();
        guardarBorrador(); // Guardar borrador al salir del campo
    });
    el.addEventListener("input", function(e) {
        // Prevenir entrada de caracteres no numéricos mientras se escribe
        let valor = e.target.value;
        if (valor && !/^\d*$/.test(valor)) {
            e.target.value = valor.replace(/[^\d]/g, '');
        }
        registrarCambioSesion(e.target);
        total();
        precioTotal();
    });
});

function esInputPedido(elemento) {
    return !!(elemento && elemento.matches && elemento.matches("input[type=number][name^='cantPed_']"));
}

function procesarEdicionPedido(input) {
    registrarCambioSesion(input);
    total();
    precioTotal();
}

let firmaTotales = "";
function actualizarTotalesSiCambio() {
    const inputs = document.querySelectorAll("#id_tabla input[name^='cantPed_']");
    let firmaNueva = "";
    for (let i = 0; i < inputs.length; i++) {
        firmaNueva += "|" + (inputs[i].value || "0");
    }
    if (firmaNueva !== firmaTotales) {
        firmaTotales = firmaNueva;
        total();
        precioTotal();
    }
}

// Respaldo robusto: captura global para asegurar marcado en cualquier forma de edición.
document.addEventListener("input", function(e) {
    if (!esInputPedido(e.target)) return;
    procesarEdicionPedido(e.target);
});

document.addEventListener("change", function(e) {
    if (!esInputPedido(e.target)) return;
    procesarEdicionPedido(e.target);
});

document.addEventListener("blur", function(e) {
    if (!esInputPedido(e.target)) return;
    procesarEdicionPedido(e.target);
}, true);

// Recalculo de respaldo sobre la tabla completa para asegurar actualización inmediata
// en teclado, flechas del input number, pegado y otros cambios del navegador.
const tablaPedidos = document.getElementById("id_tabla");
if (tablaPedidos) {
  ["input", "change", "keyup", "paste"].forEach(function(evt) {
    tablaPedidos.addEventListener(evt, function(e) {
      if (!esInputPedido(e.target)) return;
      procesarEdicionPedido(e.target);
    });
  });
  // Fallback defensivo: si algún navegador/plugin no dispara eventos esperados,
  // igual mantenemos los totales sincronizados sin necesidad de refrescar.
  setInterval(actualizarTotalesSiCambio, 250);
}

// Intentar restaurar borrador al cargar la página (solo si hay borrador y no hay datos actuales)
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        let borrador = localStorage.getItem('pedido_borrador_' + documentTitle);
        if (borrador) {
            // Verificar si hay datos actuales en el formulario
            let tieneDatos = false;
            document.querySelectorAll('input[name^="cantPed_"]').forEach(input => {
                if (parseInt(input.value || 0) > 0) {
                    tieneDatos = true;
                }
            });
            
            // Solo restaurar si no hay datos actuales
            if (!tieneDatos) {
                if (restaurarBorrador()) {
                    // Mostrar notificación discreta
                    Swal.fire({
                        icon: 'info',
                        title: 'Borrador restaurado',
                        text: 'Se ha restaurado el último pedido guardado',
                        timer: 3000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                }
            }
        }
    }, 1000);
});

/************************************************************************************************************************************************************ */

function verificarTotal(e) {
  //capturo el total de stocken casa central para un articulo a partir del change en un input de la fila
  let total = parseInt(
    e.target.parentElement.parentElement.children[6].textContent
  );

  //guardo el arreglo con los inputs de cantidad de la fila seleccionada
  let fila =
    e.target.parentElement.parentElement.querySelectorAll("input[type=number][name^='cantPed_']");

  //guardo el precio del articulo de la fila en cuestión
  let precioArticulo = parseInt(
    e.target.parentElement.parentElement.querySelector("#precio").textContent
  );

  let totalFila = 0;

  //sumo los value de los input de la fila
  fila.forEach((el) => (totalFila += parseInt(el.value || 0)));

  //comparo la suma de la fila con el total stock del articulo
  if (totalFila > total) {
    totalArticulos.value -= totalFila; //borro los cambios en el "Total de Articulos"
    totalPrecio.value -= totalFila * precioArticulo; //borro los cambios en "Importe Total"
    Swal.fire({
      icon: "error",
      title: "Error...",
      text: "La cantidad ingresada es mayor al stock disponible!",
    });
    fila.forEach((el) => (el.value = 0));
    total();
    precioTotal();
    guardarBorrador(); // Guardar borrador después de corregir
  } else {
    //si está ok cargo el codigo de articulo y cantidad solicitada al arreglo artCargados, luego comparo con el stock en central por si hubo cambios en el stock y este es menor al solicitado
    let codigoArticulo =
      e.target.parentElement.parentElement.children[1].innerHTML.trim();
    //verifico si el codigo ya se encuentra en el arreglo artcargados, en ese caso se actualiza la cantidad, para no duplicar los registros
    if (buscarArticulo(codigoArticulo) != -1) {
      artCargados[indice].cantidad = totalFila;
    } else {
      artCargados.push({ codigo: codigoArticulo, cantidad: totalFila });
    }
    guardarBorrador(); // Guardar borrador cuando hay cambios válidos
  }
}

function buscarArticulo(codigo) {
  indice = artCargados.findIndex((el) => {
    return el.codigo == codigo;
  });
  return indice;
}

/***********************************************************************************************************************************************************  */
//controlar stock
/*  form.addEventListener("submit",controlar); */
form.addEventListener("submit", function (e) {
  e.preventDefault();
  
  // Guardar borrador antes de enviar
  guardarBorrador();
  
  // Validar todos los inputs antes de enviar
  let inputsInvalidos = [];
  let todosLosInputs = document.querySelectorAll("input[type=number][name^='cantPed_']");
  
  todosLosInputs.forEach(function(input) {
    if (!validarInputCantidad(input)) {
      inputsInvalidos.push(input);
    }
  });
  
  if (inputsInvalidos.length > 0) {
    Swal.fire({
      icon: 'error',
      title: 'Error de validación',
      text: 'Por favor, corrija las cantidades inválidas antes de enviar el pedido',
      timer: 4000,
      showConfirmButton: true
    });
    if (inputsInvalidos.length > 0) {
      inputsInvalidos[0].focus();
    }
    return false;
  }
  
  preloader.style.display='block';
  traerStock();

  /*  e.preventDefault() */
});
/* document.getElementById('btnEnviar').addEventListener('click',controlar); */

function controlar() {
  /*  e.preventDefault(); */
  /*  form.preventDefault(); */
  console.log("que queres");
  traerStock();
  /* controlarStockActual(); */
}

let conexion1;
function traerStock() {
  // vuelvo a traer el stock para chequear si este fue modificado
  conexion1 = new XMLHttpRequest();
  conexion1.onreadystatechange = () => {
    if (conexion1.readyState == 4 && conexion1.status == 200) {
      console.log("entraste a traerStock");
      stock = JSON.parse(conexion1.responseText);
      console.log('erer: '+stock);
      controlarStockActual();
    }
  };

  conexion1.open("GET", "traerStock.php?title="+documentTitle, true);
  conexion1.send();
}
var encontrado;
function controlarStockActual() {
  let b;
  for (let i = 0; i < artCargados.length; i++) {
    b = 0;
    for (let x = 0; x < stock.length; x++) {
      if (stock[x].COD_ARTICU == artCargados[i].codigo.trim()) {
        b = 1;
        encontrado = stock[x];
        console.log("asdf" + encontrado);
        if (artCargados[i].cantidad > encontrado.CANT_STOCK) {
          stockModificado.push(artCargados[i].codigo);
        }
        break;
      }
    }
    if (b == 0) {
      stockModificado.push(artCargados[i].codigo);
    }
    /*   encontrado = stock.find(el => 
      el.COD_ARTICU = artCargados[i].codigo.trim()
    ); */
  }
  preloader.style.display='none';
  console.log("encontrado: " + encontrado);
  if (stockModificado.length > 0) {
    // Resaltar artículos con problemas
    resaltarArticulosProblema(stockModificado);
    
    // Crear mensaje HTML con lista de artículos
    let listaArticulos = stockModificado.map(codigo => 
      `<li><strong>${codigo}</strong></li>`
    ).join('');
    
    Swal.fire({
      icon: "error",
      title: "Stock modificado",
      html: `<p>Los siguientes artículos han cambiado de stock o ya no están disponibles:</p>
             <ul style="text-align: left; max-height: 200px; overflow-y: auto;">${listaArticulos}</ul>
             <p><strong>Sus datos han sido guardados automáticamente.</strong></p>
             <p>¿Qué desea hacer?</p>`,
      showCancelButton: true,
      confirmButtonText: '<i class="fas fa-edit"></i> Corregir pedido',
      cancelButtonText: '<i class="fas fa-redo"></i> Recargar página',
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#6c757d',
      allowOutsideClick: false,
      allowEscapeKey: false
    }).then((result) => {
      if (result.isConfirmed) {
        // Mantener los datos y permitir corrección
        // Los artículos ya están resaltados en rojo
        Swal.fire({
          icon: 'info',
          title: 'Corrija los artículos resaltados',
          html: `<p>Los artículos con problemas están resaltados en <span style="color: #dc3545;"><strong>rojo</strong></span>.</p>
                 <p>Por favor, ajuste las cantidades y vuelva a intentar.</p>`,
          timer: 5000,
          showConfirmButton: true
        });
        // El usuario puede corregir y volver a enviar
      } else {
        // Recargar página (el borrador se restaurará automáticamente)
        location.reload();
      }
    });
    
    // Limpiar el array para la próxima validación
    stockModificado = [];
  } else {
    // Todo está bien, limpiar borrador y enviar
    localStorage.removeItem('pedido_borrador_' + documentTitle);
    limpiarResaltado();
    /*  form.submit(); */
    form.action = "cargarPedidoNuevoCordoba.php";
    form.submit();
  }
}
