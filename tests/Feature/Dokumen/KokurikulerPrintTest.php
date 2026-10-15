<?php

namespace Tests\Feature\Dokumen;

use App\Http\Controllers\Dokumen\CetakDokumenController;
use App\Models\RiwayatKelas;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
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

class KokurikulerPrintTest extends TestCase
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
        // Exercise the actual feature migration; never migrate a user database.
        $migration = database_path('migrations/2026_03_01_000001_add_kokurikuler_to_riwayat_kelas_table.php');
        $this->assertFileExists($migration);
        (require $migration)->up();
        Schema::table('riwayat_kelas', function (Blueprint $table) {
            $table->timestamps();
        });
    }

    private function loginAs(int $id, string $role): void
    {
        $user = (new User)->forceFill(['id' => $id, 'name' => 'Staff Uji']);
        $user->setRelation('roles', new Collection([(new Role)->forceFill(['name' => $role, 'guard_name' => 'web'])]));
        $this->actingAs($user);
    }

    public function test_saved_and_default_kokurikuler_are_rendered(): void
    {
        $this->install();
        $this->withoutMiddleware();
        $this->loginAs(3, 'Wali Kelas');
        $note = 'KOK-SISWA-SATU bekerja sama <b>dengan santun</b> & mandiri.';
        $this->postJson('/dokumen/simpan-kokurikuler', ['riwayat_kelas_id' => 1, 'kokurikuler' => '  '.$note.'  '])
            ->assertOk()->assertJsonPath('kokurikuler', $note);
        $this->assertSame($note, RiwayatKelas::findOrFail(1)->kokurikuler);
        $this->assertNull(DB::table('riwayat_kelas')->where('riwayat_kelas_id', 99)->value('kokurikuler'));

        $before = DB::table('skor_asesmen_siswa')->get()->toJson();
        $captured = [];
        View::composer('dokumen.pdf_rapor', function ($view) use (&$captured) {
            $captured = $view->getData();
        });
        $response = (new CetakDokumenController)->cetakRapor(Request::create('/dokumen/cetak-rapor', 'POST', [
            'kelas_ajar_id' => 10, 'siswa' => [1, 2], 'tanggal_rapor' => '2026-06-24',
        ]));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $html = view('dokumen.pdf_rapor', $captured)->render();
        $sections = preg_split('/<section class="rapor-siswa">/', $html);
        $this->assertStringContainsString(e($note), $sections[1]);
        $this->assertStringNotContainsString('<b>dengan santun</b>', $html);
        $this->assertStringNotContainsString('KOK-SISWA-SATU', $sections[2]);
        $default = 'Ananda Peserta cukup Mampu menerapkan perilaku keimanan dan ketakwaan terhadap Tuhan Yang Maha Esa dengan cukup ,serta memiliki kemandirian yang cukup mampu dalam menyelesaikan tugas';
        $this->assertStringContainsString($default, $sections[2]);
        $this->assertSame([1 => [1 => 83], 2 => [1 => null]], $captured['nilaiAkhirByRiwayat']);
        $this->assertSame(2, substr_count($html, 'Bandung, 24 Juni 2026'));
        $this->assertSame($before, DB::table('skor_asesmen_siswa')->get()->toJson());

        $this->postJson('/dokumen/simpan-kokurikuler', ['riwayat_kelas_id' => 1, 'kokurikuler' => '   '])
            ->assertOk()->assertJsonPath('kokurikuler', $default);
        $this->assertNull(DB::table('riwayat_kelas')->where('riwayat_kelas_id', 1)->value('kokurikuler'));
        $captured['siswaList'] = RiwayatKelas::with('siswa.user')->whereIn('riwayat_kelas_id', [1, 2])->get();
        $this->assertSame(2, substr_count(view('dokumen.pdf_rapor', $captured)->render(), $default));
    }

    public function test_kokurikuler_edit_respects_class_owner_and_length(): void
    {
        $this->install();
        $this->withoutMiddleware();
        $this->loginAs(999, 'Wali Kelas');
        $this->postJson('/dokumen/simpan-kokurikuler', ['riwayat_kelas_id' => 1, 'kokurikuler' => 'Dilarang'])
            ->assertForbidden();
        $this->assertNull(DB::table('riwayat_kelas')->where('riwayat_kelas_id', 1)->value('kokurikuler'));

        $this->loginAs(4, 'Bagian Akademik');
        $note = str_repeat('é', 3000);
        $this->postJson('/dokumen/simpan-kokurikuler', ['riwayat_kelas_id' => 1, 'kokurikuler' => $note])
            ->assertOk();
        $this->postJson('/dokumen/simpan-kokurikuler', ['riwayat_kelas_id' => 1, 'kokurikuler' => $note.'é'])
            ->assertUnprocessable()->assertJsonValidationErrors('kokurikuler')
            ->assertJsonPath('errors.kokurikuler.0', 'Catatan kokurikuler maksimal 3.000 karakter.');
        $this->assertSame($note, DB::table('riwayat_kelas')->where('riwayat_kelas_id', 1)->value('kokurikuler'));
    }

    public function test_long_plain_text_keeps_all_lines_inside_printable_pdf(): void
    {
        $data = RaporFixture::make(5, 2, false);
        $note = "AWAL-KOK\n".str_repeat("Peserta mampu bekerja sama, menyelesaikan tugas dan menjelaskan hasil pengamatan secara mandiri.\n", 13).'AKHIR-KOK';
        $data['siswaList'][0]->kokurikuler = $note;
        $data['siswaList'][1]->kokurikuler = 'CATATAN-SISWA-DUA';
        $data['nilaiAkhirByRiwayat'] = [1 => [1 => 83, 2 => 83], 2 => [1 => 60, 2 => 60]];
        $html = view('dokumen.pdf_rapor', $data)->render();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $sections = (new \DOMXPath($dom))->query('//section');
        $normalize = static fn ($text) => preg_replace('/\s+/u', ' ', trim($text));
        $this->assertStringContainsString($normalize($note), $normalize($sections[0]->textContent));
        $this->assertStringNotContainsString('CATATAN-SISWA-DUA', $sections[0]->textContent);
        $this->assertStringContainsString('CATATAN-SISWA-DUA', $sections[1]->textContent);
        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        $this->assertStringStartsWith('%PDF-', $pdf->output());
        $baselines = [];
        foreach ($pdf->getDomPDF()->getCanvas()->get_cpdf()->objects as $object) {
            if ($object['t'] !== 'contents') continue;
            preg_match_all('/\bBT\s+(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+Td\b/', $object['c'], $positions, PREG_SET_ORDER);
            foreach ($positions as $position) $baselines[] = (float) $position[2];
        }
        $this->assertNotEmpty($baselines);
        $this->assertGreaterThanOrEqual(42.0, min($baselines));
        $this->assertLessThanOrEqual(800.0, max($baselines));
    }
}
