<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/_products.php';

$productsFile = __DIR__ . '/../js/products.js';

$mensaje = null;
$error = null;
$cambios = [];
$noEncontrados = [];

/** Normaliza un encabezado de columna: minúsculas, sin tildes ni símbolos. */
function normalizarHeader($h) {
    $h = trim((string) $h);
    $h = mb_strtolower($h, 'UTF-8');
    $h = strtr($h, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
    return preg_replace('/[^a-z0-9]+/', '', $h);
}

/** Deja solo dígitos (quita puntos de miles, símbolos de moneda, espacios). */
function limpiarNumero($v) {
    $v = preg_replace('/[^0-9]/', '', (string) $v);
    return $v === '' ? null : (int) $v;
}

$camposValidos = ['precio', 'precioVenta', 'stock'];
$step = $_POST['step'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'preview') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Token invalido');
    }

    if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        $error = 'No se pudo subir el archivo. Intenta de nuevo.';
    } else {
        $raw = file_get_contents($_FILES['csv']['tmp_name']);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw); // BOM de Excel
        $lines = array_values(array_filter(
            preg_split("/\r\n|\r|\n/", $raw),
            function ($l) { return trim($l) !== ''; }
        ));

        if (count($lines) < 2) {
            $error = 'El archivo está vacío o no tiene filas de datos.';
        } else {
            $headerLine = $lines[0];
            $delim = (substr_count($headerLine, ';') > substr_count($headerLine, ',')) ? ';' : ',';
            $headerRaw = str_getcsv($headerLine, $delim);

            $colMap = [];
            foreach ($headerRaw as $i => $h) {
                $norm = normalizarHeader($h);
                if (in_array($norm, ['codigo', 'code'], true)) {
                    $colMap[$i] = 'codigo';
                } elseif (in_array($norm, ['precio', 'preciolista'], true)) {
                    $colMap[$i] = 'precio';
                } elseif (in_array($norm, ['precioventa', 'precioventacliente', 'preciofinal', 'pvp'], true)) {
                    $colMap[$i] = 'precioVenta';
                } elseif (in_array($norm, ['stock', 'existencia', 'existencias'], true)) {
                    $colMap[$i] = 'stock';
                }
            }

            $codigoCol = array_search('codigo', $colMap, true);
            $camposDetectados = array_values(array_unique(array_diff(array_values($colMap), ['codigo'])));

            if ($codigoCol === false) {
                $error = 'El archivo debe tener una columna "codigo" en el encabezado.';
            } elseif (!$camposDetectados) {
                $error = 'El archivo debe tener al menos una columna "precio", "precioVenta" o "stock" además de "codigo".';
            } else {
                $products = loadProducts($productsFile);
                if ($products === null) {
                    $error = 'No se pudo leer js/products.js.';
                } else {
                    $porCodigo = [];
                    foreach ($products as $idx => $p) {
                        $porCodigo[$p['codigo']] = $idx;
                    }

                    for ($li = 1; $li < count($lines); $li++) {
                        $row = str_getcsv($lines[$li], $delim);
                        $codigo = trim($row[$codigoCol] ?? '');
                        if ($codigo === '') continue;

                        if (!isset($porCodigo[$codigo])) {
                            $noEncontrados[] = $codigo;
                            continue;
                        }

                        $p = $products[$porCodigo[$codigo]];
                        $camposCambio = [];
                        foreach ($colMap as $ci => $campo) {
                            if ($campo === 'codigo' || !isset($row[$ci])) continue;
                            $nuevo = limpiarNumero($row[$ci]);
                            if ($nuevo === null) continue;
                            $actual = (int) ($p[$campo] ?? 0);
                            if ($nuevo !== $actual) {
                                $camposCambio[$campo] = ['de' => $actual, 'a' => $nuevo];
                            }
                        }

                        if ($camposCambio) {
                            $cambios[] = [
                                'id' => $p['id'],
                                'codigo' => $codigo,
                                'titulo' => $p['titulo'],
                                'cambios' => $camposCambio,
                            ];
                        }
                    }

                    if (!$cambios && !$noEncontrados) {
                        $error = 'No se detectaron cambios respecto a los precios/stock actuales.';
                    }
                }
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'aplicar') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Token invalido');
    }

    $payload = json_decode($_POST['cambios_json'] ?? '[]', true);
    if (!is_array($payload)) $payload = [];

    $products = loadProducts($productsFile);
    if ($products === null) {
        $error = 'No se pudo leer js/products.js — no se guardó nada.';
    } else {
        $porId = [];
        foreach ($products as $idx => $p) $porId[$p['id']] = $idx;

        $aplicados = 0;
        foreach ($payload as $c) {
            $id = (int) ($c['id'] ?? 0);
            if (!isset($porId[$id])) continue;
            $idx = $porId[$id];
            $tocoAlgo = false;
            foreach (($c['cambios'] ?? []) as $campo => $vals) {
                if (!in_array($campo, $camposValidos, true)) continue;
                $a = $vals['a'] ?? null;
                if ($a === null || !is_numeric($a)) continue;
                $products[$idx][$campo] = max(0, (int) $a);
                $tocoAlgo = true;
            }
            if ($tocoAlgo) $aplicados++;
        }

        if (saveProducts($productsFile, $products)) {
            $mensaje = "Se aplicaron cambios a {$aplicados} producto(s).";
        } else {
            $error = 'El servidor no dejó guardar el archivo (permiso denegado). No se aplicó ningún cambio — avisa al encargado técnico.';
        }
    }
}

function fmtMoney2($n) { return number_format((float) $n, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Importar Precios — Inalco</title>
  <link rel="stylesheet" href="admin.css">
</head>
<body>
  <?php $navTitle = '📥 Importar Precios — Inalco'; include __DIR__ . '/_nav.php'; ?>

  <div class="admin-wrap">
    <?php if ($mensaje): ?><div class="save-ok">✅ <?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="login-error">⚠️ <?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

    <?php if (!$cambios && !$noEncontrados): ?>
      <div class="panel-box">
        <p class="help-text">
          Sube un archivo <strong>CSV</strong> (si tu lista viene en Excel, usa
          "Guardar como… → CSV") con una columna <code>codigo</code> y al menos
          una de <code>precio</code>, <code>precioVenta</code> o <code>stock</code>.
          Solo se actualizan los productos cuyo código coincida con uno ya
          existente en el catálogo, y solo los campos que vengan en el archivo.
        </p>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">
          <input type="hidden" name="step" value="preview">
          <input type="file" name="csv" accept=".csv,text/csv" required class="file-input">
          <button type="submit" class="btn-guardar-precios">Analizar archivo</button>
        </form>
      </div>
    <?php else: ?>
      <div class="panel-box">
        <p><strong><?= count($cambios) ?></strong> producto(s) con cambios detectados<?php if ($noEncontrados): ?>, <strong><?= count($noEncontrados) ?></strong> código(s) no encontrados (se omiten)<?php endif; ?>.</p>
      </div>

      <?php if ($cambios): ?>
      <table>
        <thead><tr><th>Código</th><th>Título</th><th>Campo</th><th>Actual</th><th>Nuevo</th></tr></thead>
        <tbody>
          <?php foreach ($cambios as $c): foreach ($c['cambios'] as $campo => $v): ?>
            <tr>
              <td class="items-detail"><?= htmlspecialchars($c['codigo'], ENT_QUOTES) ?></td>
              <td><?= htmlspecialchars($c['titulo'], ENT_QUOTES) ?></td>
              <td><?= htmlspecialchars($campo, ENT_QUOTES) ?></td>
              <td>$<?= fmtMoney2($v['de']) ?></td>
              <td><strong>$<?= fmtMoney2($v['a']) ?></strong></td>
            </tr>
          <?php endforeach; endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>

      <?php if ($noEncontrados): ?>
        <p class="help-text">Códigos no encontrados en el catálogo: <?= htmlspecialchars(implode(', ', $noEncontrados), ENT_QUOTES) ?></p>
      <?php endif; ?>

      <?php if ($cambios): ?>
        <form method="post" style="margin-top:16px;">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">
          <input type="hidden" name="step" value="aplicar">
          <input type="hidden" name="cambios_json" value='<?= htmlspecialchars(json_encode($cambios), ENT_QUOTES) ?>'>
          <button type="submit" class="btn-guardar-precios">Aplicar <?= count($cambios) ?> cambio(s)</button>
          <a href="importar.php" class="btn-cancelar">Cancelar</a>
        </form>
      <?php else: ?>
        <a href="importar.php" class="btn-cancelar">Volver</a>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</body>
</html>
