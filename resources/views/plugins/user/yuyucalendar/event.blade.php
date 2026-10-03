<div class="border-left pl-2 mb-2 {{ $event['type'] === 'calendar' ? 'border-primary' : 'border-success' }}">
<div class="small text-muted">
@if($event['type'] === 'todo_due')<span class="badge badge-warning">ToDo期限</span>
@elseif($event['type'] === 'todo_schedule')<span class="badge badge-success">ToDo予定</span>
@else<span class="badge badge-primary">予定</span>
@if($event['status'] === 1)<span class="badge badge-warning">一時保存</span>@elseif($event['status'] === 2)<span class="badge badge-warning">承認待ち</span>@endif
@endif
</div>
@if(!$event['all_day'])<div class="small">{{ substr($event['start'], 0, 10) < $date_key ? '前日' : substr($event['start'], 11, 5) }} ～ {{ substr($event['end'], 0, 10) > $date_key ? '翌日' : substr($event['end'], 11, 5) }}</div>@endif
<a href="{{ $event['url'] }}">{{ $event['title'] }}</a>
@if($event['type'] !== 'calendar')<span class="small text-muted">（{{ \App\Models\User\YuyuTodo\Todo::STATES[$event['status']] ?? $event['status'] }}）</span>@endif
</div>
