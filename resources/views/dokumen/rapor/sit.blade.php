<div class="rapor-sit">
    <h1>LAPORAN HASIL BELAJAR<br>(RAPOR MUATAN SEKOLAH ISLAM TERPADU)</h1>
    @include('dokumen.rapor.identitas')
    @include('dokumen.rapor.tabel-nilai', ['mapels' => $mapelSit])
    <div class="penutup">
        @include('dokumen.rapor.tanda-tangan')
    </div>
</div>
