<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/_products.php';

$productsFile = __DIR__ . '/../js/products.js';

$mensaje = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Token invalido');
    }

    $products = loadProducts($productsFile);
    if ($products === null) {
        $error = 'No se pudo leer js/products.js — no se guardó nada.';
    } else {
        $precios      = $_POST['precio'] ?? [];
        $preciosVenta = $_POST['precioVenta'] ?? [];
        $stocks       = $_POST['stock'] ?? [];
        $cambios = 0;

        foreach ($products as &$p) {
            $id = (string) $p['id'];
            if (isset($precios[$id])      && is_numeric($precios[$id]))      { $p['precio']      = max(0, (int) $precios[$id]); }
            if (isset($preciosVenta[$id]) && is_numeric($preciosVenta[$id])) { $p['precioVenta']  = max(0, (int) $preciosVenta[$id]); }
            if (isset($stocks[$id])       && is_numeric($stocks[$id]))       { $p['stock']        = max(0, (int) $stocks[$id]); }
            $cambios++;
        }
        unset($p);

        if (saveProducts($productsFile, $products)) {
            $mensaje = 'Cambios guardados correctamente.';
        } else {
            $error = 'El servidor no dejó guardar el archivo (permiso denegado). No se aplicó ningún cambio — avisa al encargado técnico.';
        }
    }
}

$products = loadProducts($productsFile) ?? [];

function fmtMoney2($n) { return number_format((float) $n, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Precios y Stock — Inalco</title>
  <link rel="stylesheet" href="admin.css">
</head>
<body>
  <?php $navTitle = '💲 Precios y Stock — Inalco'; include __DIR__ . '/_nav.php'; ?>

  <div class="admin-wrap">
    <?php if ($mensaje): ?><div class="save-ok">✅ <?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="login-error">⚠️ <?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

    <input type="text" id="buscador" class="buscador" placeholder="Buscar por código, título o categoría..." onkeyup="filtrarTabla()">

    <form method="post" id="formPrecios">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">
      <table id="tablaProductos">
        <thead>
          <tr>
            <th>Código</th><th>Título</th><th>Categoría</th><th>Stock</th>
            <th>PMP (costo)</th><th>Precio Lista</th><th>Precio Final (cliente)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $p): $id = (int) $p['id']; ?>
            <tr>
              <td class="items-detail"><?= htmlspecialchars($p['codigo'], ENT_QUOTES) ?></td>
              <td><?= htmlspecialchars($p['titulo'], ENT_QUOTES) ?></td>
              <td class="items-detail"><?= htmlspecialchars($p['categoria'], ENT_QUOTES) ?></td>
              <td><input type="number" min="0" name="stock[<?= $id ?>]" value="<?= (int) $p['stock'] ?>" class="precio-input"></td>
              <td class="items-detail">$<?= fmtMoney2($p['pmp']) ?></td>
              <td><input type="number" min="0" name="precio[<?= $id ?>]" value="<?= (int) $p['precio'] ?>" class="precio-input"></td>
              <td><input type="number" min="0" name="precioVenta[<?= $id ?>]" value="<?= (int) $p['precioVenta'] ?>" class="precio-input precio-final"></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <button type="submit" class="btn-guardar-precios">Guardar todos los cambios</button>
    </form>
  </div>

  <script>
    function filtrarTabla() {
      const q = document.getElementById('buscador').value.trim().toLowerCase();
      document.querySelectorAll('#tablaProductos tbody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    }
  </script>
</body>
</html>
