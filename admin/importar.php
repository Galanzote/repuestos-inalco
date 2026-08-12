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

/** Convierte "A", "B", ..., "AA" (letras de columna de Excel) a índice 0-based. */
function colLetrasAIndice($letras) {
    $indice = 0;
    for ($i = 0; $i < strlen($letras); $i++) {
        $indice = $indice * 26 + (ord($letras[$i]) - ord('A') + 1);
    }
    return $indice - 1;
}

/**
 * Lee la primera hoja de un .xlsx y devuelve un array de filas (cada fila
 * un array de valores en texto), o null si no se pudo leer. Un .xlsx es en
 * realidad un .zip con XML adentro, así que esto se hace con las
 * extensiones estándar de PHP (Zip + SimpleXML), sin librerías externas.
 */
function leerXlsx($path) {
    if (!class_exists('ZipArchive')) return null;

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return null;

    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $ssObj = @simplexml_load_string($ssXml, 'SimpleXMLElement', LIBXML_NONET);
        if ($ssObj !== false) {
            foreach ($ssObj->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string) $si->t;
                } else {
                    $texto = '';
                    foreach ($si->r as $r) $texto .= (string) $r->t;
                    $sharedStrings[] = $texto;
                }
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) return null;

    $sheetObj = @simplexml_load_string($sheetXml, 'SimpleXMLElement', LIBXML_NONET);
    if ($sheetObj === false || !isset($sheetObj->sheetData)) return null;

    $filas = [];
    foreach ($sheetObj->sheetData->row as $row) {
        $celdas = [];
        $colIndex = 0;
        foreach ($row->c as $c) {
            $ref = (string) $c['r'];
            $colLetras = preg_replace('/[0-9]/', '', $ref);
            $indiceReal = $colLetras !== '' ? colLetrasAIndice($colLetras) : $colIndex;
            while ($colIndex < $indiceReal) { $celdas[$colIndex] = ''; $colIndex++; }

            $tipo = (string) $c['t'];
            if ($tipo === 'inlineStr') {
                $valor = isset($c->is->t) ? (string) $c->is->t : '';
            } else {
                $valor = isset($c->v) ? (string) $c->v : '';
                if ($tipo === 's') {
                    $valor = $sharedStrings[(int) $valor] ?? '';
                }
            }
            $celdas[$colIndex] = $valor;
            $colIndex++;
        }
        $filas[] = $celdas;
    }
    return $filas;
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
    } elseif ($_FILES['csv']['size'] > 10 * 1024 * 1024) {
        $error = 'El archivo pesa más de 10MB — es demasiado para una lista de precios, revisá que sea el correcto.';
    } else {
        $ext = strtolower(pathinfo($_FILES['csv']['name'], PATHINFO_EXTENSION));

        if ($ext === 'xlsx') {
            $filas = leerXlsx($_FILES['csv']['tmp_name']);
            if ($filas === null) {
                $error = 'No se pudo leer el archivo Excel. Probá guardarlo como .xlsx de nuevo, o exportalo como CSV.';
            }
        } elseif ($ext === 'csv') {
            $raw = file_get_contents($_FILES['csv']['tmp_name']);
            $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw); // BOM de Excel
            $lines = array_values(array_filter(
                preg_split("/\r\n|\r|\n/", $raw),
                function ($l) { return trim($l) !== ''; }
            ));
            $delim = (!empty($lines) && substr_count($lines[0], ';') > substr_count($lines[0], ',')) ? ';' : ',';
            $filas = array_map(function ($l) use ($delim) { return str_getcsv($l, $delim); }, $lines);
        } else {
            $error = 'Formato no reconocido — subí un archivo .csv o .xlsx.';
            $filas = null;
        }

        if (!$error && (empty($filas) || count($filas) < 2)) {
            $error = 'El archivo está vacío o no tiene filas de datos.';
        }

        if (!$error) {
            $headerRaw = $filas[0];

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

                    for ($li = 1; $li < count($filas); $li++) {
                        $row = $filas[$li];
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
          Sube un archivo <strong>Excel (.xlsx)</strong> o <strong>CSV</strong> con una
          columna <code>codigo</code> y al menos una de <code>precio</code>,
          <code>precioVenta</code> o <code>stock</code>. Si el Excel tiene varias hojas,
          se lee solo la primera. Solo se actualizan los productos cuyo código coincida
          con uno ya existente en el catálogo, y solo los campos que vengan en el archivo.
          <br>Tip: si la columna <code>codigo</code> tiene ceros a la izquierda (ej.
          <code>0188863336</code>), formateá esa columna como <strong>Texto</strong> en
          Excel antes de guardar — si queda como número, Excel borra el cero solo.
        </p>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">
          <input type="hidden" name="step" value="preview">
          <input type="file" name="csv" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required class="file-input">
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
