<?php

namespace App\Http\Controllers\API\Muk;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureMukRole;
use App\Http\Requests\Muk\IndexPenilaianRequest;
use App\Http\Resources\Muk\PenilaianMukCollection;
use App\Http\Resources\Muk\PenilaianMukResource;
use App\Models\Asesi;
use App\Models\Asesor;
use App\Models\JadwalAsesmen;
use App\Models\PenilaianMuk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Daftar & detail penilaian untuk asesi dan asesor. Semua query lewat
 * PenilaianMuk::forUser(), jadi penilaian milik orang lain = 404.
 */
class PenilaianController extends Controller
{
    private const RELASI = [
        'pendaftaran.asesi',
        'pendaftaran.skemaSertifikasi',
        'jadwalAsesmen.asesor',
        'jadwalAsesmen.tuk',
        'dokumenPenilaian.jenisDokumen',
    ];

    public function index(IndexPenilaianRequest $request): PenilaianMukCollection
    {
        $user = $request->user();
        $peran = EnsureMukRole::peranDari($user);

        $query = PenilaianMuk::query()
            ->forUser($user)
            ->when($request->validated('status'), fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($request->validated('kelompok'), fn (Builder $q, string $k) => match ($k) {
                'perlu_tindakan' => $q->perluTindakan($peran),
                'berlangsung' => $q->where('status', PenilaianMuk::STATUS_BERLANGSUNG),
                'selesai' => $q->whereIn('status', PenilaianMuk::STATUS_SELESAI),
            });

        $halaman = $query
            ->with(self::RELASI)
            ->orderByDesc(
                JadwalAsesmen::select('tanggal_asesmen')
                    ->whereColumn('jadwal_asesmens.id', 'penilaian_muks.jadwal_asesmen_id')
            )
            ->orderByDesc('id')
            ->paginate($request->validated('per_page') ?? 15)
            ->withQueryString();

        return new PenilaianMukCollection($halaman, $this->idPerluTindakan($user, $peran, $halaman->pluck('id')->all()));
    }

    public function show(Request $request, int $penilaian): PenilaianMukResource
    {
        $user = $request->user();
        $peran = EnsureMukRole::peranDari($user);

        $model = PenilaianMuk::query()->forUser($user)->with(self::RELASI)->findOrFail($penilaian);

        return (new PenilaianMukResource($model))
            ->tandaiPerluTindakan($this->idPerluTindakan($user, $peran, [$model->id]) !== [])
            ->detailUntuk($peran);
    }

    /** @return array<int, int> */
    private function idPerluTindakan(Asesi|Asesor $user, string $peran, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return PenilaianMuk::query()
            ->forUser($user)
            ->whereKey($ids)
            ->perluTindakan($peran)
            ->pluck('id')
            ->all();
    }
}
