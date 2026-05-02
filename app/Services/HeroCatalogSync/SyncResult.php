<?php

namespace App\Services\HeroCatalogSync;

class SyncResult
{
    /**
     * @param  array<int, string>  $newHeroSlugs
     * @param  array<int, string>  $skipped
     * @param  array<int, string>  $errors
     */
    public function __construct(
        public readonly int $seen,
        public readonly int $created,
        public readonly int $updated,
        public readonly array $newHeroSlugs,
        public readonly array $skipped,
        public readonly array $errors,
        public readonly bool $dryRun,
    ) {}
}
