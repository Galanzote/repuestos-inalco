<?php
/**
 * Leer/escribir js/products.js compartido por productos.php, importar.php
 * y producto-nuevo.php.
 */

/**
 * Lee js/products.js y devuelve el array de productos (o null si el
 * archivo no tiene el formato esperado).
 */
function loadProducts($file) {
    if (!file_exists($file)) return null;
    $content = file_get_contents($file);
    if (!preg_match('/const\s+PRODUCTS\s*=\s*(\[.*\])\s*;/s', $content, $m)) {
        return null;
    }
    $data = json_decode($m[1], true);
    return is_array($data) ? $data : null;
}

/**
 * Reescribe js/products.js con el array actualizado, dejando una copia
 * de respaldo con fecha antes de sobreescribir (se conservan las ultimas 20).
 */
function saveProducts($file, $products) {
    $backupDir = __DIR__ . '/../js/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    if (file_exists($file)) {
        copy($file, $backupDir . '/products_' . date('Ymd_His') . '.js');
    }
    $backups = glob($backupDir . '/products_*.js');
    sort($backups);
    while (count($backups) > 20) {
        @unlink(array_shift($backups));
    }

    $lines = [];
    foreach ($products as $p) {
        $lines[] = '  ' . json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $out = "const PRODUCTS = [\n" . implode(",\n", $lines) . "\n];\n";
    file_put_contents($file, $out);
}
