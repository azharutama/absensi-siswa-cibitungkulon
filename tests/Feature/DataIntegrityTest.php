<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Periode;
use App\Models\Siswa;
use App\Models\User;
use App\Models\WhatsappNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_alpa_attendance_sends_whatsapp_notification_immediately(): void
    {
        config()->set('services.fonnte.token', 'test-token');
        Http::fake([
            '*' => Http::response([
                'status' => true,
                'message' => 'OK',
                'data' => [
                    'id' => 'msg-1',
                    'requestid' => 'req-1',
                ],
            ], 200),
        ]);

        $periode = Periode::factory()->create([
            'tanggal_mulai' => today()->subDay()->toDateString(),
            'tanggal_selesai' => today()->toDateString(),
        ]);
        $kelas = Kelas::factory()->for($periode)->create();
        $guru = User::factory()->guru()->create();
        $kelas->gurus()->attach($guru, ['is_wali_kelas' => true]);
        $siswa = Siswa::factory()->create([
            'kelas_id' => $kelas->id,
            'periode_id' => $periode->id,
            'nama_ayah' => 'Bapak Siswa',
            'no_whatsapp_ayah' => '081234567890',
            'nama_wali' => null,
            'no_whatsapp_wali' => null,
        ]);

        $this->actingAs($guru)
            ->post(route('absensi.store'), [
                'kelas_id' => $kelas->id,
                'tanggal' => today()->toDateString(),
                'absensi' => [$siswa->id => 'alpa'],
            ])
            ->assertRedirect(route('absensi.create', ['kelas_id' => $kelas->id, 'tanggal' => today()->toDateString()]));

        $notification = WhatsappNotification::query()->firstOrFail();

        $this->assertDatabaseHas('absensis', ['siswa_id' => $siswa->id, 'status' => 'alpa']);
        $this->assertSame('6281234567890', $notification->parent_phone);
        $this->assertSame('sent', $notification->status);
        $this->assertSame('msg-1', $notification->provider_message_id);
        $this->assertSame('req-1', $notification->provider_request_id);
        Http::assertSentCount(1);
    }

    public function test_student_csv_import_uses_the_active_class(): void
    {
        $periode = Periode::factory()->create(['status_aktif' => true]);
        $kelas = Kelas::factory()->for($periode)->create(['nama_kelas' => '4-A']);
        $operator = User::factory()->operator()->create();
        $csv = implode("\n", [
            'nis,nisn,nama_siswa,jenis_kelamin,nama_ayah,no_whatsapp_ayah,nama_ibu,no_whatsapp_ibu,status',
            '101,0012345678,Siswa Impor,L,Ayah Siswa,081234567890,Ibu Siswa,081234567891,aktif',
        ]);

        $this->actingAs($operator)
            ->post(route('siswa.import'), [
                'file' => UploadedFile::fake()->createWithContent('siswa.csv', $csv),
                'kelas_id' => $kelas->id,
            ])
            ->assertRedirect(route('siswa.index'));

        $this->assertDatabaseHas('siswas', [
            'nis' => '101',
            'nisn' => '0012345678',
            'nama_siswa' => 'Siswa Impor',
            'kelas_id' => $kelas->id,
            'periode_id' => $periode->id,
        ]);
    }

    public function test_attendance_date_must_be_inside_the_class_period(): void
    {
        $periode = Periode::factory()->create([
            'nama_periode' => 'Semester Ganjil 2026/2027',
            'tanggal_mulai' => today()->subDays(30)->toDateString(),
            'tanggal_selesai' => today()->toDateString(),
        ]);
        $kelas = Kelas::factory()->for($periode)->create(['nama_kelas' => '1-A']);
        $guru = User::factory()->guru()->create();
        $kelas->gurus()->attach($guru, ['is_wali_kelas' => true]);
        $siswa = Siswa::factory()->create([
            'kelas_id' => $kelas->id,
            'periode_id' => $periode->id,
        ]);

        $response = $this->actingAs($guru)->post(route('absensi.store'), [
            'kelas_id' => $kelas->id,
            'tanggal' => today()->subDays(31)->toDateString(),
            'absensi' => [$siswa->id => 'hadir'],
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('absensis', 0);
    }

    public function test_future_attendance_cannot_be_saved(): void
    {
        $periode = Periode::factory()->create([
            'nama_periode' => 'Semester Genap 2026/2027',
            'tanggal_mulai' => today()->subDay()->toDateString(),
            'tanggal_selesai' => today()->addDays(30)->toDateString(),
        ]);
        $kelas = Kelas::factory()->for($periode)->create(['nama_kelas' => '1-A']);
        $guru = User::factory()->guru()->create();
        $kelas->gurus()->attach($guru, ['is_wali_kelas' => true]);
        $siswa = Siswa::factory()->create([
            'kelas_id' => $kelas->id,
            'periode_id' => $periode->id,
        ]);

        $response = $this->actingAs($guru)->post(route('absensi.store'), [
            'kelas_id' => $kelas->id,
            'tanggal' => today()->addDay()->toDateString(),
            'absensi' => [$siswa->id => 'hadir'],
        ]);

        $response->assertSessionHasErrors('tanggal');
        $this->assertDatabaseCount('absensis', 0);
    }

    public function test_class_with_attendance_history_cannot_be_deleted_after_student_moves(): void
    {
        $periode = Periode::factory()->create(['nama_periode' => 'Semester Genap 2025/2026']);
        $kelasLama = Kelas::factory()->for($periode)->create(['nama_kelas' => '1-A']);
        $kelasBaru = Kelas::factory()->for($periode)->create(['nama_kelas' => '1-B']);
        $operator = User::factory()->operator()->create();
        $guru = User::factory()->guru()->create();
        $siswa = Siswa::factory()->create([
            'kelas_id' => $kelasBaru->id,
            'periode_id' => $periode->id,
        ]);
        Absensi::factory()->create([
            'siswa_id' => $siswa->id,
            'kelas_id' => $kelasLama->id,
            'user_id' => $guru->id,
            'periode_id' => $periode->id,
            'tanggal' => $periode->tanggal_mulai,
        ]);

        $response = $this->actingAs($operator)->delete(route('kelas.destroy', $kelasLama));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('kelas', ['id' => $kelasLama->id]);
    }

    public function test_student_requires_at_least_one_school_identifier(): void
    {
        $periode = Periode::factory()->create([
            'nama_periode' => 'Semester Ganjil 2026/2027',
            'status_aktif' => true,
        ]);
        $kelas = Kelas::factory()->for($periode)->create(['nama_kelas' => '2-A']);
        $operator = User::factory()->operator()->create();

        $response = $this->actingAs($operator)->post(route('siswa.store'), [
            'nama_siswa' => 'Siswa Tanpa Nomor',
            'jenis_kelamin' => 'laki-laki',
            'nama_ayah' => 'Ayah',
            'no_whatsapp_ayah' => '081234567890',
            'nama_ibu' => 'Ibu',
            'no_whatsapp_ibu' => '081234567891',
            'kelas_id' => $kelas->id,
        ]);

        $response->assertSessionHasErrors(['nis', 'nisn']);
        $this->assertDatabaseMissing('siswas', ['nama_siswa' => 'Siswa Tanpa Nomor']);
    }

    public function test_period_dates_cannot_exclude_existing_attendance(): void
    {
        $periode = Periode::factory()->create([
            'nama_periode' => 'Semester Genap 2025/2026',
            'tanggal_mulai' => today()->subDays(60)->toDateString(),
            'tanggal_selesai' => today()->toDateString(),
        ]);
        $kelas = Kelas::factory()->for($periode)->create(['nama_kelas' => '3-A']);
        $operator = User::factory()->operator()->create();
        $guru = User::factory()->guru()->create();
        $siswa = Siswa::factory()->create([
            'kelas_id' => $kelas->id,
            'periode_id' => $periode->id,
        ]);
        Absensi::factory()->create([
            'siswa_id' => $siswa->id,
            'kelas_id' => $kelas->id,
            'user_id' => $guru->id,
            'periode_id' => $periode->id,
            'tanggal' => today()->subDays(45)->toDateString(),
        ]);

        $response = $this->actingAs($operator)->put(route('periode.update', $periode), [
            'nama_periode' => $periode->nama_periode,
            'tanggal_mulai' => today()->subDays(30)->toDateString(),
            'tanggal_selesai' => today()->toDateString(),
            'status_aktif' => false,
        ]);

        $response->assertSessionHasErrors(['tanggal_mulai', 'tanggal_selesai']);
        $this->assertSame(today()->subDays(60)->toDateString(), $periode->fresh()->tanggal_mulai->toDateString());
    }

    public function test_period_date_ranges_cannot_overlap(): void
    {
        Periode::factory()->create([
            'nama_periode' => 'Semester Lama',
            'tanggal_mulai' => today()->subDays(60)->toDateString(),
            'tanggal_selesai' => today()->subDays(20)->toDateString(),
        ]);
        $operator = User::factory()->operator()->create();

        $response = $this->actingAs($operator)->post(route('periode.store'), [
            'nama_periode' => 'Semester Bertumpuk',
            'tanggal_mulai' => today()->subDays(30)->toDateString(),
            'tanggal_selesai' => today()->addDays(30)->toDateString(),
            'status_aktif' => false,
        ]);

        $response->assertSessionHasErrors('tanggal_mulai');
        $this->assertDatabaseMissing('periodes', ['nama_periode' => 'Semester Bertumpuk']);
    }

    public function test_active_operator_cannot_demote_own_account(): void
    {
        $operator = User::factory()->operator()->create();

        $response = $this->actingAs($operator)->put(route('guru.update', $operator), [
            'nip' => $operator->nip,
            'nama' => $operator->nama,
            'email' => $operator->email,
            'no_telepon' => $operator->no_telepon,
            'alamat' => $operator->address,
            'role' => 'guru',
            'jenis_kelamin' => $operator->jenis_kelamin,
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertSame('operator', $operator->fresh()->role);
    }
}
