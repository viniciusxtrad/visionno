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

/**
 * Envia o PDF pro backend no Render, que faz o upload pro Upstash Blob
 * e devolve o LINK final.
 */
public class RenderApi {

    private static final String TAG = "RenderApi";
    private static final String RENDER_URL = "https://visioncarnotas.onrender.com";

    public interface LinkCallback {
        void onSuccess(String link);
        void onError(String erro);
    }

    public static void enviarPdf(
        File pdfFile,
        String os, String cliente, String telefone,
        String veiculo, String placa, String mecanico,
        String data, String total,
        LinkCallback callback
    ) {
        try {
            OkHttpClient client = new OkHttpClient.Builder()
                .connectTimeout(30, java.util.concurrent.TimeUnit.SECONDS)
                .writeTimeout(120, java.util.concurrent.TimeUnit.SECONDS)
                .readTimeout(60, java.util.concurrent.TimeUnit.SECONDS)
                .build();

            RequestBody pdfBody = RequestBody.create(
                pdfFile, MediaType.parse("application/pdf"));

            MultipartBody body = new MultipartBody.Builder()
                .setType(MultipartBody.FORM)
                .addFormDataPart("pdf", pdfFile.getName(), pdfBody)
                .addFormDataPart("os", os)
                .addFormDataPart("cliente", cliente)
                .addFormDataPart("telefone", telefone)
                .addFormDataPart("veiculo", veiculo)
                .addFormDataPart("placa", placa)
                .addFormDataPart("mecanico", mecanico)
                .addFormDataPart("data", data)
                .addFormDataPart("total", total)
                .build();

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
                            callback.onError("HTTP " + response.code() + ": " + bodyStr);
                        }
                    } catch (Exception e) {
                        callback.onError("Erro: " + e.getMessage());
                    } finally {
                        response.close();
                    }
                }
            });

        } catch (Exception e) {
            Log.e(TAG, "Erro: " + e.getMessage());
            callback.onError(e.getMessage());
        }
    }
}
