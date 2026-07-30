<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_download_filtered_recap_as_excel(): void
    {
        $operator = User::factory()->operator()->create();
        $kelas = Kelas::factory()->create(['nama_kelas' => '1 A']);
        $siswa = Siswa::factory()->create([
            'kelas_id' => $kelas->id,
            'periode_id' => $kelas->periode_id,
            'nama_siswa' => 'Siti Aminah',
        ]);

        Absensi::factory()->create([
            'siswa_id' => $siswa->id,
            'kelas_id' => $kelas->id,
            'periode_id' => $kelas->periode_id,
            'user_id' => $operator->id,
            'tanggal' => '2026-07-01',
            'status' => 'hadir',
        ]);

        $this->actingAs($operator)
            ->get(route('rekap.export', [
                'kelas_id' => $kelas->id,
                'preset' => 'custom',
                'tanggal_mulai' => '2026-07-01',
                'tanggal_berakhir' => '2026-07-31',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
