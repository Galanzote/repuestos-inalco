# Convención de nombres — fotos de productos

Cada foto se nombra `{codigo}-{n}.{ext}`:

- `{codigo}` = el código corto del repuesto (columna "Código" en la planilla de repuestos, sin ceros de relleno). **Ojo:** no es el mismo valor que el campo `codigo` de `products.js` (ese es el "N° de Parte", más largo). El código corto es el que hay que usar para nombrar fotos nuevas.
- `{n}` = número de foto para ese producto, empezando en 1 (`-1`, `-2`, `-3`...) cuando hay varios ángulos.
- `{ext}` = `jpg` o `webp`, el formato original.

Ejemplos:
- `88863336-1.jpg` → única foto del producto con código `88863336`.
- `19373994-1.jpg`, `19373994-2.jpg`, `19373994-3.jpg` → galería de 3 fotos del producto con código `19373994`.

## Agregar fotos nuevas

1. Nombra el archivo con el código corto del producto (el mismo que aparece en la planilla de repuestos, columna "Código").
2. Si el producto ya tiene fotos, usa el siguiente número disponible (`-2`, `-3`, etc).
3. Súbelo a esta carpeta.
4. Agrega la ruta al array `imagenes` del producto correspondiente en `js/products.js`, por ejemplo:
   ```js
   "imagenes": ["images/productos/88863336-1.jpg", "images/productos/88863336-2.jpg"]
   ```

## Optimización

Las fotos JPG mayores a 1400px de ancho/alto se redimensionan automáticamente a 1400px (lado más largo) y se recomprimen a calidad 82 antes de subirlas al repo, para no cargar el sitio con imágenes pesadas. Los `.webp` ya vienen livianos del origen y se dejan tal cual.
