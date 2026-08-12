<?php
/**
 * Barra superior compartida por todas las paginas del panel.
 * Requiere que $_SESSION ya este iniciada (via auth.php) antes de incluir.
 * Variable opcional $navTitle para el titulo de cada pagina.
 */
?>
  <div class="admin-header">
    <h1><?= $navTitle ?? 'Panel — Inalco' ?></h1>
    <div>
      <a href="index.php" style="margin-right:16px;">Pedidos</a>
      <a href="productos.php" style="margin-right:16px;">Precios y Stock</a>
      <a href="importar.php" style="margin-right:16px;">Importar Precios</a>
      <a href="producto-nuevo.php" style="margin-right:16px;">Agregar Producto</a>
      <span style="margin-right:16px;font-size:13px;">👤 <?= htmlspecialchars($_SESSION['admin_user'] ?? '', ENT_QUOTES) ?></span>
      <a href="logout.php">Cerrar sesión</a>
    </div>
  </div>
