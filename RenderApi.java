package com.mecanicasouza.invoice;

import android.util.Log;

import org.json.JSONObject;

import java.io.File;
import java.io.IOException;

import okhttp3.Call;
import okhttp3.Callback;
import okhttp3.MediaType;
import okhttp3.MultipartBody;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.RequestBody;
import okhttp3.Response;

public class RenderApi {

    private static final String TAG = "RenderApi";
    private static final String RENDER_URL = "https://visioncarnotas.onrender.com";

    public interface LinkCallback {
        void onSuccess(String link);
        void onError(String erro);
    }

    public static void enviarPdf(
        File pdfFile,
        File jpgFile,
        String os, String cliente, String telefone,
        String veiculo, String placa, String mecanico,
        String data, String total,
        LinkCallback callback
    ) {
        try {
            OkHttpClient client = new OkHttpClient.Builder()
                .connectTimeout(60, java.util.concurrent.TimeUnit.SECONDS)
                .writeTimeout(180, java.util.concurrent.TimeUnit.SECONDS)
                .readTimeout(60, java.util.concurrent.TimeUnit.SECONDS)
                .build();

            RequestBody pdfBody = RequestBody.create(
                MediaType.parse("application/pdf"), pdfFile);

            MultipartBody.Builder builder = new MultipartBody.Builder()
                .setType(MultipartBody.FORM)
                .addFormDataPart("pdf", pdfFile.getName(), pdfBody)
                .addFormDataPart("os", os)
                .addFormDataPart("cliente", cliente)
                .addFormDataPart("telefone", telefone)
                .addFormDataPart("veiculo", veiculo)
                .addFormDataPart("placa", placa)
                .addFormDataPart("mecanico", mecanico)
                .addFormDataPart("data", data)
                .addFormDataPart("total", total);

            // Adiciona o JPG se existir
            if (jpgFile != null && jpgFile.exists()) {
                RequestBody jpgBody = RequestBody.create(
                    MediaType.parse("image/jpeg"), jpgFile);
                builder.addFormDataPart("jpg", jpgFile.getName(), jpgBody);
            }

            RequestBody body = builder.build();

            Request request = new Request.Builder()
                .url(RENDER_URL + "/upload-pdf")
                .post(body)
                .build();

            client.newCall(request).enqueue(new Callback() {
                @Override
                public void onFailure(Call call, IOException e) {
                    Log.e(TAG, "Falha: " + e.getMessage());
                    callback.onError(e.getMessage());
                }

                @Override
                public void onResponse(Call call, Response response) {
                    try {
                        String bodyStr = response.body() != null
                            ? response.body().string() : "";

                        Log.e("RESPOSTA_SERVIDOR",
                            "HTTP " + response.code() + "\n" + bodyStr);

                        String trimmed = bodyStr.trim();
                        if (!trimmed.startsWith("{")) {
                            callback.onError(
                                "Servidor retornou HTML (HTTP " + response.code() + "): " +
                                trimmed.substring(0, Math.min(200, trimmed.length())));
                            return;
                        }

                        if (response.isSuccessful()) {
                            JSONObject resp = new JSONObject(bodyStr);
                            String link = resp.optString("link", "");
                            if (!link.isEmpty()) {
                                Log.d(TAG, "Link: " + link);
                                callback.onSuccess(link);
                            } else {
                                callback.onError("Resposta sem link");
                            }
                        } else {
                            try {
                                JSONObject resp = new JSONObject(bodyStr);
                                String erro = resp.optString("erro", "Erro desconhecido");
                                callback.onError("HTTP " + response.code() + ": " + erro);
                            } catch (Exception e) {
                                callback.onError("HTTP " + response.code() + ": " + trimmed);
                            }
                        }
                    } catch (Exception e) {
                        Log.e(TAG, "Erro parsing: " + e.getMessage(), e);
                        callback.onError("Erro: " + e.getMessage());
                    } finally {
                        response.close();
                    }
                }
            });

        } catch (Exception e) {
            Log.e(TAG, "Erro: " + e.getMessage(), e);
            callback.onError(e.getMessage());
        }
    }
}
