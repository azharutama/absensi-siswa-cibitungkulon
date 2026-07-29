<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\RiwayatKelasSiswa;
use App\Models\Siswa;
use App\Services\SiswaImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use ZipArchive;

class SiswaController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
        ]);

        $siswas = Siswa::query()
            ->select([
                'id',
                'nis',
                'nisn',
                'nama_siswa',
                'jenis_kelamin',
                'kelas_id',
            ])
            ->with([
                'kelas:id,nama_kelas',
            ])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('nama_siswa', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%");
                });
            })
            ->when($filters['kelas_id'] ?? null, fn ($query, $kelasId) => $query->where('kelas_id', $kelasId))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $kelas = Kelas::query()
            ->select('id', 'nama_kelas')
            ->orderBy('nama_kelas')
            ->get();

        return view('siswa.index', compact('siswas', 'kelas'));
    }

    public function create(): View
    {
        return view('siswa.create', $this->formOptions());
    }

    public function importForm(): View
    {
        return view('siswa.import', $this->formOptions());
    }

    public function import(Request $request, SiswaImportService $importService): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv', 'max:5120'],
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
        ]);

        try {
            $summary = $importService->import($data['file'], $this->findKelas((int) $data['kelas_id']));
        } catch (RuntimeException $exception) {
            return back()
                ->with('error', $exception->getMessage());
        }

        $message = "Import selesai. {$summary['created']} siswa baru ditambahkan, {$summary['updated']} siswa diperbarui, {$summary['skipped']} baris kosong dilewati.";

        return to_route('siswa.index')
            ->with($summary['errors'] ? 'warning' : 'success', $message)
            ->with('import_errors', $summary['errors']);
    }

    public function downloadTemplate()
    {
        $headers = [
            'nis',
            'nisn',
            'nama_siswa',
            'jenis_kelamin',
            'nama_ayah',
            'no_whatsapp_ayah',
            'nama_ibu',
            'no_whatsapp_ibu',
            'nama_wali',
            'no_whatsapp_wali',
        ];

        $rows = [
            $headers,
            [
                '101',
                '1234567890',
                'Contoh Nama Siswa',
                'laki-laki',
                'Contoh Ayah',
                '081234567890',
                'Contoh Ibu',
                '081234567891',
                '',
                '',
            ],
        ];

        $filePath = tempnam(storage_path('app/private'), 'template-import-siswa-');

        if ($filePath === false) {
            throw new RuntimeException('File template sementara tidak dapat dibuat.');
        }

        $this->createSimpleXlsx($filePath, $rows);

        return response()->download($filePath, 'template_import_siswa.xlsx')->deleteFileAfterSend();
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $kelasSelected = $this->findKelas((int) $data['kelas_id']);

        $data['kelas_id'] = $kelasSelected->id;

        Siswa::create($data);

        return to_route('siswa.index')
            ->with('success', 'Data siswa berhasil ditambahkan.');
    }

    public function edit(Siswa $siswa): View
    {
        return view('siswa.edit', [
            'siswa' => $siswa,
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, Siswa $siswa): RedirectResponse
    {
        $data = $this->validatedData($request, $siswa);

        $kelasSelected = $this->findKelas((int) $data['kelas_id']);
        $data['kelas_id'] = $kelasSelected->id;

        $siswa->update($data);

        return to_route('siswa.index')
            ->with('success', 'Data siswa berhasil diperbarui.');
    }

    public function destroy(Siswa $siswa): RedirectResponse
    {
        if ($siswa->absensis()->exists()) {
            return to_route('siswa.index')
                ->with('error', 'Siswa yang memiliki riwayat absensi tidak dapat dihapus.');
        }

        $siswa->delete();

        return to_route('siswa.index')
            ->with('success', 'Data siswa berhasil dihapus.');
    }

    public function ubahKelasForm(): View
    {
        return view('siswa.ubah-kelas', [
            'kelas' => Kelas::query()
                ->select('id', 'nama_kelas')
                ->orderBy('nama_kelas')
                ->get(),
        ]);
    }

    public function ubahKelas(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kelas_asal_id' => ['required', 'integer', 'exists:kelas,id'],
            'kelas_tujuan_id' => ['required', 'integer', 'exists:kelas,id', 'different:kelas_asal_id'],
        ]);

        $kelasAsal = Kelas::findOrFail((int) $data['kelas_asal_id']);
        $kelasTujuan = Kelas::findOrFail((int) $data['kelas_tujuan_id']);

        $count = Siswa::query()
            ->where('kelas_id', $kelasAsal->id)
            ->count();

        if ($count === 0) {
            return redirect()->back()
                ->with('error', 'Tidak ada siswa di kelas asal.');
        }

        DB::transaction(function () use ($kelasAsal, $kelasTujuan): void {
            Siswa::query()
                ->where('kelas_id', $kelasAsal->id)
                ->update(['kelas_id' => $kelasTujuan->id]);

            Siswa::query()
                ->where('kelas_id', $kelasTujuan->id)
                ->where('status', 'aktif')
                ->chunkById(200, function ($siswas) use ($kelasAsal, $kelasTujuan): void {
                    foreach ($siswas as $siswa) {
                        RiwayatKelasSiswa::create([
                            'siswa_id' => $siswa->id,
                            'kelas_asal_id' => $kelasAsal->id,
                            'kelas_tujuan_id' => $kelasTujuan->id,
                            'tanggal_kenaikan' => today(),
                            'status' => 'aktif',
                        ]);
                    }
                });
        });

        return to_route('siswa.index')
            ->with('success', "{$count} siswa berhasil dipindahkan ke kelas baru.");
    }

    /** @return array<string, mixed> */
    private function validatedData(Request $request, ?Siswa $siswa = null): array
    {
        return $request->validate([
            'nis' => [
                'required_without:nisn',
                'nullable',
                'string',
                'max:50',
                Rule::unique('siswas', 'nis')->ignore($siswa),
            ],
            'nisn' => [
                'required_without:nis',
                'nullable',
                'string',
                'max:50',
                Rule::unique('siswas', 'nisn')->ignore($siswa),
            ],
            'nama_siswa' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', Rule::in(['laki-laki', 'perempuan'])],
            'nama_ayah' => ['required', 'string', 'max:255'],
            'no_whatsapp_ayah' => ['required', 'string', 'max:20'],
            'nama_ibu' => ['required', 'string', 'max:255'],
            'no_whatsapp_ibu' => ['required', 'string', 'max:20'],
            'nama_wali' => ['nullable', 'string', 'max:255'],
            'no_whatsapp_wali' => ['nullable', 'string', 'max:20'],
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'alamat' => ['nullable', 'string'],
        ]);
    }

    /** @return array{kelas: Collection} */
    private function formOptions(): array
    {
        return [
            'kelas' => Kelas::query()
                ->select('id', 'nama_kelas')
                ->orderBy('nama_kelas')
                ->get(),
        ];
    }

    private function findKelas(int $kelasId): Kelas
    {
        $kelas = Kelas::query()->find($kelasId);

        if (! $kelas) {
            throw ValidationException::withMessages([
                'kelas_id' => 'Kelas tidak ditemukan.',
            ]);
        }

        return $kelas;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function createSimpleXlsx(string $filePath, array $rows): void
    {
        $zip = new ZipArchive;
        if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('File template XLSX tidak dapat dibuat.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Import Siswa" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>');

        $zip->addFromString('xl/worksheets/sheet1.xml', $this->buildWorksheetXml($rows));
        $zip->close();
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function buildWorksheetXml(array $rows): string
    {
        $xmlRows = '';

        foreach ($rows as $rowIndex => $row) {
            $cellXml = '';

            foreach ($row as $columnIndex => $value) {
                $cell = $this->excelColumnName($columnIndex + 1).($rowIndex + 1);
                $escapedValue = htmlspecialchars($value, ENT_XML1);
                $cellXml .= "<c r=\"{$cell}\" t=\"inlineStr\"><is><t>{$escapedValue}</t></is></c>";
            }

            $rowNumber = $rowIndex + 1;
            $xmlRows .= "<row r=\"{$rowNumber}\">{$cellXml}</row>";
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>'.$xmlRows.'</sheetData>
</worksheet>';
    }

    private function excelColumnName(int $number): string
    {
        $name = '';

        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }
}
