@extends('layouts.master')

@section('title', 'Pilih Cetak Dokumen')

@section('css')
    <link rel="stylesheet" href="/build/css/plugins/style.css" />
@endsection

@section('content')
    <x-breadcrumb item="Dokumen" link="{{ route('dokumen.kelas') }}" active="Pilih Cetak" />

    <div class="card">
        <div class="card-body">

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('warning'))
                <div class="alert alert-warning">{{ session('warning') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <form method="POST" action="{{ route('dokumen.cetak.sampul') }}" id="formCetakDokumen">
                @csrf
                <input type="hidden" name="kelas_ajar_id" value="{{ $kelasAjar->kelas_ajar_id }}">

                <div class="mb-3">
                    <label>Jenis Dokumen</label>
                    <select name="jenis" class="form-select" id="jenisDokumen" required>
                        <option value="sampul" @selected(old('jenis', 'sampul') === 'sampul')>Sampul Rapor</option>
                        <option value="rapor" @selected(old('jenis') === 'rapor')>Rapor</option>
                        <option value="buku_induk" @selected(old('jenis') === 'buku_induk')>Buku Induk</option>
                    </select>
                </div>

                <div class="mb-3" id="tanggalRaporField" @if (old('jenis') !== 'rapor') hidden @endif>
                    <label for="tanggalRapor" class="form-label">Tanggal Rapor</label>
                    <input type="date" name="tanggal_rapor" id="tanggalRapor"
                        class="form-control @error('tanggal_rapor') is-invalid @enderror"
                        value="{{ old('tanggal_rapor', '') }}"
                        @if (old('jenis') === 'rapor') required @else disabled @endif
                        @error('tanggal_rapor') aria-invalid="true" aria-describedby="tanggalRaporError" @enderror>
                    @error('tanggal_rapor')
                        <div class="invalid-feedback" id="tanggalRaporError" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <div class="table-responsive mt-4">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="checkAll" checked>
                                </th>
                                <th>Nama</th>
                                <th>NIS</th>
                                <th>NISN</th>
                                <th>Jenis Kelamin</th>
                                <th>Alamat</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($siswaList as $siswa)
                                <tr>
                                    <td>
                                        <input type="checkbox" name="siswa[]" value="{{ $siswa['riwayat_kelas_id'] }}"
                                            checked>
                                    </td>
                                    <td>{{ $siswa['nama'] }}</td>
                                    <td>{{ $siswa['nis'] }}</td>
                                    <td>{{ $siswa['nisn'] }}</td>
                                    <td>{{ $siswa['jenis_kelamin'] === 'p' ? 'Perempuan' : 'Laki-laki' }}</td>
                                    <td>{{ $siswa['alamat'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="btn btn-success mt-2">Cetak PDF</button>
                <a href="{{ route('dokumen.kelas') }}" class="btn btn-secondary mt-2">Kembali</a>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
    <script type="module">
        import {
            DataTable
        } from '/build/js/plugins/module.js';
        window.dt = new DataTable('#pc-dt-simple');
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Checkbox all
            const checkAll = document.getElementById('checkAll');
            if (checkAll) {
                checkAll.addEventListener('change', function() {
                    document.querySelectorAll('input[name="siswa[]"]').forEach(cb => {
                        cb.checked = checkAll.checked;
                    });
                });
            }

            // Mapping value ke action
            const actionMap = {
                'sampul': "{{ route('dokumen.cetak.sampul') }}",
                'rapor': "{{ route('dokumen.cetak.rapor') }}",
                'buku_induk': "{{ route('dokumen.cetak.buku_induk') }}"
            };

            // Ganti action form sesuai jenis dokumen
            const jenisSelect = document.getElementById('jenisDokumen');
            const form = document.getElementById('formCetakDokumen');
            const tanggalField = document.getElementById('tanggalRaporField');
            const tanggalInput = document.getElementById('tanggalRapor');
            function updateJenisDokumen() {
                form.action = actionMap[jenisSelect.value];
                const isRapor = jenisSelect.value === 'rapor';
                tanggalField.hidden = !isRapor;
                tanggalInput.disabled = !isRapor;
                tanggalInput.required = isRapor;
            }
            jenisSelect.addEventListener('change', updateJenisDokumen);
            updateJenisDokumen();
        });
    </script>
@endsection
