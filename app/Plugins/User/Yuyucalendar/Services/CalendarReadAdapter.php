<?php

namespace App\Plugins\User\Yuyucalendar\Services;

use App\Models\User\Calendars\CalendarPost;
use App\Plugins\User\Calendars\CalendarsPlugin;
use Illuminate\Support\Facades\Auth;

/** Reuse Calendar's post visibility rules in the ORIGINAL frame's Gate context. */
class CalendarReadAdapter extends CalendarsPlugin
{
    public function isCan($role, $post = null, $plugin_name = null, $buckets = null, $frame = null): bool
    {
        // CMS role_* Gates ignore their arguments and use the current request page.
        // Resolve roles explicitly from the source frame through the standard role trait.
        return Auth::check() && $this->checkRoleFromFrame(Auth::user(), $role, $this->frame);
    }

    public function postsBetween($calendar_id, string $from, string $until)
    {
        $query = CalendarPost::where('calendar_id', $calendar_id)
            ->where('start_date', '<=', $until)->where('end_date', '>=', $from)
            ->whereExists(function ($query) use ($calendar_id) {
                $query->selectRaw('1')->from('calendars')->where('calendars.id', $calendar_id)
                    ->where('calendars.bucket_id', $this->frame->bucket_id)->whereNull('calendars.deleted_at');
            });
        return $this->appendAuthWhereBase($query, 'calendar_posts')->orderBy('start_date')->orderBy('start_time')->get();
    }
}
