<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait AutoSelectsSingleKelas
{
    /**
     * Auto-redirect jika user hanya memiliki 1 kelas dan belum memilih kelas
     * 
     * @param Request $request
     * @param \Illuminate\Support\Collection $kelas
     * @param string $routeName
     * @param array $additionalParams
     * @return RedirectResponse|null
     */
    protected function autoRedirectForSingleKelas(
        Request $request,
        $kelas,
        string $routeName,
        array $additionalParams = []
    ): ?RedirectResponse {
        // Cek jika belum ada kelas_id di request dan user hanya punya 1 kelas
        if (!$request->has('kelas_id') && $kelas->count() === 1) {
            $params = array_merge(
                ['kelas_id' => $kelas->first()->id],
                $additionalParams
            );
            
            return redirect()->route($routeName, $params);
        }

        return null;
    }

    /**
     * Get kelas ID dengan auto-select jika hanya 1 kelas
     * 
     * @param mixed $kelasIdFromRequest
     * @param \Illuminate\Support\Collection $kelas
     * @return int|string|null
     */
    protected function getKelasIdWithAutoSelect($kelasIdFromRequest, $kelas)
    {
        if (blank($kelasIdFromRequest) && $kelas->count() === 1) {
            return $kelas->first()->id;
        }

        return $kelasIdFromRequest;
    }
}
