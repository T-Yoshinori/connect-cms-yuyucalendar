<?php

namespace App\Plugins\User\Yuyucalendar;

use App\Models\Common\ConnectCarbon;
use App\Models\Core\FrameConfig;
use App\Plugins\User\UserPluginBase;
use App\Plugins\User\Yuyucalendar\Services\CalendarSourceService;
use App\Plugins\User\Yuyucalendar\Services\CalendarPeriodService;
use App\Plugins\User\Yuyucalendar\Services\CalendarProviderService;
use App\Plugins\User\Yuyucalendar\Services\CalendarReturnService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @plugin_title YuyuCalendar
 * @plugin_desc 複数のカレンダーとTodoを集約して表示します。
 */
class YuyucalendarPlugin extends UserPluginBase
{
    public $use_getpost = false;

    public function getPublicFunctions()
    {
        return ['get' => ['index'], 'post' => ['calendarSaveView']];
    }

    public function declareRole()
    {
        return ['index' => [], 'editView' => ['frames.edit'], 'calendarSaveView' => ['frames.edit']];
    }

    public function getFirstFrameEditAction()
    {
        return 'editView';
    }

    private function settings(): array
    {
        return [
            'sources' => array_values(array_filter(explode('|', FrameConfig::getConfigValue($this->frame_configs, 'calendar_sources', '')), 'ctype_digit')),
            'view' => FrameConfig::getConfigValue($this->frame_configs, 'calendar_view', 'month'),
            'scope' => FrameConfig::getConfigValue($this->frame_configs, 'calendar_todo_scope', 'mine'),
            'state' => FrameConfig::getConfigValue($this->frame_configs, 'calendar_todo_state', 'open'),
        ];
    }

    public function index($request, $page_id, $frame_id)
    {
        $settings = $this->settings();
        $sources = (new CalendarSourceService())->available($settings['sources']);
        $prefix = 'ycal_' . $frame_id . '_';
        $input = [];
        foreach (['date' => today()->toDateString(), 'view' => $settings['view'], 'scope' => $settings['scope'], 'state' => $settings['state']] as $key => $default) {
            $input[$key] = $request->input($prefix . $key, $default);
        }
        $filters = Validator::make($input, [
            'date' => 'required|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2100-12-31',
            'view' => 'required|in:month,week,day,list', 'scope' => 'required|in:mine,all', 'state' => 'required|in:open,all',
        ])->validate();
        $selected = $sources->keys()->all();
        if ($request->has($prefix . 'selected')) {
            $values = Validator::make(['sources' => $request->input($prefix . 'sources', [])], [
                'sources' => 'array|max:200', 'sources.*' => 'integer|min:1',
            ])->validate();
            $selected = array_values(array_intersect($selected, array_map('intval', $values['sources'])));
        }
        $period_service = new CalendarPeriodService();
        $period = $period_service->period($filters['date'], $filters['view']);
        $provider = new CalendarProviderService();
        $events = $provider->events($selected, $period['from']->toDateString(), $period['until']->toDateString(), $filters);
        $return_query = array_filter($request->query(), function ($key) {
            return preg_match('/^ycal_[0-9]+_(date|view|scope|state|selected|sources|page)$/', $key);
        }, ARRAY_FILTER_USE_KEY);
        foreach ($filters as $key => $value) {
            $return_query[$prefix . $key] = $value;
        }
        $return_query[$prefix . 'selected'] = 1;
        $return_query[$prefix . 'sources'] = $selected;
        $return_url = url($this->page->permanent_link) . '?' . http_build_query($return_query) . '#frame-' . $frame_id;
        $return_service = new CalendarReturnService();
        $return_tokens = [];
        $events = $events->map(function ($event) use ($return_service, $return_url, &$return_tokens) {
            $source_id = (int) $event['source_frame_id'];
            if (!isset($return_tokens[$source_id])) {
                $return_tokens[$source_id] = $return_service->token($return_url, $source_id);
            }
            $event['url'] = $return_service->append($event['url'], $return_tokens[$source_id]);
            return $event;
        });
        $daily_events = $period_service->byDay($events, $period['from']->toDateString(), $period['until']->toDateString());
        $dates = [];
        foreach (array_keys($daily_events) as $date) {
            $dates[$date] = new ConnectCarbon($date);
        }
        $dates = $this->addHolidaysFromTo(reset($dates)->copy(), end($dates)->copy()->endOfDay(), $dates);
        $unscheduled = $provider->unscheduled($selected, $filters, $prefix . 'page');
        if ($unscheduled) {
            $unscheduled->appends($request->query())->fragment('frame-' . $frame_id);
            $unscheduled->getCollection()->transform(function ($todo) use ($return_service, $return_url, &$return_tokens) {
                $source_id = (int) $todo->calendar_source_frame_id;
                if (!isset($return_tokens[$source_id])) {
                    $return_tokens[$source_id] = $return_service->token($return_url, $source_id);
                }
                $todo->calendar_detail_url = $return_service->append($todo->calendar_detail_url, $return_tokens[$source_id]);
                return $todo;
            });
        }
        $link = function (array $changes) use ($request, $prefix, $frame_id) {
            $query = $request->query();
            unset($query[$prefix . 'page']);
            foreach ($changes as $key => $value) {
                $query[$prefix . $key] = $value;
            }
            return url($this->page->permanent_link) . '?' . http_build_query($query) . '#frame-' . $frame_id;
        };
        return $this->view('index', compact('sources', 'selected', 'prefix', 'filters', 'period', 'events', 'daily_events', 'dates', 'unscheduled', 'link'));
    }

    public function editView($request, $page_id, $frame_id)
    {
        $settings = $this->settings();
        $sources = (new CalendarSourceService())->available();
        return $this->view('frame_settings', compact('settings', 'sources'));
    }

    public function calendarSaveView($request, $page_id, $frame_id)
    {
        $data = Validator::make($request->all(), [
            'calendar_sources' => 'nullable|array|max:200', 'calendar_sources.*' => 'integer|min:1|distinct',
            'calendar_view' => 'required|in:month,week,day,list',
            'calendar_todo_scope' => 'required|in:mine,all', 'calendar_todo_state' => 'required|in:open,all',
        ])->validate();
        $ids = array_map('intval', $data['calendar_sources'] ?? []);
        $available = (new CalendarSourceService())->available($ids)->keys()->all();
        abort_unless(count(array_diff($ids, $available)) === 0, 403);
        $data['calendar_sources'] = implode('|', $ids);
        DB::transaction(function () use ($data, $frame_id) {
            foreach ($data as $name => $value) {
                FrameConfig::updateOrCreate(['frame_id' => $frame_id, 'name' => $name], ['value' => $value]);
            }
        });
        return collect(['redirect_path' => url('/plugin/yuyucalendar/editView/' . $page_id . '/' . $frame_id) . '#frame-' . $frame_id]);
    }
}
