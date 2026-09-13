<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsActivity;
use App\Models\Absensi;
use App\Models\Periode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class PeriodeController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        // Ambil kedua semester terbaru dalam 1 query untuk optimasi
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

        // Format untuk tampilan di view (d/m/Y)
        $periodeDataDisplay = [
            'semester_1_tanggal_mulai' => $semester1?->tanggal_mulai?->format('d/m/Y'),
            'semester_1_tanggal_selesai' => $semester1?->tanggal_selesai?->format('d/m/Y'),
            'semester_2_tanggal_mulai' => $semester2?->tanggal_mulai?->format('d/m/Y'),
            'semester_2_tanggal_selesai' => $semester2?->tanggal_selesai?->format('d/m/Y'),
        ];

        $liburMingguan = collect();
        $liburNasional = collect();

        // Ambil periode pertama untuk menampilkan hari libur
        $periode = $semester1 ?? $semester2;

        if ($periode) {
            $liburMingguan = $periode->hariLiburs
                ->where('tipe', 'mingguan')
                ->map(fn($item) => [
                    'hari' => $item->hari,
                    'keterangan' => $item->keterangan,
                ]);

            $liburNasional = $periode->hariLiburs
                ->where('tipe', 'nasional')
                ->map(fn($item) => [
                    'tanggal' => $item->tanggal?->format('Y-m-d') ?? '', // Format Y-m-d untuk input HTML5 date
                    'nama_libur' => $item->keterangan,
                ]);
        }

        return view('periode.index', compact('periode', 'periodeData', 'periodeDataDisplay', 'liburMingguan', 'liburNasional'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tahun_ajaran' => [
                'required',
                'string',
                'max:20',
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

        // Konversi d/m/Y ke Y-m-d untuk database
        $validated['semester_1_tanggal_mulai'] = $this->parseDate($validated['semester_1_tanggal_mulai'])->format('Y-m-d');
        $validated['semester_1_tanggal_selesai'] = $this->parseDate($validated['semester_1_tanggal_selesai'])->format('Y-m-d');
        $validated['semester_2_tanggal_mulai'] = $this->parseDate($validated['semester_2_tanggal_mulai'])->format('Y-m-d');
        $validated['semester_2_tanggal_selesai'] = $this->parseDate($validated['semester_2_tanggal_selesai'])->format('Y-m-d');

        foreach ($validated['libur_nasional'] ?? [] as &$libur) {
            $libur['tanggal'] = $this->parseDate($libur['tanggal'])->format('Y-m-d');
        }

        DB::transaction(function () use ($validated): void {
            if (Periode::query()->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'tahun_ajaran' => 'Periode sudah tersedia. Gunakan menu ubah untuk memperbarui periode yang aktif.',
                ]);
            }

            $tahunAjaran = trim($validated['tahun_ajaran']);

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

        // Format untuk tampilan di view (d/m/Y)
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
        $validated = $request->validate([
            'tahun_ajaran' => [
                'required',
                'string',
                'max:20',
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

        // Konversi d/m/Y ke Y-m-d untuk database
        $validated['semester_1_tanggal_mulai'] = $this->parseDate($validated['semester_1_tanggal_mulai'])->format('Y-m-d');
        $validated['semester_1_tanggal_selesai'] = $this->parseDate($validated['semester_1_tanggal_selesai'])->format('Y-m-d');
        $validated['semester_2_tanggal_mulai'] = $this->parseDate($validated['semester_2_tanggal_mulai'])->format('Y-m-d');
        $validated['semester_2_tanggal_selesai'] = $this->parseDate($validated['semester_2_tanggal_selesai'])->format('Y-m-d');

        foreach ($validated['libur_nasional'] ?? [] as &$libur) {
            $libur['tanggal'] = $this->parseDate($libur['tanggal'])->format('Y-m-d');
        }

        DB::transaction(function () use ($id, $validated): void {
            Periode::query()->orderBy('id')->lockForUpdate()->get(['id']);
            $lockedPeriode = Periode::query()->findOrFail($id);
            $tahunAjaran = trim($validated['tahun_ajaran']);
            $tahunAjaranLama = $lockedPeriode->tahun_ajaran;

            $semester1 = Periode::query()
                ->where('tahun_ajaran', $tahunAjaranLama)
                ->where('semester', 1)
                ->lockForUpdate()
                ->first();
            $semester2 = Periode::query()
                ->where('tahun_ajaran', $tahunAjaranLama)
                ->where('semester', 2)
                ->lockForUpdate()
                ->first();

            if ($semester1) {
                $oldData = $semester1->load('hariLiburs')->toArray();
                $semester1->update([
                    'tahun_ajaran' => $tahunAjaran,
                    'nama_periode' => "Semester Ganjil {$tahunAjaran}",
                    'tanggal_mulai' => $validated['semester_1_tanggal_mulai'],
                    'tanggal_selesai' => $validated['semester_1_tanggal_selesai'],
                ]);
                $semester1->hariLiburs()->delete();
                $this->storeHariLiburs($semester1, $validated);
                $this->logUpdate(
                    'Periode',
                    $semester1,
                    ['old' => $oldData, 'new' => $semester1->fresh('hariLiburs')->toArray()],
                    "Memperbarui periode {$semester1->namaLengkap()}"
                );
            }

            if ($semester2) {
                $oldData = $semester2->load('hariLiburs')->toArray();
                $semester2->update([
                    'tahun_ajaran' => $tahunAjaran,
                    'nama_periode' => "Semester Genap {$tahunAjaran}",
                    'tanggal_mulai' => $validated['semester_2_tanggal_mulai'],
                    'tanggal_selesai' => $validated['semester_2_tanggal_selesai'],
                ]);
                $semester2->hariLiburs()->delete();
                $this->storeHariLiburs($semester2, $validated);
                $this->logUpdate(
                    'Periode',
                    $semester2,
                    ['old' => $oldData, 'new' => $semester2->fresh('hariLiburs')->toArray()],
                    "Memperbarui periode {$semester2->namaLengkap()}"
                );
            } else {
                $semester2 = Periode::create([
                    'tahun_ajaran' => $tahunAjaran,
                    'semester' => 2,
                    'tipe_periode' => 'semester',
                    'nama_periode' => "Semester Genap {$tahunAjaran}",
                    'tanggal_mulai' => $validated['semester_2_tanggal_mulai'],
                    'tanggal_selesai' => $validated['semester_2_tanggal_selesai'],
                ]);

                $this->storeHariLiburs($semester2, $validated);
                $this->logCreate(
                    'Periode',
                    $semester2->fresh('hariLiburs'),
                    "Menambahkan periode {$semester2->namaLengkap()}"
                );
            }
        });

        return redirect()->route('periode.index')->with('success', 'Periode akademik Semester 1 dan Semester 2 berhasil diperbarui.');
    }

    public function reset(Request $request)
    {
        DB::transaction(function (): void {
            Absensi::query()->delete();
            Periode::query()->delete();
        });

        return redirect()->route('periode.index')->with('success', 'Semua data periode dan absensi berhasil direset.');
    }

    /**
     * Simpan data hari libur (mingguan & nasional) untuk periode
     * 
     * @param  array<string, mixed> $validated
     */
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
                $keterangan .= ' - ' . $libur['keterangan'];
            }

            $periode->hariLiburs()->create([
                'tipe' => 'nasional',
                'tanggal' => $libur['tanggal'],
                'keterangan' => $keterangan,
            ]);
        }
    }

    /**
     * Parse string tanggal support format d/m/Y dan Y-m-d
     */
    private function parseDate(string $date): Carbon
    {
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
            return Carbon::createFromFormat('d/m/Y', $date);
        }
        return Carbon::parse($date);
    }
}
