<table class="tanda-tangan">
    <tr>
        <td style="width: 40%"><br>Orang Tua,</td>
        <td style="width: 20%"></td>
        <td style="width: 40%">Bandung, {{ $tanggalRapor }}<br>Wali Kelas,</td>
    </tr>
    <tr><td class="ruang-ttd"></td><td></td><td></td></tr>
    <tr><td>.................................................</td><td></td><td>{{ $text($kelasAjar->waliKelas?->name) }}</td></tr>
</table>
<div class="kepala-sekolah">
    Mengetahui,<br>Kepala Sekolah
    <div class="ruang-ttd"></div>
    <span class="nama-kepala">{{ $namaKepala }}</span>
</div>
