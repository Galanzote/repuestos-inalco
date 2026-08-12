<?php
/**
 * Plantilla de conexion a la base de datos.
 *
 * 1. Copia este archivo como "db.php" (mismo directorio).
 * 2. Completa los 4 valores de abajo con los datos reales que te dio cPanel
 *    al crear la base de datos (Asistente de bases de datos MySQL).
 * 3. "db.php" NO se sube a GitHub (esta en .gitignore) — solo vive en el
 *    servidor de cPanel. Nunca pongas credenciales reales en db.example.php.
 */

$DB_HOST = 'localhost';                  // casi siempre 'localhost' en cPanel
$DB_NAME = 'usuario_repuestos';          // nombre real de la base (con el prefijo que puso cPanel)
$DB_USER = 'usuario_dbuser';             // usuario real de la base
$DB_PASS = 'CAMBIA_ESTA_CLAVE';          // clave real de la base

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'No se pudo conectar a la base de datos.']);
    exit;
}
