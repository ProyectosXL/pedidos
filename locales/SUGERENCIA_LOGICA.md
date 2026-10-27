# Lógica de la columna Sugerencia — pedidos.php

## Visibilidad

La columna se muestra únicamente cuando el sistema puede obtener datos del local
(stock local y ventas locales). Si la conexión al servidor de locales falla, la columna no aparece.

```php
$mostrarSugerencia = ($mostrarStockLocal || $mostrarVentas30d) && !$errorConexion;
```

Aplica para: locales propios (GT), franquicias (FR) y exterior (UR/EX).

---

## Paso 1 — MOS_TARGET base por rubro

Se define cuántos meses de cobertura de stock se quiere sugerir según el tipo de producto.
La lógica es: mayor ticket o mayor volumen físico → menor cobertura requerida.

| Rubro | MOS_TARGET |
|---|---|
| Carteras de Vinilico, Accesorios de Vinilico, Bijou, Llaveros | 2.10 |
| Billeteras de Vinilico, Billeteras de Cuero, Accesorios de Cuero, Cosmetica, Home, Cintos de Cuero | 2.00 |
| Carteras de Cuero, Chalinas, Paraguas | 1.75 |
| Calzados, Camperas, Indumentaria, Toallas | 1.50 |
| Equipajes | 1.25 |
| Cualquier otro rubro | 2.00 (default) |

---

## Paso 2 — Ajuste por comportamiento del artículo

El MOS_TARGET base se ajusta relativamente según los flags del stored procedure:

```
ES_TOP = 1 o ES_TENDENCIA = 1  →  MOS_TARGET += 0.25  (producto caliente o en crecimiento)
ES_NUEVO = 1                   →  MOS_TARGET -= 0.25  (producto nuevo, ser conservador)
```

Los flags son mutuamente excluyentes en el ajuste: si un artículo es TOP y NUEVO al mismo
tiempo, gana TOP (se usa elseif, no dos if independientes).

---

## Paso 3 — Ventas efectivas (suavizado de demanda)

La fórmula base necesita una estimación de demanda local. El problema es que `ventas30`
(ventas del local en los últimos 30 días) puede ser 0 por falta de stock previo o por
tratarse de un artículo sin historial, no necesariamente porque no haya demanda.

Se aplica un suavizado en tres casos ordenados por prioridad:

```
1. ventas30 == 0 AND stockLocal > 0
   → ventasEfectivas = 0
   Tiene stock pero no vende: el artículo no rota en este local.
   No tiene sentido sugerir más.

2. ventas30 == 0 AND stockLocal == 0
   → ventasEfectivas = round(UNIDADES_30D * pctRed)
   Sin venta local y sin stock: no hay dato confiable.
   Se usa como piso mínimo el porcentaje proporcional de las ventas
   de toda la red (UNIDADES_30D del stored procedure).

   pctRed = 0.02 para franquicias  (1/65 locales de la red)
   pctRed = 0.05 para locales propios  (1/21 locales de la red)

3. ventas30 > 0
   → ventasEfectivas = ventas30
   Hay dato local real: se confía en él directamente.
```

---

## Paso 4 — Caso especial hardcoded

Antes de la fórmula general, se evalúa un caso particular:

```
stockLocal <= 2  AND  stock_central > 100  AND  ventasEfectivas == 0
→ sugerencia = 1
```

Lógica: el local tiene muy poco de este artículo (0, 1 o 2 unidades) pero el depósito
central tiene amplia disponibilidad. Aunque no haya demanda registrada, se sugiere
reponer al menos 1 unidad para no quedar sin presencia del producto.

Si este caso se activa, se saltea el paso 5.

---

## Paso 5 — Fórmula principal

```
sugerencia = max(0, round(ventasEfectivas * MOS_TARGET - stockLocal))
```

**Interpretación:** cuántas unidades necesita el local para llegar al nivel de cobertura
deseado (MOS_TARGET meses), descontando lo que ya tiene.

**Casos especiales dentro del paso 5:**

```
errorConexion != null
→ sugerencia = null  (mostrar "-")

ventasEfectivas == 0 AND stockLocal == 0 AND ES_NUEVO == 1
→ sugerencia = max(round(UNIDADES_30D * 0.10), 0)
   Artículo nuevo sin ningún historial: se sugiere el 10% de la venta
   de la red como cantidad de arranque.

ventasEfectivas == 0 AND stockLocal == 0
→ sugerencia = null  (sin datos suficientes, mostrar "-")

ventasEfectivas == 0 AND stockLocal > 0
→ sugerencia = 0  (no rota)
```

---

## Paso 6 — Penalización sublineal general

Aplica una curva potencial a cualquier sugerencia mayor a 0, independientemente del stock.
Evita que cálculos altos se traduzcan linealmente en pedidos grandes.

```
SI sugerencia > 0:
   sugerencia = round(sugerencia ^ 0.8)
```

**Ejemplos:**
| Antes | Después |
|---|---|
| 1 | 1 |
| 3 | 2 |
| 5 | 4 |
| 8 | 5 |
| 10 | 6 |
| 15 | 9 |
| 20 | 11 |
| 50 | 21 |

Una sugerencia de 1 nunca puede llegar a 0 (pow(1, 0.8) = 1 exacto).

---

## Paso 7 — Decaimiento por disponibilidad en stock central

Solo aplica cuando el stock central es menor que la sugerencia post-Paso 6.
Penaliza progresivamente según qué tan lejos está el stock de cubrir lo sugerido.

```
SI sugerencia > 0 AND stock_central > 0 AND stock_central < sugerencia:
   cobertura = stock_central / sugerencia   (ratio 0..1)
   sugerencia = round(sugerencia * cobertura ^ 1.5)
```

El exponente 1.5 define la agresividad: a mayor exponente, más brusca la caída cuando el stock es bajo.

**Ejemplos con exponente 1.5:**
| Sugerencia (post-6) | Stock central | Cobertura | Resultado |
|---|---|---|---|
| 6 | 5 | 83% | 6 (no aplica) |
| 6 | 4 | 67% | 3 |
| 9 | 5 | 56% | 4 |
| 11 | 6 | 55% | 5 |
| 11 | 12 | 109% | 11 (no aplica) |

Cuando stock >= sugerencia, este paso no modifica nada (Paso 8 tampoco cappea).
Ambos pasos 6 y 7 se complementan: 6 reduce por magnitud, 7 reduce por escasez.

---

## Paso 8 — Capping por stock central y detección de sugerencia cappada

Como salvaguarda final, la sugerencia no puede superar el stock disponible en central.
En la práctica, si el Paso 7 aplicó con exponente >= 1.0, la sugerencia ya quedará
por debajo del stock y este paso no modifica nada.

```
SI sugerencia > stock_central:
   sugerenciaSinCap = sugerencia
   sugerencia = stock_central
   sugerenciaCappada = true
```

Cuando `sugerenciaCappada = true`, el badge muestra el texto *"(últimos)"* y el tooltip
indica la sugerencia original (`sugerenciaSinCap`) vs el disponible central, para que
el usuario entienda que el número es bajo por falta de stock en central, no por falta
de necesidad en el local.
