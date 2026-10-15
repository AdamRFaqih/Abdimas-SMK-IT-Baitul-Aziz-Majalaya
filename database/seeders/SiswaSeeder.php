<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Siswa;
use App\Models\OrangTua;
use App\Models\KelasAjar;
use App\Models\TahunAjaran;
use App\Models\Kelas;
use App\Models\RiwayatKelas;
use App\Models\Kelurahan;
use App\Models\Kecamatan;
use App\Models\Kabupaten;
use App\Models\Provinsi;

class SiswaSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // Cari kelas ajar X RPL 1, tahun ajaran 2025/2026, semester Ganjil
            $tahunAjaran = TahunAjaran::where('tahun', '2025/2026')->where('semester', 'Ganjil')->first();
            $kelas = Kelas::where('nama_kelas', 'X RPL 1')->first();
            $kelasAjar = KelasAjar::where('kelas_id', $kelas?->kelas_id)
                ->where('tahun_ajaran_id', $tahunAjaran?->tahun_ajaran_id)
                ->first();

            if (!$kelasAjar) {
                throw new \Exception('Kelas ajar X RPL 1 2025/2026 Ganjil tidak ditemukan. Pastikan sudah menjalankan KelasAjarSeeder.');
            }

            // Ambil data wilayah yang valid dan berelasi
            $provinsi = Provinsi::first();
            $kabupaten = $provinsi ? $provinsi->kabupaten()->first() : null;
            $kecamatan = $kabupaten ? $kabupaten->kecamatan()->first() : null;
            $kelurahan = $kecamatan ? $kecamatan->kelurahan()->first() : null;

            if (!$provinsi || !$kabupaten || !$kecamatan || !$kelurahan) {
                throw new \Exception('Data wilayah tidak lengkap. Pastikan sudah menjalankan WilayahSeeder.');
            }

            // Data siswa dummy
            $jumlahSiswa = 20;
            for ($i = 1; $i <= $jumlahSiswa; $i++) {
                $nis = '25RPL1' . str_pad($i, 3, '0', STR_PAD_LEFT);
                $nisn = '10025' . str_pad($i, 5, '0', STR_PAD_LEFT);
                $nama = ($i === 1) ? 'Anton Susilo' : 'Siswa RPL ' . $i;
                $jenis_kelamin = ($i === 1) ? 'l' : ($i % 2 === 0 ? 'l' : 'p');
                $tanggal_lahir = '2010-' . str_pad(($i % 12) + 1, 2, '0', STR_PAD_LEFT) . '-' . (($i % 28) + 1);
                $agama = ($i === 1) ? 'Islam' : ($i % 3 === 0 ? 'Kristen' : ($i % 2 === 0 ? 'Islam' : 'Hindu'));
                $pendidikan_sebelumnya = ($i === 1) ? 'SMP Negeri 1 Majalaya' : 'SDN ' . $i . ' Contoh';
                $alamat = ($i === 1) ? 'Jl. Raya Majalaya No. 12' : 'Jl. Siswa RPL ' . $i;

                // Ortu
                $ortu_nama_ayah = ($i === 1) ? 'Budi Susilo' : 'Ayah RPL ' . $i;
                $ortu_nama_ibu = ($i === 1) ? 'Siti Rahma' : 'Ibu RPL ' . $i;
                $ortu_pekerjaan_ayah = $i % 2 === 0 ? 'Guru' : 'Petani';
                $ortu_pekerjaan_ibu = $i % 2 === 0 ? 'Ibu Rumah Tangga' : 'Pedagang';
                $ortu_jalan = ($i === 1) ? 'Jl. Raya Majalaya No. 12' : 'Jl. Ortu RPL ' . $i;

                // 1. Buat user siswa
                $userSiswa = User::firstOrCreate([
                    'username' => $nis,
                ], [
                    'name' => $nama,
                    'email' => null,
                    'password' => Hash::make('password123'),
                ]);
                $userSiswa->update(['name' => $nama]);
                $userSiswa->assignRole('Siswa');

                // 2. Buat user ortu
                $ortuUsername = 'ortu_' . $nis;
                $suffix = 1;
                $base = $ortuUsername;
                while (User::where('username', $ortuUsername)->exists()) {
                    $ortuUsername = $base . '_' . $suffix;
                    $suffix++;
                }
                $userOrtu = User::firstOrCreate([
                    'username' => $ortuUsername,
                ], [
                    'name' => $ortu_nama_ayah,
                    'email' => null,
                    'password' => Hash::make('password123'),
                ]);
                $userOrtu->assignRole('Orang Tua');

                // 3. Buat data ortu
                $orangTua = OrangTua::firstOrCreate([
                    'user_id' => $userOrtu->id,
                    'nama_ayah' => $ortu_nama_ayah,
                    'nama_ibu' => $ortu_nama_ibu,
                ], [
                    'pekerjaan_ayah' => $ortu_pekerjaan_ayah,
                    'pekerjaan_ibu' => $ortu_pekerjaan_ibu,
                    'jalan' => $ortu_jalan,
                    'kelurahan_id' => $kelurahan->kelurahan_id,
                ]);

                // 4. Buat siswa
                $siswa = Siswa::firstOrCreate([
                    'user_id' => $userSiswa->id,
                    'nis' => $nis,
                ], [
                    'nisn' => $nisn,
                    'nama' => $nama,
                    'jenis_kelamin' => $jenis_kelamin,
                    'tempat_lahir_kabupaten_id' => $kabupaten->kabupaten_id,
                    'tanggal_lahir' => $tanggal_lahir,
                    'agama' => $agama,
                    'pendidikan_sebelumnya' => $pendidikan_sebelumnya,
                    'alamat' => $alamat,
                    'orang_tua_id' => $orangTua->orang_tua_id,
                    'kelurahan_id' => $kelurahan->kelurahan_id,
                ]);
                $siswa->update([
                    'nama' => $nama,
                    'jenis_kelamin' => $jenis_kelamin,
                    'alamat' => $alamat,
                ]);

                // 5. Masukkan ke kelas ajar (pastikan 1 siswa hanya 1 kelas per tahun ajaran)
                $tahunAjaranId = $kelasAjar->tahun_ajaran_id;
                $sudahAda = RiwayatKelas::where('siswa_id', $siswa->siswa_id)
                    ->whereHas('kelasAjar', function ($q) use ($tahunAjaranId) {
                        $q->where('tahun_ajaran_id', $tahunAjaranId);
                    })->exists();

                if (!$sudahAda) {
                    RiwayatKelas::firstOrCreate([
                        'siswa_id' => $siswa->siswa_id,
                        'kelas_ajar_id' => $kelasAjar->kelas_ajar_id,
                    ]);
                }
            }
        });
    }
}
