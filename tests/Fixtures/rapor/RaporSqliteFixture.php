<?php

namespace Tests\Fixtures\rapor;

use Illuminate\Support\Facades\DB;

/** Minimal schema for the print query; never runs application migrations. */
class RaporSqliteFixture
{
    public static function install(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \LogicException('Rapor fixture requires a separate SQLite :memory: connection.');
        }

        foreach ([
            'users' => 'id integer primary key, name text',
            'staff' => 'staff_id integer primary key, user_id integer, nama text',
            'sekolah' => 'npsn integer primary key, nama_sekolah text, alamat text, staff_id integer, kelurahan_id integer',
            'kelas' => 'kelas_id integer primary key, nama_kelas text',
            'tahun_ajaran' => 'tahun_ajaran_id integer primary key, tahun text, semester text',
            'kelas_ajar' => 'kelas_ajar_id integer primary key, kelas_id integer, tahun_ajaran_id integer, wali_user_id integer',
            'siswa' => 'siswa_id integer primary key, user_id integer, nama text, nisn text',
            'riwayat_kelas' => 'riwayat_kelas_id integer primary key, kelas_ajar_id integer, siswa_id integer',
            'intrakurikuler' => 'intrakurikuler_id integer primary key, kelas_ajar_id integer, nama_pelajaran text',
            'asesmen_formatif' => 'asesmen_formatif_id integer primary key, riwayat_kelas_id integer, intrakurikuler_id integer, deskripsi_catatan_tertinggi text, deskripsi_catatan_terendah text',
            'asesmen_sumatif' => 'asesmen_sumatif_id integer primary key, intrakurikuler_id integer, tahun_ajaran_id integer, asesmen_type text',
            'skor_asesmen_siswa' => 'skor_asesmen_siswa_id integer primary key, riwayat_kelas_id integer, asesmen_sumatif_id integer, tahun_ajaran_id integer, nilai integer',
            'kehadiran_intrakurikuler' => 'kehadiran_intrakurikuler_id integer primary key, riwayat_kelas_id integer, status text',
        ] as $table => $columns) {
            DB::statement('create table '.$table.' ('.$columns.')');
        }

        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Peserta Uji 1'], ['id' => 2, 'name' => 'Peserta Uji 2'],
            ['id' => 3, 'name' => 'Wali Kelas Uji'], ['id' => 4, 'name' => 'Kepala Sekolah Uji'],
        ]);
        DB::table('staff')->insert(['staff_id' => 1, 'user_id' => 4, 'nama' => 'Kepala Sekolah Uji']);
        DB::table('sekolah')->insert(['npsn' => 1, 'nama_sekolah' => 'SMK Contoh Uji', 'alamat' => 'Jalan Pendidikan Uji', 'staff_id' => 1]);
        DB::table('kelas')->insert(['kelas_id' => 1, 'nama_kelas' => 'XI RPL Uji']);
        DB::table('tahun_ajaran')->insert(['tahun_ajaran_id' => 20, 'tahun' => '2026/2027', 'semester' => 'Genap']);
        DB::table('kelas_ajar')->insert(['kelas_ajar_id' => 10, 'kelas_id' => 1, 'tahun_ajaran_id' => 20, 'wali_user_id' => 3]);
        DB::table('intrakurikuler')->insert(['intrakurikuler_id' => 1, 'kelas_ajar_id' => 10, 'nama_pelajaran' => 'Muatan Uji']);
        for ($student = 1; $student <= 2; $student++) {
            DB::table('siswa')->insert(['siswa_id' => $student, 'user_id' => $student, 'nama' => 'Peserta Uji '.$student, 'nisn' => '000000000'.$student]);
            DB::table('riwayat_kelas')->insert(['riwayat_kelas_id' => $student, 'kelas_ajar_id' => 10, 'siswa_id' => $student]);
            DB::table('kehadiran_intrakurikuler')->insert(['riwayat_kelas_id' => $student, 'status' => 'sakit']);
        }
        DB::table('riwayat_kelas')->insert(['riwayat_kelas_id' => 99, 'kelas_ajar_id' => 99, 'siswa_id' => 1]);
        $components = [['sumatif_lingkup', 80], ['sumatif_lingkup', 90], ['test', 70], ['non_test', 90], ['sas', 0]];
        foreach ($components as $index => [$type, $nilai]) {
            DB::table('asesmen_sumatif')->insert(['asesmen_sumatif_id' => $index + 1, 'intrakurikuler_id' => 1, 'tahun_ajaran_id' => 20, 'asesmen_type' => $type]);
            DB::table('skor_asesmen_siswa')->insert(['riwayat_kelas_id' => 1, 'asesmen_sumatif_id' => $index + 1, 'tahun_ajaran_id' => 20, 'nilai' => $nilai]);
        }
    }
}
