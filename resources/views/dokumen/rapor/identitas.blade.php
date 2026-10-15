<table class="identitas">
    <tr>
        <td style="width: 24%">Nama Peserta Didik</td><td style="width: 2%">:</td><td style="width: 35%">{{ $text($siswa?->user?->name ?: $siswa?->nama) }}</td>
        <td style="width: 18%">Kelas</td><td style="width: 2%">:</td><td style="width: 19%">{{ $text($namaKelas) }}</td>
    </tr>
    <tr>
        <td>NISN</td><td>:</td><td>{{ $text($siswa?->nisn) }}</td>
        <td>Fase</td><td>:</td><td class="fase">{{ $fase }}</td>
    </tr>
    <tr>
        <td>Sekolah</td><td>:</td><td>{{ $text($sekolah?->nama_sekolah) }}</td>
        <td>Semester</td><td>:</td><td>{{ $text($tahunAjaran?->semester) }}</td>
    </tr>
    <tr>
        <td>Alamat</td><td>:</td><td>{{ $text($sekolah?->alamat) }}</td>
        <td>Tahun Pelajaran</td><td>:</td><td>{{ $text($tahunAjaran?->tahun) }}</td>
    </tr>
</table>
