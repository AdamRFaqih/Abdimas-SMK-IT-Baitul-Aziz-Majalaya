<?php

namespace Tests\Fixtures\rapor;

use App\Models\AsesmenFormatif;
use App\Models\AsesmenSumatif;
use App\Models\Intrakurikuler;
use App\Models\Kelas;
use App\Models\KelasAjar;
use App\Models\RiwayatKelas;
use App\Models\Sekolah;
use App\Models\Siswa;
use App\Models\SkorAsesmenSiswa;
use App\Models\Staff;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/** Synthetic loaded relations only; nothing is saved or queried. */
class RaporFixture
{
    public static function make(int $subjects = 13, int $students = 1, bool $long = true, string $semester = 'Genap'): array
    {
        $siswaList = new Collection;
        for ($student = 1; $student <= $students; $student++) {
            $siswa = (new Siswa)->forceFill([
                'siswa_id' => $student,
                'nama' => 'Peserta Uji '.$student,
                'nisn' => '00000000'.str_pad((string) $student, 2, '0', STR_PAD_LEFT),
            ])->setRelation('user', (new User)->forceFill(['name' => 'Peserta Uji '.$student]));
            $siswaList->push((new RiwayatKelas)->forceFill([
                'riwayat_kelas_id' => $student,
                'kelas_ajar_id' => 10,
                'rekap_kehadiran' => ['sakit' => $student, 'izin' => 0, 'alpha' => 0],
            ])->setRelation('siswa', $siswa)->setRelation('skorAsesmen', new Collection));
        }

        $mapels = new Collection;
        for ($subject = 1; $subject <= $subjects; $subject++) {
            $formatif = new Collection;
            foreach ($siswaList as $rk) {
                $name = $rk->siswa->nama;
                $detail = $long && $subject % 3 === 1
                    ? str_repeat('Mampu menjelaskan konsep, menghubungkan hasil pengamatan dengan contoh, dan menyajikan alasan secara runtut. ', 3)
                    : 'Mampu menjelaskan konsep dan menyampaikan hasil pengamatan dengan runtut.';
                $formatif->push((new AsesmenFormatif)->forceFill([
                    'riwayat_kelas_id' => $rk->riwayat_kelas_id,
                    'intrakurikuler_id' => $subject,
                    'deskripsi_catatan_tertinggi' => $name.' memahami materi '.$subject.'. '.$detail.' AKHIR-TINGGI-'.$rk->riwayat_kelas_id.'-'.$subject,
                    'deskripsi_catatan_terendah' => $name.' perlu berlatih menyusun kesimpulan materi '.$subject.'. '.($long && $subject % 4 === 0 ? str_repeat('Latihan dilakukan bertahap dengan pendampingan dan umpan balik yang jelas. ', 3) : '').' AKHIR-RENDAH-'.$rk->riwayat_kelas_id.'-'.$subject,
                ]));
                foreach (['sumatif_lingkup' => [80, 90], 'test' => [70], 'non_test' => [90]] as $type => $scores) {
                    foreach ($scores as $nilai) {
                        $rk->skorAsesmen->push(self::score($rk->riwayat_kelas_id, $subject, 20, $type, $rk->riwayat_kelas_id === 1 ? $nilai : 60));
                    }
                }
            }
            $mapels->push((new Intrakurikuler)->forceFill([
                'intrakurikuler_id' => $subject,
                'kelas_ajar_id' => 10,
                'nama_pelajaran' => 'Muatan Uji '.str_pad((string) $subject, 2, '0', STR_PAD_LEFT).($subject === 13 ? ' dengan Nama Mata Pelajaran yang Panjang' : ''),
            ])->setRelation('asesmenFormatif', $formatif));
        }

        $kelasAjar = (new KelasAjar)->forceFill(['kelas_ajar_id' => 10, 'tahun_ajaran_id' => 20])
            ->setRelation('kelas', (new Kelas)->forceFill(['nama_kelas' => 'XI RPL Uji']))
            ->setRelation('tahunAjaran', (new TahunAjaran)->forceFill(['tahun_ajaran_id' => 20, 'tahun' => '2026/2027', 'semester' => $semester]))
            ->setRelation('waliKelas', (new User)->forceFill(['name' => 'Wali Kelas Uji']))
            ->setRelation('intrakurikuler', $mapels)
            ->setRelation('riwayatKelas', $siswaList);
        $staff = (new Staff)->forceFill(['nama' => 'Kepala Sekolah Uji'])
            ->setRelation('user', (new User)->forceFill(['name' => 'Kepala Sekolah Uji']));
        $sekolah = (new Sekolah)->forceFill(['nama_sekolah' => 'SMK Contoh Uji', 'alamat' => 'Jalan Pendidikan Uji Nomor 1'])
            ->setRelation('staff', $staff);

        $tanggalRapor = '24 Juni 2026';

        return compact('kelasAjar', 'siswaList', 'sekolah', 'tanggalRapor');
    }

    public static function score(int $riwayat, int $subject, int $period, string $type, ?int $nilai): SkorAsesmenSiswa
    {
        return (new SkorAsesmenSiswa)->forceFill([
            'riwayat_kelas_id' => $riwayat,
            'tahun_ajaran_id' => $period,
            'nilai' => $nilai,
        ])->setRelation('asesmenSumatif', (new AsesmenSumatif)->forceFill([
            'intrakurikuler_id' => $subject,
            'tahun_ajaran_id' => $period,
            'asesmen_type' => $type,
        ]));
    }
}
