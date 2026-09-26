<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\MlApiException;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ModelVersion;
use App\Models\Prediction;
use App\Services\AuditService;
use App\Services\MlPredictionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ModelVersionController extends Controller
{
    public function __construct(
        private readonly MlPredictionService $ml,
        private readonly AuditService $audit,
    ) {}

    public function index(): View
    {
        return view('admin.models.index', [
            'models' => ModelVersion::query()
                ->withCount('predictions')
                ->orderByDesc('is_active')
                ->orderByDesc('trained_at')
                ->orderByDesc('id')
                ->paginate(15),
            'health' => $this->ml->health(),
            'configured' => $this->ml->isConfigured(),
        ]);
    }

    public function metrics(): View
    {
        $active = ModelVersion::active();

        return view('admin.models.metrics', [
            'active' => $active,
            'health' => $this->ml->health(),
            'usage' => $active
                ? Prediction::query()->where('model_version_id', $active->id)->count()
                : 0,
            'totalPredictions' => Prediction::query()->count(),
        ]);
    }

    /**
     * Ambil informasi model dari ML API dan catat ke katalog lokal.
     * Metrik hanya tersimpan bila ML API benar-benar mengirimkannya.
     */
    public function sync(): RedirectResponse
    {
        try {
            $info = $this->ml->modelInfo();
        } catch (MlApiException $e) {
            return back()->with('error', $e->getMessage());
        }

        $version = $info['model_version'] ?? null;

        if (blank($version)) {
            return back()->with('error', 'ML API tidak mengirimkan versi model.');
        }

        $metrics = $info['metrics'] ?? [];

        $model = ModelVersion::updateOrCreate(
            ['version' => (string) $version],
            [
                'algorithm' => $info['algorithm'] ?? 'XGBoost',
                'artifact_name' => $info['artifact_name'] ?? null,
                'trained_at' => filled($info['trained_at'] ?? null) ? Carbon::parse($info['trained_at']) : null,
                'dataset_size' => $info['dataset_size'] ?? null,
                'training_size' => $info['training_size'] ?? null,
                'testing_size' => $info['testing_size'] ?? null,
                'accuracy' => $metrics['accuracy'] ?? null,
                'precision_score' => $metrics['precision'] ?? null,
                'recall' => $metrics['recall'] ?? null,
                'f1_score' => $metrics['f1_score'] ?? null,
                'parameters_json' => $info['parameters'] ?? null,
                'features_json' => $info['feature_order'] ?? $info['features'] ?? null,
                'checksum' => $info['checksum'] ?? null,
                'is_active' => true,
            ]
        );

        // Hanya satu model yang boleh aktif pada satu waktu.
        ModelVersion::query()->where('id', '!=', $model->id)->update(['is_active' => false]);

        $this->audit->log(
            AuditLog::ACTION_MODEL_SYNC,
            'model_version',
            $model->id,
            'Sinkronisasi informasi model '.$model->version,
        );

        $note = $model->hasMetrics()
            ? ''
            : ' Metrik belum tersedia dari ML API dan ditampilkan sebagai "-".';

        return back()->with('toast', [
            'message' => 'Informasi model '.$model->version.' berhasil disinkronkan.'.$note,
            'type' => 'success',
        ]);
    }
}
