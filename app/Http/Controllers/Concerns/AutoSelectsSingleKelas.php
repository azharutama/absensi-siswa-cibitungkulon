<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait AutoSelectsSingleKelas
{
    protected function autoRedirectForSingleKelas(
        Request $request,
        Collection $kelas,
        string $routeName,
        array $additionalParams = []
    ): ?RedirectResponse {
        $kelasId = $this->singleKelasId($kelas);

        if ($request->has('kelas_id') || $kelasId === null) {
            return null;
        }

        return redirect()->route($routeName, array_merge(['kelas_id' => $kelasId], $additionalParams));
    }

    protected function getKelasIdWithAutoSelect(mixed $kelasIdFromRequest, Collection $kelas): mixed
    {
        if (blank($kelasIdFromRequest)) {
            return $this->singleKelasId($kelas) ?? $kelasIdFromRequest;
        }

        return $kelasIdFromRequest;
    }

    private function singleKelasId(Collection $kelas): mixed
    {
        return $kelas->count() === 1 ? $kelas->first()->id : null;
    }
}
