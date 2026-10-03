<?php

namespace App\Plugins\User\Yuyucalendar\Services;

use App\Plugins\User\Yuyutodo\Services\TodoQueryService;
use Carbon\CarbonImmutable;

class CalendarProviderService
{
    /** Bounded date range, live permission checks, no copying and no arbitrary first-N cutoff. */
    public function events(array $frame_ids, string $from, string $until, array $filters = [])
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $from);
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $until);
        abort_unless($start <= $end && $start->diffInDays($end) <= 62, 422);
        $sources = (new CalendarSourceService())->available($frame_ids);
        $events = collect();
        foreach ($sources as $source) {
            $frame = $source['frame'];
            if ($source['type'] === 'calendars') {
                $adapter = new CalendarReadAdapter($frame->page, $frame);
                foreach ($adapter->postsBetween($source['record']->id, $from, $until) as $post) {
                    $events->push([
                        'id' => 'calendar:' . $post->id, 'type' => 'calendar', 'title' => $post->title,
                        'source_name' => $source['name'], 'source_frame_id' => $frame->id,
                        'start' => $post->start_date . 'T' . substr($post->start_time ?: '00:00:00', 0, 8),
                        'end' => $post->end_date . 'T' . substr($post->end_time ?: '00:00:00', 0, 8),
                        'all_day' => (bool) $post->allday_flag, 'status' => (int) $post->status,
                        'url' => $this->detailUrl($source, 'show', $post->id),
                    ]);
                }
            } else {
                $query = (new TodoQueryService())->query([$source['record']->id], $this->todoFilters($filters));
                $todos = $query->where(function ($dates) use ($from, $until) {
                    $dates->whereBetween('due_date', [$from, $until])->orWhere(function ($scheduled) use ($from, $until) {
                        $scheduled->where('starts_at', '<=', $until . ' 23:59:59')
                            ->where(function ($ends) use ($from) {
                                $ends->where('ends_at', '>=', $from . ' 00:00:00')->orWhere(function ($point) use ($from) {
                                    $point->whereNull('ends_at')->where('starts_at', '>=', $from . ' 00:00:00');
                                });
                            });
                    });
                })->get();
                foreach ($todos as $todo) {
                    $base = [
                        'title' => $todo->title, 'source_name' => $source['name'], 'source_frame_id' => $frame->id,
                        'status' => $todo->state, 'url' => $this->detailUrl($source, 'todoShow', $todo->id),
                    ];
                    if ($todo->starts_at && $todo->starts_at->toDateString() <= $until
                        && ($todo->ends_at ?: $todo->starts_at)->toDateString() >= $from) {
                        $events->push($base + [
                            'id' => 'todo_schedule:' . $todo->id, 'type' => 'todo_schedule', 'all_day' => false,
                            'start' => $todo->starts_at->format('Y-m-d\TH:i:s'),
                            'end' => ($todo->ends_at ?: $todo->starts_at)->format('Y-m-d\TH:i:s'),
                        ]);
                    }
                    if ($todo->due_date && $todo->due_date->toDateString() >= $from && $todo->due_date->toDateString() <= $until) {
                        $events->push($base + [
                            'id' => 'todo_due:' . $todo->id, 'type' => 'todo_due', 'all_day' => true,
                            'start' => $todo->due_date->format('Y-m-d\T00:00:00'),
                            'end' => $todo->due_date->format('Y-m-d\T23:59:59'),
                        ]);
                    }
                }
            }
        }
        // Frames sharing a bucket can have different post roles. Union allowed posts, then deduplicate.
        return $events->unique('id')->sortBy(function ($event) {
            return $event['start'] . ':' . $event['id'];
        })->values();
    }

    public function unscheduled(array $frame_ids, array $filters = [], string $page_name = 'page')
    {
        $sources = (new CalendarSourceService())->available($frame_ids)->where('type', 'yuyutodo');
        if ($sources->isEmpty()) {
            return null;
        }
        $list_ids = $sources->map(function ($source) {
            return $source['record']->id;
        })->unique()->all();
        $todos = (new TodoQueryService())->query($list_ids, $this->todoFilters($filters))
            ->whereNull('due_date')->whereNull('starts_at')->whereNull('ends_at')->paginate(20, ['*'], $page_name);
        $todos->getCollection()->transform(function ($todo) use ($sources) {
            $source = $sources->first(function ($source) use ($todo) {
                return $source['record']->id === $todo->list_id;
            });
            $todo->calendar_detail_url = $this->detailUrl($source, 'todoShow', $todo->id);
            $todo->calendar_source_frame_id = (int) $source['frame']->id;
            return $todo;
        });
        return $todos;
    }

    private function todoFilters(array $filters): array
    {
        return [
            'scope' => ($filters['scope'] ?? 'mine') === 'all' ? 'all' : 'mine',
            'state' => ($filters['state'] ?? 'open') === 'all' ? 'all' : 'open',
        ];
    }

    private function detailUrl(array $source, string $action, int $id): string
    {
        $frame = $source['frame'];
        return url('/plugin/' . $source['type'] . '/' . $action . '/' . $frame->page_id . '/' . $frame->id . '/' . $id)
            . '#frame-' . $frame->id;
    }
}
