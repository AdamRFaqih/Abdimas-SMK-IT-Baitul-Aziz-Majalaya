<?php

namespace Tests\Feature\Dokumen;

use App\Http\Controllers\Dokumen\CetakDokumenController;
use App\Models\RiwayatKelas;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Tests\Fixtures\rapor\RaporFixture;
use Tests\Fixtures\rapor\RaporSqliteFixture;
use Tests\TestCase;

class RaporRenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // No application/user database can be used by these fixtures.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        DB::connection()->beforeExecuting(function () {
            throw new \LogicException('Rapor in-memory fixtures must not query the database.');
        });
    }

    private function data(array $data): array
    {
        $method = new \ReflectionMethod(CetakDokumenController::class, 'nilaiRaporByRiwayat');
        $data['nilaiAkhirByRiwayat'] = $method->invoke(new CetakDokumenController, $data['kelasAjar'], $data['siswaList']);

        return $data;
    }

    public function test_scores_are_scoped_to_student_subject_and_period(): void
    {
        $data = RaporFixture::make(2, 2, false);
        $scores = $data['siswaList'][0]->skorAsesmen;
        // Deliberately misplaced records catch missing scope checks in the real mapper.
        $scores->push(RaporFixture::score(999, 1, 20, 'test', 0));
        $scores->push(RaporFixture::score(1, 999, 20, 'non_test', 0));
        $scores->push(RaporFixture::score(1, 1, 99, 'sumatif_lingkup', 0));
        $scores->push(RaporFixture::score(1, 1, 99, 'test', 0));
        $data['siswaList']->push((new RiwayatKelas)->forceFill(['riwayat_kelas_id' => 999, 'kelas_ajar_id' => 99]));

        $this->assertSame([1 => [1 => 83, 2 => 83], 2 => [1 => 60, 2 => 60]], $this->data($data)['nilaiAkhirByRiwayat']);
    }

    public function test_sas_does_not_change_final_score(): void
    {
        $data = RaporFixture::make(1, 1, false);
        $data['siswaList'][0]->skorAsesmen->push(RaporFixture::score(1, 1, 20, 'sas', 0));

        $this->assertSame(83, $this->data($data)['nilaiAkhirByRiwayat'][1][1]);
    }

    public function test_zero_and_missing_scores_render_without_becoming_each_other(): void
    {
        $data = RaporFixture::make(2, 1, false);
        $data['siswaList'][0]->setRelation('skorAsesmen', new Collection([RaporFixture::score(1, 1, 20, 'test', 0)]));
        $data = $this->data($data);

        $this->assertSame([1 => [1 => 0, 2 => null]], $data['nilaiAkhirByRiwayat']);
        $html = view('dokumen.pdf_rapor', $data)->render();
        $this->assertMatchesRegularExpression('/class="nilai-akhir"[^>]*>\s*0\s*</', $html);
        $this->assertMatchesRegularExpression('/class="nilai-akhir"[^>]*>\s*-\s*</', $html);
    }

    public function test_genap_has_placeholder_and_existing_staff_names(): void
    {
        $html = view('dokumen.pdf_rapor', $this->data(RaporFixture::make(1, 1, false)))->render();

        $this->assertStringContainsString('Kokurikuler', $html);
        $this->assertStringContainsString('Keputusan', $html);
        $this->assertMatchesRegularExpression('/class="keputusan-placeholder"[^>]*>\s*-\s*</', $html);
        $this->assertStringContainsString('Wali Kelas Uji', $html);
        $this->assertStringContainsString('Kepala Sekolah Uji', $html);
        $this->assertStringNotContainsString('Naik ke kelas', $html);
        $this->assertStringNotContainsString('Ekstrakurikuler', $html);
    }

    public function test_ganjil_has_no_promotion_decision(): void
    {
        $html = view('dokumen.pdf_rapor', $this->data(RaporFixture::make(1, 1, false, 'Ganjil')))->render();

        $this->assertStringNotContainsString('Keputusan', $html);
        $this->assertStringNotContainsString('Naik ke kelas', $html);
    }

    public function test_empty_identity_and_subject_data_render_safely(): void
    {
        $data = RaporFixture::make(0, 1, false);
        $data['sekolah'] = null;
        $data['kelasAjar']->setRelation('kelas', null)->setRelation('tahunAjaran', null)->setRelation('waliKelas', null);
        $data['siswaList'][0]->setRelation('siswa', null);
        $html = view('dokumen.pdf_rapor', $this->data($data))->render();

        $this->assertStringContainsString('LAPORAN HASIL BELAJAR', $html);
        $this->assertStringNotContainsString('Kepala Sekolah Uji', $html);
        $this->assertStringContainsString('class="fase">-<', $html);
        $this->assertStringContainsString('class="nama-kepala">-<', $html);
    }

    public function test_phase_uses_the_complete_grade_token(): void
    {
        foreach (['X' => 'E', '10 RPL' => 'E', 'XI RPL' => 'F', '11' => 'F', 'XII-RPL' => 'F', '12 RPL' => 'F', 'Xylophone' => '-', 'Kelas Uji' => '-', '' => '-'] as $grade => $phase) {
            $data = RaporFixture::make(0, 1, false);
            $data['kelasAjar']->kelas->nama_kelas = $grade;
            $html = view('dokumen.pdf_rapor', $this->data($data))->render();
            $this->assertStringContainsString('class="fase">'.$phase.'<', $html, 'Grade: '.$grade);
        }
    }

    public function test_two_students_keep_all_subjects_and_narratives_in_order(): void
    {
        $data = $this->data(RaporFixture::make(13, 2));
        $html = view('dokumen.pdf_rapor', $data)->render();
        $students = preg_split('/<section class="rapor-siswa">/', $html);
        $this->assertCount(3, $students);
        for ($student = 1; $student <= 2; $student++) {
            for ($subject = 1; $subject <= 13; $subject++) {
                $this->assertSame(1, substr_count($students[$student], 'Muatan Uji '.str_pad((string) $subject, 2, '0', STR_PAD_LEFT)));
                $this->assertStringContainsString('AKHIR-TINGGI-'.$student.'-'.$subject, $students[$student]);
                $this->assertStringContainsString('AKHIR-RENDAH-'.$student.'-'.$subject, $students[$student]);
            }
            $this->assertStringNotContainsString('Peserta Uji '.(3 - $student), $students[$student]);
        }
    }

    public function test_dompdf_renders_a4_portrait_with_local_watermark(): void
    {
        $pdf = Pdf::loadView('dokumen.pdf_rapor', $this->data(RaporFixture::make(13, 2)))->setPaper('a4', 'portrait');
        $output = $pdf->output();

        $this->assertStringStartsWith('%PDF-', $output);
        $this->assertSame([], $pdf->getDomPDF()->getOptions()->getAllowedRemoteHosts() ?? []);
        $this->assertFalse($pdf->getDomPDF()->getOptions()->isRemoteEnabled());
        $this->assertEqualsWithDelta(595.28, $pdf->getDomPDF()->getCanvas()->get_width(), 0.1);
        $this->assertEqualsWithDelta(841.89, $pdf->getDomPDF()->getCanvas()->get_height(), 0.1);
        $this->assertGreaterThan(2, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }

    public function test_print_controller_preserves_student_selection_and_only_reads_data(): void
    {
        DB::purge();
        RaporSqliteFixture::install();
        DB::connection()->beforeExecuting(function (string $sql) {
            if (!str_starts_with(strtolower(ltrim($sql)), 'select')) {
                throw new \LogicException('Printing must only read data. SQL: '.$sql);
            }
        });
        $captured = [];
        View::composer('dokumen.pdf_rapor', function ($view) use (&$captured) {
            $captured = $view->getData();
        });

        foreach ([[1, 99], null, []] as $selection) {
            $payload = ['kelas_ajar_id' => 10, 'tanggal_rapor' => '2026-06-24'];
            if ($selection !== null) $payload['siswa'] = $selection;
            $response = (new CetakDokumenController)->cetakRapor(Request::create('/cetak-rapor', 'POST', $payload));
            $expectedIds = $selection === null ? [1, 2] : ($selection === [] ? [] : [1]);

            $this->assertStringStartsWith('%PDF-', $response->getContent());
            $this->assertSame($expectedIds, $captured['siswaList']->pluck('riwayat_kelas_id')->values()->all());
            $this->assertSame($selection === [] ? [] : ($selection === null ? [1 => [1 => 83], 2 => [1 => null]] : [1 => [1 => 83]]), $captured['nilaiAkhirByRiwayat']);
            if ($expectedIds !== []) {
                $this->assertSame(['sakit' => 1, 'izin' => 0, 'alpha' => 0], $captured['siswaList']->first()->rekap_kehadiran);
                $this->assertSame('Kepala Sekolah Uji', $captured['sekolah']->staff->user->name);
            }
        }
        $this->assertSame(1, DB::table('asesmen_sumatif')->where('asesmen_type', 'sas')->count());
        $this->assertSame(5, DB::table('skor_asesmen_siswa')->count());
    }

    public function test_narratives_taller_than_a_page_stay_inside_printable_a4(): void
    {
        $data = RaporFixture::make(13, 1, false);
        $formatif = $data['kelasAjar']->intrakurikuler[0]->asesmenFormatif[0];
        $formatif->deskripsi_catatan_tertinggi = 'MULAI-SUPER '.str_repeat('Peserta menjelaskan konsep dan menerapkan hasil pengamatan secara runtut dengan contoh yang sesuai. ', 34).' SELESAI-SUPER';
        $formatif->deskripsi_catatan_terendah = 'MULAI-SUPER-BIMBINGAN '.str_repeat('Peserta perlu pendampingan dalam menyusun kesimpulan dan mengevaluasi hasil pembelajaran. ', 22).' SELESAI-SUPER-BIMBINGAN';
        $data = $this->data($data);
        $html = view('dokumen.pdf_rapor', $data)->render();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $paragraphs = (new \DOMXPath($document))->query('//p[@class="capaian"]');
        $text = preg_replace('/\s+/u', ' ', implode(' ', array_map(static fn ($paragraph) => $paragraph->textContent, iterator_to_array($paragraphs))));
        $this->assertStringContainsString(preg_replace('/\s+/u', ' ', $formatif->deskripsi_catatan_tertinggi), $text);
        $this->assertStringContainsString(preg_replace('/\s+/u', ' ', $formatif->deskripsi_catatan_terendah), $text);
        $this->assertSame(1, substr_count($html, 'Muatan Uji 01'));

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        $pdf->output();
        $baselines = [];
        // Inspect real CPDF text placement, not extraction alone: text outside the
        // page can still be extracted even though it is invisible when printed.
        foreach ($pdf->getDomPDF()->getCanvas()->get_cpdf()->objects as $object) {
            if ($object['t'] !== 'contents') continue;
            preg_match_all('/\bBT\s+(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+Td\b/', $object['c'], $positions, PREG_SET_ORDER);
            foreach ($positions as $position) $baselines[] = (float) $position[2];
        }
        $this->assertGreaterThan(100, count($baselines), 'Real text commands must be inspected.');
        $this->assertGreaterThanOrEqual(42.0, min($baselines), 'Text crosses the bottom 15mm print margin.');
        $this->assertLessThanOrEqual(800.0, max($baselines), 'Text crosses the top 15mm print margin.');
    }
}
