<?php

use App\Http\Controllers\Absensi\AbsensiControllerEkstrakurikuler;
use App\Http\Controllers\Absensi\AbsensiControllerIntrakurikuler;
use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Akademik\KelasController;
use App\Http\Controllers\Akademik\MasterOrangTuaController;
use App\Http\Controllers\Akademik\MasterSiswaController;
use App\Http\Controllers\Akademik\StaffController;
use App\Http\Controllers\Akademik\TahunAjaranController;
use App\Http\Controllers\Intrakurikuler\AssesmentFormatifController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Dokumen\CetakDokumenController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Ekstrakurikuler\EkstrakurikulerController;
use App\Http\Controllers\Ekstrakurikuler\EkstrakurikulerExcelController;
use App\Http\Controllers\Ekstrakurikuler\EkstrakurikulerSiswaController;
use App\Http\Controllers\Intrakurikuler\IntrakurikulerController;
use App\Http\Controllers\Ekstrakurikuler\PenilaianEkstrakurikulerController;
use App\Http\Controllers\Account\SiswaController;
use App\Http\Controllers\Akademik\SekolahController;
use App\Http\Controllers\Intrakurikuler\AssesmentSumatifController;
use App\Http\Controllers\Intrakurikuler\ExcelController;
use App\Http\Controllers\Intrakurikuler\LingkupMateriController;
use App\Http\Controllers\Intrakurikuler\TujuanPembelajaranController;
use App\Http\Controllers\WilayahController;

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // return view('welcome');
    return redirect('/login');
});

// Auth::routes();
Route::get('login', [AuthController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('login', [AuthController::class, 'login'])->middleware('guest');
Route::post('logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Dashboard
Route::middleware('auth')->group(function () {
    Route::prefix('dashboard')->name('dashboard.')->group(function () {

        Route::middleware('role:Bagian Akademik')->group(function () {
            Route::get('/akademik', [DashboardController::class, 'dashboardAkademik'])->name('akademik');
        });

        Route::middleware('role:Kepala Sekolah')->group(function () {
            Route::get('/kepala-sekolah', [DashboardController::class, 'dashboardKepalaSekolah'])->name('kepalaSekolah');
        });

        Route::middleware('role:Guru Mapel')->group(function () {
            Route::get('/guru-mapel', [DashboardController::class, 'guruMapel'])
                ->name('guruMapel');
        });

        Route::middleware('role:Siswa')->group(function () {
            Route::get('/siswa', [DashboardController::class, 'dashboardSiswa'])->name('siswa');
        });

        Route::middleware('role:Wali Kelas')->group(function () {
            Route::get('/wali-kelas', [DashboardController::class, 'waliKelas'])
                ->name('waliKelas');
        });
    });
});

// Route untuk manajemen user oleh super admin
// Route::prefix('superadmin')->middleware(['auth', 'role:Super Admin'])->name('superadmin.')->group(function () {
//     Route::resource('users', UserController::class);
// });

// Route khusus bagian akademik
Route::middleware(['auth', 'role:Bagian Akademik'])->prefix('akademik')->name('akademik.')->group(function () {
    Route::resource('tahun_ajaran', TahunAjaranController::class);
    Route::resource('kelas', KelasController::class);
    Route::prefix('kelas/{kelas_ajar}')->group(function () {
        Route::resource('siswa', SiswaController::class);
        Route::get('ajax/search-siswa', [SiswaController::class, 'ajaxSearchSiswa'])->name('kelas.ajax.search-siswa');
        Route::post('add-existing-siswa', [SiswaController::class, 'addExistingSiswa'])->name('kelas.add-existing-siswa');
        Route::post('siswa/{siswa}/pindahkan', [SiswaController::class, 'pindahkanSiswa'])->name('kelas.pindahkan-siswa');

        Route::get('load-siswa', [SiswaController::class, 'showLoadSiswaForm'])->name('kelas.show-load-siswa');
        Route::post('load-siswa', [SiswaController::class, 'loadSiswaFromKelas'])->name('kelas.load-siswa');

        // Route::get('ajax/kelas/search', [SiswaController::class, 'ajaxSearchKelas'])->name('ajax.kelas.search');
    });
    Route::get('/akun', [AccountController::class, 'index'])->name('akun.index');
    Route::patch('/akun/{user}/toggle', [AccountController::class, 'toggleActive'])->name('akun.toggle');

    Route::get('/sekolah', [SekolahController::class, 'index'])->name('sekolah.index');
    Route::get('/sekolah/edit', [SekolahController::class, 'edit'])->name('sekolah.edit');
    Route::put('/sekolah', [SekolahController::class, 'update'])->name('sekolah.update');

    Route::get('kelas/ajax/kelas/search', [SiswaController::class, 'ajaxSearchKelas'])->name('ajax.kelas.search');
    Route::resource('staff', StaffController::class);
    
    Route::get('master-siswa/download-template', [MasterSiswaController::class, 'downloadTemplate'])->name('master-siswa.download-template');
    Route::post('master-siswa/preview-import', [MasterSiswaController::class, 'previewImport'])->name('master-siswa.preview-import');
    Route::post('master-siswa/confirm-import', [MasterSiswaController::class, 'confirmImport'])->name('master-siswa.confirm-import');
    Route::resource('master-siswa', MasterSiswaController::class)->parameters(['master-siswa' => 'siswa']);
    Route::resource('master-orang-tua', MasterOrangTuaController::class)->parameters(['master-orang-tua' => 'orangTua'])->except(['create', 'store']);
});

// Define a group of routes with 'auth' middleware applied
Route::middleware(['auth'])->group(function () {
    // Define a GET route for the root URL ('/')
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // intrakurikuler
    Route::resource('intrakurikuler', IntrakurikulerController::class)
        ->middleware('role:Guru Mapel|Bagian Akademik');

    Route::prefix('intrakurikuler/{intrakurikuler}')
        ->middleware('role:Guru Mapel|Bagian Akademik')
        ->group(function () {

            Route::resource('lingkup-materi', LingkupMateriController::class)
                ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

            Route::resource('assesment-sumatif', AssesmentSumatifController::class)
                ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

            Route::get('assesment-sumatif/{riwayatKelas}/detail', [AssesmentSumatifController::class, 'detailAssesmentSumatif'])
                ->name('assesment_sumatif.detail');

            Route::post('assesment-sumatif/{riwayatKelas}/detail', [AssesmentSumatifController::class, 'storeDetailAssesmentSumatif'])
                ->name('assesment_sumatif.detail.store');

            Route::put('assesment-sumatif/{riwayatKelas}/detail', [AssesmentSumatifController::class, 'updateDetailAssesmentSumatif'])
                ->name('assesment_sumatif.detail.update');

            Route::resource('tujuan-pembelajaran', TujuanPembelajaranController::class)
                ->except(['show']);

            Route::resource('assesment-formatif', AssesmentFormatifController::class);

            Route::get('/template-asesmen-formatif', [ExcelController::class, 'downloadFormatif'])
                ->name('templateFormatif');
            Route::post('/assesmen-formatif/import', [AssesmentFormatifController::class, 'importExcel'])
                ->name('assesment-formatif.import');

            Route::get('template-assesmen-sumatif-excel', [ExcelController::class, 'downloadSumatifTemplate'])
                ->name('assesment_sumatif.template');
            Route::post('/assesmen-sumatif/import', [AssesmentSumatifController::class, 'importExcelSumatif'])
                ->name('assesment_sumatif.import');

            Route::get('template-tp-lm-excel', [ExcelController::class, 'downloadInputTpLmTemplate'])
                ->name('template_tp_lm.template');
            Route::post('import-tp-lm', [ExcelController::class, 'importInputTpLm'])
                ->name('template_tp_lm.import');

            Route::get('assesment-formatif/{riwayatKelas}/detail', [AssesmentFormatifController::class, 'detailAssesmentFormatif'])
                ->name('assesment-formatif.detail');
            Route::post('assesment-formatif/{riwayatKelas}/save-detail', [AssesmentFormatifController::class, 'saveDetail'])
                ->name('assesment-formatif.save-detail');
        });


    // Ekstrakurikuler
    Route::resource('ekstrakurikuler', EkstrakurikulerController::class)->middleware('role:Guru Mapel|Bagian Akademik');
    Route::prefix('ekstrakurikuler/{ekstrakurikuler}')->group(function () {
        Route::get('manage-siswa/create', [EkstrakurikulerSiswaController::class, 'create'])
            ->name('ekstrakurikuler.manage-siswa.create');
        Route::post('manage-siswa', [EkstrakurikulerSiswaController::class, 'store'])
            ->name('ekstrakurikuler.manage-siswa.store');
        Route::post('manage-siswa/add-existing', [EkstrakurikulerSiswaController::class, 'addExistingSiswa'])
            ->name('ekstrakurikuler.manage-siswa.add-existing');

        Route::get('manage-siswa/load-siswa', [EkstrakurikulerSiswaController::class, 'showLoadSiswaForm'])
            ->name('ekstrakurikuler.manage-siswa.show-load-siswa');
        Route::post('manage-siswa/load-siswa', [EkstrakurikulerSiswaController::class, 'loadSiswaFromKelas'])
            ->name('ekstrakurikuler.manage-siswa.load-siswa');

        Route::resource('manage-siswa', EkstrakurikulerSiswaController::class)
            ->only(['index', 'destroy'])
            ->names('ekstrakurikuler.manage-siswa');

        Route::get('ajax/search-siswa', [EkstrakurikulerSiswaController::class, 'ajaxSearchSiswa'])
            ->name('ekstrakurikuler.ajax.search-siswa');
        Route::get('ajax/kelas/search', [EkstrakurikulerSiswaController::class, 'ajaxSearchKelas'])
            ->name('ekstrakurikuler.ajax.kelas.search');

        Route::resource('penilaian_ekstrakurikuler', PenilaianEkstrakurikulerController::class);

        Route::get('penilaian-template-excel', [EkstrakurikulerExcelController::class, 'downloadTemplate'])
            ->name('ekstrakurikuler.penilaian.template-excel');
        Route::post('penilaian-import-excel', [EkstrakurikulerExcelController::class, 'importExcel'])
            ->name('ekstrakurikuler.penilaian.import-excel');
    })->middleware('role:Guru Mapel|Bagian Akademik');


    // Absensi
    Route::prefix('absensi')->name('absensi.')->group(function () {
        Route::get('intrakurikuler', [AbsensiControllerIntrakurikuler::class, 'listIntrakurikuler'])
            ->name('intrakurikuler.list')
            ->middleware('role:Guru Mapel|Bagian Akademik|Super Admin');

        Route::get('intrakurikuler/{intrakurikuler}/harian', [AbsensiControllerIntrakurikuler::class, 'harian'])
            ->name('intrakurikuler.harian');

        Route::post('intrakurikuler/{intrakurikuler}/harian', [AbsensiControllerIntrakurikuler::class, 'storeHarian'])
            ->name('intrakurikuler.harian.store');

        Route::get('intrakurikuler/{intrakurikuler}/rekap', [AbsensiControllerIntrakurikuler::class, 'rekap'])
            ->name('intrakurikuler.rekap');

        // list ekstra
        Route::get('list-ekstrakurikuler', [AbsensiControllerEkstrakurikuler::class, 'listEkstrakurikuler'])
            ->name('ekstrakurikuler.list');

        // harian
        Route::get('ekstrakurikuler/{ekstrakurikuler}/harian', [AbsensiControllerEkstrakurikuler::class, 'harian'])
            ->name('ekstrakurikuler.harian');

        Route::post('ekstrakurikuler/{ekstrakurikuler}/harian', [AbsensiControllerEkstrakurikuler::class, 'storeHarian'])
            ->name('ekstrakurikuler.harian.store');

        // rekap
        Route::get('ekstrakurikuler/{ekstrakurikuler}/rekap', [AbsensiControllerEkstrakurikuler::class, 'rekap'])
            ->name('ekstrakurikuler.rekap');
    })->middleware('role:Guru Mapel|Bagian Akademik|Super Admin');


    // Dokumen
    Route::prefix('dokumen')->name('dokumen.')->group(function () {
        Route::get('kelas', [CetakDokumenController::class, 'kelas'])->name('kelas');
        Route::get('kelas/{kelas_ajar}/pilih', [CetakDokumenController::class, 'pilihCetak'])->name('kelas.pilih');
        Route::post('cetak-sampul', [CetakDokumenController::class, 'cetakSampul'])->name('cetak.sampul');
        Route::post('cetak-rapor', [CetakDokumenController::class, 'cetakRapor'])->name('cetak.rapor');
        Route::post('simpan-kokurikuler', [CetakDokumenController::class, 'simpanKokurikuler'])->name('simpan.kokurikuler');
        Route::post('cetak-buku-induk', [CetakDokumenController::class, 'cetakBukuInduk'])->name('cetak.buku_induk');
        Route::get('mutasi', [CetakDokumenController::class, 'mutasi'])->name('mutasi');
        Route::post('cetak-mutasi', [CetakDokumenController::class, 'cetakMutasi'])->name('cetak.mutasi');
    });
});

// Route::get('/wilayah/provinsi', [WilayahController::class, 'provinsi']);
// Route::get('/wilayah/kabupaten/{provinsi_id}', [WilayahController::class, 'kabupaten']);
// Route::get('/wilayah/kecamatan/{kabupaten_id}', [WilayahController::class, 'kecamatan']);
// Route::get('/wilayah/kelurahan/{kecamatan_id}', [WilayahController::class, 'kelurahan']);
Route::get('ajax/tempat-lahir/kabupaten', [WilayahController::class, 'searchKabupaten'])->name('ajax.tempat_lahir.kabupaten');
Route::get('ajax/domisili/kelurahan', [WilayahController::class, 'searchKelurahan'])->name('ajax.domisili.kelurahan');

Route::get('/ajax/orang-tua', [SiswaController::class, 'ajaxSearchOrangTua'])
    ->name('ajax.orang_tua');
