<?php

namespace App\Plugins\User\Yuyucalendar\Services;

use App\Models\Common\Frame;
use App\Models\User\Calendars\Calendar;
use App\Models\User\YuyuTodo\TodoList;
use App\Plugins\User\Yuyutodo\Services\TodoAccessService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class CalendarSourceService
{
    /** Resolve sources on every request; neither saved IDs nor labels grant access. */
    public function available(?array $frame_ids = null)
    {
        $query = Frame::with('page')->whereIn('plugin_name', ['calendars', 'yuyutodo'])->orderBy('id');
        if ($frame_ids !== null) {
            $query->whereIn('id', $frame_ids);
        }
        $sources = collect();
        $todo_installed = class_exists(TodoList::class) && Schema::hasTable('yuyu_todo_lists');
        foreach ($query->get() as $frame) {
            $page = $frame->page;
            if (!$page || !$frame->isVisible($page, Auth::user()) || $frame->isInvisiblePrivateFrame()) {
                continue;
            }
            $tree = $page->getPageTreeByGoingBackParent(null);
            if (!$page->isVisibleAncestorsAndSelf($tree) || $page->isRequestPassword(request(), $tree)) {
                continue;
            }
            if ($frame->plugin_name === 'calendars') {
                $record = Calendar::where('bucket_id', $frame->bucket_id)->first();
            } else {
                $record = $todo_installed ? TodoList::where('bucket_id', $frame->bucket_id)->first() : null;
                if (!$record || !(new TodoAccessService())->canViewList($record)) {
                    continue;
                }
            }
            if (!$record) {
                continue;
            }
            $sources->put((int) $frame->id, [
                'frame' => $frame, 'record' => $record,
                'type' => $frame->plugin_name, 'name' => $record->name,
                'label' => $record->name . '（' . $page->page_name . ' / フレーム ' . $frame->id . '）',
            ]);
        }
        return $sources;
    }
}
