<?php
/**
 * Script de un solo uso para crear (o resetear) el usuario del panel de admin.
 *
 * Como usarlo:
 *   1. Completa api/db.php con las credenciales reales de tu base de datos.
 *   2. Sube este archivo a cPanel junto con el resto.
 *   3. Visita https://tu-dominio.cl/db/create_admin.php?user=TU_USUARIO&pass=TU_CLAVE
 *      (elige un usuario y clave propios, no dejes los de ejemplo)
 *   4. Deberia mostrar "Usuario creado/actualizado correctamente".
 *   5. IMPORTANTE: borra este archivo del servidor apenas termines. Si queda
 *      publicado, cualquiera podria crear o resetear el usuario admin.
 */

require __DIR__ . '/../api/db.php';

$user = $_GET['user'] ?? '';
$pass = $_GET['pass'] ?? '';

if ($user === '' || $pass === '') {
    http_response_code(400);
    echo "Uso: create_admin.php?user=TU_USUARIO&pass=TU_CLAVE";
    exit;
}

if (strlen($pass) < 8) {
    http_response_code(400);
    echo "La clave debe tener al menos 8 caracteres.";
    exit;
}

$hash = password_hash($pass, PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    'INSERT INTO admin_users (username, password_hash) VALUES (:u, :h)
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)'
);
$stmt->execute(['u' => $user, 'h' => $hash]);

echo "Usuario '" . htmlspecialchars($user, ENT_QUOTES) . "' creado/actualizado correctamente. Ahora borra este archivo (db/create_admin.php) del servidor.";
