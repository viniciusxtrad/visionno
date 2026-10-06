<?php
// ═══════════════════════════════════════════════════════════════
// VisionCar — Recebe PDF do app e sobe pro Upstash Blob
// POST /upload-pdf
// ═══════════════════════════════════════════════════════════════

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

$BLOB_ENDPOINT   = getenv('UPSTASH_BLOB_ENDPOINT')   ?: 'https://bf69fa0872bd.blob.upstash.io';
$BLOB_REGION     = getenv('UPSTASH_BLOB_REGION')     ?: 'us-east-1';
$BLOB_BUCKET     = getenv('UPSTASH_BLOB_BUCKET')     ?: 'visioncar';
$BLOB_ACCESS_KEY = getenv('UPSTASH_BLOB_ACCESS_KEY') ?: '';
$BLOB_SECRET_KEY = getenv('UPSTASH_BLOB_SECRET_KEY') ?: '';
$REDIS_URL       = getenv('UPSTASH_REDIS_REST_URL')   ?: 'https://eminent-blowfish-205260.upstash.io';
$REDIS_TOKEN     = getenv('UPSTASH_REDIS_REST_TOKEN') ?: 'gQAAAAAAAyHMAAIgcDI5N2ZjYTc0YzljZWE0ZDc2YWE5M2M1YTdhNjUxODRiYw';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
    exit;
}

if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['erro' => 'PDF não enviado ou com erro']);
    exit;
}

$os       = $_POST['os']       ?? 'OS-0000';
$cliente  = $_POST['cliente']  ?? 'Cliente';
$telefone = $_POST['telefone'] ?? '';
$veiculo  = $_POST['veiculo']  ?? '';
$placa    = $_POST['placa']    ?? '';
$mecanico = $_POST['mecanico'] ?? '';
$data     = $_POST['data']     ?? date('d/m/Y H:i');
$total    = $_POST['total']    ?? 'R$ 0,00';

$pdfTmp = $_FILES['pdf']['tmp_name'];
$pdfNome = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $_FILES['pdf']['name']);

// ─── Faz upload pro Upstash Blob (S3) via HTTP PUT ───
$key = 'notas/' . $pdfNome;
$url = $BLOB_ENDPOINT . '/' . $BLOB_BUCKET . '/' . $key;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_PUT, true);
curl_setopt($ch, CURLOPT_INFILE, fopen($pdfTmp, 'rb'));
curl_setopt($ch, CURLOPT_INFILESIZE, filesize($pdfTmp));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $BLOB_ACCESS_KEY,
    'Content-Type: application/pdf',
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode < 200 || $httpCode >= 300) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro no upload pro Blob: HTTP ' . $httpCode]);
    exit;
}

$pdfUrl = $url;

// ─── Salva no Redis ───
$token = substr(bin2hex(random_bytes(4)), 0, 6);

$nota = json_encode([
    'os'        => $os,
    'cliente'   => $cliente,
    'telefone'  => $telefone,
    'veiculo'   => $veiculo,
    'placa'     => $placa,
    'mecanico'  => $mecanico,
    'data'      => $data,
    'total'     => $total,
    'status'    => 'Concluída',
    'jpg_url'   => $pdfUrl,
    'pdf_url'   => $pdfUrl,
    'whatsapp'  => '5544997022672',
    'criado_em' => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_UNICODE);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $REDIS_URL . '/set/' . urlencode($token) . '?EX=31536000');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $nota);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $REDIS_TOKEN,
    'Content-Type: text/plain'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_exec($ch);
curl_close($ch);

$link = 'https://' . $_SERVER['HTTP_HOST'] . '/nota/' . $os . '-' . $token;

echo json_encode([
    'sucesso' => true,
    'link'    => $link,
    'pdf_url' => $pdfUrl,
], JSON_UNESCAPED_UNICODE);
