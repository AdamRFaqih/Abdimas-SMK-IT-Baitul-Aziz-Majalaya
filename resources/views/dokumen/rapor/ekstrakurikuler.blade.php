@php
    $ekskul = $pesertaEkskul?->ekstrakurikuler;
    // Match the existing assessment screen: the first saved description.
    $deskripsiEkskul = $text($pesertaEkskul?->penilaians->first()?->deskripsi);
    $barisEkskul = $capaianRows([$deskripsiEkskul]);
    $rekapEkskul = $pesertaEkskul?->rekap_kehadiran;
@endphp
<div class="rapor-ekskul">
    <h1>CAPAIAN HASIL BELAJAR EKSTRAKURIKULER</h1>
    @include('dokumen.rapor.identitas')
    <h2>A. EKSKUL PILIHAN : {{ $text($ekskul?->nama_pelajaran) }}</h2>
    <table class="nilai nilai-ekskul">
        <thead>
            <tr>
                <th rowspan="2" style="width: 6%">No</th>
                <th rowspan="2" style="width: 25%">Materi/Aspek Penilaian</th>
                <th colspan="3">Nilai</th>
            </tr>
            <tr>
                <th style="width: 12%">Angka</th>
                <th style="width: 12%">Predikat</th>
                <th style="width: 45%">Deskripsi</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($barisEkskul as $baris)
                <tr>
                    <td class="nomor">{{ $loop->first ? '1' : '' }}</td>
                    <td class="aspek-ekskul">-</td>
                    <td class="angka-ekskul">-</td>
                    <td class="predikat-ekskul">-</td>
                    <td class="deskripsi-ekskul">{{ implode(' ', $baris) }}</td>
                </tr>
            @endforeach
            <tr>
                <th colspan="2">NILAI AKHIR (50-100)</th>
                <td class="akhir-ekskul">-</td><td class="akhir-ekskul">-</td><td class="akhir-ekskul">-</td>
            </tr>
        </tbody>
    </table>
    <div class="penutup">
        <h2>B. KEHADIRAN</h2>
        <table class="rekap">
            <tr>
                <td style="width: 45%">
                    <table class="ketidakhadiran">
                        <tr><th style="width: 10%">No</th><th style="width: 60%">Keterangan</th><th style="width: 30%">Jumlah</th></tr>
                        <tr><td>1</td><td>Sakit</td><td class="jumlah-ekskul">{{ $rekapEkskul['sakit'] ?? '-' }}</td></tr>
                        <tr><td>2</td><td>Izin</td><td class="jumlah-ekskul">{{ $rekapEkskul['izin'] ?? '-' }}</td></tr>
                        <tr><td>3</td><td>Tanpa Keterangan</td><td class="jumlah-ekskul">{{ $rekapEkskul['alpha'] ?? '-' }}</td></tr>
                    </table>
                </td>
                <td style="width: 10%"></td>
                <td style="width: 45%">
                    <table class="ketentuan">
                        <tr><td colspan="2"><strong>Ketentuan Nilai:</strong></td></tr>
                        <tr><td style="width: 35%">86-100</td><td>= A (Sangat Baik)</td></tr>
                        <tr><td>71-85</td><td>= B (Baik)</td></tr>
                        <tr><td>50-70</td><td>= C (Cukup)</td></tr>
                    </table>
                </td>
            </tr>
        </table>
        <table class="tanda-tangan">
            <tr><td colspan="2">Bandung, {{ $tanggalRapor }}</td></tr>
            <tr><td style="width: 50%">Orang Tua/Wali</td><td style="width: 50%">Pelatih</td></tr>
            <tr><td class="ruang-ttd"></td><td class="ruang-ttd"></td></tr>
            <tr><td>(........................................)</td><td class="nama-pelatih">{{ $text($ekskul?->pembina?->name) }}</td></tr>
        </table>
        <div class="kepala-sekolah">
            Mengetahui,<br>Kepala Sekolah
            <div class="ruang-ttd"></div>
            <div class="nama-kepala">{{ $namaKepala }}</div>
        </div>
    </div>
</div>
