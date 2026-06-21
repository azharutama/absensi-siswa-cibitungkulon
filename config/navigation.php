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
    ],

    'guru' => [
        ['label' => 'Dashboard', 'route' => 'dashboard'],
        ['label' => 'Absensi', 'route' => 'absensi.index'],
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
];
