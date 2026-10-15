<?php

namespace Tests\Feature\Dokumen;

use App\Http\Controllers\Dokumen\CetakDokumenController;
use App\Models\KelasAjar;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;
use Tests\Fixtures\rapor\RaporSqliteFixture;
use Tests\TestCase;

class MainReportScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        RaporSqliteFixture::install();
    }

    public function test_main_report_prints_all_subjects_without_additional_sections(): void
    {
        DB::table('intrakurikuler')->insert([
            'intrakurikuler_id' => 2, 'kelas_ajar_id' => 10, 'nama_pelajaran' => 'Muatan SIT Uji',
        ]);
        DB::table('asesmen_formatif')->insert([
            'riwayat_kelas_id' => 1, 'intrakurikuler_id' => 2,
            'deskripsi_catatan_tertinggi' => 'CAPAIAN-MAPEL-UTAMA',
        ]);
        $captured = [];
        View::composer('dokumen.pdf_rapor', function ($view) use (&$captured) {
            $captured = $view->getData();
        });
        $response = (new CetakDokumenController)->cetakRapor(Request::create('/cetak-rapor', 'POST', [
            'kelas_ajar_id' => 10, 'siswa' => [1, 2], 'tanggal_rapor' => '2026-06-24',
            'sit_mapel_ids' => [2], // Old clients must no longer partition subjects.
        ]));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.view('dokumen.pdf_rapor', $captured)->render());
        $xpath = new \DOMXPath($document);
        $sections = $xpath->query('//section[@class="rapor-siswa"]');
        $this->assertCount(2, $sections);
        foreach ($sections as $section) {
            $this->assertSame(1, $xpath->query('.//table[@class="nilai"]', $section)->length);
            foreach (['Muatan Uji', 'Muatan SIT Uji'] as $subject) {
                $this->assertSame(1, $xpath->query('.//table[@class="nilai"]//td[normalize-space(.)="'.$subject.'"]', $section)->length);
            }
            $this->assertSame(1, substr_count($section->textContent, 'LAPORAN HASIL BELAJAR'));
            $this->assertStringNotContainsString('CAPAIAN EKSTRAKURIKULER', strtoupper($section->textContent));
            $this->assertSame(0, $xpath->query('.//div[@class="rapor-sit" or @class="rapor-ekskul"]', $section)->length);
            $this->assertStringContainsString('24 Juni 2026', $section->textContent);
        }
        $this->assertStringContainsString('CAPAIAN-MAPEL-UTAMA', $sections[0]->textContent);
        $this->assertSame([1 => [1 => 83, 2 => null], 2 => [1 => null, 2 => null]], $captured['nilaiAkhirByRiwayat']);
    }

    public function test_print_form_keeps_date_and_kokurikuler_without_sit_picker(): void
    {
        $user = (new User)->forceFill(['id' => 3, 'name' => 'Wali Uji']);
        $user->setRelation('roles', new Collection([(new Role)->forceFill(['name' => 'Wali Kelas', 'guard_name' => 'web'])]));
        $this->actingAs($user);
        $html = view('dokumen.pilih_cetak', [
            'kelasAjar' => KelasAjar::findOrFail(10), 'siswaList' => collect(),
            'errors' => new \Illuminate\Support\ViewErrorBag,
        ])->render();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//input[@name="tanggal_rapor" and @type="date"]')->length);
        $this->assertSame(1, $xpath->query('//button[@id="btnEditKokurikuler"]')->length);
        $this->assertSame(1, $xpath->query('//textarea[@id="modalInputKokurikuler" and @maxlength="3000"]')->length);
        $this->assertSame(1, $xpath->query('//button[@id="btnResetDefault"]')->length);
        $this->assertStringNotContainsString('sit_mapel_ids', $html);
        $this->assertStringNotContainsString('sitMapel', $html);
    }
}
