<?php
/**
 * Endpoint publico: recibe una solicitud de llamada desde el sitio
 * (submitCallLead en app.js) y la envia por correo a ventas. No guarda
 * nada en base de datos — si en algun momento se necesita verlas en el
 * panel de admin, se puede extender igual que se hizo con pedidos.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodo no permitido']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON invalido']);
    exit;
}

function reqString($data, $key, $maxLen) {
    if (!isset($data[$key]) || !is_string($data[$key])) return '';
    return mb_substr(trim($data[$key]), 0, $maxLen);
}

$nombre   = reqString($data, 'nombre', 150);
$telefono = reqString($data, 'telefono', 30);
$consulta = reqString($data, 'consulta', 1000);

if ($telefono === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Falta el telefono']);
    exit;
}

$destino = 'aborges@inalco.cl';
$remitente = 'llamadas@inalcotiendaonline.cl';
$asunto = '=?UTF-8?B?' . base64_encode('Nueva solicitud de llamada - Inalco') . '?=';

$cuerpo  = "Se recibio una nueva solicitud de llamada desde el sitio.\n\n";
$cuerpo .= 'Nombre: ' . ($nombre !== '' ? $nombre : 'No indicado') . "\n";
$cuerpo .= "Telefono: {$telefono}\n";
$cuerpo .= 'Consulta: ' . ($consulta !== '' ? $consulta : 'Sin especificar') . "\n";
$cuerpo .= 'Fecha: ' . date('d-m-Y H:i') . "\n";

$headers  = "From: Inalco Sitio Web <{$remitente}>\r\n";
$headers .= "Reply-To: {$remitente}\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$enviado = @mail($destino, $asunto, $cuerpo, $headers, "-f {$remitente}");

if ($enviado) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo enviar el correo']);
}
