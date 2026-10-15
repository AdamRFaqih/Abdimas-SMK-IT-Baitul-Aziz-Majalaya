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

            <div id="ajaxAlertContainer"></div>

            <form method="POST" action="{{ route('dokumen.cetak.sampul') }}" id="formCetakDokumen">
                @csrf
                <input type="hidden" name="kelas_ajar_id" value="{{ $kelasAjar->kelas_ajar_id }}">

                <div class="mb-3">
                    <label>Jenis Dokumen</label>
                    <select name="jenis" class="form-select" id="jenisDokumen" required>
                        <option value="sampul">Sampul Rapor</option>
                        <option value="rapor">Rapor</option>
                        <option value="buku_induk">Buku Induk</option>
                    </select>
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
                                            data-nama="{{ $siswa['nama'] }}"
                                            data-kokurikuler="{{ $siswa['kokurikuler'] }}"
                                            class="siswa-checkbox"
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
                <button type="button" class="btn btn-primary mt-2" id="btnEditKokurikuler" style="display: none;">
                    <i class="bi bi-pencil-square me-1"></i> Edit Kokurikuler
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Edit Kokurikuler -->
    <div class="modal fade" id="modalKokurikuler" tabindex="-1" aria-labelledby="modalKokurikulerLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalKokurikulerLabel">Edit Kokurikuler</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modalRiwayatKelasId">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="modalInputKokurikuler" class="form-label fw-semibold mb-0">
                                Kokurikuler <span id="modalNamaSiswa" class="text-primary fw-normal small"></span>
                            </label>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="btnResetDefault">
                                <i class="bi bi-arrow-counterclockwise"></i> Reset ke Default
                            </button>
                        </div>
                        <textarea class="form-control" id="modalInputKokurikuler" rows="6" placeholder="Masukkan catatan kokurikuler..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="btnSimpanKokurikuler">Simpan Perubahan</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Alert / Notifikasi -->
    <div class="modal fade" id="modalAlert" tabindex="-1" aria-labelledby="modalAlertLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content text-center p-3">
                <div class="modal-body pb-2">
                    <div class="mb-3">
                        <i id="modalAlertIcon" class="bi bi-exclamation-circle text-warning" style="font-size: 3.25rem;"></i>
                    </div>
                    <h5 class="fw-bold mb-2" id="modalAlertTitle">Pemberitahuan</h5>
                    <p class="text-muted mb-0" id="modalAlertMessage">Silakan pilih salah satu siswa terlebih dahulu.</p>
                </div>
                <div class="modal-footer justify-content-center border-0 pt-2">
                    <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">Mengerti</button>
                </div>
            </div>
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

            // Ganti action form sesuai jenis dokumen & toggle button Edit Kokurikuler
            const jenisSelect = document.getElementById('jenisDokumen');
            const form = document.getElementById('formCetakDokumen');
            const btnEditKokurikuler = document.getElementById('btnEditKokurikuler');

            function updateUIForJenis() {
                form.action = actionMap[jenisSelect.value] || form.action;
                if (btnEditKokurikuler) {
                    btnEditKokurikuler.style.display = (jenisSelect.value === 'rapor') ? 'inline-block' : 'none';
                }
            }

            jenisSelect.addEventListener('change', updateUIForJenis);
            updateUIForJenis();

            // Modal Alert helper
            const modalAlertEl = document.getElementById('modalAlert');
            const modalAlertInstance = modalAlertEl ? new bootstrap.Modal(modalAlertEl) : null;
            const modalAlertTitle = document.getElementById('modalAlertTitle');
            const modalAlertMessage = document.getElementById('modalAlertMessage');
            const modalAlertIcon = document.getElementById('modalAlertIcon');

            function showAlertModal(message, title = 'Pemberitahuan', icon = 'bi-exclamation-circle text-warning') {
                if (modalAlertTitle) modalAlertTitle.textContent = title;
                if (modalAlertMessage) modalAlertMessage.textContent = message;
                if (modalAlertIcon) modalAlertIcon.className = `bi ${icon}`;
                if (modalAlertInstance) {
                    modalAlertInstance.show();
                } else {
                    alert(message);
                }
            }

            // Modal Kokurikuler
            const modalEl = document.getElementById('modalKokurikuler');
            const modalInstance = modalEl ? new bootstrap.Modal(modalEl) : null;
            const modalRiwayatKelasId = document.getElementById('modalRiwayatKelasId');
            const modalNamaSiswa = document.getElementById('modalNamaSiswa');
            const modalInputKokurikuler = document.getElementById('modalInputKokurikuler');
            const btnSimpanKokurikuler = document.getElementById('btnSimpanKokurikuler');
            const ajaxAlertContainer = document.getElementById('ajaxAlertContainer');

            if (btnEditKokurikuler) {
                btnEditKokurikuler.addEventListener('click', function() {
                    const checkedBoxes = Array.from(document.querySelectorAll('input[name="siswa[]"]:checked'));

                    if (checkedBoxes.length === 0) {
                        showAlertModal('Silakan pilih salah satu siswa terlebih dahulu.', 'Pemberitahuan', 'bi-exclamation-circle text-warning');
                        return;
                    }

                    if (checkedBoxes.length > 1) {
                        showAlertModal('Pilih hanya 1 siswa untuk mengedit catatan kokurikuler (saat ini terpilih ' + checkedBoxes.length + ' siswa).', 'Pemberitahuan', 'bi-exclamation-circle text-warning');
                        return;
                    }

                    const targetCb = checkedBoxes[0];
                    const riwayatKelasId = targetCb.value;
                    const nama = targetCb.getAttribute('data-nama') || '';
                    let kokurikuler = targetCb.getAttribute('data-kokurikuler') || '';

                    if (!kokurikuler.trim()) {
                        const namaDepan = nama.trim().split(' ')[0] || 'Siswa';
                        kokurikuler = `Ananda ${namaDepan} cukup Mampu menerapkan perilaku keimanan dan ketakwaan terhadap Tuhan Yang Maha Esa dengan cukup ,serta memiliki kemandirian yang cukup mampu dalam menyelesaikan tugas`;
                    }

                    modalRiwayatKelasId.value = riwayatKelasId;
                    modalNamaSiswa.textContent = nama ? `(${nama})` : '';
                    modalInputKokurikuler.value = kokurikuler;

                    if (modalInstance) {
                        modalInstance.show();
                    }
                });
            }

            const btnResetDefault = document.getElementById('btnResetDefault');
            if (btnResetDefault) {
                btnResetDefault.addEventListener('click', function() {
                    const rawNama = modalNamaSiswa.textContent.replace(/[()]/g, '').trim();
                    const namaDepan = rawNama.split(' ')[0] || 'Siswa';
                    modalInputKokurikuler.value = `Ananda ${namaDepan} cukup Mampu menerapkan perilaku keimanan dan ketakwaan terhadap Tuhan Yang Maha Esa dengan cukup ,serta memiliki kemandirian yang cukup mampu dalam menyelesaikan tugas`;
                });
            }

            if (btnSimpanKokurikuler) {
                btnSimpanKokurikuler.addEventListener('click', async function() {
                    const riwayatKelasId = modalRiwayatKelasId.value;
                    const kokurikulerText = modalInputKokurikuler.value;

                    btnSimpanKokurikuler.disabled = true;
                    btnSimpanKokurikuler.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Menyimpan...';

                    try {
                        const response = await fetch("{{ route('dokumen.simpan.kokurikuler') }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                riwayat_kelas_id: riwayatKelasId,
                                kokurikuler: kokurikulerText
                            })
                        });

                        const result = await response.json();

                        if (response.ok && result.status === 'success') {
                            // Update attribute on checkbox
                            const cb = document.querySelector(`input[name="siswa[]"][value="${riwayatKelasId}"]`);
                            if (cb) {
                                cb.setAttribute('data-kokurikuler', result.kokurikuler ?? '');
                            }

                            if (modalInstance) {
                                modalInstance.hide();
                            }

                            if (ajaxAlertContainer) {
                                ajaxAlertContainer.innerHTML = `
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="bi bi-check-circle-fill me-2"></i> ${result.message || 'Catatan kokurikuler berhasil disimpan.'}
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                `;
                                ajaxAlertContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            }
                        } else {
                            showAlertModal(result.message || 'Gagal menyimpan catatan kokurikuler.', 'Gagal', 'bi-x-circle text-danger');
                        }
                    } catch (err) {
                        console.error(err);
                        showAlertModal('Terjadi kesalahan koneksi saat menyimpan catatan kokurikuler.', 'Kesalahan', 'bi-x-circle text-danger');
                    } finally {
                        btnSimpanKokurikuler.disabled = false;
                        btnSimpanKokurikuler.innerHTML = 'Simpan Perubahan';
                    }
                });
            }
        });
    </script>
@endsection
