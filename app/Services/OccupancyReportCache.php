<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * File-based cache for monthly occupancy report payloads.
 */
class OccupancyReportCache
{
    private const VERSION_KEY = 'occupancy_board_cache_version';

    private const TTL_SECONDS = 120;

    public static function remember(
        Carbon $start,
        Carbon $end,
        ?Carbon $compareStart,
        ?Carbon $compareEnd,
        callable $resolver
    ): array {
        $key = sprintf(
            'occupancy_report:v%d:%s:%s:%s:%s',
            OccupancyBoardCache::version(),
            $start->toDateString(),
            $end->toDateString(),
            $compareStart?->toDateString() ?? 'none',
            $compareEnd?->toDateString() ?? 'none'
        );

        return Cache::remember($key, self::TTL_SECONDS, $resolver);
    }
}
