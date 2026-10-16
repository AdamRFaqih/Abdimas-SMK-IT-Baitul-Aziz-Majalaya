<?php

namespace Tests\Feature\Dokumen;

use App\Http\Controllers\Dokumen\CetakDokumenController;
use App\Models\KelasAjar;
use App\Models\RiwayatKelas;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;
use Tests\Fixtures\rapor\RaporFixture;
use Tests\Fixtures\rapor\RaporSqliteFixture;
use Tests\TestCase;

class PromotionDecisionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
    }

    private function install(): void
    {
        RaporSqliteFixture::install();
        $migration = database_path('migrations/2026_10_16_000001_add_kenaikan_kelas_to_riwayat_kelas_table.php');
        $this->assertFileExists($migration);
        (require $migration)->up();
        (require database_path('migrations/2026_03_01_000001_add_kokurikuler_to_riwayat_kelas_table.php'))->up();
        Schema::table('riwayat_kelas', function (Blueprint $table) {
            $table->timestamps();
        });
        DB::table('tahun_ajaran')->insert(['tahun_ajaran_id' => 21, 'tahun' => '2026/2027', 'semester' => 'Ganjil']);
        DB::table('kelas_ajar')->insert(['kelas_ajar_id' => 99, 'kelas_id' => 1, 'tahun_ajaran_id' => 21, 'wali_user_id' => 3]);
        $this->withoutMiddleware();
        $this->loginAs(3, 'Wali Kelas');
    }

    private function loginAs(int $id, string $role): void
    {
        $user = (new User)->forceFill(['id' => $id, 'name' => 'Staff Uji']);
        $user->setRelation('roles', new Collection([(new Role)->forceFill(['name' => $role, 'guard_name' => 'web'])]));
        $this->actingAs($user);
    }

    private function save(int $id, ?string $status, ?string $target = null)
    {
        return $this->postJson('/dokumen/simpan-kenaikan-kelas', [
            'riwayat_kelas_id' => $id, 'status_kenaikan_kelas' => $status, 'kelas_tujuan' => $target,
        ]);
    }

    public function test_genap_decision_is_saved_per_history_and_clears_stale_target(): void
    {
        $this->install();
        $other = DB::table('riwayat_kelas')->where('riwayat_kelas_id', 99)->first();
        $students = DB::table('siswa')->get()->toJson();
        DB::table('tahun_ajaran')->where('tahun_ajaran_id', 20)->update(['semester' => ' GeNaP ']);
        $this->assertSame('-', RiwayatKelas::findOrFail(1)->informasi_kenaikan_kelas);
        $this->save(1, 'naik', '  XI RPL 1  ')->assertOk()->assertJsonPath('status', 'success')
            ->assertJsonPath('kelas_tujuan', 'XI RPL 1')
            ->assertJsonPath('informasi_kenaikan_kelas', 'Naik ke kelas: XI RPL 1');
        $this->assertSame('Naik ke kelas: XI RPL 1', RiwayatKelas::findOrFail(1)->informasi_kenaikan_kelas);
        $this->loginAs(4, 'Bagian Akademik');
        $this->save(2, 'tidak_naik', 'Tujuan lama')->assertOk()->assertJsonPath('kelas_tujuan', null);
        $this->assertSame('Tidak naik kelas', RiwayatKelas::findOrFail(2)->informasi_kenaikan_kelas);
        $this->save(1, 'tidak_naik', 'XI RPL 1')->assertOk()->assertJsonPath('kelas_tujuan', null);
        $this->save(1, 'naik', 'XI RPL 1')->assertOk();
        $this->save(1, '')->assertOk()->assertJsonPath('status_kenaikan_kelas', null)
            ->assertJsonPath('kelas_tujuan', null)->assertJsonPath('informasi_kenaikan_kelas', '-');
        $this->assertNull(RiwayatKelas::findOrFail(1)->kelas_tujuan);
        $this->assertEquals($other, DB::table('riwayat_kelas')->where('riwayat_kelas_id', 99)->first());
        $this->assertSame($students, DB::table('siswa')->get()->toJson());
        $this->assertSame(3, DB::table('riwayat_kelas')->count());
        foreach ([['naik', null], ['naik', '   '], ['invalid', 'XI']] as [$status, $target]) {
            $this->assertSame('-', (new RiwayatKelas(['status_kenaikan_kelas' => $status, 'kelas_tujuan' => $target]))->informasi_kenaikan_kelas);
        }
    }

    public function test_save_decision_rejects_invalid_target_foreign_owner_and_ganjil(): void
    {
        $this->install();
        $before = DB::table('riwayat_kelas')->get()->toJson();
        $this->save(1, 'naik', '   ')->assertUnprocessable()->assertJsonValidationErrors('kelas_tujuan')
            ->assertJsonPath('errors.kelas_tujuan.0', 'Kelas tujuan wajib diisi saat memilih Naik.');
        $this->save(1, 'naik', str_repeat('a', 101))->assertUnprocessable();
        $this->save(1, 'otomatis', 'XI')->assertUnprocessable();
        $this->loginAs(999, 'Wali Kelas');
        $this->save(1, 'naik', 'XI RPL 1')->assertForbidden();
        $this->loginAs(3, 'Guru Mapel');
        $this->save(1, 'naik', 'XI RPL 1')->assertForbidden();
        $this->loginAs(3, 'Wali Kelas');
        $this->save(99, 'naik', 'XI RPL 1')->assertUnprocessable()
            ->assertJsonPath('message', 'Keputusan kenaikan kelas hanya dapat diisi pada semester Genap.');
        $this->assertSame($before, DB::table('riwayat_kelas')->get()->toJson());
    }

    public function test_genap_table_and_pdf_use_decisions_while_ganjil_hides_them(): void
    {
        $this->install();
        $target = 'XI <b>RPL</b> & 1';
        $this->save(1, 'naik', $target)->assertOk();
        $this->save(2, 'tidak_naik')->assertOk();
        DB::table('riwayat_kelas')->where('riwayat_kelas_id', 1)->update(['kokurikuler' => 'KOK-PROMOSI-UJI']);
        $before = DB::table('riwayat_kelas')->get()->toJson();
        $captured = [];
        View::composer('dokumen.pdf_rapor', function ($view) use (&$captured) { $captured = $view->getData(); });
        $response = (new CetakDokumenController)->cetakRapor(Request::create('/dokumen/cetak-rapor', 'POST', [
            'kelas_ajar_id' => 10, 'siswa' => [1, 2], 'tanggal_rapor' => '2026-06-24',
        ]));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $html = view('dokumen.pdf_rapor', $captured)->render();
        $sections = preg_split('/<section class="rapor-siswa">/', $html);
        $this->assertStringContainsString('Berdasarkan pencapaian kompetensi pada semester ke-1 dan ke-2, peserta didik:', $sections[1]);
        $this->assertStringContainsString(e('Naik ke kelas: '.$target), $sections[1]);
        $this->assertStringNotContainsString('<b>RPL</b>', $html);
        $this->assertStringNotContainsString('Tidak naik kelas', $sections[1]);
        $this->assertStringContainsString('Tidak naik kelas', $sections[2]);
        $this->assertStringNotContainsString('Naik ke kelas:', $sections[2]);
        $this->assertStringContainsString('KOK-PROMOSI-UJI', $sections[1]);
        $this->assertSame(2, substr_count($html, 'Bandung, 24 Juni 2026'));
        $this->assertSame([1 => [1 => 83], 2 => [1 => null]], $captured['nilaiAkhirByRiwayat']);
        $this->assertSame($before, DB::table('riwayat_kelas')->get()->toJson());
        $data = RaporFixture::make(1, 1, false);
        $this->assertStringContainsString('class="keputusan-placeholder">-</td>', view('dokumen.pdf_rapor', $data)->render());
        $data['kelasAjar']->tahunAjaran->semester = 'Ganjil';
        $this->assertStringNotContainsString('<table class="keputusan">', view('dokumen.pdf_rapor', $data)->render());

        $rows = RiwayatKelas::with('siswa.user')->whereIn('riwayat_kelas_id', [1, 2])->get()->map(fn ($rk) => [
            'riwayat_kelas_id' => $rk->getKey(), 'nama' => $rk->siswa->nama, 'nis' => '-', 'nisn' => '-',
            'jenis_kelamin' => 'l', 'alamat' => '-', 'kokurikuler' => $rk->kokurikuler, 'kokurikuler_default' => '-',
            'status_kenaikan_kelas' => $rk->status_kenaikan_kelas, 'kelas_tujuan' => $rk->kelas_tujuan,
            'informasi_kenaikan_kelas' => $rk->informasi_kenaikan_kelas,
        ]);
        $render = fn ($id) => view('dokumen.pilih_cetak', [
            'kelasAjar' => KelasAjar::with('tahunAjaran')->findOrFail($id), 'siswaList' => $rows,
            'errors' => new \Illuminate\Support\ViewErrorBag,
        ])->render();
        $ui = $render(10);
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$ui);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(2, $xpath->query('//button[@type="button" and contains(@class,"edit-kenaikan-kelas")]')->length);
        $this->assertSame(1, $xpath->query('//div[@id="modalKenaikanKelas"]')->length);
        $this->assertStringContainsString(e('Naik ke kelas: '.$target), $ui);
        $this->assertStringContainsString('Informasi Kenaikan Kelas', $ui);
        $this->assertStringContainsString('tanggal_rapor', $ui);
        $this->assertStringContainsString('btnEditKokurikuler', $ui);
        $ganjil = $render(99);
        $this->assertStringNotContainsString('id="modalKenaikanKelas"', $ganjil);
        $this->assertStringNotContainsString('Informasi Kenaikan Kelas', $ganjil);
    }
}
