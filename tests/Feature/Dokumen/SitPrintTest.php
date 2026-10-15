<?php

namespace Tests\Feature\Dokumen;

use App\Http\Controllers\Dokumen\CetakDokumenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Tests\Fixtures\rapor\RaporSqliteFixture;
use Tests\TestCase;

class SitPrintTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        RaporSqliteFixture::install();
        DB::table('intrakurikuler')->insert([
            ['intrakurikuler_id' => 2, 'kelas_ajar_id' => 10, 'nama_pelajaran' => 'Muatan Pilihan Uji'],
            ['intrakurikuler_id' => 3, 'kelas_ajar_id' => 10, 'nama_pelajaran' => 'Muatan Kosong Uji'],
            ['intrakurikuler_id' => 99, 'kelas_ajar_id' => 99, 'nama_pelajaran' => 'Muatan Kelas Lain'],
        ]);
        DB::table('asesmen_sumatif')->insert(['asesmen_sumatif_id' => 6, 'intrakurikuler_id' => 2, 'tahun_ajaran_id' => 20, 'asesmen_type' => 'test']);
        DB::table('skor_asesmen_siswa')->insert(['riwayat_kelas_id' => 1, 'asesmen_sumatif_id' => 6, 'tahun_ajaran_id' => 20, 'nilai' => 0]);
        DB::table('asesmen_formatif')->insert([
            'riwayat_kelas_id' => 1, 'intrakurikuler_id' => 2,
            'deskripsi_catatan_tertinggi' => 'AWAL-SIT '.str_repeat('Peserta memahami materi dan menerapkan pembelajaran dengan runtut. ', 18).' AKHIR-SIT',
            'deskripsi_catatan_terendah' => 'BIMBINGAN-SIT <b>latihan mandiri</b>',
        ]);
    }

    private function printData(array $selection = []): array
    {
        $captured = [];
        View::composer('dokumen.pdf_rapor', function ($view) use (&$captured) {
            $captured = $view->getData();
        });
        $response = (new CetakDokumenController)->cetakRapor(Request::create('/cetak-rapor', 'POST', [
            'kelas_ajar_id' => 10, 'siswa' => [1, 2], 'tanggal_rapor' => '2026-06-24',
            'sit_mapel_ids' => $selection,
        ]));
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        return $captured;
    }

    private function sections(array $data): \DOMXPath
    {
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.view('dokumen.pdf_rapor', $data)->render());

        return new \DOMXPath($document);
    }

    public function test_selected_sit_subjects_partition_without_loss(): void
    {
        $before = $this->printData();
        // Request order must not change the class's subject order.
        $after = $this->printData([3, 2]);
        $this->assertArrayHasKey('mapelUtama', $after);
        $this->assertSame([1], $after['mapelUtama']->pluck('intrakurikuler_id')->values()->all());
        $this->assertSame([2, 3], $after['mapelSit']->pluck('intrakurikuler_id')->values()->all());
        $this->assertSame([1 => [1 => 83, 2 => 0, 3 => null], 2 => [1 => null, 2 => null, 3 => null]], $after['nilaiAkhirByRiwayat']);
        $this->assertSame($before['nilaiAkhirByRiwayat'], $after['nilaiAkhirByRiwayat']);
        $xpath = $this->sections($after);
        $this->assertSame(2, $xpath->query('//section[@class="rapor-siswa"]/div[@class="rapor-utama"]/following-sibling::div[@class="rapor-sit"]')->length);
        foreach ([1, 2] as $student) {
            $main = $xpath->query('(//div[@class="rapor-utama"])['.$student.']')->item(0)->textContent;
            $sit = $xpath->query('(//div[@class="rapor-sit"])['.$student.']')->item(0)->textContent;
            $this->assertStringNotContainsString('Muatan Pilihan Uji', $main);
            $this->assertStringContainsString('Muatan Uji', $main);
            $this->assertStringNotContainsString('Muatan Uji', $sit);
            $this->assertStringContainsString('Muatan Pilihan Uji', $sit);
            $this->assertStringContainsString('Peserta Uji '.$student, $sit);
            $this->assertStringNotContainsString('Peserta Uji '.(3 - $student), $sit);
            $this->assertSame('1', trim($xpath->query('(//div[@class="rapor-sit"])['.$student.']//tbody/tr/td[@class="nomor"]')->item(0)->textContent));
            $score = $xpath->query('(//div[@class="rapor-sit"])['.$student.']//td[@class="nilai-akhir"]')->item(0)->textContent;
            $this->assertSame($student === 1 ? '0' : '-', trim($score));
        }
        $beforeXpath = $this->sections($before);
        $this->assertSame(
            array_map(fn ($p) => $p->textContent, iterator_to_array($beforeXpath->query('//p[@class="capaian"]'))),
            array_map(fn ($p) => $p->textContent, iterator_to_array($xpath->query('//p[@class="capaian"]'))),
        );
        $html = view('dokumen.pdf_rapor', $after)->render();
        $this->assertSame(6, substr_count($html, 'Bandung, 24 Juni 2026'));
        $this->assertStringContainsString('&lt;b&gt;latihan mandiri&lt;/b&gt;', $html);
        $this->assertStringContainsString('AKHIR-SIT', $html);
    }

    public function test_empty_sit_selection_and_foreign_subject(): void
    {
        $data = $this->printData();
        $this->assertArrayHasKey('mapelUtama', $data);
        $this->assertSame([1, 2, 3], $data['mapelUtama']->pluck('intrakurikuler_id')->values()->all());
        $this->assertCount(0, $data['mapelSit']);
        $xpath = $this->sections($data);
        $this->assertSame(2, $xpath->query('//div[@class="rapor-sit"]//tbody/tr/td[@colspan="4" and normalize-space(.)="-"]')->length);
        $this->withoutMiddleware();
        foreach ([[99], [2, 2]] as $invalid) {
            $this->postJson(route('dokumen.cetak.rapor'), [
                'kelas_ajar_id' => 10, 'tanggal_rapor' => '2026-06-24', 'sit_mapel_ids' => $invalid,
            ])->assertUnprocessable()->assertJsonValidationErrors('sit_mapel_ids.0');
        }
    }
}
