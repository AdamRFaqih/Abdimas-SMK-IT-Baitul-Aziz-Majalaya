@extends('layouts.master')

@section('title', 'Pilih Cetak Dokumen')

@section('css')
    <link rel="stylesheet" href="/build/css/plugins/style.css" />
    <style>
        #btnEditKokurikuler i {
            font-size: 13px !important;
            vertical-align: -1px;
            line-height: 1;
        }
    </style>
@endsection

@section('content')
    @php($genap = strtolower(trim($kelasAjar->tahunAjaran?->semester ?? '')) === 'genap')
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
                                @if ($genap)
                                    <th class="kolom-kenaikan-kelas" @if (old('jenis') !== 'rapor') hidden @endif>Informasi Kenaikan Kelas</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($siswaList as $siswa)
                                <tr>
                                    <td>
                                        <input type="checkbox" name="siswa[]" value="{{ $siswa['riwayat_kelas_id'] }}"
                                            data-nama="{{ $siswa['nama'] }}"
                                            data-kokurikuler="{{ $siswa['kokurikuler'] }}"
                                            data-kokurikuler-default="{{ $siswa['kokurikuler_default'] }}"
                                            class="siswa-checkbox"
                                            checked>
                                    </td>
                                    <td>{{ $siswa['nama'] }}</td>
                                    <td>{{ $siswa['nis'] }}</td>
                                    <td>{{ $siswa['nisn'] }}</td>
                                    <td>{{ $siswa['jenis_kelamin'] === 'p' ? 'Perempuan' : 'Laki-laki' }}</td>
                                    <td>{{ $siswa['alamat'] }}</td>
                                    @if ($genap)
                                        <td class="kolom-kenaikan-kelas" @if (old('jenis') !== 'rapor') hidden @endif>
                                            <span class="informasi-kenaikan-kelas">{{ $siswa['informasi_kenaikan_kelas'] }}</span>
                                            <button type="button" class="btn btn-sm btn-primary edit-kenaikan-kelas"
                                                data-riwayat-id="{{ $siswa['riwayat_kelas_id'] }}" data-nama="{{ $siswa['nama'] }}"
                                                data-status="{{ $siswa['status_kenaikan_kelas'] }}" data-tujuan="{{ $siswa['kelas_tujuan'] }}"
                                                aria-label="Edit kenaikan kelas {{ $siswa['nama'] }}">Edit</button>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="btn btn-success mt-2">Cetak PDF</button>
                <a href="{{ route('dokumen.kelas') }}" class="btn btn-secondary mt-2">Kembali</a>
                <button type="button" class="btn btn-primary mt-2" id="btnEditKokurikuler" style="display: none;">
                    <i class="bi bi-pencil-square me-1" style="font-size: 13px; vertical-align: -1px; line-height: 1;"></i> Edit Kokurikuler
                </button>
            </form>
        </div>
    </div>

    @if ($genap)
        <div class="modal fade" id="modalKenaikanKelas" tabindex="-1" aria-labelledby="modalKenaikanKelasLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" id="formKenaikanKelas">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalKenaikanKelasLabel">Edit Kenaikan Kelas</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <p id="namaKenaikanKelas"></p>
                        <div class="mb-3">
                            <label for="statusKenaikanKelas" class="form-label">Keputusan</label>
                            <select id="statusKenaikanKelas" class="form-select">
                                <option value="">Belum diisi</option>
                                <option value="naik">Naik</option>
                                <option value="tidak_naik">Tidak naik</option>
                            </select>
                        </div>
                        <div id="kelasTujuanField" hidden>
                            <label for="kelasTujuan" class="form-label">Kelas tujuan</label>
                            <input id="kelasTujuan" type="text" class="form-control" maxlength="100" aria-describedby="kenaikanKelasError">
                        </div>
                        <div class="text-danger mt-2" id="kenaikanKelasError" role="alert"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="btnSimpanKenaikanKelas">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

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
                        <textarea class="form-control" id="modalInputKokurikuler" rows="6" maxlength="3000"
                            aria-describedby="kokurikulerHelp kokurikulerError" placeholder="Masukkan catatan kokurikuler..."></textarea>
                        <div class="form-text" id="kokurikulerHelp">Maksimal 3.000 karakter. Catatan kosong memakai template default.</div>
                        <div class="text-danger mt-2" id="kokurikulerError" role="alert"></div>
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
            const tanggalField = document.getElementById('tanggalRaporField');
            const tanggalInput = document.getElementById('tanggalRapor');

            function updateUIForJenis() {
                form.action = actionMap[jenisSelect.value] || form.action;
                const isRapor = jenisSelect.value === 'rapor';
                tanggalField.hidden = !isRapor;
                tanggalInput.disabled = !isRapor;
                tanggalInput.required = isRapor;
                document.querySelectorAll('.kolom-kenaikan-kelas').forEach(cell => {
                    cell.hidden = !isRapor;
                });
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

            @if ($genap)
                const kenaikanModal = new bootstrap.Modal(document.getElementById('modalKenaikanKelas'));
                const kenaikanForm = document.getElementById('formKenaikanKelas');
                const kenaikanStatus = document.getElementById('statusKenaikanKelas');
                const tujuanInput = document.getElementById('kelasTujuan');
                const kenaikanError = document.getElementById('kenaikanKelasError');
                const simpanKenaikan = document.getElementById('btnSimpanKenaikanKelas');
                let kenaikanTarget = null;

                function updateTujuan() {
                    const naik = kenaikanStatus.value === 'naik';
                    document.getElementById('kelasTujuanField').hidden = !naik;
                    tujuanInput.required = naik;
                    tujuanInput.disabled = !naik;
                    tujuanInput.setCustomValidity('');
                    kenaikanError.textContent = '';
                }
                kenaikanStatus.addEventListener('change', updateTujuan);
                tujuanInput.addEventListener('input', () => tujuanInput.setCustomValidity(''));
                document.querySelectorAll('.edit-kenaikan-kelas').forEach(button => {
                    button.addEventListener('click', () => {
                        kenaikanTarget = button;
                        document.getElementById('namaKenaikanKelas').textContent = button.dataset.nama;
                        kenaikanStatus.value = button.dataset.status || '';
                        tujuanInput.value = button.dataset.tujuan || '';
                        updateTujuan();
                        kenaikanModal.show();
                    });
                });
                kenaikanForm.addEventListener('submit', async event => {
                    event.preventDefault();
                    const button = kenaikanTarget;
                    if (!button || simpanKenaikan.disabled) return;
                    tujuanInput.setCustomValidity(kenaikanStatus.value === 'naik' && !tujuanInput.value.trim()
                        ? 'Kelas tujuan wajib diisi saat memilih Naik.' : '');
                    if (!kenaikanForm.reportValidity()) return;
                    kenaikanError.textContent = '';
                    simpanKenaikan.disabled = true;
                    try {
                        const response = await fetch("{{ route('dokumen.simpan.kenaikan-kelas') }}", {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                            body: JSON.stringify({
                                riwayat_kelas_id: button.dataset.riwayatId,
                                status_kenaikan_kelas: kenaikanStatus.value || null,
                                kelas_tujuan: kenaikanStatus.value === 'naik' ? tujuanInput.value : null
                            })
                        });
                        const result = await response.json();
                        if (!response.ok || result.status !== 'success') {
                            kenaikanError.textContent = Object.values(result.errors || {}).flat()[0]
                                || result.message || 'Gagal menyimpan keputusan kenaikan kelas.';
                            return;
                        }
                        button.dataset.status = result.status_kenaikan_kelas || '';
                        button.dataset.tujuan = result.kelas_tujuan || '';
                        button.parentElement.querySelector('.informasi-kenaikan-kelas').textContent = result.informasi_kenaikan_kelas;
                        kenaikanModal.hide();
                        const notice = document.createElement('div');
                        notice.className = 'alert alert-success';
                        notice.setAttribute('role', 'alert');
                        notice.textContent = result.message;
                        document.getElementById('ajaxAlertContainer').replaceChildren(notice);
                    } catch (error) {
                        kenaikanError.textContent = 'Terjadi kesalahan koneksi saat menyimpan keputusan kenaikan kelas.';
                    } finally {
                        simpanKenaikan.disabled = false;
                    }
                });
            @endif

            // Modal Kokurikuler
            const modalEl = document.getElementById('modalKokurikuler');
            const modalInstance = modalEl ? new bootstrap.Modal(modalEl) : null;
            const modalRiwayatKelasId = document.getElementById('modalRiwayatKelasId');
            const modalNamaSiswa = document.getElementById('modalNamaSiswa');
            const modalInputKokurikuler = document.getElementById('modalInputKokurikuler');
            const btnSimpanKokurikuler = document.getElementById('btnSimpanKokurikuler');
            const ajaxAlertContainer = document.getElementById('ajaxAlertContainer');
            const kokurikulerError = document.getElementById('kokurikulerError');
            let defaultKokurikuler = '';
            let resetToDefault = false;
            modalInputKokurikuler.addEventListener('input', function() {
                resetToDefault = false;
                kokurikulerError.textContent = '';
            });

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
                    defaultKokurikuler = targetCb.getAttribute('data-kokurikuler-default') || '';
                    const kokurikuler = targetCb.getAttribute('data-kokurikuler') || defaultKokurikuler;
                    resetToDefault = false;
                    kokurikulerError.textContent = '';

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
                    modalInputKokurikuler.value = defaultKokurikuler;
                    resetToDefault = true;
                    kokurikulerError.textContent = '';
                });
            }

            if (btnSimpanKokurikuler) {
                btnSimpanKokurikuler.addEventListener('click', async function() {
                    const riwayatKelasId = modalRiwayatKelasId.value;
                    if (!modalInputKokurikuler.reportValidity()) return;
                    const kokurikulerText = resetToDefault ? null : modalInputKokurikuler.value;
                    kokurikulerError.textContent = '';

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
                                const alertEl = document.createElement('div');
                                alertEl.className = 'alert alert-success';
                                alertEl.setAttribute('role', 'alert');
                                alertEl.textContent = result.message || 'Catatan kokurikuler berhasil disimpan.';
                                ajaxAlertContainer.replaceChildren(alertEl);
                                ajaxAlertContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            }
                        } else {
                            kokurikulerError.textContent = result.errors?.kokurikuler?.[0] || result.message || 'Gagal menyimpan catatan kokurikuler.';
                        }
                    } catch (err) {
                        console.error(err);
                        kokurikulerError.textContent = 'Terjadi kesalahan koneksi saat menyimpan catatan kokurikuler.';
                    } finally {
                        btnSimpanKokurikuler.disabled = false;
                        btnSimpanKokurikuler.innerHTML = 'Simpan Perubahan';
                    }
                });
            }
        });
    </script>
@endsection
