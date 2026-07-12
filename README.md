# Sistem Absensi SDN Cibitung Kulon 02

Aplikasi Laravel untuk mengelola siswa, kelas, periode akademik, absensi,
rekap kehadiran, serta notifikasi WhatsApp orang tua/wali.

## Persyaratan

- PHP 8.3 atau lebih baru
- Composer 2
- MySQL 8
- Node.js 20.19+ atau 22.12+ beserta npm (sesuai persyaratan Vite 8)
- Ekstensi PHP: PDO MySQL, Mbstring, OpenSSL, XML, Ctype, JSON, dan Zip

## Instalasi lokal

```bash
composer install
copy .env.example .env
php artisan key:generate
```

Atur koneksi MySQL, `SEED_DEFAULT_PASSWORD`, dan kredensial Fonnte di `.env`,
kemudian jalankan:

```bash
php artisan migrate --seed
npm ci
npm run build
php artisan storage:link
```

Jalankan aplikasi dan worker antrean pada terminal terpisah:

```bash
php artisan serve
php artisan queue:work --tries=3
```

## Peran pengguna

- `operator`: mengelola guru, siswa, kelas, periode, rekap, dan notifikasi.
- `guru`: mengisi absensi dan melihat data kelas yang diampu.
- `kepala_sekolah`: melihat dashboard dan rekap sekolah.

Akun tidak didaftarkan secara publik. Operator membuat dan mengelola akun
melalui menu Data Guru.

## Pengujian yang aman

Pengujian wajib memakai database MySQL terpisah yang namanya berakhiran
`_test`. Jangan pernah mengarahkan PHPUnit ke database aplikasi.

1. Buat database dan user MySQL khusus testing.
2. Salin `.env.testing.example` menjadi `.env.testing`.
3. Isi kredensial user testing.
4. Pastikan `DB_DATABASE=absensi-cibitungkulon_test`.
5. Jalankan test melalui Composer:

```bash
composer test
```

Base test memiliki fail-fast guard yang membatalkan eksekusi sebelum
`migrate:fresh` jika environment bukan `testing` atau nama database tidak
berakhiran `_test`.

## Notifikasi WhatsApp

Konfigurasi Fonnte tersedia melalui variabel berikut:

```env
FONNTE_BASE_URL=https://api.fonnte.com
FONNTE_TOKEN=
FONNTE_COUNTRY_CODE=62
FONNTE_CONNECT_ONLY=true
FONNTE_TIMEOUT=15
FONNTE_RETENTION_DAYS=365
```

Pastikan worker antrean selalu aktif agar notifikasi alpa dapat diproses. Untuk
pengembangan lokal, scheduler dapat dijalankan pada terminal terpisah:

```bash
php artisan schedule:work
```

Di server produksi, jalankan `php artisan schedule:run` setiap menit melalui cron
atau Task Scheduler. Scheduler menghapus riwayat notifikasi yang sudah melewati
masa retensi.

## Pemeriksaan sebelum deploy

```bash
vendor/bin/pint --test
composer audit --locked
npm audit
npm run build
```

Setelah konfigurasi produksi final:

```bash
php artisan optimize
```

Gunakan `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, cookie secure, serta
backup MySQL terjadwal sebelum aplikasi digunakan untuk data sekolah.
