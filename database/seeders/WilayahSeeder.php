<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WilayahSeeder extends Seeder
{
    private string $baseUrl = 'https://wilayah.id/api';

    public function run(): void
    {
        // Path ke file wilayah_data.sql
        $sqlFile = database_path('sql/wilayah_data.sql');
        if (file_exists($sqlFile)) {
            $isMysql = DB::connection()->getDriverName() === 'mysql';
            if ($isMysql) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            }

            $sql = file_get_contents($sqlFile);

            // Replace fixed database name with current database name if different
            $currentDb = DB::connection()->getDatabaseName();
            if ($isMysql && $currentDb && $currentDb !== 'abdimas') {
                $sql = str_replace('`abdimas`', "`{$currentDb}`", $sql);
            }

            DB::unprepared($sql);

            if ($isMysql) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
            $this->command?->info('Wilayah data imported from wilayah_data.sql');
        } else {
            $this->command?->error('wilayah_data.sql not found!');
        }
    }

    // public function run(): void
    // {
    //     // Biar tidak berat
    //     DB::disableQueryLog();
    //     @set_time_limit(0);

    //     $now = now();

    //     $this->command?->info('Fetching provinces...');
    //     $provinces = $this->fetchJson("{$this->baseUrl}/provinces.json");

    //     if (!$provinces) {
    //         $this->command?->error('Failed to fetch provinces.');
    //         return;
    //     }

    //     // =========================
    //     // INSERT/UPSERT PROVINSI
    //     // =========================
    //     $provinsiRows = collect($provinces)->map(fn ($p) => [
    //         'provinsi_id' => (string) $p['code'],
    //         'nama'        => $p['name'],
    //         'created_at'  => $now,
    //         'updated_at'  => $now,
    //     ])->all();

    //     DB::table('provinsi')->upsert(
    //         $provinsiRows,
    //         ['provinsi_id'],
    //         ['nama', 'updated_at']
    //     );

    //     // =========================
    //     // LOOP TURUNAN
    //     // =========================
    //     $kabupatenUpserts = 0;
    //     $kecamatanUpserts = 0;
    //     $kelurahanUpserts = 0;

    //     foreach ($provinces as $prov) {
    //         $provId = (string) $prov['code'];
    //         $this->command?->info("Fetching regencies for provinsi {$provId} - {$prov['name']} ...");

    //         $regencies = $this->fetchJson("{$this->baseUrl}/regencies/{$provId}.json");
    //         if (!$regencies) {
    //             $this->command?->warn("Skip provinsi {$provId}, regencies fetch failed.");
    //             continue;
    //         }

    //         // -------------------------
    //         // UPSERT KABUPATEN
    //         // -------------------------
    //         $kabupatenRows = [];
    //         foreach ($regencies as $r) {
    //             $kabupatenRows[] = [
    //                 'kabupaten_id' => (string) $r['code'],
    //                 'provinsi_id'  => $provId,
    //                 'nama'         => $r['name'],
    //                 'created_at'   => $now,
    //                 'updated_at'   => $now,
    //             ];
    //         }

    //         $this->chunkUpsert('kabupaten', $kabupatenRows, ['kabupaten_id'], ['provinsi_id', 'nama', 'updated_at']);
    //         $kabupatenUpserts += count($kabupatenRows);

    //         // -------------------------
    //         // LOOP KECAMATAN & KELURAHAN
    //         // -------------------------
    //         foreach ($regencies as $reg) {
    //             $regId = (string) $reg['code'];
    //             $this->command?->info("  Fetching districts for kabupaten {$regId} - {$reg['name']} ...");

    //             $districts = $this->fetchJson("{$this->baseUrl}/districts/{$regId}.json");
    //             if (!$districts) {
    //                 $this->command?->warn("  Skip kabupaten {$regId}, districts fetch failed.");
    //                 continue;
    //             }

    //             // UPSERT KECAMATAN (per kabupaten)
    //             $kecamatanRows = [];
    //             foreach ($districts as $d) {
    //                 $kecamatanRows[] = [
    //                     'kecamatan_id' => (string) $d['code'],
    //                     'kabupaten_id' => $regId,
    //                     'nama'         => $d['name'],
    //                     'created_at'   => $now,
    //                     'updated_at'   => $now,
    //                 ];
    //             }

    //             $this->chunkUpsert('kecamatan', $kecamatanRows, ['kecamatan_id'], ['kabupaten_id', 'nama', 'updated_at']);
    //             $kecamatanUpserts += count($kecamatanRows);

    //             // LOOP KELURAHAN/DESA (per kecamatan)
    //             foreach ($districts as $dist) {
    //                 $distId = (string) $dist['code'];
    //                 $this->command?->info("    Fetching villages for kecamatan {$distId} - {$dist['name']} ...");

    //                 $villages = $this->fetchJson("{$this->baseUrl}/villages/{$distId}.json");
    //                 if (!$villages) {
    //                     $this->command?->warn("    Skip kecamatan {$distId}, villages fetch failed.");
    //                     continue;
    //                 }

    //                 $kelurahanRows = [];
    //                 foreach ($villages as $v) {
    //                     $kelurahanRows[] = [
    //                         'kelurahan_id' => (string) $v['code'],
    //                         'kecamatan_id' => $distId,
    //                         'nama'         => $v['name'],
    //                         'created_at'   => $now,
    //                         'updated_at'   => $now,
    //                     ];
    //                 }

    //                 $this->chunkUpsert('kelurahan', $kelurahanRows, ['kelurahan_id'], ['kecamatan_id', 'nama', 'updated_at']);
    //                 $kelurahanUpserts += count($kelurahanRows);

    //                 // optional: kecilkan kemungkinan rate-limit
    //                 usleep(50_000); // 50ms
    //             }

    //             usleep(50_000);
    //         }

    //         usleep(80_000);
    //     }

    //     $this->command?->info("Done. Upserted approx:");
    //     $this->command?->info("- Provinsi  : " . count($provinsiRows));
    //     $this->command?->info("- Kabupaten : " . $kabupatenUpserts);
    //     $this->command?->info("- Kecamatan : " . $kecamatanUpserts);
    //     $this->command?->info("- Kelurahan : " . $kelurahanUpserts);
    // }

    // /**
    //  * Fetch API json, return `data` array or null.
    //  */
    // private function fetchJson(string $url): ?array
    // {
    //     try {
    //         $res = Http::retry(3, 500)
    //             ->timeout(60)
    //             ->acceptJson()
    //             ->get($url);

    //         if (!$res->successful()) {
    //             Log::warning("WilayahSeeder HTTP not successful", [
    //                 'url' => $url,
    //                 'status' => $res->status(),
    //                 'body' => $res->body(),
    //             ]);
    //             return null;
    //         }

    //         $json = $res->json();
    //         return $json['data'] ?? null;
    //     } catch (\Throwable $e) {
    //         Log::error("WilayahSeeder exception", [
    //             'url' => $url,
    //             'error' => $e->getMessage(),
    //         ]);
    //         return null;
    //     }
    // }

    // /**
    //  * Upsert in chunks to avoid huge single query / memory usage.
    //  */
    // private function chunkUpsert(string $table, array $rows, array $uniqueBy, array $updateColumns, int $chunkSize = 1000): void
    // {
    //     foreach (array_chunk($rows, $chunkSize) as $chunk) {
    //         DB::table($table)->upsert($chunk, $uniqueBy, $updateColumns);
    //     }
    // }
}
