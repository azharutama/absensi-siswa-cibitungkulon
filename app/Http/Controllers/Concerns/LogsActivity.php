<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

trait LogsActivity
{
    /**
     * Log aktivitas create
     * 
     * @param string $modelType - Tipe model (e.g., 'Guru', 'Siswa')
     * @param Model $model - Instance model yang di-create
     * @param string|null $customDescription - Deskripsi custom (optional)
     * @param array|null $additionalProperties - Properties tambahan (optional)
     * @return void
     */
    protected function logCreate(
        string $modelType,
        Model $model,
        ?string $customDescription = null,
        ?array $additionalProperties = null
    ): void {
        $description = $customDescription ?? "Menambahkan {$modelType} baru: {$this->getModelName($model)}";
        $properties = $additionalProperties ?? ['data' => $model->toArray()];

        ActivityLog::log(
            'create',
            $modelType,
            $model->id,
            $description,
            $properties
        );
    }

    /**
     * Log aktivitas update
     * 
     * @param string $modelType - Tipe model (e.g., 'Guru', 'Siswa')
     * @param Model $model - Instance model yang di-update
     * @param array $changes - Perubahan yang dilakukan
     * @param string|null $customDescription - Deskripsi custom (optional)
     * @return void
     */
    protected function logUpdate(
        string $modelType,
        Model $model,
        array $changes = [],
        ?string $customDescription = null
    ): void {
        $description = $customDescription ?? "Memperbarui data {$modelType}: {$this->getModelName($model)}";
        $properties = empty($changes) ? null : ['changes' => $changes];

        ActivityLog::log(
            'update',
            $modelType,
            $model->id,
            $description,
            $properties
        );
    }

    /**
     * Log aktivitas delete
     * 
     * @param string $modelType - Tipe model (e.g., 'Guru', 'Siswa')
     * @param int $modelId - ID model yang dihapus
     * @param string $modelName - Nama model untuk display
     * @param string|null $customDescription - Deskripsi custom (optional)
     * @return void
     */
    protected function logDelete(
        string $modelType,
        int $modelId,
        string $modelName,
        ?string $customDescription = null
    ): void {
        $description = $customDescription ?? "Menghapus {$modelType}: {$modelName}";

        ActivityLog::log(
            'delete',
            $modelType,
            $modelId,
            $description,
            null
        );
    }

    /**
     * Log custom activity
     * 
     * @param string $activityType - Tipe aktivitas (e.g., 'import', 'export')
     * @param string $modelType - Tipe model
     * @param int|null $modelId - ID model (optional)
     * @param string $description - Deskripsi aktivitas
     * @param array|null $properties - Properties tambahan (optional)
     * @return void
     */
    protected function logCustomActivity(
        string $activityType,
        string $modelType,
        ?int $modelId,
        string $description,
        ?array $properties = null
    ): void {
        ActivityLog::log(
            $activityType,
            $modelType,
            $modelId,
            $description,
            $properties
        );
    }

    /**
     * Helper untuk mendapatkan nama model
     * 
     * @param Model $model
     * @return string
     */
    private function getModelName(Model $model): string
    {
        // Coba ambil attribute 'nama', 'nama_siswa', 'nama_kelas', dll
        $nameAttributes = ['nama', 'nama_siswa', 'nama_guru', 'nama_kelas', 'title', 'name'];
        
        foreach ($nameAttributes as $attr) {
            if (isset($model->$attr)) {
                return $model->$attr;
            }
        }

        // Fallback ke ID jika tidak ada nama
        return "ID: {$model->id}";
    }
}
