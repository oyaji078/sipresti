<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MlApiException;
use App\Models\Measurement;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Satu-satunya pintu keluar aplikasi menuju ML API di Vercel.
 *
 * Controller tidak boleh memuat logika HTTP. Semua urusan payload, API key,
 * timeout, parsing, dan logging terkumpul di sini.
 *
 * Data identitas balita TIDAK PERNAH dikirim: hanya empat feature model.
 */
class MlPredictionService
{
    public function __construct(private readonly array $config) {}

    public static function fromConfig(): self
    {
        return new self(config('sipresti.ml'));
    }

    public function isConfigured(): bool
    {
        return filled($this->config['url'] ?? null) && filled($this->config['key'] ?? null);
    }

    /**
     * Susun payload prediksi dari sebuah pemeriksaan.
     *
     * @return array{gender:string, age_months:int, weight_kg:float, height_cm:float}
     */
    public function buildFeatures(Measurement $measurement): array
    {
        return [
            'gender' => $measurement->toddler->gender,
            'age_months' => (int) $measurement->age_months,
            'weight_kg' => (float) $measurement->weight_kg,
            'height_cm' => (float) $measurement->height_cm,
        ];
    }

    /**
     * Jalankan prediksi. Mengembalikan data hasil model apa adanya.
     *
     * @return array{prediction_class:string, prediction_label:string, probability:float, model_version:?string, request_id:string}
     *
     * @throws MlApiException
     */
    public function predict(array $features, ?string $requestId = null): array
    {
        if (! $this->isConfigured()) {
            Log::error('ML API belum dikonfigurasi (ML_API_URL / ML_API_KEY kosong).');

            throw MlApiException::notConfigured();
        }

        $requestId ??= (string) Str::uuid();
        $payload = array_merge(['request_id' => $requestId], $features);

        $startedAt = microtime(true);

        try {
            $response = Http::withHeaders([
                'X-API-Key' => (string) $this->config['key'],
                'Accept' => 'application/json',
                'User-Agent' => 'SIPRESTI-Hostinger/1.0',
            ])
                ->connectTimeout((int) ($this->config['connect_timeout'] ?? 5))
                ->timeout((int) ($this->config['timeout'] ?? 10))
                ->asJson()
                ->post($this->endpoint('/api/v1/predict'), $payload);
        } catch (ConnectionException $e) {
            Log::warning('ML API tidak dapat dihubungi', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);

            throw MlApiException::unavailable($e->getMessage());
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        if (! $response->successful()) {
            $this->throwFromErrorResponse($response, $requestId, $durationMs);
        }

        $body = $response->json();

        if (! is_array($body) || ($body['success'] ?? false) !== true || ! isset($body['data'])) {
            Log::error('Respons ML API tidak dikenali', [
                'request_id' => $requestId,
                'body' => $this->safeExcerpt($response->body()),
            ]);

            throw MlApiException::invalidResponse();
        }

        $data = $body['data'];

        foreach (['prediction_class', 'prediction_label', 'probability'] as $field) {
            if (! array_key_exists($field, $data)) {
                Log::error('Respons ML API tidak lengkap', [
                    'request_id' => $requestId,
                    'missing' => $field,
                ]);

                throw MlApiException::invalidResponse("Field '{$field}' tidak ada pada respons.");
            }
        }

        $probability = (float) $data['probability'];

        if ($probability < 0 || $probability > 1) {
            Log::error('Probability di luar rentang 0..1', [
                'request_id' => $requestId,
                'probability' => $probability,
            ]);

            throw MlApiException::invalidResponse('Nilai probability di luar rentang 0..1.');
        }

        Log::info('Prediksi ML berhasil', [
            'request_id' => $requestId,
            'model_version' => $data['model_version'] ?? null,
            'duration_ms' => $durationMs,
        ]);

        return [
            'prediction_class' => (string) $data['prediction_class'],
            'prediction_label' => (string) $data['prediction_label'],
            'probability' => $probability,
            'model_version' => isset($data['model_version']) ? (string) $data['model_version'] : null,
            'request_id' => $requestId,
        ];
    }

    /** Status layanan untuk indikator UI dan halaman model. */
    public function health(): array
    {
        if (! $this->isConfigured()) {
            return ['reachable' => false, 'model_loaded' => false, 'model_version' => null, 'message' => 'ML API belum dikonfigurasi.'];
        }

        try {
            $response = Http::withHeaders(['Accept' => 'application/json'])
                ->connectTimeout(3)
                ->timeout(5)
                ->get($this->endpoint('/api/health'));
        } catch (ConnectionException $e) {
            return ['reachable' => false, 'model_loaded' => false, 'model_version' => null, 'message' => 'Layanan prediksi tidak dapat dihubungi.'];
        }

        $body = $response->json();

        if (! is_array($body)) {
            return ['reachable' => false, 'model_loaded' => false, 'model_version' => null, 'message' => 'Respons layanan tidak dikenali.'];
        }

        return [
            'reachable' => true,
            'model_loaded' => (bool) ($body['model_loaded'] ?? false),
            'model_version' => $body['model_version'] ?? null,
            'message' => ($body['model_loaded'] ?? false) ? 'Layanan prediksi aktif.' : 'Layanan aktif, tetapi model belum dimuat.',
        ];
    }

    /**
     * Informasi model untuk sinkronisasi tabel model_versions.
     *
     * @throws MlApiException
     */
    public function modelInfo(): array
    {
        if (! $this->isConfigured()) {
            throw MlApiException::notConfigured();
        }

        try {
            $response = Http::withHeaders([
                'X-API-Key' => (string) $this->config['key'],
                'Accept' => 'application/json',
            ])
                ->connectTimeout((int) ($this->config['connect_timeout'] ?? 5))
                ->timeout((int) ($this->config['timeout'] ?? 10))
                ->get($this->endpoint('/api/v1/model/info'));
        } catch (ConnectionException $e) {
            throw MlApiException::unavailable($e->getMessage());
        }

        if (! $response->successful()) {
            $this->throwFromErrorResponse($response, null, null);
        }

        $body = $response->json();

        if (! is_array($body) || ! isset($body['data']) || ! is_array($body['data'])) {
            throw MlApiException::invalidResponse();
        }

        return $body['data'];
    }

    // --------------------------------------------------------------- private
    private function endpoint(string $path): string
    {
        return rtrim((string) $this->config['url'], '/').$path;
    }

    private function throwFromErrorResponse(Response $response, ?string $requestId, ?int $durationMs): never
    {
        $body = $response->json();
        $code = is_array($body) ? (string) data_get($body, 'error.code', '') : '';
        $message = is_array($body) ? (string) data_get($body, 'error.message', '') : '';

        Log::warning('ML API mengembalikan error', [
            'request_id' => $requestId,
            'status' => $response->status(),
            'code' => $code,
            'duration_ms' => $durationMs,
        ]);

        throw match (true) {
            $response->status() === 401 => MlApiException::unauthorized(),
            $code === 'MODEL_NOT_READY', $response->status() === 503 => MlApiException::modelNotReady(),
            $code === 'VALIDATION_ERROR', $response->status() === 422 => MlApiException::validation($message ?: 'data tidak valid'),
            default => MlApiException::unavailable('HTTP '.$response->status().' '.$code),
        };
    }

    private function safeExcerpt(string $body): string
    {
        return mb_substr(preg_replace('/\s+/', ' ', $body) ?? '', 0, 200);
    }
}
