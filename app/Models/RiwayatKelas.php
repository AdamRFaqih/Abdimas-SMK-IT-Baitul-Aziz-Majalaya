<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiwayatKelas extends Model
{
    protected $table = 'riwayat_kelas';
    protected $primaryKey = 'riwayat_kelas_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'siswa_id',
        'kelas_ajar_id',
        'kokurikuler',
        'status_kenaikan_kelas',
        'kelas_tujuan',
    ];

    public function getInformasiKenaikanKelasAttribute(): string
    {
        if ($this->status_kenaikan_kelas === 'tidak_naik') {
            return 'Tidak naik kelas';
        }

        $tujuan = trim($this->kelas_tujuan ?? '');
        if ($this->status_kenaikan_kelas === 'naik' && $tujuan !== '') {
            return 'Naik ke kelas: '.$tujuan;
        }

        return '-';
    }

    /**
     * Catatan kokurikuler siswa (default template jika belum diisi)
     */
    public function getKokurikulerAttribute($value): string
    {
        if (!empty($value)) {
            return $value;
        }

        $namaLengkap = $this->siswa?->user?->name ?? $this->siswa?->nama ?? 'Siswa';
        $namaDepan = explode(' ', trim($namaLengkap))[0] ?? 'Siswa';

        return "Ananda {$namaDepan} cukup Mampu menerapkan perilaku keimanan dan ketakwaan terhadap Tuhan Yang Maha Esa dengan cukup ,serta memiliki kemandirian yang cukup mampu dalam menyelesaikan tugas";
    }

    /**
     * RiwayatKelas milik satu Siswa
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id', 'siswa_id');
    }

    /**
     * RiwayatKelas milik satu KelasAjar
     */
    public function kelasAjar(): BelongsTo
    {
        return $this->belongsTo(KelasAjar::class, 'kelas_ajar_id', 'kelas_ajar_id');
    }

    /**
     * Kehadiran intrakurikuler per siswa per mapel di kelas_ajar ini
     */
    public function kehadiranIntrakurikuler(): HasMany
    {
        return $this->hasMany(KehadiranIntrakurikuler::class, 'riwayat_kelas_id', 'riwayat_kelas_id');
    }

    /**
     * Skor asesmen sumatif per siswa (berdasarkan riwayat_kelas)
     */
    public function skorAsesmen(): HasMany
    {
        return $this->hasMany(SkorAsesmenSiswa::class, 'riwayat_kelas_id', 'riwayat_kelas_id');
    }

    /**
     * Keikutsertaan ekskul siswa pada tahun ajaran/kelas terkait (pivot siswa_ekstrakurikuler)
     */
    public function siswaEkstrakurikuler(): HasMany
    {
        return $this->hasMany(SiswaEkstrakurikuler::class, 'riwayat_kelas_id', 'riwayat_kelas_id');
    }

    public function asesmenFormatif(): HasMany
    {
        return $this->hasMany(AsesmenFormatif::class, 'riwayat_kelas_id', 'riwayat_kelas_id');
    }
}
