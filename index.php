<?php
// ═══════════════════════════════════════════════════════════════
// VisionCar — Landing page da Ordem de Serviço
// URL: /nota/OS-0043-a7f3b9
// ═══════════════════════════════════════════════════════════════

// ─── Configuração Upstash Redis ───
$REDIS_URL   = getenv('UPSTASH_REDIS_REST_URL')   ?: 'https://eminent-blowfish-205260.upstash.io';
$REDIS_TOKEN = getenv('UPSTASH_REDIS_REST_TOKEN') ?: 'gQAAAAAAAyHMAAIgcDI5N2ZjYTc0YzljZWE0ZDc2YWE5M2M1YTdhNjUxODRiYw';

// ─── Pega o token da URL ───
// URL: /nota/OS-0043-a7f3b9  →  pega "a7f3b9"
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($request_uri, PHP_URL_PATH);

// Remove /nota/ do começo
$slug = preg_replace('#^/nota/#', '', $path);
$slug = trim($slug, '/');

// Extrai o token (depois do último hífen)
$partes = explode('-', $slug);
$token = end($partes);

if (empty($token) || strlen($token) < 6) {
    http_response_code(404);
    echo "Link inválido";
    exit;
}

// ─── Consulta o Redis ───
function redisGet($url, $token, $key) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '/get/' . urlencode($key));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) return null;

    $data = json_decode($response, true);
    return $data['result'] ?? null;
}

$json = redisGet($REDIS_URL, $REDIS_TOKEN, $token);

if (!$json) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Nota não encontrada</title></head><body style="font-family:sans-serif;background:#0A0E14;color:#E6E6E6;text-align:center;padding:50px;"><h1>❌ Nota não encontrada</h1><p>Este link é inválido ou expirou.</p></body></html>';
    exit;
}

// Decodifica os dados
$dados = json_decode($json, true);

if (!$dados) {
    http_response_code(500);
    echo "Erro ao processar dados";
    exit;
}

// ─── Extrai os dados ───
$os       = htmlspecialchars($dados['os']      ?? 'OS-0000', ENT_QUOTES, 'UTF-8');
$cliente  = htmlspecialchars($dados['cliente'] ?? 'Cliente', ENT_QUOTES, 'UTF-8');
$telefone = htmlspecialchars($dados['telefone']?? '', ENT_QUOTES, 'UTF-8');
$veiculo  = htmlspecialchars($dados['veiculo'] ?? '', ENT_QUOTES, 'UTF-8');
$placa    = htmlspecialchars($dados['placa']   ?? '', ENT_QUOTES, 'UTF-8');
$mecanico = htmlspecialchars($dados['mecanico']?? '', ENT_QUOTES, 'UTF-8');
$data     = htmlspecialchars($dados['data']    ?? date('d/m/Y H:i'), ENT_QUOTES, 'UTF-8');
$total    = htmlspecialchars($dados['total']   ?? 'R$ 0,00', ENT_QUOTES, 'UTF-8');
$status   = htmlspecialchars($dados['status']  ?? 'Concluída', ENT_QUOTES, 'UTF-8');
$jpg_url  = $dados['jpg_url'] ?? '';
$pdf_url  = $dados['pdf_url'] ?? '';
$whatsapp = preg_replace('/[^0-9]/', '', $dados['whatsapp'] ?? '5544997022672');

// ─── Monta URL canônica da página ───
$base_url = 'https://' . $_SERVER['HTTP_HOST'];
$page_url = $base_url . '/nota/' . $slug;

// ─── Descrição pro og:description ───
$descricao = "Cliente: $cliente | Veículo: $veiculo | Total: $total";

// ─── Status badge ───
$status_class = 'status-ok';
$status_icon  = '✓';
$status_lower = strtolower($status);
if (strpos($status_lower, 'andamento') !== false) {
    $status_class = 'status-andamento';
    $status_icon  = '⏳';
} elseif (strpos($status_lower, 'aguardando') !== false) {
    $status_class = 'status-aguardando';
    $status_icon  = '⏰';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- ═══════════════ OPEN GRAPH — PREVIEW WHATSAPP ═══════════════ -->
<meta property="og:title" content="Ordem de Serviço #<?= $os ?> — VisionCar">
<meta property="og:description" content="<?= htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:image" content="<?= htmlspecialchars($jpg_url, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:image:type" content="image/jpeg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= htmlspecialchars($page_url, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:site_name" content="VisionCar">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Ordem de Serviço #<?= $os ?> — VisionCar">
<meta name="twitter:description" content="<?= htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8') ?>">
<meta name="twitter:image" content="<?= htmlspecialchars($jpg_url, ENT_QUOTES, 'UTF-8') ?>">

<title>OS #<?= $os ?> — VisionCar</title>

<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: #0A0E14;
    color: #E6E6E6;
    padding: 16px;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.card {
    max-width: 600px;
    width: 100%;
    background: #161B22;
    border: 1px solid #1F2630;
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    animation: fadeIn 0.4s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.header {
    text-align: center;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 2px solid #D4A24C;
}

.header .logo {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: #D4A24C;
    color: #0A0E14;
    font-size: 32px;
    font-weight: 900;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    box-shadow: 0 4px 20px rgba(212,162,76,0.4);
}

.header h1 {
    font-size: 20px;
    color: #D4A24C;
    margin-bottom: 4px;
    letter-spacing: 0.5px;
}

.header .os-num {
    font-size: 13px;
    color: #8B949E;
    letter-spacing: 1px;
}

.status {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 1px;
    margin-top: 12px;
}

.status-ok {
    background: rgba(63,185,80,0.15);
    color: #3FB950;
    border: 1px solid #3FB950;
}

.status-andamento {
    background: rgba(212,162,76,0.15);
    color: #D4A24C;
    border: 1px solid #D4A24C;
}

.status-aguardando {
    background: rgba(248,81,73,0.15);
    color: #F85149;
    border: 1px solid #F85149;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
    background: #0A0E14;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
    font-size: 13px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    border-bottom: 1px solid #1F2630;
}

.info-row:last-child { border-bottom: none; }

.info-row .label {
    color: #8B949E;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-row .value {
    color: #E6E6E6;
    font-weight: bold;
    text-align: right;
}

.info-row .value.total {
    color: #D4A24C;
    font-size: 16px;
}

.preview-img {
    width: 100%;
    border-radius: 12px;
    border: 1px solid #1F2630;
    margin-bottom: 16px;
    display: block;
    box-shadow: 0 8px 24px rgba(0,0,0,0.3);
}

.btn {
    display: block;
    width: 100%;
    padding: 18px;
    border-radius: 16px;
    text-align: center;
    text-decoration: none;
    font-weight: 900;
    font-size: 15px;
    letter-spacing: 1px;
    text-transform: uppercase;
    transition: all 0.15s;
    border: none;
    cursor: pointer;
    font-family: inherit;
}

.btn-pdf {
    background: #25D366;
    color: #fff;
    box-shadow: 0 5px 0 #1a9e4a;
    margin-bottom: 10px;
}

.btn-pdf:active {
    transform: translateY(2px);
    box-shadow: 0 2px 0 #1a9e4a;
}

.btn-whats {
    background: #1F2630;
    color: #D4A24C;
    border: 2px solid #D4A24C;
    box-shadow: none;
}

.btn-whats:active {
    transform: translateY(2px);
}

.qr-section {
    text-align: center;
    padding: 16px;
    background: #0A0E14;
    border-radius: 12px;
    margin-bottom: 16px;
}

.qr-section .qr-label {
    font-size: 11px;
    color: #8B949E;
    margin-bottom: 10px;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.qr-section .qr-code {
    width: 120px;
    height: 120px;
    background: #fff;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    font-size: 60px;
    color: #0A0E14;
}

.footer {
    text-align: center;
    font-size: 10px;
    color: #6E7681;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid #1F2630;
    line-height: 1.6;
}

.footer strong {
    color: #D4A24C;
}
</style>
</head>
<body>

<div class="card">

    <!-- ══════════════ HEADER ══════════════ -->
    <div class="header">
        <div class="logo">V</div>
        <h1>MECÂNICA VISION CAR</h1>
        <div class="os-num">ORDEM DE SERVIÇO #<?= $os ?></div>
        <div class="status <?= $status_class ?>"><?= $status_icon ?> <?= strtoupper($status) ?></div>
    </div>

    <!-- ══════════════ INFO ══════════════ -->
    <div class="info-grid">
        <div class="info-row">
            <span class="label">Cliente</span>
            <span class="value"><?= $cliente ?></span>
        </div>
        <?php if (!empty($telefone)): ?>
        <div class="info-row">
            <span class="label">Telefone</span>
            <span class="value"><?= $telefone ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($veiculo)): ?>
        <div class="info-row">
            <span class="label">Veículo</span>
            <span class="value"><?= $veiculo ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($placa)): ?>
        <div class="info-row">
            <span class="label">Placa</span>
            <span class="value"><?= $placa ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($mecanico)): ?>
        <div class="info-row">
            <span class="label">Mecânico</span>
            <span class="value"><?= $mecanico ?></span>
        </div>
        <?php endif; ?>
        <div class="info-row">
            <span class="label">Data</span>
            <span class="value"><?= $data ?></span>
        </div>
        <div class="info-row">
            <span class="label">Total</span>
            <span class="value total"><?= $total ?></span>
        </div>
    </div>

    <!-- ══════════════ PREVIEW DA NOTA ══════════════ -->
    <?php if (!empty($jpg_url)): ?>
    <img class="preview-img"
         src="<?= htmlspecialchars($jpg_url, ENT_QUOTES, 'UTF-8') ?>"
         alt="Preview da Ordem de Serviço #<?= $os ?>">
    <?php endif; ?>

    <!-- ══════════════ BOTÃO BAIXAR PDF ══════════════ -->
    <?php if (!empty($pdf_url)): ?>
    <a class="btn btn-pdf"
       href="<?= htmlspecialchars($pdf_url, ENT_QUOTES, 'UTF-8') ?>"
       download>
        📄 BAIXAR PDF COMPLETO
    </a>
    <?php endif; ?>

    <!-- ══════════════ BOTÃO WHATSAPP OFICINA ══════════════ -->
    <a class="btn btn-whats"
       href="https://wa.me/<?= $whatsapp ?>?text=Olá!%20Vi%20a%20OS%20%23<?= urlencode($os) ?>">
        💬 FALAR COM A OFICINA
    </a>

    <!-- ══════════════ QR CODE ══════════════ -->
    <div class="qr-section">
        <div class="qr-label">Escaneie para guardar</div>
        <div class="qr-code">📱</div>
    </div>

    <!-- ══════════════ FOOTER ══════════════ -->
    <div class="footer">
        <strong>MECÂNICA VISION CAR</strong><br>
        R. Mario Preto, 55 - Jd Hilario<br>
        Terra Boa - PR | (44) 99702-2672<br>
        <br>
        Este link é único e intransferível<br>
        Gerado em <?= $data ?>
    </div>

</div>

</body>
</html>
