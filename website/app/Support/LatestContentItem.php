<?php

namespace App\Support;

use Carbon\Carbon;

final class LatestContentItem
{
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly ?string $thumbnail,
        public readonly string $categoryLabel,
        public readonly Carbon $publishedAt,
        public readonly string $url,
    ) {
    }
}
