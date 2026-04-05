<?php

namespace App\Http\Controllers\Jurusan;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TopicModelingModelController
{
    public function download(Request $request, string $jobId)
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $jobId)) {
            abort(400, 'Invalid job id');
        }

        $baseUrl = rtrim(config('services.fastapi.base_url', 'http://localhost:8000'), '/');
        $url = "{$baseUrl}/api/v1/training/model/{$jobId}/download";

        try {
            $resp = Http::timeout(300)
                ->withOptions(['stream' => true])
                ->get($url);

            if (!$resp->successful()) {
                if ($resp->status() === 404) {
                    abort(404, 'Model archive not found');
                }

                Log::warning('FastAPI model download failed', [
                    'job_id' => $jobId,
                    'status' => $resp->status(),
                    'body' => $resp->body(),
                ]);

                abort(502, 'Failed to download model from FastAPI');
            }

            $filename = "model_{$jobId}.tar.gz";
            $contentType = $resp->header('content-type') ?: 'application/gzip';
            $length = $resp->header('content-length');

            return response()->streamDownload(function () use ($resp) {
                $stream = $resp->resource();
                try {
                    while (!feof($stream)) {
                        echo fread($stream, 1024 * 64);
                        flush();
                    }
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            }, $filename, array_filter([
                'Content-Type' => $contentType,
                'Content-Length' => $length,
            ]));
        } catch (\Throwable $e) {
            Log::error('Model download proxy failed', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);

            abort(502, 'Failed to proxy model download');
        }
    }
}
