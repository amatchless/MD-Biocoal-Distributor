<?php

use Carbon\CarbonInterface;

if (! function_exists('local_time')) {
    /** Format a stored (UTC) timestamp in the display timezone. */
    function local_time(?CarbonInterface $at, string $format = 'd M Y, h:i A'): string
    {
        return $at ? $at->copy()->timezone(config('app.display_timezone'))->format($format) : '';
    }
}
