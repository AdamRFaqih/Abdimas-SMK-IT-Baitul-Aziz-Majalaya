<?php

namespace App\Http\Controllers\Dokumen;

use App\Http\Controllers\Controller;
use App\Models\KelasAjar;
use App\Models\Sekolah;
use App\Support\RaporNilaiCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
// use Spatie\LaravelPdf\Facades\Pdf;
use Barryvdh\DomPDF\Facade\Pdf;

class CetakDokumenController extends Controller
{
    // Halaman Tabel kelas
    public function kelas()
    {
        $user = Auth::user();
        $userId = Auth::id();

        if ($user->hasRole('Wali Kelas')) {
            $kelasList = KelasAjar::query()
                ->where('wali_user_id', $userId)
                ->join('tahun_ajaran', 'kelas_ajar.tahun_ajaran_id', '=', 'tahun_ajaran.tahun_ajaran_id')
                ->select('kelas_ajar.*')
                ->with('kelas', 'tahunAjaran', 'waliKelas')
                ->withCount('riwayatKelas')
                ->orderBy('tahun_ajaran.tahun', 'desc')
                ->get();
        }
        if ($user->hasRole('Bagian Akademik')) {
            $kelasList = KelasAjar::query()
                ->join('tahun_ajaran', 'kelas_ajar.tahun_ajaran_id', '=', 'tahun_ajaran.tahun_ajaran_id')
                ->select('kelas_ajar.*')
                ->with('kelas', 'tahunAjaran', 'waliKelas')
                ->withCount('riwayatKelas')
                ->orderBy('tahun_ajaran.tahun', 'desc')
                ->get();
        }

        return view('dokumen.kelas', compact('kelasList'));
    }

    // Halaman pilih siswa dan jenis dokumen untuk dicetak
    public function pilihCetak($kelas_ajar)
    {
        $kelasAjar = KelasAjar::with(['kelas', 'tahunAjaran', 'waliKelas', 'riwayatKelas.siswa.user'])->findOrFail($kelas_ajar);

        $user = Auth::user();
        if (!$user->hasRole('Bagian Akademik') && $user->id != $kelasAjar->wali_user_id) {
            $namaKelas = $kelasAjar->kelas->nama_kelas;
            $tahunAjaran = $kelasAjar->tahunAjaran->tahun;
            $semester = $kelasAjar->tahunAjaran->semester;

            return back()->with('error', "Anda tidak punya akses untuk melihat Opsi Cetak Dokumen di kelas $namaKelas $tahunAjaran $semester");
        }

        $siswaList = $kelasAjar->riwayatKelas->map(function ($rk) {
            $siswa = $rk->siswa;
            $user = $siswa->user ?? null;
            return [
                'riwayat_kelas_id' => $rk->riwayat_kelas_id,
                'nama' => $user->name ?? $siswa->nama,
                'nis' => $siswa->nis,
                'nisn' => $siswa->nisn,
                'jenis_kelamin' => $siswa->jenis_kelamin,
                'alamat' => $siswa->alamat . ' Kel. ' . $siswa->kelurahan->nama . ' Kec. ' . $siswa->kelurahan->kecamatan->nama . $siswa->kelurahan->kecamatan->kabupaten->nama,
            ];
        });

        return view('dokumen.pilih_cetak', compact('kelasAjar', 'siswaList'));
    }

    // Generate PDF Sampul Rapor
    public function cetakSampul(Request $request)
    {
        $request->validate([
            'kelas_ajar_id' => 'required|exists:kelas_ajar,kelas_ajar_id',
            'jenis' => 'required|in:sampul,rapor',
            'siswa' => 'array',
            'siswa.*' => 'exists:riwayat_kelas,riwayat_kelas_id',
        ]);

        $sekolah = Sekolah::firstOrFail();
        $kelasAjar = KelasAjar::with([
            'kelas',
            'tahunAjaran',
            'waliKelas',
            'riwayatKelas.siswa.user',
            'riwayatKelas.siswa.kelurahan.kecamatan.kabupaten'
        ])->findOrFail($request->kelas_ajar_id);

        $siswaIds = $request->siswa ?? $kelasAjar->riwayatKelas->pluck('riwayat_kelas_id')->toArray();
        $siswaList = $kelasAjar->riwayatKelas->whereIn('riwayat_kelas_id', $siswaIds);

        $namaKelas = $kelasAjar->kelas->nama_kelas;
        $tahunAjaran = str_replace('/', '-', $kelasAjar->tahunAjaran->tahun);
        $semester = $kelasAjar->tahunAjaran->semester;

        $filename = 'Sampul Rapor ' . $namaKelas . ' ' . $tahunAjaran . ' ' . $semester . '.pdf';

        return Pdf::loadView('dokumen.pdf_sampul', [
            'kelasAjar' => $kelasAjar,
            'siswaList' => $siswaList,
            'sekolah' => $sekolah,
        ])
            ->setPaper('a4', 'portrait')
            // ->download($filename);
            ->stream($filename);
    }

    // Generate PDF Halaman Nilai Rapor
    public function cetakRapor(Request $request)
    {
        $request->validate([
            'kelas_ajar_id' => 'required|exists:kelas_ajar,kelas_ajar_id',
            'tanggal_rapor' => 'required|date_format:Y-m-d',
            'siswa' => 'array',
            'siswa.*' => 'exists:riwayat_kelas,riwayat_kelas_id',
        ], [
            'tanggal_rapor.required' => 'Tanggal Rapor wajib dipilih sebelum mencetak.',
            'tanggal_rapor.date_format' => 'Tanggal Rapor harus berupa tanggal yang valid (YYYY-MM-DD).',
        ]);

        $tanggalRapor = \Carbon\Carbon::createFromFormat('!Y-m-d', $request->input('tanggal_rapor'))
            ->locale('id')->translatedFormat('d F Y');

        $sekolah = Sekolah::with(['kelurahan.kecamatan.kabupaten', 'staff.user'])->first();
        $kelasAjar = KelasAjar::with([
            'kelas',
            'tahunAjaran',
            'waliKelas',
            'intrakurikuler.asesmenFormatif',
            'riwayatKelas.siswa.user',
            'riwayatKelas.kehadiranIntrakurikuler',
            'riwayatKelas.skorAsesmen.asesmenSumatif',
        ])->findOrFail($request->kelas_ajar_id);

        $siswaIds = $request->siswa ?? $kelasAjar->riwayatKelas->pluck('riwayat_kelas_id')->toArray();
        $siswaList = $kelasAjar->riwayatKelas->whereIn('riwayat_kelas_id', $siswaIds);

        $nilaiAkhirByRiwayat = $this->nilaiRaporByRiwayat($kelasAjar, $siswaList);

        // Hitung kehadiran per siswa
        foreach ($siswaList as $rk) {
            $kehadiran = [
                'sakit' => 0,
                'izin' => 0,
                'alpha' => 0,
            ];
            foreach ($rk->kehadiranIntrakurikuler as $absen) {
                if ($absen->status === 'sakit') $kehadiran['sakit']++;
                if ($absen->status === 'izin') $kehadiran['izin']++;
                if ($absen->status === 'alpha') $kehadiran['alpha']++;
            }
            $rk->rekap_kehadiran = $kehadiran;
        }

        $namaKelas = $kelasAjar->kelas->nama_kelas;
        $tahunAjaran = str_replace('/', '-', $kelasAjar->tahunAjaran->tahun);
        $semester = $kelasAjar->tahunAjaran->semester;

        $filename = 'Cetak Rapor ' . $namaKelas . ' ' . $tahunAjaran . ' ' . $semester . '.pdf';

        $pdf = Pdf::loadView('dokumen.pdf_rapor', [
            'kelasAjar' => $kelasAjar,
            'siswaList' => $siswaList,
            'sekolah' => $sekolah,
            'nilaiAkhirByRiwayat' => $nilaiAkhirByRiwayat,
            'tanggalRapor' => $tanggalRapor,
        ])->setPaper('a4', 'portrait')
            // ->download($filename);
            ->stream($filename);

        return $pdf;
    }

    /** Read loaded scores using the same component selection as the sumatif index. */
    private function nilaiRaporByRiwayat(KelasAjar $kelasAjar, Collection $siswaList): array
    {
        $nilaiByRiwayat = [];

        foreach ($siswaList as $rk) {
            if ((string) $rk->kelas_ajar_id !== (string) $kelasAjar->kelas_ajar_id) {
                continue;
            }

            foreach ($kelasAjar->intrakurikuler as $mapel) {
                if ((string) $mapel->kelas_ajar_id !== (string) $kelasAjar->kelas_ajar_id) {
                    continue;
                }

                $lingkup = [];
                $test = null;
                $nonTest = null;

                foreach ($rk->skorAsesmen as $skor) {
                    $asesmen = $skor->asesmenSumatif;
                    if (!$asesmen
                        || (string) $skor->riwayat_kelas_id !== (string) $rk->riwayat_kelas_id
                        || (string) $asesmen->intrakurikuler_id !== (string) $mapel->intrakurikuler_id
                        || (string) $asesmen->tahun_ajaran_id !== (string) $kelasAjar->tahun_ajaran_id) {
                        continue;
                    }

                    // Read before the integer cast so a legacy empty value remains absent.
                    $nilai = $skor->getAttributes()['nilai'] ?? null;
                    if ($nilai === null || $nilai === '') {
                        continue;
                    }
                    $nilai = (int) $nilai;

                    if ($asesmen->asesmen_type === 'sumatif_lingkup') $lingkup[] = $nilai;
                    if ($asesmen->asesmen_type === 'test') $test = $nilai;
                    if ($asesmen->asesmen_type === 'non_test') $nonTest = $nilai;
                }

                $nilaiByRiwayat[$rk->riwayat_kelas_id][$mapel->intrakurikuler_id]
                    = RaporNilaiCalculator::calculate($lingkup, $test, $nonTest);
            }
        }

        return $nilaiByRiwayat;
    }

    // Generate PDF Buku Induk
    public function cetakBukuInduk(Request $request)
    {
        $request->validate([
            'kelas_ajar_id' => 'required|exists:kelas_ajar,kelas_ajar_id',
            'siswa' => 'array',
            'siswa.*' => 'exists:riwayat_kelas,riwayat_kelas_id',
        ]);

        $sekolah = Sekolah::with('kelurahan.kecamatan.kabupaten')->first();
        $kelasAjar = KelasAjar::with([
            'kelas',
            'tahunAjaran',
            'waliKelas',
            'intrakurikuler.pengampu',
            'intrakurikuler.tujuanPembelajaran',
            'intrakurikuler.asesmenFormatif.details.tujuanPembelajaran',
            'intrakurikuler.asesmenSumatif.skorSiswa',
            'riwayatKelas.siswa.user',
            'riwayatKelas.siswa.siswaEkstrakurikuler.ekstrakurikuler.pembina',
            'riwayatKelas.siswa.siswaEkstrakurikuler.penilaians',
            'riwayatKelas.kehadiranIntrakurikuler',
            'riwayatKelas.skorAsesmen.asesmenSumatif',
            'riwayatKelas.siswa',
        ])->findOrFail($request->kelas_ajar_id);

        $siswaIds = $request->siswa ?? $kelasAjar->riwayatKelas->pluck('riwayat_kelas_id')->toArray();
        $siswaList = $kelasAjar->riwayatKelas->whereIn('riwayat_kelas_id', $siswaIds);

        // Hitung kehadiran per siswa
        foreach ($siswaList as $rk) {
            $kehadiran = [
                'sakit' => 0,
                'izin' => 0,
                'alpha' => 0,
            ];
            foreach ($rk->kehadiranIntrakurikuler as $absen) {
                if ($absen->status === 'sakit') $kehadiran['sakit']++;
                if ($absen->status === 'izin') $kehadiran['izin']++;
                if ($absen->status === 'alpha') $kehadiran['alpha']++;
            }
            $rk->rekap_kehadiran = $kehadiran;
        }

        $namaKelas = $kelasAjar->kelas->nama_kelas;
        $tahunAjaran = str_replace('/', '-', $kelasAjar->tahunAjaran->tahun);
        $semester = $kelasAjar->tahunAjaran->semester;

        $filename = 'Buku Induk tes ' . $namaKelas . ' ' . $tahunAjaran . ' ' . $semester . '.pdf';

        $pdf = Pdf::loadView('dokumen.pdf_buku_induk', [
            'kelasAjar' => $kelasAjar,
            'siswaList' => $siswaList,
            'sekolah' => $sekolah,
        ])->setPaper('a4', 'portrait')
            // ->download($filename);
            ->stream($filename);

        return $pdf;
    }

    // Halaman Cetak Mutasi
    public function mutasi()
    {
        return view('dokumen.mutasi');
    }

    // Generate PDF Mutasi
    public function cetakMutasi(Request $request)
    {
        $request->validate([
            'jenis' => 'required|in:masuk,keluar',
            'jumlah' => 'required|integer|min:1|max:50',
        ]);

        $sekolah = Sekolah::query()->firstOrFail();

        $jenis = $request->jenis;
        $jumlah = (int) $request->jumlah;

        $view = $jenis === 'keluar'
            ? 'dokumen.pdf_mutasi_keluar'
            : 'dokumen.pdf_mutasi_masuk';

        $filename = "Mutasi $jenis.pdf";

        $pdf = Pdf::loadView($view, [
            'jumlah' => $jumlah,
            'sekolah' => $sekolah,
        ])->setPaper('a4', 'portrait')
            // ->download($filename);
            ->stream($filename);

        return $pdf;
    }
}
