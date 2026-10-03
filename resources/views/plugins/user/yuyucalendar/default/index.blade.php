@extends('core.cms_frame_base')
@section("plugin_contents_$frame->id")
<form action="{{ url($page->permanent_link) }}#frame-{{ $frame->id }}" method="GET" class="mb-3">
@foreach(request()->query() as $key => $value)
@if(strpos($key, $prefix) !== 0)
@if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">
@elseif(is_array($value))
@foreach($value as $index => $item)@if(is_scalar($item))<input type="hidden" name="{{ $key }}[{{ $index }}]" value="{{ $item }}">@endif
@endforeach
@endif
@endif
@endforeach
<input type="hidden" name="{{ $prefix }}selected" value="1">
<input type="hidden" name="{{ $prefix }}view" value="{{ $filters['view'] }}">
<div class="form-row align-items-end">
<div class="col-sm mb-2"><label for="{{ $prefix }}date">表示日</label><input id="{{ $prefix }}date" name="{{ $prefix }}date" type="date" class="form-control" required min="1900-01-01" max="2100-12-31" value="{{ $filters['date'] }}"></div>
<div class="col-sm mb-2"><label for="{{ $prefix }}scope">ToDoの対象</label><select id="{{ $prefix }}scope" name="{{ $prefix }}scope" class="form-control">@foreach(['mine' => '自分に関係するToDo', 'all' => '閲覧可能なToDo'] as $value => $label)<option value="{{ $value }}" @if($filters['scope'] === $value) selected @endif>{{ $label }}</option>@endforeach</select></div>
<div class="col-sm mb-2"><label for="{{ $prefix }}state">ToDoの状態</label><select id="{{ $prefix }}state" name="{{ $prefix }}state" class="form-control">@foreach(['open' => '未完了', 'all' => 'すべての状態'] as $value => $label)<option value="{{ $value }}" @if($filters['state'] === $value) selected @endif>{{ $label }}</option>@endforeach</select></div>
<div class="col-auto mb-2"><button class="btn btn-secondary" type="submit">表示</button></div>
</div>
<details><summary>表示元を選択（{{ count($selected) }}件）</summary><fieldset class="border rounded p-2 mt-2"><legend class="sr-only">表示元</legend>
@forelse($sources as $id => $source)
<div class="form-check"><input id="{{ $prefix }}source_{{ $id }}" class="form-check-input" type="checkbox" name="{{ $prefix }}sources[]" value="{{ $id }}" @if(in_array($id, $selected, true)) checked @endif><label class="form-check-label" for="{{ $prefix }}source_{{ $id }}">{{ $source['label'] }}</label></div>
@empty<p class="mb-0 text-muted">閲覧可能な表示元がありません。</p>@endforelse
</fieldset></details>
</form>
<div class="text-right mb-2">
<div class="btn-group" role="group" aria-label="表示形式">@foreach(['month' => '月', 'week' => '週', 'day' => '日', 'list' => '一覧'] as $value => $label)<a class="btn btn-sm {{ $filters['view'] === $value ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ $link(['view' => $value]) }}" @if($filters['view'] === $value) aria-current="true" @endif>{{ $label }}</a>@endforeach</div>
</div>
<nav class="text-center mb-1" aria-label="カレンダー表示">
    <a href="{{ $link(['date' => $period['previous']->toDateString()]) }}" aria-label="前へ"><i class="fas fa-chevron-circle-left" aria-hidden="true"></i></a>
    @if(in_array($filters['view'], ['month', 'list']))
    <h5 class="d-inline">{{ $period['anchor']->format('Y') }}年</h5>
    <h3 class="d-inline">{{ $period['anchor']->format('n') }}月</h3>
    @elseif($filters['view'] === 'week')
    <h5 class="d-inline">{{ $period['from']->format('Y') }}年</h5>
    <h3 class="d-inline">{{ $period['from']->format('n/j') }} ～ {{ $period['until']->format($period['from']->year === $period['until']->year ? 'n/j' : 'Y/n/j') }}</h3>
    @else
    <h5 class="d-inline">{{ $period['anchor']->format('Y') }}年</h5>
    <h3 class="d-inline">{{ $period['anchor']->format('n月j日') }}</h3>
    @endif
    <a href="{{ $link(['date' => $period['next']->toDateString()]) }}" aria-label="次へ"><i class="fas fa-chevron-circle-right" aria-hidden="true"></i></a>
    <div class="d-inline align-bottom ml-3">
        <a href="{{ $link(['date' => today()->toDateString()]) }}" class="badge badge-pill badge-info">{{ in_array($filters['view'], ['month', 'list']) ? '今月へ' : ($filters['view'] === 'week' ? '今週へ' : '今日へ') }}</a>
    </div>
</nav>
@if($sources->isEmpty())<p class="alert alert-info">表示元が未設定、または閲覧できる表示元がありません。フレーム編集の「集約・表示設定」を確認してください。</p>@endif
@if(in_array($filters['view'], ['month', 'week']))
<div class="table-responsive">
<table class="table table-bordered" style="table-layout: fixed; min-width: 700px"><caption class="sr-only">{{ $period['anchor']->format('Y年n月') }}の集約カレンダー</caption><thead><tr class="thead">@foreach(['日', '月', '火', '水', '木', '金', '土'] as $weekday)<th scope="col" class="text-center {{ $weekday === '日' ? 'cc-color-sunday' : ($weekday === '土' ? 'cc-color-saturday' : '') }}">{{ $weekday }}</th>@endforeach</tr></thead><tbody>
@foreach($dates as $date_key => $date)
@if($date->dayOfWeek === 0)<tr>@endif
<td class="{{ $filters['view'] === 'month' && $date->month !== $period['anchor']->month ? 'bg-light' : '' }}" style="overflow-wrap: anywhere">
<a class="font-weight-bold {{ $date->isToday() ? 'badge badge-info' : '' }} {{ $date->dayOfWeek === 0 || $date->hasHoliday() ? 'cc-color-sunday' : ($date->dayOfWeek === 6 ? 'cc-color-saturday' : '') }}" href="{{ $link(['view' => 'day', 'date' => $date_key]) }}">{{ $date->format('n/j') }}</a>
@if($date->hasHoliday())<div class="small text-danger">{{ $date->getHolidayName() }}</div>@endif
@foreach($daily_events[$date_key] as $event)@include('plugins.user.yuyucalendar.event')@endforeach
</td>
@if($date->dayOfWeek === 6)</tr>@endif
@endforeach
</tbody></table>
</div>
@else
@php($shown = false)
@foreach($dates as $date_key => $date)
@if($daily_events[$date_key]->isNotEmpty() || $filters['view'] === 'day')
@php($shown = true)
<div class="border-bottom mb-3"><h3 class="h6">{{ $date->format('Y/m/d') }}（{{ ['日','月','火','水','木','金','土'][$date->dayOfWeek] }}） @if($date->hasHoliday())<span class="text-danger">{{ $date->getHolidayName() }}</span>@endif</h3>
@forelse($daily_events[$date_key] as $event)@include('plugins.user.yuyucalendar.event')@empty<p class="text-muted">この日の予定はありません。</p>@endforelse
</div>
@endif
@endforeach
@if(!$shown)<p class="text-muted">この期間の予定はありません。</p>@endif
@endif
@if($unscheduled)
<h3 class="h6 mt-4">日付未設定のToDo</h3><p class="small text-muted">期限と予定日時が未設定のToDoです。上の「ToDoの対象・状態」の選択が適用されます。</p>
<ul class="list-group mb-2">@forelse($unscheduled as $todo)<li class="list-group-item"><a href="{{ $todo->calendar_detail_url }}">{{ $todo->title }}</a> <span class="small text-muted">{{ \App\Models\User\YuyuTodo\Todo::STATES[$todo->state] }}</span></li>@empty<li class="list-group-item text-muted">日付未設定のToDoはありません。</li>@endforelse</ul>
@include('plugins.common.user_paginate', ['posts' => $unscheduled, 'frame' => $frame, 'aria_label_name' => '日付未設定のToDo'])
@endif
<div class="mt-3">@foreach($sources as $id => $source)@if(in_array($id, $selected, true))<a class="btn btn-outline-secondary btn-sm mr-1 mb-1" href="{{ url($source['frame']->page->permanent_link) }}#frame-{{ $id }}">{{ $source['name'] }}の元の一覧へ</a>@endif
@endforeach</div>
<p class="small text-muted mt-3">予定の詳細・登録・編集は元のCalendar／YuyuToDoで行います。担当者は共有ToDoの状態を変更できます。</p>
@endsection
