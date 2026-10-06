package com.mecanicasouza.invoice;

import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Context;
import android.content.Intent;
import android.graphics.Bitmap;
import android.graphics.pdf.PdfRenderer;
import android.net.Uri;
import android.os.Bundle;
import android.os.ParcelFileDescriptor;
import android.widget.Button;
import android.widget.ImageView;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AlertDialog;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.content.FileProvider;

import java.io.File;
import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Date;
import java.util.List;
import java.util.Locale;

public class PdfPreviewActivity extends AppCompatActivity {

    private ImageView imagePreview;
    private TextView textOrderNumber;
    private Button buttonConfirm, buttonShare, buttonCopyCoupon, buttonPrint;
    private File pdfFile;
    private String orderNumber;
    private String date, client, phone, address, vehicle, plate, mechanic;
    private List<Item> items;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        setTheme(R.style.AppTheme_Lite);
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_pdf_preview);

        String pdfPath = getIntent().getStringExtra("pdf_path");
        orderNumber    = getIntent().getStringExtra("order_number");
        date           = getIntent().getStringExtra("date");
        client         = getIntent().getStringExtra("client");
        phone          = getIntent().getStringExtra("phone");
        address        = getIntent().getStringExtra("address");
        vehicle        = getIntent().getStringExtra("vehicle");
        plate          = getIntent().getStringExtra("plate");
        mechanic       = getIntent().getStringExtra("mechanic");
        items          = (List<Item>) getIntent().getSerializableExtra("items");
        if (items == null) items = new ArrayList<>();

        pdfFile = new File(pdfPath);

        imagePreview     = findViewById(R.id.image_preview);
        textOrderNumber  = findViewById(R.id.text_order_number);
        buttonConfirm    = findViewById(R.id.button_confirm);
        buttonShare      = findViewById(R.id.button_share);
        buttonCopyCoupon = findViewById(R.id.button_copy_coupon);
        buttonPrint      = findViewById(R.id.button_print);

        textOrderNumber.setText("Ordem Nº: " + orderNumber);
        renderPdfPreview();

        buttonConfirm.setOnClickListener(v -> confirmAndBackup());
        buttonShare.setOnClickListener(v -> sharePdf());
        buttonCopyCoupon.setOnClickListener(v -> copyCoupon());
        buttonPrint.setOnClickListener(v -> showPrintOptions());
    }

    private void showPrintOptions() {
        String coupon = buildCoupon();
        Intent intent = new Intent(Intent.ACTION_SEND);
        intent.setType("text/plain");
        intent.putExtra(Intent.EXTRA_TEXT, coupon);
        intent.putExtra(Intent.EXTRA_SUBJECT, "Cupom OS " + orderNumber);
        startActivity(Intent.createChooser(intent, "Imprimir cupom com:"));
    }

    private void copyCoupon() {
        String coupon = buildCoupon();
        ClipboardManager cb = (ClipboardManager) getSystemService(Context.CLIPBOARD_SERVICE);
        cb.setPrimaryClip(ClipData.newPlainText("Cupom", coupon));
        Toast.makeText(this, "✅ Cupom copiado!", Toast.LENGTH_LONG).show();
    }

    private String buildCoupon() {
        String L = "================================";
        String l = "--------------------------------";
        StringBuilder s = new StringBuilder();

        s.append(L).append("\n");
        s.append(cen(new WorkshopSettingsManager(this).getShopName())).append("\n");
        s.append(wl(new WorkshopSettingsManager(this).getShopAddress())).append("\n");
        s.append(L).append("\n");
        s.append(cen("ORDEM DE SERVIÇO")).append("\n");
        s.append(L).append("\n");
        s.append("\n");

        s.append("OS: ").append(nn(orderNumber)).append("\n");
        s.append("Data: ").append(nn(date)).append("\n");
        s.append("\n");

        s.append("CLIENTE").append("\n");
        s.append(l).append("\n");
        s.append(wl("Nome: " + nn(client))).append("\n");
        s.append(wl("Fone: " + nn(phone))).append("\n");
        if (!nn(address).isEmpty())
            s.append(wl("End.: " + nn(address))).append("\n");
        s.append("\n");

        s.append("VEÍCULO").append("\n");
        s.append(l).append("\n");
        s.append(wl(nn(vehicle))).append("\n");
        if (!nn(plate).isEmpty())
            s.append("Placa: ").append(nn(plate)).append("\n");
        s.append("\n");

        s.append(L).append("\n");
        s.append("SERVIÇOS").append("\n");
        s.append(l).append("\n");
        s.append("Qtd  Descrição         Vl.Un  Total").append("\n");
        s.append(l).append("\n");

        double total = 0;
        for (Item item : items) {
            if (item.quantity == 0 && item.description.trim().isEmpty()) continue;
            double t = item.getTotalValue();
            total += t;
            s.append(String.format("%-4d %-18s %5.2f %6.2f%n",
                item.quantity, trunc(item.description, 18),
                item.unitValue, t));
        }

        s.append(l).append("\n");
        s.append("\n");
        s.append(cen(String.format("TOTAL: R$ %.2f", total))).append("\n");
        s.append("\n");

        if (mechanic != null && !mechanic.trim().isEmpty()) {
            s.append("Mecânico: ").append(mechanic).append("\n");
        } else {
            s.append("Mecânico: _____________________").append("\n");
        }
        s.append("\n");

        s.append(L).append("\n");
        s.append(cen(new WorkshopSettingsManager(this).getWarrantyShortText())).append("\n");
        s.append(cen("Obrigado pela preferência!")).append("\n");
        s.append("\n");
        s.append(cen(new SimpleDateFormat("dd/MM/yyyy HH:mm",
            Locale.getDefault()).format(new Date()))).append("\n");

        return s.toString();
    }

    private String cen(String t) {
        if (t.length() >= 32) return t;
        int p = (32 - t.length()) / 2;
        StringBuilder sb = new StringBuilder();
        for (int i = 0; i < p; i++) sb.append(' ');
        sb.append(t);
        return sb.toString();
    }

    private String wl(String t) {
        return t.length() <= 32 ? t : t.substring(0, 32) + "\n  " + t.substring(32);
    }

    private String trunc(String t, int max) {
        return t.length() <= max ? t : t.substring(0, max - 1) + ".";
    }

    private String nn(String s) { return s != null ? s : ""; }

    private void renderPdfPreview() {
        try {
            ParcelFileDescriptor fd = ParcelFileDescriptor.open(
                pdfFile, ParcelFileDescriptor.MODE_READ_ONLY);
            PdfRenderer renderer = new PdfRenderer(fd);
            PdfRenderer.Page page = renderer.openPage(0);

            Bitmap bmp = Bitmap.createBitmap(
                page.getWidth() * 2, page.getHeight() * 2,
                Bitmap.Config.ARGB_8888);
            page.render(bmp, null, null, PdfRenderer.Page.RENDER_MODE_FOR_DISPLAY);
            imagePreview.setImageBitmap(bmp);

            page.close(); renderer.close(); fd.close();
        } catch (Exception e) {
            Toast.makeText(this, "Erro ao visualizar PDF", Toast.LENGTH_SHORT).show();
        }
    }

    private void confirmAndBackup() {
        if (!DriveBackupManager.hasDriveFolder(this)) {
            Toast.makeText(this,
                "Escolha a pasta do Google Drive (só uma vez)",
                Toast.LENGTH_LONG).show();
            DriveBackupManager.requestFolder(this);
        } else {
            doUploadToDrive();
        }
    }

    private void doUploadToDrive() {
        try {
            DriveBackupManager.uploadPdf(this, pdfFile);
            Toast.makeText(this, "✅ PDF salvo no Drive!", Toast.LENGTH_LONG).show();
            finish();
        } catch (Exception e) {
            Toast.makeText(this, "Erro ao enviar: " + e.getMessage(),
                Toast.LENGTH_LONG).show();
        }
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);
        if (requestCode == DriveBackupManager.REQ_DRIVE) {
            if (DriveBackupManager.handleFolderResult(this, requestCode, resultCode, data)) {
                doUploadToDrive();
            } else {
                Toast.makeText(this, "Pasta não selecionada. Operação cancelada.",
                    Toast.LENGTH_SHORT).show();
            }
        }
    }

    private void sharePdf() {
        if (pdfFile == null || !pdfFile.exists()) {
            Toast.makeText(this, "Arquivo PDF não encontrado!", Toast.LENGTH_SHORT).show();
            return;
        }

        Toast.makeText(this, "Preparando link...", Toast.LENGTH_SHORT).show();

        double totalD = 0;
        for (Item item : items) totalD += item.getTotalValue();
        final String totalStr = String.format(new Locale("pt", "BR"), "R$ %.2f", totalD);

        RenderApi.enviarPdf(
            pdfFile,
            orderNumber != null ? orderNumber : "",
            client != null ? client : "",
            phone != null ? phone : "",
            vehicle != null ? vehicle : "",
            plate != null ? plate : "",
            mechanic != null ? mechanic : "",
            date != null ? date : "",
            totalStr,
            new RenderApi.LinkCallback() {
                @Override
                public void onSuccess(String link) {
                    runOnUiThread(() -> compartilharLink(link));
                }
                @Override
                public void onError(String erro) {
                    runOnUiThread(() ->
                        Toast.makeText(PdfPreviewActivity.this,
                            "Erro: " + erro, Toast.LENGTH_LONG).show());
                }
            });
    }

    private void compartilharLink(String link) {
        Intent intent = new Intent(Intent.ACTION_SEND);
        intent.setType("text/plain");
        intent.putExtra(Intent.EXTRA_TEXT,
            "📄 *MECÂNICA VISION CAR*\n" +
            "Ordem de Serviço #" + orderNumber + "\n\n" +
            "🔗 " + link + "\n\n" +
            "Toque no link para ver e baixar o PDF.");
        intent.putExtra(Intent.EXTRA_SUBJECT,
            "Ordem de Serviço #" + orderNumber);
        startActivity(Intent.createChooser(intent, "Compartilhar Nota"));
    }
}
