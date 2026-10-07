import express from "express";
import multer from "multer";
import { Bucket } from "@upstash/blob";
import { Redis } from "@upstash/redis";

const app = express();
const PORT = process.env.PORT || 3000;

// ═══════════════════════════════════════════════════════════════
// Configurações (via variáveis de ambiente do Render)
// ═══════════════════════════════════════════════════════════════
const BLOB_TOKEN = process.env.UPSTASH_BLOB_TOKEN;
const REDIS_URL = process.env.UPSTASH_REDIS_REST_URL;
const REDIS_TOKEN = process.env.UPSTASH_REDIS_REST_TOKEN;

if (!BLOB_TOKEN) console.error("⚠️  UPSTASH_BLOB_TOKEN não configurado!");
if (!REDIS_URL || !REDIS_TOKEN) console.error("⚠️  Redis não configurado!");

const bucket = new Bucket({ token: BLOB_TOKEN });
const redis = new Redis({ url: REDIS_URL, token: REDIS_TOKEN });

// Multer — recebe PDF + JPG na memória
const upload = multer({ storage: multer.memoryStorage() });

// ═══════════════════════════════════════════════════════════════
// Rota raiz
// ═══════════════════════════════════════════════════════════════
app.get("/", (req, res) => {
    res.send(`
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>VisionCar</title>
            <style>
                body { font-family: -apple-system, sans-serif; background: #0A0E14; color: #E6E6E6;
                       display: flex; align-items: center; justify-content: center;
                       min-height: 100vh; margin: 0; padding: 20px; text-align: center; }
                .card { max-width: 400px; background: #161B22; border: 1px solid #1F2630;
                        border-radius: 20px; padding: 40px 30px; }
                .logo { width: 80px; height: 80px; border-radius: 50%; background: #D4A24C;
                        color: #0A0E14; font-size: 36px; font-weight: 900;
                        display: flex; align-items: center; justify-content: center;
                        margin: 0 auto 20px; }
                h1 { color: #D4A24C; font-size: 22px; margin-bottom: 10px; }
                p { color: #8B949E; font-size: 14px; line-height: 1.6; }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="logo">V</div>
                <h1>VISION CAR</h1>
                <p>Sistema de Notas para Oficinas</p>
                <p style="margin-top:20px;font-size:12px">Acesse o link completo da nota para visualizar</p>
            </div>
        </body>
        </html>
    `);
});

// ═══════════════════════════════════════════════════════════════
// Ping (UptimeRobot)
// ═══════════════════════════════════════════════════════════════
app.get("/ping", (req, res) => {
    res.type("text/plain").send("OK");
});

// ═══════════════════════════════════════════════════════════════
// Upload do PDF + JPG
// ═══════════════════════════════════════════════════════════════
app.post("/upload-pdf", upload.fields([
    { name: "pdf", maxCount: 1 },
    { name: "jpg", maxCount: 1 }
]), async (req, res) => {
    try {
        console.log("📥 Recebendo upload...");

        const pdfFile = req.files && req.files["pdf"] ? req.files["pdf"][0] : null;
        const jpgFile = req.files && req.files["jpg"] ? req.files["jpg"][0] : null;

        if (!pdfFile) {
            return res.status(400).json({ erro: "PDF não enviado" });
        }

        console.log("📄 PDF:", pdfFile.originalname, "Tamanho:", pdfFile.size, "bytes");
        if (jpgFile) {
            console.log("🖼️  JPG:", jpgFile.originalname, "Tamanho:", jpgFile.size, "bytes");
        } else {
            console.log("⚠️  JPG não enviado!");
        }

        const os = req.body.os || "OS-0000";
        const cliente = req.body.cliente || "Cliente";
        const telefone = req.body.telefone || "";
        const veiculo = req.body.veiculo || "";
        const placa = req.body.placa || "";
        const mecanico = req.body.mecanico || "";
        const data = req.body.data || new Date().toLocaleString("pt-BR");
        const total = req.body.total || "R$ 0,00";

        // Sanitiza nomes
        const pdfNome = pdfFile.originalname.replace(/[^A-Za-z0-9_\-\.]/g, "_");
        const jpgNome = jpgFile
            ? jpgFile.originalname.replace(/[^A-Za-z0-9_\-\.]/g, "_")
            : pdfNome.replace(/\.pdf$/i, ".jpg");

        const pdfKey = `notas/${pdfNome}`;
        const jpgKey = `notas/${jpgNome}`;

        // Upload PDF
        const pdfBlob = await bucket.put(pdfKey, pdfFile.buffer);
        const pdfUrl = pdfBlob.url;
        console.log("✅ PDF no Blob:", pdfUrl);

        // Upload JPG (se tiver)
        let jpgUrl = pdfUrl; // fallback
        if (jpgFile) {
            const jpgBlob = await bucket.put(jpgKey, jpgFile.buffer);
            jpgUrl = jpgBlob.url;
            console.log("✅ JPG no Blob:", jpgUrl);
        } else {
            console.log("⚠️  Usando PDF como jpg_url (preview não vai funcionar)");
        }

        // Gera token curto
        const token = Math.random().toString(36).substring(2, 8);

        // Salva no Redis
        const nota = {
            os, cliente, telefone, veiculo, placa, mecanico, data, total,
            status: "Concluída",
            jpg_url: jpgUrl,      // ← IMAGEM pro preview
            pdf_url: pdfUrl,      // ← PDF pro download
            whatsapp: "5544997022672",
            criado_em: new Date().toISOString()
        };

        await redis.set(token, JSON.stringify(nota), { ex: 31536000 });

        const link = `https://${req.get("host")}/nota/${os}-${token}`;

        console.log("🔗 Link gerado:", link);

        res.json({
            sucesso: true,
            link,
            jpg_url: jpgUrl,
            pdf_url: pdfUrl
        });

    } catch (err) {
        console.error("❌ Erro no upload:", err);
        res.status(500).json({ erro: "Erro no upload: " + err.message });
    }
});

// ═══════════════════════════════════════════════════════════════
// Página da nota
// ═══════════════════════════════════════════════════════════════
app.get("/nota/:slug", async (req, res) => {
    try {
        const slug = req.params.slug;
        const token = slug.split("-").pop();

        if (!token || token.length < 6) {
            return res.status(404).send("<h1>Link inválido</h1>");
        }

        const json = await redis.get(token);

        if (!json) {
            return res.status(404).send(`
                <!DOCTYPE html>
                <html><head><meta charset="UTF-8"><title>Nota não encontrada</title></head>
                <body style="font-family:sans-serif;background:#0A0E14;color:#E6E6E6;text-align:center;padding:50px;">
                <h1>❌ Nota não encontrada</h1><p>Este link é inválido ou expirou.</p>
                </body></html>
            `);
        }

        const dados = typeof json === "string" ? JSON.parse(json) : json;

        const os = dados.os || "OS-0000";
        const cliente = dados.cliente || "Cliente";
        const telefone = dados.telefone || "";
        const veiculo = dados.veiculo || "";
        const placa = dados.placa || "";
        const mecanico = dados.mecanico || "";
        const data = dados.data || "";
        const total = dados.total || "R$ 0,00";
        const status = dados.status || "Concluída";
        const jpg_url = dados.jpg_url || "";
        const pdf_url = dados.pdf_url || "";
        const whatsapp = (dados.whatsapp || "5544997022672").replace(/\D/g, "");

        const pageUrl = `https://${req.get("host")}/nota/${slug}`;
        const descricao = `Cliente: ${cliente} | Veículo: ${veiculo} | Total: ${total}`;

        console.log("📄 Página da nota:", os, "| jpg_url:", jpg_url);

        res.send(`
            <!DOCTYPE html>
            <html lang="pt-BR">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">

                <meta property="og:title" content="Ordem de Serviço #${os} — VisionCar">
                <meta property="og:description" content="${descricao}">
                <meta property="og:image" content="${jpg_url}">
                <meta property="og:image:type" content="image/jpeg">
                <meta property="og:image:width" content="1200">
                <meta property="og:image:height" content="630">
                <meta property="og:type" content="website">
                <meta property="og:url" content="${pageUrl}">
                <meta property="og:site_name" content="VisionCar">

                <meta name="twitter:card" content="summary_large_image">
                <meta name="twitter:title" content="Ordem de Serviço #${os} — VisionCar">
                <meta name="twitter:description" content="${descricao}">
                <meta name="twitter:image" content="${jpg_url}">

                <title>OS #${os} — VisionCar</title>

                <style>
                    * { box-sizing: border-box; margin: 0; padding: 0; }
                    body { font-family: -apple-system, sans-serif; background: #0A0E14;
                           color: #E6E6E6; padding: 16px; min-height: 100vh;
                           display: flex; align-items: center; justify-content: center; }
                    .card { max-width: 600px; width: 100%; background: #161B22;
                            border: 1px solid #1F2630; border-radius: 20px;
                            padding: 24px; box-shadow: 0 20px 60px rgba(0,0,0,0.5); }
                    .header { text-align: center; margin-bottom: 20px;
                              padding-bottom: 20px; border-bottom: 2px solid #D4A24C; }
                    .logo { width: 70px; height: 70px; border-radius: 50%;
                            background: #D4A24C; color: #0A0E14; font-size: 32px;
                            font-weight: 900; display: flex; align-items: center;
                            justify-content: center; margin: 0 auto 12px; }
                    h1 { font-size: 20px; color: #D4A24C; margin-bottom: 4px; }
                    .os-num { font-size: 13px; color: #8B949E; }
                    .status { display: inline-block; padding: 6px 14px;
                              border-radius: 20px; font-size: 11px; font-weight: 900;
                              margin-top: 12px; background: rgba(63,185,80,0.15);
                              color: #3FB950; border: 1px solid #3FB950; }
                    .info-grid { background: #0A0E14; border-radius: 12px;
                                 padding: 16px; margin-bottom: 16px; font-size: 13px; }
                    .info-row { display: flex; justify-content: space-between;
                                padding: 6px 0; border-bottom: 1px solid #1F2630; }
                    .info-row:last-child { border-bottom: none; }
                    .label { color: #8B949E; font-weight: 600; font-size: 12px;
                             text-transform: uppercase; }
                    .value { color: #E6E6E6; font-weight: bold; }
                    .value.total { color: #D4A24C; font-size: 16px; }
                    .preview-img { width: 100%; border-radius: 12px;
                                   border: 1px solid #1F2630; margin-bottom: 16px; }
                    .btn { display: block; width: 100%; padding: 18px;
                           border-radius: 16px; text-align: center;
                           text-decoration: none; font-weight: 900; font-size: 15px;
                           letter-spacing: 1px; text-transform: uppercase;
                           margin-bottom: 10px; }
                    .btn-pdf { background: #25D366; color: #fff;
                               box-shadow: 0 5px 0 #1a9e4a; }
                    .btn-whats { background: #1F2630; color: #D4A24C;
                                 border: 2px solid #D4A24C; }
                    .footer { text-align: center; font-size: 10px; color: #6E7681;
                              margin-top: 20px; padding-top: 16px;
                              border-top: 1px solid #1F2630; }
                    .footer strong { color: #D4A24C; }
                </style>
            </head>
            <body>
                <div class="card">
                    <div class="header">
                        <div class="logo">V</div>
                        <h1>MECÂNICA VISION CAR</h1>
                        <div class="os-num">ORDEM DE SERVIÇO #${os}</div>
                        <div class="status">✓ ${status.toUpperCase()}</div>
                    </div>

                    <div class="info-grid">
                        <div class="info-row"><span class="label">Cliente</span><span class="value">${cliente}</span></div>
                        ${telefone ? `<div class="info-row"><span class="label">Telefone</span><span class="value">${telefone}</span></div>` : ""}
                        ${veiculo ? `<div class="info-row"><span class="label">Veículo</span><span class="value">${veiculo}</span></div>` : ""}
                        ${placa ? `<div class="info-row"><span class="label">Placa</span><span class="value">${placa}</span></div>` : ""}
                        ${mecanico ? `<div class="info-row"><span class="label">Mecânico</span><span class="value">${mecanico}</span></div>` : ""}
                        <div class="info-row"><span class="label">Data</span><span class="value">${data}</span></div>
                        <div class="info-row"><span class="label">Total</span><span class="value total">${total}</span></div>
                    </div>

                    ${jpg_url ? `<img class="preview-img" src="${jpg_url}" alt="Preview">` : ""}

                    ${pdf_url ? `<a class="btn btn-pdf" href="${pdf_url}" download>📄 BAIXAR PDF COMPLETO</a>` : ""}

                    <a class="btn btn-whats" href="https://wa.me/${whatsapp}?text=Olá!%20Vi%20a%20OS%20%23${os}">
                        💬 FALAR COM A OFICINA
                    </a>

                    <div class="footer">
                        <strong>MECÂNICA VISION CAR</strong><br>
                        R. Mario Preto, 55 - Jd Hilario<br>
                        Terra Boa - PR | (44) 99702-2672
                    </div>
                </div>
            </body>
            </html>
        `);

    } catch (err) {
        console.error("❌ Erro na nota:", err);
        res.status(500).send("Erro ao carregar nota");
    }
});

app.listen(PORT, () => {
    console.log(`✅ VisionCar rodando na porta ${PORT}`);
});
