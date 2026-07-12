<?php

namespace App\Http\Controllers;

use App\Models\Periode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PeriodeController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim($filters['search'] ?? '');

        $query = Periode::query()
            ->select(['id', 'nama_periode', 'tanggal_mulai', 'tanggal_selesai', 'status_aktif', 'created_at']);

        if ($search !== '') {
            $query->where('nama_periode', 'like', "%{$search}%");
        }

        $periodes = $query
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('periode.index', compact('periodes'));
    }

    public function create()
    {
        return view('periode.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validatePeriode($request);

        DB::transaction(function () use ($validated): void {
            // Urutan lock yang konsisten mencegah dua aktivasi berjalan bersamaan.
            Periode::query()->orderBy('id')->lockForUpdate()->get(['id']);
            $this->ensurePeriodDoesNotOverlap($validated);

            if ((bool) $validated['status_aktif']) {
                Periode::query()
                    ->where('status_aktif', true)
                    ->update(['status_aktif' => false]);
            }

            $periode = Periode::create([
                'nama_periode' => $validated['nama_periode'],
                'tanggal_mulai' => $validated['tanggal_mulai'],
                'tanggal_selesai' => $validated['tanggal_selesai'],
                'status_aktif' => (bool) $validated['status_aktif'],
            ]);

            $this->storeHariLiburs($periode, $validated);
        });

        return redirect()->route('periode.index')->with('success', 'Periode akademik dan hari libur berhasil disimpan.');
    }

    public function edit($id)
    {
        // Muat periode beserta relasi hari liburnya agar otomatis ter-fetch di form edit
        $periode = Periode::with('hariLiburs')->findOrFail($id);

        return view('periode.edit', compact('periode'));
    }

    public function update(Request $request, $id)
    {
        $periode = Periode::findOrFail($id);
        $validated = $this->validatePeriode($request, $periode);

        DB::transaction(function () use ($id, $validated): void {
            Periode::query()->orderBy('id')->lockForUpdate()->get(['id']);
            $lockedPeriode = Periode::query()->findOrFail($id);
            $this->ensurePeriodDoesNotOverlap($validated, $lockedPeriode->id);
            $this->ensureAttendanceFitsPeriod($lockedPeriode, $validated);

            if ((bool) $validated['status_aktif']) {
                Periode::query()
                    ->whereKeyNot($lockedPeriode->id)
                    ->where('status_aktif', true)
                    ->update(['status_aktif' => false]);
            }

            $lockedPeriode->update([
                'nama_periode' => $validated['nama_periode'],
                'tanggal_mulai' => $validated['tanggal_mulai'],
                'tanggal_selesai' => $validated['tanggal_selesai'],
                'status_aktif' => (bool) $validated['status_aktif'],
            ]);

            $lockedPeriode->hariLiburs()->delete();
            $this->storeHariLiburs($lockedPeriode, $validated);
        });

        return redirect()->route('periode.index')->with('success', 'Periode akademik berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $periode = Periode::query()
            ->withCount(['kelas', 'siswas', 'absensis'])
            ->findOrFail($id);

        if ($periode->status_aktif) {
            return redirect()->route('periode.index')->with('error', 'Periode aktif tidak dapat dihapus.');
        }

        if ($periode->kelas_count > 0 || $periode->siswas_count > 0 || $periode->absensis_count > 0) {
            return redirect()->route('periode.index')->with(
                'error',
                'Periode tidak dapat dihapus karena masih memiliki kelas, siswa, atau riwayat absensi.'
            );
        }

        DB::transaction(fn () => $periode->delete());

        return redirect()->route('periode.index')->with('success', 'Periode akademik berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function validatePeriode(Request $request, ?Periode $periode = null): array
    {
        return $request->validate([
            'nama_periode' => [
                'required',
                'string',
                'max:100',
                Rule::unique('periodes', 'nama_periode')->ignore($periode),
            ],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'status_aktif' => ['required', 'boolean'],
            'libur_mingguan' => ['nullable', 'array'],
            'libur_mingguan.*' => ['array'],
            'libur_mingguan.*.hari' => [
                'required',
                'string',
                Rule::in(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']),
                'distinct',
            ],
            'libur_mingguan.*.keterangan' => ['required', 'string', 'max:255'],
            'libur_nasional' => ['nullable', 'array'],
            'libur_nasional.*' => ['array'],
            'libur_nasional.*.tanggal' => [
                'required',
                'date',
                'after_or_equal:tanggal_mulai',
                'before_or_equal:tanggal_selesai',
                'distinct',
            ],
            'libur_nasional.*.nama_libur' => ['required', 'string', 'max:255'],
            'libur_nasional.*.keterangan' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /** @param array<string, mixed> $validated */
    private function ensurePeriodDoesNotOverlap(array $validated, ?int $ignoredPeriodId = null): void
    {
        $overlapExists = Periode::query()
            ->when($ignoredPeriodId, fn ($query) => $query->whereKeyNot($ignoredPeriodId))
            ->whereDate('tanggal_mulai', '<=', $validated['tanggal_selesai'])
            ->whereDate('tanggal_selesai', '>=', $validated['tanggal_mulai'])
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'tanggal_mulai' => 'Rentang tanggal periode tidak boleh tumpang tindih dengan periode lain.',
            ]);
        }
    }

    /** @param array<string, mixed> $validated */
    private function ensureAttendanceFitsPeriod(Periode $periode, array $validated): void
    {
        $attendanceRange = $periode->absensis()
            ->selectRaw('MIN(tanggal) AS earliest_date, MAX(tanggal) AS latest_date')
            ->first();
        $earliestDate = $attendanceRange?->getAttribute('earliest_date');
        $latestDate = $attendanceRange?->getAttribute('latest_date');

        if (
            ($earliestDate && $earliestDate < $validated['tanggal_mulai'])
            || ($latestDate && $latestDate > $validated['tanggal_selesai'])
        ) {
            throw ValidationException::withMessages([
                'tanggal_mulai' => "Periode masih memiliki riwayat absensi dari {$earliestDate} sampai {$latestDate}.",
                'tanggal_selesai' => 'Rentang baru harus tetap mencakup seluruh riwayat absensi tersebut.',
            ]);
        }
    }

    /** @param array<string, mixed> $validated */
    private function storeHariLiburs(Periode $periode, array $validated): void
    {
        foreach ($validated['libur_mingguan'] ?? [] as $libur) {
            $periode->hariLiburs()->create([
                'tipe' => 'mingguan',
                'hari' => $libur['hari'],
                'keterangan' => $libur['keterangan'],
            ]);
        }

        foreach ($validated['libur_nasional'] ?? [] as $libur) {
            $keterangan = $libur['nama_libur'];

            if (filled($libur['keterangan'] ?? null)) {
                $keterangan .= ' - '.$libur['keterangan'];
            }

            $periode->hariLiburs()->create([
                'tipe' => 'nasional',
                'tanggal' => $libur['tanggal'],
                'keterangan' => $keterangan,
            ]);
        }
    }
}
