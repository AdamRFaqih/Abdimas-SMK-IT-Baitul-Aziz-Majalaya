<table class="nilai">
    <thead>
        <tr>
            <th style="width: 5%">No</th><th style="width: 27%">Muatan Pelajaran</th>
            <th style="width: 9%">Nilai<br>Akhir</th><th style="width: 59%">Capaian Kompetensi</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($mapels as $mapel)
            @php
                $formatif = $mapel->asesmenFormatif
                    ->where('riwayat_kelas_id', $rk->riwayat_kelas_id)
                    ->where('intrakurikuler_id', $mapel->intrakurikuler_id)
                    ->first();
                $nilaiAkhir = $nilaiAkhirByRiwayat[$rk->riwayat_kelas_id][$mapel->intrakurikuler_id] ?? null;
                $nomorMapel = $loop->iteration;
                $barisCapaian = $capaianRows([
                    $text($formatif?->deskripsi_catatan_tertinggi),
                    $text($formatif?->deskripsi_catatan_terendah),
                ]);
            @endphp
            @foreach ($barisCapaian as $baris)
            <tr>
                <td class="nomor">{{ $nomorMapel }}</td>
                <td>{{ $loop->first ? $text($mapel->nama_pelajaran) : 'Lanjutan capaian' }}</td>
                <td class="nilai-akhir">{{ $loop->first ? ($nilaiAkhir ?? '-') : '' }}</td>
                <td>
                    @foreach ($baris as $paragraph)
                        <p class="capaian">{{ $paragraph }}</p>
                    @endforeach
                </td>
            </tr>
            @endforeach
        @empty
            <tr><td colspan="4" class="nomor">-</td></tr>
        @endforelse
    </tbody>
</table>
