# Fotos del carrusel del hero

El hero de `index.html` (banner principal, arriba de todo) rota automáticamente entre las fotos listadas en `#heroPhotos`, cada 5 segundos.

## Dimensión recomendada

**1600 × 600 px** (relación de aspecto ≈ 2.7:1, formato banner ancho).

- **Ancho 1600px**: se ve nítido incluso en monitores grandes, sin pesar de más.
- **Alto 600px**: cubre bien la altura real del bloque del hero en el sitio, que ronda los 460-650px según el ancho de pantalla del visitante.
- **Formato**: JPG, calidad ~80-85%, apuntando a menos de 200-250 KB por imagen.

## Composición

La imagen se estira/recorta automáticamente para cubrir todo el espacio disponible (`background-size: cover`, centrada). Por eso conviene:

- Dejar el vehículo o protagonista **centrado**, con margen a los costados — en celular se recorta más por los lados que en desktop.
- Si la foto viene de una campaña/diseño (Outlet, Cyber, etc.), lo ideal es recortarla para dejar **solo el vehículo y el fondo**, sin el texto ni los precios superpuestos del diseño original (el hero ya tiene su propio texto encima).

## Agregar/cambiar una foto

1. Prepará la imagen según la dimensión recomendada de arriba.
2. Subila a esta carpeta.
3. Agregá o reemplazá el `<div class="hero-photo">` correspondiente en `index.html`, dentro de `#heroPhotos`:
   ```html
   <div class="hero-photo" style="background-image:url('images/hero/nombre-archivo.jpg')"></div>
   ```
   El primero de la lista debe tener también la clase `active` (es el que se muestra al cargar la página).
4. El intervalo de rotación (5 segundos) se controla en `initHeroCarousel()` dentro de `js/app.js`.

## Fotos actuales

| Archivo | Dimensiones | Vehículo |
|---|---|---|
| `inalco-chevrolet.jpg` | 1600×600 | Concesionario Inalco (fachada + 4 vehículos) |
| `colorado.jpg` | 1600×600 | Chevrolet Colorado |
| `silverado.jpg` | 1600×600 | Chevrolet Silverado |
| `groove.jpg` | 1600×600 | Chevrolet Groove |
| `blazer.jpg` | 1600×600 | Chevrolet Blazer |
| `sail-sedan.jpg` | 1600×600 | Chevrolet Sail Sedán |

Estas seis ya vienen en la dimensión recomendada (1600×600), sin texto de precios ni promociones — no necesitaron recorte manual.
