<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/../api/db.php';

$estadosValidos = ['pendiente', 'despachado', 'entregado'];

// Cambiar estado de un pedido (accion que modifica datos → protegida con CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_estado') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Token invalido');
    }
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $nuevoEstado = $_POST['estado'] ?? '';
    if ($orderId && in_array($nuevoEstado, $estadosValidos, true)) {
        $stmt = $pdo->prepare('UPDATE orders SET estado = :e WHERE id = :id');
        $stmt->execute(['e' => $nuevoEstado, 'id' => $orderId]);
    }
    header('Location: index.php' . (isset($_GET['estado']) ? '?estado=' . urlencode($_GET['estado']) : ''));
    exit;
}

// Filtro por estado
$filtro = $_GET['estado'] ?? '';
if ($filtro !== '' && !in_array($filtro, $estadosValidos, true)) {
    $filtro = '';
}

if ($filtro !== '') {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE estado = :e ORDER BY fecha DESC');
    $stmt->execute(['e' => $filtro]);
} else {
    $stmt = $pdo->query('SELECT * FROM orders ORDER BY fecha DESC');
}
$orders = $stmt->fetchAll();

function fmtMoney($n) { return '$' . number_format((float) $n, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel de Pedidos — Inalco</title>
  <link rel="stylesheet" href="admin.css">
</head>
<body>
  <div class="admin-header">
    <h1>📋 Panel de Pedidos — Inalco</h1>
    <div>
      <span style="margin-right:16px;font-size:13px;">👤 <?= htmlspecialchars($_SESSION['admin_user'] ?? '', ENT_QUOTES) ?></span>
      <a href="logout.php">Cerrar sesión</a>
    </div>
  </div>

  <div class="admin-wrap">
    <div class="filters">
      <a href="index.php" class="<?= $filtro === '' ? 'active' : '' ?>">Todos (<?= count($orders) ?>)</a>
      <a href="?estado=pendiente" class="<?= $filtro === 'pendiente' ? 'active' : '' ?>">Pendientes</a>
      <a href="?estado=despachado" class="<?= $filtro === 'despachado' ? 'active' : '' ?>">Despachados</a>
      <a href="?estado=entregado" class="<?= $filtro === 'entregado' ? 'active' : '' ?>">Entregados</a>
    </div>

    <?php if (!$orders): ?>
      <div class="empty">Todavía no hay pedidos<?= $filtro ? ' con este estado' : '' ?>.</div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>N°</th><th>Fecha</th><th>Cliente</th><th>RUT</th><th>Contacto</th>
          <th>Entrega</th><th>Productos</th><th>Total</th><th>Estado</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
          <?php $items = json_decode($o['items'], true) ?: []; ?>
          <tr>
            <td><?= htmlspecialchars($o['order_code'], ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($o['fecha'])), ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($o['nombre'], ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($o['rut'], ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($o['telefono'], ENT_QUOTES) ?><br><span style="color:#888"><?= htmlspecialchars($o['email'], ENT_QUOTES) ?></span></td>
            <td><?= $o['delivery_type'] === 'retiro' ? 'Retiro en bodega' : 'Despacho — ' . htmlspecialchars($o['comuna'], ENT_QUOTES) ?></td>
            <td class="items-detail"><?php foreach ($items as $it): ?>
              <?= htmlspecialchars(($it['titulo'] ?? ''), ENT_QUOTES) ?> x<?= (int) ($it['qty'] ?? 0) ?><br>
            <?php endforeach; ?></td>
            <td><strong><?= fmtMoney($o['total']) ?></strong></td>
            <td>
              <form method="post" style="display:flex;gap:6px;align-items:center;">
                <input type="hidden" name="action" value="update_estado">
                <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">
                <span class="badge badge-<?= htmlspecialchars($o['estado'], ENT_QUOTES) ?>"><?= htmlspecialchars($o['estado'], ENT_QUOTES) ?></span>
                <select name="estado" class="estado-select" onchange="this.form.submit()">
                  <?php foreach ($estadosValidos as $e): ?>
                    <option value="<?= $e ?>" <?= $e === $o['estado'] ? 'selected' : '' ?>><?= ucfirst($e) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</body>
</html>
