<?php

namespace App\Services\Muk\Dokumen;

use Illuminate\Contracts\Container\Container;

/**
 * Peta slug URL ("apl-01") -> handler. Slug yang tidak terdaftar = 404,
 * sehingga dokumen yang belum diimplementasikan tidak bisa diakses lewat API.
 */
class DokumenHandlerRegistry
{
    /** @var array<string, class-string<DokumenHandler>> */
    private array $handlers;

    public function __construct(private Container $container)
    {
        $this->handlers = config('muk.dokumen_handlers', []);
    }

    public function untukSlug(string $slug): ?DokumenHandler
    {
        $class = $this->handlers[$slug] ?? null;

        return $class ? $this->container->make($class) : null;
    }

    public function untukKode(string $kode): ?DokumenHandler
    {
        return $this->untukSlug(self::slugDariKode($kode));
    }

    /** "FR.APL.01" -> "apl-01" (sama dengan JenisDokumenMuk::slug). */
    public static function slugDariKode(string $kode): string
    {
        return str($kode)->after('FR.')->lower()->replace('.', '-')->toString();
    }
}
