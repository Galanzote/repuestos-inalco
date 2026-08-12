<?php
/**
 * Incluir al inicio de cualquier pagina protegida del panel.
 * Si no hay sesion de admin activa, redirige a login.php.
 */

require __DIR__ . '/_session.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Token CSRF para las acciones que modifican datos (cambiar estado de un pedido).
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
