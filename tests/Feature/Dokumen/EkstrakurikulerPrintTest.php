<?php

namespace Tests\Feature\Dokumen;

use App\Http\Controllers\Dokumen\CetakDokumenController;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Tests\Fixtures\rapor\RaporSqliteFixture;
use Tests\Fixtures\rapor\RaporFixture;
use Tests\TestCase;

class EkstrakurikulerPrintTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        RaporSqliteFixture::install();
        DB::statement('alter table riwayat_kelas add column kokurikuler text');
        DB::table('riwayat_kelas')->where('riwayat_kelas_id', 1)->update(['kokurikuler' => 'Catatan paket tersimpan']);
        DB::table('ekstrakurikuler')->insert([
            ['ekstrakurikuler_id' => 1, 'tahun_ajaran_id' => 20, 'user_id' => 3, 'nama_pelajaran' => 'Ekskul Aktif Uji'],
            ['ekstrakurikuler_id' => 2, 'tahun_ajaran_id' => 20, 'user_id' => null, 'nama_pelajaran' => 'Ekskul Kosong Uji'],
            ['ekstrakurikuler_id' => 3, 'tahun_ajaran_id' => 99, 'user_id' => 3, 'nama_pelajaran' => 'Ekskul Periode Lain'],
        ]);
        DB::table('siswa_ekstrakurikuler')->insert([
            ['siswa_ekstrakurikuler_id' => 11, 'siswa_id' => 1, 'ekstrakurikuler_id' => 1],
            ['siswa_ekstrakurikuler_id' => 12, 'siswa_id' => 1, 'ekstrakurikuler_id' => 2],
            ['siswa_ekstrakurikuler_id' => 13, 'siswa_id' => 1, 'ekstrakurikuler_id' => 3],
            ['siswa_ekstrakurikuler_id' => 14, 'siswa_id' => 99, 'ekstrakurikuler_id' => 1],
        ]);
        DB::table('penilaian_ekstrakurikuler')->insert([
            ['siswa_ekstrakurikuler_id' => 11, 'deskripsi' => 'AWAL-EKS '.rtrim(str_repeat('Peserta bekerja sama dan berlatih dengan tekun. ', 26)).' AKHIR-EKS <b>mandiri</b>'],
            ['siswa_ekstrakurikuler_id' => 13, 'deskripsi' => 'NARASI-PERIODE-LAIN'],
            ['siswa_ekstrakurikuler_id' => 14, 'deskripsi' => 'NARASI-SISWA-LAIN'],
        ]);
        DB::table('kehadiran_ekstrakurikuler')->insert([
            ['siswa_ekstrakurikuler_id' => 11, 'ekstrakurikuler_id' => 1, 'status' => 'sakit'],
            ['siswa_ekstrakurikuler_id' => 11, 'ekstrakurikuler_id' => 1, 'status' => 'izin'],
            ['siswa_ekstrakurikuler_id' => 11, 'ekstrakurikuler_id' => 1, 'status' => 'izin'],
            ['siswa_ekstrakurikuler_id' => 11, 'ekstrakurikuler_id' => 1, 'status' => 'alpha'],
            // Both keys must match; malformed legacy attendance must not leak.
            ['siswa_ekstrakurikuler_id' => 11, 'ekstrakurikuler_id' => 2, 'status' => 'sakit'],
            ['siswa_ekstrakurikuler_id' => 14, 'ekstrakurikuler_id' => 1, 'status' => 'sakit'],
        ]);
    }

    private function packet(array $sit = []): array
    {
        $captured = [];
        View::composer('dokumen.pdf_rapor', function ($view) use (&$captured) { $captured = $view->getData(); });
        DB::connection()->beforeExecuting(function (string $sql) {
            if (!str_starts_with(strtolower(ltrim($sql)), 'select')) throw new \LogicException('Print wrote academic data');
        });
        $response = (new CetakDokumenController)->cetakRapor(Request::create('/cetak-rapor', 'POST', [
            'kelas_ajar_id' => 10, 'tanggal_rapor' => '2026-06-24', 'siswa' => [1, 2], 'sit_mapel_ids' => $sit,
        ]));
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        return $captured;
    }

    private function xpath(array $data): \DOMXPath
    {
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.view('dokumen.pdf_rapor', $data)->render());

        return new \DOMXPath($document);
    }

    public function test_extracurricular_print_uses_student_and_period(): void
    {
        // Missing student/period or attendance-pair filters must fail this test.
        $data = $this->packet();
        $this->assertArrayHasKey('ekskulByRiwayat', $data);
        $this->assertSame([11, 12], $data['ekskulByRiwayat'][1]->pluck('siswa_ekstrakurikuler_id')->all());
        $this->assertCount(0, $data['ekskulByRiwayat'][2]);
        $xpath = $this->xpath($data);
        $sections = $xpath->query('//div[@class="rapor-ekskul"]');
        $this->assertSame(3, $sections->length);
        foreach ([[1, ['1', '2', '1']], [2, ['0', '0', '0']], [3, ['-', '-', '-']]] as [$section, $expected]) {
            $counts = $xpath->query('(//div[@class="rapor-ekskul"])['.$section.']//td[@class="jumlah-ekskul"]');
            $this->assertSame($expected, array_map(fn ($td) => trim($td->textContent), iterator_to_array($counts)));
        }
        $this->assertStringContainsString('Wali Kelas Uji', $sections->item(0)->textContent);
        $this->assertSame('-', trim($xpath->query('(//div[@class="rapor-ekskul"])[2]//td[@class="nama-pelatih"]')->item(0)->textContent));
        $html = view('dokumen.pdf_rapor', $data)->render();
        $this->assertStringNotContainsString('NARASI-PERIODE-LAIN', $html);
        $this->assertStringNotContainsString('NARASI-SISWA-LAIN', $html);
    }

    public function test_report_packet_order_date_and_placeholders(): void
    {
        // Omitting/reordering sections, fabricating scores or truncating text must fail.
        $data = $this->packet([1]);
        $xpath = $this->xpath($data);
        foreach ([1 => ['rapor-utama', 'rapor-sit', 'rapor-ekskul', 'rapor-ekskul'], 2 => ['rapor-utama', 'rapor-sit', 'rapor-ekskul']] as $student => $classes) {
            $children = $xpath->query('(//section[@class="rapor-siswa"])['.$student.']/div');
            $this->assertSame($classes, array_map(fn ($div) => $div->getAttribute('class'), iterator_to_array($children)));
            $content = $xpath->query('(//section[@class="rapor-siswa"])['.$student.']')->item(0)->textContent;
            $this->assertStringNotContainsString('Peserta Uji '.(3 - $student), $content);
            $this->assertSame(1, substr_count($content, 'Muatan Uji'));
        }
        $html = view('dokumen.pdf_rapor', $data)->render();
        $this->assertSame(7, substr_count($html, 'Bandung, 24 Juni 2026'));
        $this->assertStringContainsString('Catatan paket tersimpan', $html);
        $this->assertStringContainsString('&lt;b&gt;mandiri&lt;/b&gt;', $html);
        $descriptions = $xpath->query('(//div[@class="rapor-ekskul"])[1]//td[@class="deskripsi-ekskul"]');
        $actual = implode(' ', array_map(fn ($td) => trim($td->textContent), iterator_to_array($descriptions)));
        $this->assertSame(DB::table('penilaian_ekstrakurikuler')->where('siswa_ekstrakurikuler_id', 11)->value('deskripsi'), $actual);
        $placeholders = $xpath->query('//div[@class="rapor-ekskul"]//td[@class="aspek-ekskul" or @class="angka-ekskul" or @class="predikat-ekskul" or @class="akhir-ekskul"]');
        $this->assertGreaterThan(12, $placeholders->length);
        foreach ($placeholders as $cell) $this->assertSame('-', trim($cell->textContent));
        $this->assertSame('-', trim($xpath->query('(//div[@class="rapor-ekskul"])[2]//td[@class="deskripsi-ekskul"]')->item(0)->textContent));
    }

    public function test_description_column_keeps_space_under_merged_pdf_header(): void
    {
        // Fixed-layout DomPDF otherwise divides the merged Nilai header equally.
        $data = RaporFixture::make(1, 1, false);
        $data['nilaiAkhirByRiwayat'] = [1 => [1 => 83]];
        $pdf = Pdf::loadView('dokumen.pdf_rapor', $data)->setPaper('a4', 'portrait');
        $pdf->output();
        $widths = [];
        foreach ($pdf->getDomPDF()->getCanvas()->get_cpdf()->objects as $object) {
            if ($object['t'] !== 'contents') continue;
            if (preg_match('/([\d.]+) [\d.]+ m \1 [\d.]+ l S\n([\d.]+) [\d.]+ m \2 [\d.]+ l S\nBT [^\n]*\(Deskripsi\)/', $object['c'], $borders)) {
                $widths[] = (float) $borders[2] - (float) $borders[1];
            }
        }
        $this->assertCount(1, $widths, 'Inspect the rendered description header, not HTML widths.');
        $this->assertGreaterThanOrEqual(200, $widths[0], 'Description needs at least 40% of the printable table width.');
    }
}
