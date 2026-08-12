<?php
require __DIR__ . '/_session.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../api/db.php';

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Completa usuario y clave.';
    } else {
        $stmt = $pdo->prepare('SELECT id, password_hash FROM admin_users WHERE username = :u LIMIT 1');
        $stmt->execute(['u' => $username]);
        $row = $stmt->fetch();

        if ($row && password_verify($password, $row['password_hash'])) {
            unset($_SESSION['login_attempts']);
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $row['id'];
            $_SESSION['admin_user'] = $username;
            header('Location: index.php');
            exit;
        } else {
            // Freno simple contra fuerza bruta: mas intentos fallidos, mas espera.
            $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
            usleep(min($_SESSION['login_attempts'] * 400000, 3000000));
            $error = 'Usuario o clave incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acceso Panel — Inalco</title>
  <link rel="stylesheet" href="admin.css">
</head>
<body>
  <div class="login-box">
    <h1>📋 Panel de Pedidos</h1>
    <?php if ($error): ?>
      <div class="login-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
      <label>Usuario</label>
      <input type="text" name="username" autocomplete="username" required autofocus>
      <label>Clave</label>
      <input type="password" name="password" autocomplete="current-password" required>
      <button type="submit">Ingresar</button>
    </form>
  </div>
</body>
</html>
