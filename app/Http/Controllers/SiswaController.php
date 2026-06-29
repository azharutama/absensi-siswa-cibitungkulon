<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Periode;
use App\Models\Siswa;
use App\Services\SiswaImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Illuminate\Validation\Rule;
use ZipArchive;

class SiswaController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'periode_id' => ['nullable', 'integer', 'exists:periodes,id'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $siswas = Siswa::query()
            ->select([
                'id',
                'nis',
                'nisn',
                'nama_siswa',
                'jenis_kelamin',
                'kelas_id',
                'periode_id',
                'status',
            ])
            ->with([
                'kelas:id,nama_kelas',
                'periode:id,nama_periode',
            ])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('nama_siswa', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%");
                });
            })
            ->when($filters['kelas_id'] ?? null, fn($query, $kelasId) => $query->where('kelas_id', $kelasId))
            ->when($filters['periode_id'] ?? null, fn($query, $periodeId) => $query->where('periode_id', $periodeId))
            ->when($filters['status'] ?? null, fn($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $kelas = Kelas::query()
            ->select('id', 'nama_kelas', 'periode_id')
            ->orderBy('nama_kelas')
            ->get();

        $periodes = Periode::query()
            ->select('id', 'nama_periode')
            ->latest('tanggal_mulai')
            ->get();

        return view('siswa.index', compact('siswas', 'kelas', 'periodes'));
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
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120'],
        ]);

        try {
            $summary = $importService->import($request->file('file'));
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
            'kelas',
            'nama_ayah',
            'no_whatsapp_ayah',
            'nama_ibu',
            'no_whatsapp_ibu',
            'nama_wali',
            'no_whatsapp_wali',
            'status',
        ];

        $rows = [
            $headers,
            [
                '101',
                '1234567890',
                'Contoh Nama Siswa',
                'laki-laki',
                '1-A',
                'Contoh Ayah',
                '081234567890',
                'Contoh Ibu',
                '081234567891',
                '',
                '',
                'aktif',
            ],
        ];

        $filePath = storage_path('app/template_import_siswa.xlsx');
        $this->createSimpleXlsx($filePath, $rows);

        return response()->download($filePath, 'template_import_siswa.xlsx')->deleteFileAfterSend();
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        // Cari data kelas terpilih untuk mengekstrak periode_id bawaannya
        $kelasSelected = Kelas::findOrFail($data['kelas_id']);

        // Inject otomatis data periode_id dan status default siswa baru
        $data['periode_id'] = $kelasSelected->periode_id;
        $data['status'] = 'aktif';

        Siswa::create($data);

        return to_route('siswa.index')
            ->with('success', 'Data siswa berhasil ditambahkan.');
    }

    public function show(Siswa $siswa): View
    {
        $siswa->load(['kelas:id,nama_kelas', 'periode:id,nama_periode']);

        return view('siswa.show', compact('siswa'));
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

        // Jika kelas diubah, update juga periode_id agar mengikuti kelas yang baru
        $kelasSelected = Kelas::findOrFail($data['kelas_id']);
        $data['periode_id'] = $kelasSelected->periode_id;

        $siswa->update($data);

        return to_route('siswa.index')
            ->with('success', 'Data siswa berhasil diperbarui.');
    }

    public function destroy(Siswa $siswa): RedirectResponse
    {
        $siswa->delete();

        return to_route('siswa.index')
            ->with('success', 'Data siswa berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function validatedData(Request $request, ?Siswa $siswa = null): array
    {
        return $request->validate([
            'nis' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('siswas', 'nis')->ignore($siswa),
            ],
            'nisn' => [
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

    /** @return array{kelas: Collection, periodes: Collection} */
    private function formOptions(): array
    {
        return [
            'kelas' => Kelas::query()
                ->select('id', 'nama_kelas', 'periode_id')
                ->orderBy('nama_kelas')
                ->get(),
            'periodes' => Periode::query()
                ->select('id', 'nama_periode')
                ->latest('tanggal_mulai')
                ->get(),
        ];
    }

    /**
     * @param array<int, array<int, string>> $rows
     */
    private function createSimpleXlsx(string $filePath, array $rows): void
    {
        $zip = new ZipArchive();
        $zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

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
     * @param array<int, array<int, string>> $rows
     */
    private function buildWorksheetXml(array $rows): string
    {
        $xmlRows = '';

        foreach ($rows as $rowIndex => $row) {
            $cellXml = '';

            foreach ($row as $columnIndex => $value) {
                $cell = $this->excelColumnName($columnIndex + 1) . ($rowIndex + 1);
                $escapedValue = htmlspecialchars($value, ENT_XML1);
                $cellXml .= "<c r=\"{$cell}\" t=\"inlineStr\"><is><t>{$escapedValue}</t></is></c>";
            }

            $rowNumber = $rowIndex + 1;
            $xmlRows .= "<row r=\"{$rowNumber}\">{$cellXml}</row>";
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>' . $xmlRows . '</sheetData>
</worksheet>';
    }

    private function excelColumnName(int $number): string
    {
        $name = '';

        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }

        return $name;
    }
}
