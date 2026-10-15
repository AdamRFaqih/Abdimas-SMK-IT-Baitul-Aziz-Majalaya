<?php

namespace Tests\Feature\Dokumen;

use App\Http\Controllers\Dokumen\CetakDokumenController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Tests\Fixtures\rapor\RaporSqliteFixture;
use Tests\TestCase;

class ReportDateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        RaporSqliteFixture::install();
    }

    public function test_selected_report_date_is_used_for_print(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 10:00:00'));
        $captured = [];
        View::composer('dokumen.pdf_rapor', function ($view) use (&$captured) {
            $captured = $view->getData();
        });

        $response = (new CetakDokumenController)->cetakRapor(Request::create('/cetak-rapor', 'POST', [
            'kelas_ajar_id' => 10,
            'siswa' => [1, 2],
            'tanggal_rapor' => '2026-06-24',
        ]));

        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertSame('24 Juni 2026', $captured['tanggalRapor'] ?? null);
        $html = view('dokumen.pdf_rapor', $captured)->render();
        // Main and SIT each print the same chosen date for both students.
        $this->assertSame(4, substr_count($html, 'Bandung, 24 Juni 2026'));
        $this->assertStringNotContainsString('15 Oktober 2026', $html);
    }

    public function test_report_date_is_required_and_valid(): void
    {
        $this->withoutMiddleware();
        foreach ([null, '2026-02-30'] as $date) {
            $this->from('/dokumen/kelas/10/pilih')
                ->post(route('dokumen.cetak.rapor'), [
                    'kelas_ajar_id' => 10,
                    'siswa' => [1],
                    'tanggal_rapor' => $date,
                ])
                ->assertRedirect('/dokumen/kelas/10/pilih')
                ->assertSessionHasErrors('tanggal_rapor');
        }
    }
}
