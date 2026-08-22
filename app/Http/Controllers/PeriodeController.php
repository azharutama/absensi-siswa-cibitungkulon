<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Periode;
use App\Models\Rekap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class PeriodeController extends Controller
{
    public function index(Request $request)
    {
        // Optimasi: Ambil kedua semester sekaligus dalam 1 query
        $periodes = Periode::query()
            ->with('hariLiburs')
            ->orderBy('tahun_ajaran', 'desc')
            ->orderBy('semester', 'asc')
            ->limit(2)
            ->get();

        $semester1 = $periodes->firstWhere('semester', 1);
        $semester2 = $periodes->firstWhere('semester', 2);

        $periodeData = [
            'tahun_ajaran' => $semester1?->tahun_ajaran ?? $semester2?->tahun_ajaran ?? old('tahun_ajaran', ''),
            'semester_1_tanggal_mulai' => $semester1?->tanggal_mulai?->format('Y-m-d') ?? old('semester_1_tanggal_mulai', ''),
            'semester_1_tanggal_selesai' => $semester1?->tanggal_selesai?->format('Y-m-d') ?? old('semester_1_tanggal_selesai', ''),
            'semester_2_tanggal_mulai' => $semester2?->tanggal_mulai?->format('Y-m-d') ?? old('semester_2_tanggal_mulai', ''),
            'semester_2_tanggal_selesai' => $semester2?->tanggal_selesai?->format('Y-m-d') ?? old('semester_2_tanggal_selesai', ''),
        ];

        // Display format for views
        $periodeDataDisplay = [
            'semester_1_tanggal_mulai' => $semester1?->tanggal_mulai?->format('d/m/Y'),
            'semester_1_tanggal_selesai' => $semester1?->tanggal_selesai?->format('d/m/Y'),
            'semester_2_tanggal_mulai' => $semester2?->tanggal_mulai?->format('d/m/Y'),
            'semester_2_tanggal_selesai' => $semester2?->tanggal_selesai?->format('d/m/Y'),
        ];

        $liburMingguan = collect();
        $liburNasional = collect();

        // Ambil periode yang pertama ditemukan untuk menampilkan hari libur
        $periode = $semester1 ?? $semester2;

        if ($periode) {
            $liburMingguan = $periode->hariLiburs
                ->where('tipe', 'mingguan')
                ->map(fn ($item) => [
                    'hari' => $item->hari,
                    'keterangan' => $item->keterangan,
                ]);

            $liburNasional = $periode->hariLiburs
                ->where('tipe', 'nasional')
                ->map(fn ($item) => [
                    'tanggal' => $item->tanggal?->format('Y-m-d') ?? '', // Keep Y-m-d for HTML5 date input
                    'nama_libur' => $item->keterangan,
                ]);
        }

        return view('periode.index', compact('periode', 'periodeData', 'periodeDataDisplay', 'liburMingguan', 'liburNasional'));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePeriode($request);

        DB::transaction(function () use ($validated): void {
            Periode::query()->orderBy('id')->lockForUpdate()->get(['id']);

            $tahunAjaran = trim($validated['tahun_ajaran']);

            $this->ensureSemesterDatesDoNotOverlap($tahunAjaran, $validated);

            $semester1Start = $validated['semester_1_tanggal_mulai'];
            $semester1End = $validated['semester_1_tanggal_selesai'];
            $semester2Start = $validated['semester_2_tanggal_mulai'];
            $semester2End = $validated['semester_2_tanggal_selesai'];

            Periode::create([
                'tahun_ajaran' => $tahunAjaran,
                'semester' => 1,
                'tipe_periode' => 'semester',
                'nama_periode' => "Semester Ganjil {$tahunAjaran}",
                'tanggal_mulai' => $semester1Start,
                'tanggal_selesai' => $semester1End,
            ]);

            Periode::create([
                'tahun_ajaran' => $tahunAjaran,
                'semester' => 2,
                'tipe_periode' => 'semester',
                'nama_periode' => "Semester Genap {$tahunAjaran}",
                'tanggal_mulai' => $semester2Start,
                'tanggal_selesai' => $semester2End,
            ]);

            $periode1 = Periode::query()->where('tahun_ajaran', $tahunAjaran)->where('semester', 1)->first();
            $periode2 = Periode::query()->where('tahun_ajaran', $tahunAjaran)->where('semester', 2)->first();

            if ($periode1) {
                $this->storeHariLiburs($periode1, $validated);
            }
            if ($periode2) {
                $this->storeHariLiburs($periode2, $validated);
            }
        });

        return redirect()->route('periode.index')->with('success', 'Periode akademik Semester 1 dan Semester 2 berhasil disimpan.');
    }

    public function edit($id)
    {
        $periode = Periode::with('hariLiburs')->findOrFail($id);

        $tahunAjaran = $periode->tahun_ajaran;
        $semester1 = Periode::query()->where('tahun_ajaran', $tahunAjaran)->where('semester', 1)->first();
        $semester2 = Periode::query()->where('tahun_ajaran', $tahunAjaran)->where('semester', 2)->first();

        $periodeData = [
            'tahun_ajaran' => $tahunAjaran,
            'semester_1_tanggal_mulai' => $semester1?->tanggal_mulai?->format('Y-m-d'),
            'semester_1_tanggal_selesai' => $semester1?->tanggal_selesai?->format('Y-m-d'),
            'semester_2_tanggal_mulai' => $semester2?->tanggal_mulai?->format('Y-m-d'),
            'semester_2_tanggal_selesai' => $semester2?->tanggal_selesai?->format('Y-m-d'),
        ];

        // Display format for views
        $periodeDataDisplay = [
            'semester_1_tanggal_mulai' => $semester1?->tanggal_mulai?->format('d/m/Y'),
            'semester_1_tanggal_selesai' => $semester1?->tanggal_selesai?->format('d/m/Y'),
            'semester_2_tanggal_mulai' => $semester2?->tanggal_mulai?->format('d/m/Y'),
            'semester_2_tanggal_selesai' => $semester2?->tanggal_selesai?->format('d/m/Y'),
        ];

        return view('periode.edit', compact('periode', 'periodeData', 'periodeDataDisplay'));
    }

    public function update(Request $request, $id)
    {
        $periode = Periode::findOrFail($id);
        $validated = $this->validatePeriode($request, $periode);

        DB::transaction(function () use ($id, $validated): void {
            Periode::query()->orderBy('id')->lockForUpdate()->get(['id']);
            $lockedPeriode = Periode::query()->findOrFail($id);
            $tahunAjaran = trim($validated['tahun_ajaran']);

            $this->ensureSemesterDatesDoNotOverlap($tahunAjaran, $validated);

            $semester1 = Periode::query()
                ->where('tahun_ajaran', $tahunAjaran)
                ->where('semester', 1)
                ->lockForUpdate()
                ->first();
            $semester2 = Periode::query()
                ->where('tahun_ajaran', $tahunAjaran)
                ->where('semester', 2)
                ->lockForUpdate()
                ->first();

            if ($semester1) {
                $semester1->update([
                    'tanggal_mulai' => $validated['semester_1_tanggal_mulai'],
                    'tanggal_selesai' => $validated['semester_1_tanggal_selesai'],
                ]);
                $semester1->hariLiburs()->delete();
                $this->storeHariLiburs($semester1, $validated);
            }

            if ($semester2) {
                $semester2->update([
                    'tanggal_mulai' => $validated['semester_2_tanggal_mulai'],
                    'tanggal_selesai' => $validated['semester_2_tanggal_selesai'],
                ]);
                $semester2->hariLiburs()->delete();
                $this->storeHariLiburs($semester2, $validated);
            } else {
                Periode::create([
                    'tahun_ajaran' => $tahunAjaran,
                    'semester' => 2,
                    'tipe_periode' => 'semester',
                    'nama_periode' => "Semester Genap {$tahunAjaran}",
                    'tanggal_mulai' => $validated['semester_2_tanggal_mulai'],
                    'tanggal_selesai' => $validated['semester_2_tanggal_selesai'],
                ]);

                $semester2 = Periode::query()
                    ->where('tahun_ajaran', $tahunAjaran)
                    ->where('semester', 2)
                    ->first();

                if ($semester2) {
                    $this->storeHariLiburs($semester2, $validated);
                }
            }
        });

        return redirect()->route('periode.index')->with('success', 'Periode akademik Semester 1 dan Semester 2 berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $periode = Periode::findOrFail($id);
        $tahunAjaran = $periode->tahun_ajaran;

        $totalAbsensi = Absensi::query()
            ->whereIn('periode_id', function ($query) use ($tahunAjaran): void {
                $query->select('id')->from('periodes')->where('tahun_ajaran', $tahunAjaran);
            })
            ->count();

        if ($totalAbsensi > 0) {
            return redirect()->route('periode.index')->with(
                'error',
                'Periode tidak dapat dihapus karena masih memiliki riwayat absensi. Arsipkan saja.'
            );
        }

        DB::transaction(function () use ($tahunAjaran): void {
            Periode::query()->where('tahun_ajaran', $tahunAjaran)->delete();
        });

        return redirect()->route('periode.index')->with('success', 'Periode akademik berhasil dihapus.');
    }

    public function reset(Request $request)
    {
        DB::transaction(function (): void {
            Rekap::query()->delete();
            Absensi::query()->delete();
            Periode::query()->delete();
        });

        return redirect()->route('periode.index')->with('success', 'Semua data periode, absensi, dan rekap berhasil direset.');
    }

    private function validatePeriode(Request $request, ?Periode $periode = null): array
    {
        $validated = $request->validate([
            'tahun_ajaran' => [
                'required',
                'string',
                'max:20',
                Rule::unique('periodes', 'tahun_ajaran')
                    ->where(fn ($query) => $query->whereNull('semester'))
                    ->ignore($periode),
            ],
            'semester_1_tanggal_mulai' => ['required', 'date'],
            'semester_1_tanggal_selesai' => ['required', 'date', 'after_or_equal:semester_1_tanggal_mulai'],
            'semester_2_tanggal_mulai' => ['required', 'date', 'after_or_equal:semester_1_tanggal_selesai'],
            'semester_2_tanggal_selesai' => ['required', 'date', 'after_or_equal:semester_2_tanggal_mulai'],
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
                'after_or_equal:semester_1_tanggal_mulai',
                'before_or_equal:semester_2_tanggal_selesai',
                'distinct',
            ],
            'libur_nasional.*.nama_libur' => ['required', 'string', 'max:255'],
            'libur_nasional.*.keterangan' => ['nullable', 'string', 'max:255'],
        ], [
            'tahun_ajaran.required' => 'Tahun ajaran wajib diisi.',
            'tahun_ajaran.max' => 'Tahun ajaran maksimal 20 karakter.',
            'tahun_ajaran.unique' => 'Tahun ajaran ini sudah terdaftar.',
            'semester_1_tanggal_mulai.required' => 'Tanggal mulai Semester 1 wajib diisi.',
            'semester_1_tanggal_mulai.date_format' => 'Tanggal mulai Semester 1 harus berupa tanggal yang valid (format: dd/mm/yyyy).',
            'semester_1_tanggal_selesai.required' => 'Tanggal selesai Semester 1 wajib diisi.',
            'semester_1_tanggal_selesai.date_format' => 'Tanggal selesai Semester 1 harus berupa tanggal yang valid (format: dd/mm/yyyy).',
            'semester_1_tanggal_selesai.after_or_equal' => 'Tanggal selesai Semester 1 harus setelah atau sama dengan tanggal mulai Semester 1.',
            'semester_2_tanggal_mulai.required' => 'Tanggal mulai Semester 2 wajib diisi.',
            'semester_2_tanggal_mulai.date_format' => 'Tanggal mulai Semester 2 harus berupa tanggal yang valid (format: dd/mm/yyyy).',
            'semester_2_tanggal_mulai.after_or_equal' => 'Tanggal mulai Semester 2 harus setelah atau sama dengan tanggal selesai Semester 1.',
            'semester_2_tanggal_selesai.required' => 'Tanggal selesai Semester 2 wajib diisi.',
            'semester_2_tanggal_selesai.date_format' => 'Tanggal selesai Semester 2 harus berupa tanggal yang valid (format: dd/mm/yyyy).',
            'semester_2_tanggal_selesai.after_or_equal' => 'Tanggal selesai Semester 2 harus setelah atau sama dengan tanggal mulai Semester 2.',
            'libur_mingguan.*.hari.required' => 'Hari libur mingguan wajib dipilih.',
            'libur_mingguan.*.hari.in' => 'Hari yang dipilih tidak valid.',
            'libur_mingguan.*.keterangan.required' => 'Keterangan hari libur mingguan wajib diisi.',
            'libur_mingguan.*.keterangan.max' => 'Keterangan hari libur mingguan maksimal 255 karakter.',
            'libur_nasional.*.tanggal.required' => 'Tanggal libur nasional wajib diisi.',
            'libur_nasional.*.tanggal.date_format' => 'Tanggal libur nasional harus berupa tanggal yang valid (format: dd/mm/yyyy).',
            'libur_nasional.*.nama_libur.required' => 'Nama hari libur nasional wajib diisi.',
            'libur_nasional.*.nama_libur.max' => 'Nama hari libur nasional maksimal 255 karakter.',
            'libur_nasional.*.tanggal.after_or_equal' => 'Tanggal libur nasional harus berada dalam rentang periode.',
            'libur_nasional.*.tanggal.before_or_equal' => 'Tanggal libur nasional harus berada dalam rentang periode.',
        ]);

        // Convert d/m/Y to Y-m-d for database storage
        $validated['semester_1_tanggal_mulai'] = $this->parseDate($validated['semester_1_tanggal_mulai'])->format('Y-m-d');
        $validated['semester_1_tanggal_selesai'] = $this->parseDate($validated['semester_1_tanggal_selesai'])->format('Y-m-d');
        $validated['semester_2_tanggal_mulai'] = $this->parseDate($validated['semester_2_tanggal_mulai'])->format('Y-m-d');
        $validated['semester_2_tanggal_selesai'] = $this->parseDate($validated['semester_2_tanggal_selesai'])->format('Y-m-d');
        
        foreach ($validated['libur_nasional'] ?? [] as &$libur) {
            $libur['tanggal'] = $this->parseDate($libur['tanggal'])->format('Y-m-d');
        }

        return $validated;
    }

    /** @param array<string, mixed> $validated */
    private function ensureSemesterDatesDoNotOverlap(string $tahunAjaran, array $validated): void
    {
        $s1Start = $validated['semester_1_tanggal_mulai'];
        $s1End = $validated['semester_1_tanggal_selesai'];
        $s2Start = $validated['semester_2_tanggal_mulai'];
        $s2End = $validated['semester_2_tanggal_selesai'];

        // Abaikan periode tahun ajaran yang sedang dibuat/diubah (kedua semester ditangani bersamaan)
        $existing = Periode::query()
            ->where('tahun_ajaran', '!=', $tahunAjaran)
            ->where(function ($q) use ($s1Start, $s1End, $s2Start, $s2End): void {
                $q->whereBetween('tanggal_mulai', [$s1Start, $s1End])
                    ->orWhereBetween('tanggal_selesai', [$s1Start, $s1End])
                    ->orWhereBetween('tanggal_mulai', [$s2Start, $s2End])
                    ->orWhereBetween('tanggal_selesai', [$s2Start, $s2End])
                    ->orWhere(function ($sub) use ($s1Start, $s1End): void {
                        $sub->where('tanggal_mulai', '<=', $s1Start)
                            ->where('tanggal_selesai', '>=', $s1End);
                    });
            })
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'tahun_ajaran' => 'Rentang tanggal semester bertabrakan dengan periode tahun ajaran lain yang sudah ada.',
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

    /**
     * Parse date string supporting both d/m/Y and Y-m-d formats
     */
    private function parseDate(string $date): Carbon
    {
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
            return Carbon::createFromFormat('d/m/Y', $date);
        }
        return Carbon::parse($date);
    }
}