-- Panel de control de pedidos — Inalco Repuestos
-- Correr una sola vez en phpMyAdmin (dentro de la base de datos que crees en cPanel).
-- IMPORTANTE: cambia los nombres de tabla si tu prefijo de cPanel los antepone
-- automaticamente (ej: usuario_orders) — deja que cPanel lo maneje, este script
-- ya asume que estas parado dentro de la base de datos correcta.

CREATE TABLE IF NOT EXISTS orders (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_code    VARCHAR(40)  NOT NULL UNIQUE,
  fecha         DATETIME     NOT NULL,
  nombre        VARCHAR(150) NOT NULL,
  rut           VARCHAR(20)  NOT NULL,
  email         VARCHAR(150) NOT NULL,
  telefono      VARCHAR(30)  NOT NULL,
  delivery_type VARCHAR(20)  NOT NULL,
  direccion     VARCHAR(200) NOT NULL DEFAULT '',
  comuna        VARCHAR(100) NOT NULL DEFAULT '',
  ciudad        VARCHAR(100) NOT NULL DEFAULT '',
  notas         TEXT         NULL,
  items         JSON         NOT NULL,
  total         INT UNSIGNED NOT NULL,
  estado        ENUM('pendiente','despachado','entregado') NOT NULL DEFAULT 'pendiente',
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_estado (estado),
  INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(60)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No insertamos el usuario admin aca porque la clave debe quedar hasheada
-- con password_hash() de PHP, no en texto plano dentro de un script SQL.
-- Ver db/create_admin.php para crear el primer usuario del panel.
