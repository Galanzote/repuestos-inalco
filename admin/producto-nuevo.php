<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/_products.php';

$productsFile = __DIR__ . '/../js/products.js';
$fotosDir = __DIR__ . '/../images/productos';

$categorias = ['Refrigeración', 'Encendido', 'Filtros', 'Llantas', 'Varios', 'Sensores', 'Motor', 'Frenos', 'Transmisión', 'Distribución', 'Carrocería', 'Lubricantes'];
$mimeAExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

$mensaje = null;
$errores = [];
$valores = ['marca' => '', 'codigo' => '', 'titulo' => '', 'nombre' => '', 'comp1' => '', 'comp2' => '', 'comp3' => '', 'categoria' => '', 'categoriaOtra' => '', 'pmp' => '', 'precio' => '', 'precioVenta' => '', 'stock' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Token invalido');
    }

    foreach ($valores as $k => $v) {
        $valores[$k] = trim($_POST[$k] ?? '');
    }

    $codigo = $valores['codigo'];
    $titulo = $valores['titulo'];
    $categoria = $valores['categoria'] === '__otra__' ? $valores['categoriaOtra'] : $valores['categoria'];

    if ($codigo === '' || !preg_match('/^[A-Za-z0-9\-]+$/', $codigo)) {
        $errores[] = 'El código es obligatorio y solo puede tener letras, números y guiones.';
    }
    if ($titulo === '') {
        $errores[] = 'El título es obligatorio.';
    }
    if ($categoria === '') {
        $errores[] = 'Selecciona o escribe una categoría.';
    }
    foreach (['pmp', 'precio', 'precioVenta', 'stock'] as $campoNum) {
        if ($valores[$campoNum] !== '' && !is_numeric($valores[$campoNum])) {
            $errores[] = "El campo \"$campoNum\" debe ser un número.";
        }
    }

    $products = null;
    if (!$errores) {
        $products = loadProducts($productsFile);
        if ($products === null) {
            $errores[] = 'No se pudo leer js/products.js.';
        } else {
            foreach ($products as $p) {
                if ($p['codigo'] === $codigo) {
                    $errores[] = 'Ya existe un producto con el código "' . $codigo . '" (' . $p['titulo'] . ').';
                    break;
                }
            }
        }
    }

    $imagenes = [];
    if (!$errores && !empty($_FILES['fotos']) && is_array($_FILES['fotos']['tmp_name'])) {
        $n = count($_FILES['fotos']['tmp_name']);
        $indice = 1;
        for ($i = 0; $i < $n; $i++) {
            if ($_FILES['fotos']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            if ($_FILES['fotos']['error'][$i] !== UPLOAD_ERR_OK) {
                $errores[] = 'Uno de los archivos no se pudo subir. Intenta de nuevo.';
                continue;
            }
            if ($_FILES['fotos']['size'][$i] > 5 * 1024 * 1024) {
                $errores[] = 'Cada foto debe pesar menos de 5MB.';
                continue;
            }
            $tmp = $_FILES['fotos']['tmp_name'][$i];
            $info = @getimagesize($tmp);
            if ($info === false || !isset($mimeAExt[$info['mime']])) {
                $errores[] = 'Solo se aceptan imágenes JPG, PNG o WEBP.';
                continue;
            }
            $ext = $mimeAExt[$info['mime']];
            $destino = $fotosDir . '/' . $codigo . '-' . $indice . '.' . $ext;
            if (move_uploaded_file($tmp, $destino)) {
                $imagenes[] = 'images/productos/' . $codigo . '-' . $indice . '.' . $ext;
                $indice++;
            } else {
                $errores[] = 'No se pudo guardar una de las fotos en el servidor.';
            }
        }
    }

    if (!$errores) {
        $nuevoId = 1;
        foreach ($products as $p) {
            if ((int) $p['id'] >= $nuevoId) $nuevoId = (int) $p['id'] + 1;
        }

        $nuevoProducto = [
            'id' => $nuevoId,
            'marca' => $valores['marca'],
            'codigo' => $codigo,
            'titulo' => $titulo,
            'nombre' => $valores['nombre'] !== '' ? $valores['nombre'] : $titulo,
            'comp1' => $valores['comp1'],
            'comp2' => $valores['comp2'],
            'comp3' => $valores['comp3'],
            'pmp' => (int) ($valores['pmp'] ?: 0),
            'precio' => (int) ($valores['precio'] ?: 0),
            'precioVenta' => (int) ($valores['precioVenta'] ?: 0),
            'stock' => (int) ($valores['stock'] ?: 0),
            'categoria' => $categoria,
            'imagenes' => $imagenes,
        ];

        $products[] = $nuevoProducto;
        saveProducts($productsFile, $products);

        $mensaje = "Producto \"$titulo\" agregado con id $nuevoId.";
        // Limpiar el formulario tras guardar con éxito.
        foreach ($valores as $k => $v) $valores[$k] = '';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Agregar Producto — Inalco</title>
  <link rel="stylesheet" href="admin.css">
</head>
<body>
  <?php $navTitle = '➕ Agregar Producto — Inalco'; include __DIR__ . '/_nav.php'; ?>

  <div class="admin-wrap">
    <?php if ($mensaje): ?>
      <div class="save-ok">✅ <?= htmlspecialchars($mensaje, ENT_QUOTES) ?> <a href="productos.php">Ver en Precios y Stock →</a></div>
    <?php endif; ?>
    <?php if ($errores): ?>
      <div class="login-error">⚠️ <?php foreach ($errores as $i => $e): ?><?= $i > 0 ? '<br>' : '' ?><?= htmlspecialchars($e, ENT_QUOTES) ?><?php endforeach; ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="panel-box">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">

      <div class="form-grid">
        <label>Marca
          <input type="text" name="marca" list="marcasList" value="<?= htmlspecialchars($valores['marca'], ENT_QUOTES) ?>">
          <datalist id="marcasList"><option value="ACDelco"><option value="GM"></datalist>
        </label>
        <label>Código *
          <input type="text" name="codigo" required value="<?= htmlspecialchars($valores['codigo'], ENT_QUOTES) ?>">
        </label>
        <label class="form-full">Título *
          <input type="text" name="titulo" required value="<?= htmlspecialchars($valores['titulo'], ENT_QUOTES) ?>">
        </label>
        <label class="form-full">Nombre completo (si se deja vacío, se usa el título)
          <input type="text" name="nombre" value="<?= htmlspecialchars($valores['nombre'], ENT_QUOTES) ?>">
        </label>
        <label class="form-full">Compatibilidad 1
          <input type="text" name="comp1" value="<?= htmlspecialchars($valores['comp1'], ENT_QUOTES) ?>">
        </label>
        <label class="form-full">Compatibilidad 2
          <input type="text" name="comp2" value="<?= htmlspecialchars($valores['comp2'], ENT_QUOTES) ?>">
        </label>
        <label class="form-full">Compatibilidad 3
          <input type="text" name="comp3" value="<?= htmlspecialchars($valores['comp3'], ENT_QUOTES) ?>">
        </label>
        <label>Categoría *
          <select name="categoria" id="categoriaSelect" onchange="document.getElementById('categoriaOtraWrap').style.display = this.value === '__otra__' ? 'block' : 'none';">
            <option value="">— Selecciona —</option>
            <?php foreach ($categorias as $c): ?>
              <option value="<?= htmlspecialchars($c, ENT_QUOTES) ?>" <?= $valores['categoria'] === $c ? 'selected' : '' ?>><?= htmlspecialchars($c, ENT_QUOTES) ?></option>
            <?php endforeach; ?>
            <option value="__otra__" <?= $valores['categoria'] === '__otra__' ? 'selected' : '' ?>>Otra…</option>
          </select>
        </label>
        <label id="categoriaOtraWrap" style="display:<?= $valores['categoria'] === '__otra__' ? 'block' : 'none' ?>;">Nueva categoría
          <input type="text" name="categoriaOtra" value="<?= htmlspecialchars($valores['categoriaOtra'], ENT_QUOTES) ?>">
        </label>
        <label>PMP (costo)
          <input type="number" min="0" name="pmp" value="<?= htmlspecialchars($valores['pmp'], ENT_QUOTES) ?>">
        </label>
        <label>Precio Lista
          <input type="number" min="0" name="precio" value="<?= htmlspecialchars($valores['precio'], ENT_QUOTES) ?>">
        </label>
        <label>Precio Final (cliente)
          <input type="number" min="0" name="precioVenta" value="<?= htmlspecialchars($valores['precioVenta'], ENT_QUOTES) ?>">
        </label>
        <label>Stock
          <input type="number" min="0" name="stock" value="<?= htmlspecialchars($valores['stock'], ENT_QUOTES) ?>">
        </label>
        <label class="form-full">Fotos (opcional, JPG/PNG/WEBP, máx. 5MB c/u)
          <input type="file" name="fotos[]" accept="image/jpeg,image/png,image/webp" multiple class="file-input">
        </label>
      </div>

      <button type="submit" class="btn-guardar-precios">Guardar producto</button>
    </form>
  </div>
</body>
</html>
