<?php

return [
    'operator' => [
        ['label' => 'Dashboard', 'route' => 'dashboard'],
        ['label' => 'Data Siswa', 'route' => 'siswa.index'],
        ['label' => 'Data Kelas', 'route' => 'kelas.index'],
        ['label' => 'Data Guru', 'route' => 'guru.index'],
        ['label' => 'Data Periode', 'route' => 'periode.index'],
        ['label' => 'Rekap Absensi', 'route' => 'rekap.index'],
        ['label' => 'Notifikasi WhatsApp', 'route' => 'notifikasi.index'],
        ['label' => 'Log Aktivitas', 'route' => 'activity-logs.index'],
    ],

    'guru' => [
        ['label' => 'Dashboard', 'route' => 'dashboard'],
        ['label' => 'Absensi', 'route' => 'absensi.create'],
        ['label' => 'Data Siswa', 'route' => 'siswa.index'],
        ['label' => 'Rekap Absensi', 'route' => 'rekap.index'],
        ['label' => 'Notifikasi WhatsApp', 'route' => 'notifikasi.index'],
    ],

    'kepala_sekolah' => [
        ['label' => 'Dashboard', 'route' => 'dashboard'],
        ['label' => 'Rekap Absensi', 'route' => 'rekap.index'],
    ],

    'role_labels' => [
        'operator' => 'Operator',
        'guru' => 'Guru',
        'kepala_sekolah' => 'Kepala Sekolah',
    ],

    'dashboard_titles' => [
        'operator' => 'Dashboard Operator',
        'guru' => 'Dashboard Guru',
        'kepala_sekolah' => 'Dashboard Kepala Sekolah',
    ],

    'page_titles' => [
        'guru.index' => 'Data Guru',
        'guru.create' => 'Tambah Guru',
        'guru.edit' => 'Edit Guru',
        'siswa.index' => 'Data Siswa',
        'siswa.create' => 'Tambah Siswa',
        'siswa.edit' => 'Edit Siswa',
        'kelas.index' => 'Data Kelas',
        'kelas.create' => 'Tambah Kelas',
        'kelas.edit' => 'Edit Kelas',
        'periode.index' => 'Periode Aktif',
        'absensi.create' => 'Isi Absensi',
        'absensi.edit' => 'Edit Absensi',
        'rekap.index' => 'Rekap Absensi',
        'notifikasi.index' => 'Notifikasi WhatsApp',
        'activity-logs.index' => 'Log Aktivitas',
        'profile.edit' => 'Profil',
    ],
];
