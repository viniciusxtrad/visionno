<?php
// ═══════════════════════════════════════════════════════════════
// VisionCar — Recebe upload do app
// POST /upload
// Body JSON: { os, cliente, telefone, veiculo, placa, mecanico,
//              data, total, status, jpg_url, pdf_url }
// Retorna: { link: "https://.../nota/OS-0043-a7f3b9" }
// ═══════════════════════════════════════════════════════════════

header('Content-Type: application/json');

// ─── Configuração Upstash Redis ───
$REDIS_URL   = getenv('UPSTASH_REDIS_REST_URL')   ?: 'https://eminent-blowfish-205260.upstash.io';
$REDIS_TOKEN = getenv('UPSTASH_REDIS_REST_TOKEN') ?: 'gQAAAAAAAyHMAAIgcDI5N2ZjYTc0YzljZWE0ZDc2YWE5M2M1YTdhNjUxODRiYw';

// ─── Só aceita POST ───
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
    exit;
}

// ─── Lê o body JSON ───
$body = file_get_contents('php://input');
$dados = json_decode($body, true);

if (!$dados) {
    http_response_code(400);
    echo json_encode(['erro' => 'JSON inválido']);
    exit;
}

// ─── Valida campos obrigatórios ───
if (empty($dados['os']) || empty($dados['jpg_url']) || empty($dados['pdf_url'])) {
    http_response_code(400);
    echo json_encode(['erro' => 'Campos obrigatórios: os, jpg_url, pdf_url']);
    exit;
}

// ─── Gera token único (6 caracteres) ───
function gerarToken($tamanho = 6) {
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $token = '';
    for ($i = 0; $i < $tamanho; $i++) {
        $token .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $token;
}

$token = gerarToken(6);

// ─── Monta o JSON pra salvar no Redis ───
$nota = [
    'os'       => $dados['os'],
    'cliente'  => $dados['cliente']  ?? 'Cliente',
    'telefone' => $dados['telefone'] ?? '',
    'veiculo'  => $dados['veiculo']  ?? '',
    'placa'    => $dados['placa']    ?? '',
    'mecanico' => $dados['mecanico'] ?? '',
    'data'     => $dados['data']     ?? date('d/m/Y H:i'),
    'total'    => $dados['total']    ?? 'R$ 0,00',
    'status'   => $dados['status']   ?? 'Concluída',
    'jpg_url'  => $dados['jpg_url'],
    'pdf_url'  => $dados['pdf_url'],
    'whatsapp' => $dados['whatsapp'] ?? '5544997022672',
    'criado_em'=> date('Y-m-d H:i:s'),
];

// ─── Salva no Redis ───
function redisSet($url, $token, $key, $value) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '/set/' . urlencode($key));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $value);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: text/plain'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode === 200;
}

// ─── Salva com expiração de 365 dias ───
$json = json_encode($nota, JSON_UNESCAPED_UNICODE);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $REDIS_URL . '/set/' . urlencode($token) . '?EX=31536000');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $REDIS_TOKEN,
    'Content-Type: text/plain'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    http_response_code(500);
    echo json_encode(['erro' => 'Falha ao salvar no banco']);
    exit;
}

// ─── Monta o link ───
$base_url = 'https://' . $_SERVER['HTTP_HOST'];
$slug = $dados['os'] . '-' . $token;
$link = $base_url . '/nota/' . $slug;

// ─── Retorna o link ───
echo json_encode([
    'sucesso' => true,
    'token'   => $token,
    'slug'    => $slug,
    'link'    => $link,
], JSON_UNESCAPED_UNICODE);
