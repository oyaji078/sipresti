<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Kegagalan saat memanggil ML API.
 *
 * Pesan pada exception ini AMAN ditampilkan ke pengguna: tidak memuat URL
 * internal, API key, maupun detail teknis server.
 */
class MlApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly ?int $httpStatus = null,
        public readonly ?string $technicalDetail = null,
    ) {
        parent::__construct($message);
    }

    public static function notConfigured(): self
    {
        return new self(
            'Layanan prediksi belum dikonfigurasi. Hubungi administrator.',
            'ML_NOT_CONFIGURED'
        );
    }

    public static function unavailable(?string $detail = null): self
    {
        return new self(
            'Layanan prediksi sedang tidak tersedia. Silakan coba kembali.',
            'ML_UNAVAILABLE',
            null,
            $detail
        );
    }

    public static function modelNotReady(): self
    {
        return new self(
            'Model prediksi belum tersedia.',
            'MODEL_NOT_READY',
            503
        );
    }

    public static function unauthorized(): self
    {
        return new self(
            'Layanan prediksi menolak permintaan. Hubungi administrator.',
            'UNAUTHORIZED',
            401
        );
    }

    public static function validation(string $message): self
    {
        return new self(
            'Data pemeriksaan ditolak layanan prediksi: '.$message,
            'VALIDATION_ERROR',
            422
        );
    }

    public static function invalidResponse(?string $detail = null): self
    {
        return new self(
            'Layanan prediksi mengembalikan respons yang tidak dikenali.',
            'INVALID_RESPONSE',
            null,
            $detail
        );
    }
}
