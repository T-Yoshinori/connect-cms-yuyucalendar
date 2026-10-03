<?php

namespace App\Plugins\User\Yuyucalendar\Services;

use Carbon\CarbonImmutable;

class CalendarPeriodService
{
    public function period(string $date, string $view): array
    {
        $anchor = CarbonImmutable::createFromFormat('!Y-m-d', $date, config('app.timezone'));
        if ($view === 'month' || $view === 'list') {
            $anchor = $anchor->startOfMonth();
            $from = $view === 'month' ? $anchor->startOfWeek(0) : $anchor;
            $until = $view === 'month' ? $anchor->endOfMonth()->endOfWeek(6) : $anchor->endOfMonth();
            $previous = $anchor->subMonth();
            $next = $anchor->addMonth();
        } elseif ($view === 'week') {
            $from = $anchor->startOfWeek(0);
            $until = $from->addDays(6)->endOfDay();
            $previous = $anchor->subWeek();
            $next = $anchor->addWeek();
        } else {
            $from = $anchor->startOfDay();
            $until = $anchor->endOfDay();
            $previous = $anchor->subDay();
            $next = $anchor->addDay();
        }
        return compact('anchor', 'from', 'until', 'previous', 'next');
    }

    /** Both Calendar end dates and Todo date-only deadlines are inclusive. */
    public function byDay($events, string $from, string $until): array
    {
        $days = [];
        for ($date = CarbonImmutable::parse($from); $date->toDateString() <= $until; $date = $date->addDay()) {
            $key = $date->toDateString();
            $days[$key] = $events->filter(function ($event) use ($key) {
                return substr($event['start'], 0, 10) <= $key && substr($event['end'], 0, 10) >= $key;
            })->values();
        }
        return $days;
    }
}
