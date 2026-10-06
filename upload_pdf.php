<?php
// ═══════════════════════════════════════════════════════════════
// VisionCar — Recebe PDF do app e sobe pro Upstash Blob
// POST /upload-pdf
// ═══════════════════════════════════════════════════════════════

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

require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

try {
    $s3 = new S3Client([
        'version' => 'latest',
        'region'  => $BLOB_REGION,
        'endpoint' => $BLOB_ENDPOINT,
        'use_path_style_endpoint' => true,
        'credentials' => [
            'key'    => $BLOB_ACCESS_KEY,
            'secret' => $BLOB_SECRET_KEY,
        ],
    ]);

    $key = 'notas/' . $pdfNome;

    $result = $s3->putObject([
        'Bucket' => $BLOB_BUCKET,
        'Key'    => $key,
        'Body'   => fopen($pdfTmp, 'rb'),
        'ContentType' => 'application/pdf',
        'ACL'    => 'public-read',
    ]);

    $pdfUrl = $BLOB_ENDPOINT . '/' . $BLOB_BUCKET . '/' . $key;

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

} catch (AwsException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro no upload: ' . $e->getMessage()]);
}
