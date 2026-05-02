<?php

namespace App\Services\HeroCatalogSync;

use Closure;

class SyncOptions
{
    public function __construct(
        public readonly bool $initial = false,
        public readonly bool $dryRun = false,
        public readonly ?int $limit = null,
        public readonly ?Closure $onProgress = null,
    ) {}
}
