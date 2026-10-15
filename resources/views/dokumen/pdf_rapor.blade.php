<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Hasil Belajar</title>
    <style>
        @page { size: A4 portrait; margin: 15mm; }
        body { margin: 0; font-family: "Times New Roman", Times, serif; font-size: 11pt; line-height: 1.2; color: #000; }
        .watermark { position: fixed; top: 65mm; left: 25mm; width: 130mm; opacity: 0.06; z-index: -1000; }
        .rapor-siswa + .rapor-siswa, .rapor-sit { page-break-before: always; }
        h1 { text-align: center; font-size: 14pt; margin: 0 0 8mm; line-height: 1.4; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .identitas { margin-bottom: 5mm; }
        .identitas td { vertical-align: top; padding: 1pt 2pt; overflow-wrap: break-word; }
        .nilai { margin-bottom: 5mm; }
        .nilai thead { display: table-header-group; }
        .nilai th, .nilai td { border: 0.7pt solid #000; padding: 4pt; overflow-wrap: break-word; }
        .nilai th { text-align: center; }
        .nilai tr { page-break-inside: avoid; }
        .nilai td { vertical-align: middle; }
        .nomor, .nilai-akhir { text-align: center; }
        .capaian { margin: 0; padding: 2pt 0; }
        .capaian + .capaian { border-top: 0.5pt solid #000; margin-top: 4pt; padding-top: 4pt; }
        .penutup { page-break-inside: avoid; }
        .kokurikuler { margin-bottom: 5mm; }
        .kokurikuler { border: 0.7pt solid #000; }
        .kokurikuler h2 { font-size: 11pt; text-align: center; margin: 0; padding: 4pt; border-bottom: 0.7pt solid #000; page-break-after: avoid; }
        .catatan-kokurikuler { margin: 0; padding: 4pt; overflow-wrap: break-word; white-space: pre-wrap; }
        .ketidakhadiran th, .ketidakhadiran td, .keputusan th, .keputusan td { border: 0.7pt solid #000; padding: 4pt; }
        .rekap > tbody > tr > td { vertical-align: top; padding: 0; }
        .ketidakhadiran th, .keputusan th { text-align: center; }
        .jumlah { text-align: center; }
        .keputusan-placeholder { height: 19mm; text-align: center; }
        .tanda-tangan { margin-top: 7mm; page-break-inside: avoid; }
        .tanda-tangan td { text-align: center; vertical-align: top; overflow-wrap: break-word; }
        .ruang-ttd { height: 22mm; }
        .kepala-sekolah { margin-top: 5mm; text-align: center; }
        .nama-kepala { font-weight: bold; }
    </style>
</head>
<body>
    <img class="watermark" src="{{ public_path('images/rapor-logo.png') }}" alt="">
    @php
        // DomPDF cannot split a table row across pages. Keep continuation rows
        // small enough for the 11pt narrative column, even with wide glyphs.
        $capaianRows = static function (array $narasi): array {
            if (mb_strlen(implode(' ', $narasi)) <= 600) {
                return [$narasi];
            }
            $rows = [];
            foreach ($narasi as $paragraph) {
                $remaining = trim(preg_replace('/\s+/u', ' ', $paragraph));
                while (mb_strlen($remaining) > 600) {
                    $boundary = mb_strrpos(mb_substr($remaining, 0, 600), ' ');
                    $length = $boundary !== false && $boundary >= 300 ? $boundary : 600;
                    $rows[] = [mb_substr($remaining, 0, $length)];
                    $remaining = ltrim(mb_substr($remaining, $length));
                }
                if ($remaining !== '') $rows[] = [$remaining];
            }
            return $rows;
        };
    @endphp
    @foreach ($siswaList as $rk)
        @php
            $siswa = $rk->siswa;
            $tahunAjaran = $kelasAjar->tahunAjaran;
            $namaKelas = trim($kelasAjar->kelas?->nama_kelas ?? '');
            $fase = '-';
            if (preg_match('/^(XII|XI|X|12|11|10)(?=$|[\s.\-\/(])/i', $namaKelas, $tingkat)) {
                $fase = in_array(strtoupper($tingkat[1]), ['X', '10'], true) ? 'E' : 'F';
            }
            $text = static fn ($value) => filled($value) ? $value : '-';
            $kepala = $sekolah?->staff;
            $namaKepala = $text($kepala?->user?->name ?: $kepala?->nama);
            $genap = strtolower(trim($tahunAjaran?->semester ?? '')) === 'genap';
        @endphp
        <section class="rapor-siswa">
            <div class="rapor-utama">
            <h1>LAPORAN HASIL BELAJAR<br>(RAPOR)</h1>
            @include('dokumen.rapor.identitas')

            @include('dokumen.rapor.tabel-nilai', ['mapels' => $mapelUtama])

            <div class="kokurikuler">
                <h2>Kokurikuler</h2>
                <p class="catatan-kokurikuler">{{ $rk->kokurikuler }}</p>
            </div>
            <div class="penutup">
                <table class="rekap">
                    <tr>
                        <td style="width: 45%">
                            <table class="ketidakhadiran">
                                <tr><th colspan="2">Ketidakhadiran</th></tr>
                                <tr><td style="width: 65%">Sakit</td><td class="jumlah">{{ $rk->rekap_kehadiran['sakit'] ?? '-' }} hari</td></tr>
                                <tr><td>Izin</td><td class="jumlah">{{ $rk->rekap_kehadiran['izin'] ?? '-' }} hari</td></tr>
                                <tr><td>Tanpa Keterangan</td><td class="jumlah">{{ $rk->rekap_kehadiran['alpha'] ?? '-' }} hari</td></tr>
                            </table>
                        </td>
                        <td style="width: 10%"></td>
                        <td style="width: 45%">
                            @if ($genap)
                                <table class="keputusan">
                                    <tr><th>Keputusan</th></tr>
                                    <tr><td class="keputusan-placeholder">-</td></tr>
                                </table>
                            @endif
                        </td>
                    </tr>
                </table>

                @include('dokumen.rapor.tanda-tangan')
            </div>
            </div>
            @include('dokumen.rapor.sit')
        </section>
    @endforeach
</body>
</html>
