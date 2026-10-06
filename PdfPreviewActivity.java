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
       
