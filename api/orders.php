<?php
/**
 * Endpoint publico: recibe un pedido nuevo desde el sitio (submitOrder en app.js)
 * y lo guarda en la base de datos. Solo permite INSERT — nunca lectura de pedidos
 * ajenos desde aca (eso vive en admin/index.php, protegido por sesion).
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodo no permitido']);
    exit;
}

require __DIR__ . '/db.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON invalido']);
    exit;
}

function reqString($data, $key, $maxLen) {
    if (!isset($data[$key]) || !is_string($data[$key])) return null;
    $v = trim($data[$key]);
    if ($v === '' || mb_strlen($v) > $maxLen) return null;
    return $v;
}

$orderCode = reqString($data, 'id', 40);
$nombre    = reqString($data, 'nombre', 150);
$rut       = reqString($data, 'rut', 20);
$email     = reqString($data, 'email', 150);
$telefono  = reqString($data, 'telefono', 30);
$deliveryType = reqString($data, 'deliveryType', 20);
$fechaRaw  = reqString($data, 'fecha', 40);

$direccion = isset($data['direccion']) && is_string($data['direccion']) ? mb_substr(trim($data['direccion']), 0, 200) : '';
$comuna    = isset($data['comuna'])    && is_string($data['comuna'])    ? mb_substr(trim($data['comuna']), 0, 100)    : '';
$ciudad    = isset($data['ciudad'])    && is_string($data['ciudad'])    ? mb_substr(trim($data['ciudad']), 0, 100)    : '';
$notas     = isset($data['notas'])     && is_string($data['notas'])     ? mb_substr(trim($data['notas']), 0, 2000)   : '';

$items = isset($data['items']) && is_array($data['items']) ? $data['items'] : null;
$total = isset($data['total']) && is_numeric($data['total']) ? (int) $data['total'] : null;

if (!$orderCode || !$nombre || !$rut || !$email || !$telefono || !$deliveryType || !$items || $total === null) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Faltan campos obligatorios']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Email invalido']);
    exit;
}

$fecha = date('Y-m-d H:i:s', $fechaRaw ? strtotime($fechaRaw) : time());

$stmt = $pdo->prepare(
    'INSERT INTO orders (order_code, fecha, nombre, rut, email, telefono, delivery_type, direccion, comuna, ciudad, notas, items, total)
     VALUES (:order_code, :fecha, :nombre, :rut, :email, :telefono, :delivery_type, :direccion, :comuna, :ciudad, :notas, :items, :total)'
);

try {
    $stmt->execute([
        'order_code'    => $orderCode,
        'fecha'         => $fecha,
        'nombre'        => $nombre,
        'rut'           => $rut,
        'email'         => $email,
        'telefono'      => $telefono,
        'delivery_type' => $deliveryType,
        'direccion'     => $direccion,
        'comuna'        => $comuna,
        'ciudad'        => $ciudad,
        'notas'         => $notas,
        'items'         => json_encode($items, JSON_UNESCAPED_UNICODE),
        'total'         => $total,
    ]);
} catch (PDOException $e) {
    // order_code duplicado u otro error de BD — no exponemos el detalle interno
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar el pedido']);
    exit;
}

echo json_encode(['ok' => true]);
