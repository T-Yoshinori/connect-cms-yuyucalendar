@extends('core.cms_frame_base')
@section("plugin_contents_$frame->id")
@php
    $todo_return_token = request()->input('ycal_return');
    $todo_return_url = class_exists(\App\Plugins\User\Yuyucalendar\Services\CalendarReturnService::class)
        ? \App\Plugins\User\Yuyucalendar\Services\CalendarReturnService::fromRequest(request(), (int) $frame->id) : null;
@endphp

@include('plugins.common.errors_form_line')
<div class="card"><div class="card-header">{{ $todo->title }} @if($todo->is_private)<span class="badge badge-secondary">非公開</span>@endif</div><div class="card-body">
<div style="white-space: pre-wrap">{{ $todo->body }}</div>
<dl class="row mt-3">
@foreach (['状態' => \App\Models\User\YuyuTodo\Todo::STATES[$todo->state], '優先度' => \App\Models\User\YuyuTodo\Todo::PRIORITIES[$todo->priority], '期限' => $todo->due_date ? $todo->due_date->format('Y/m/d') : '未設定', '開始予定' => $todo->starts_at ? $todo->starts_at->format('Y/m/d H:i') : '未設定', '終了予定' => $todo->ends_at ? $todo->ends_at->format('Y/m/d H:i') : '未設定', '担当者' => $users->pluck('name')->implode('、'), '担当グループ' => $groups->pluck('name')->implode('、'), '公開範囲' => $todo->is_private ? '登録者本人のみ' : 'ページの閲覧対象者', '完了日時' => $todo->completed_at ? $todo->completed_at->format('Y/m/d H:i') : ''] as $label => $value)
<dt class="col-sm-3">{{ $label }}</dt><dd class="col-sm-9">{{ $value }}</dd>
@endforeach
</dl>
<p class="text-muted">@if ($todo->is_private)非公開Todoの状態変更・編集・削除は登録者本人のみ可能です。@else共有Todoの状態は、登録者・担当者・担当グループのメンバーが変更できます。内容・期限・担当設定の編集と削除は登録者のみ可能です。@endif</p>
@if ($can_state)
<form method="POST" action="{{ url('/redirect/plugin/yuyutodo/todoState/'.$page->id.'/'.$frame->id.'/'.$todo->id) }}#frame-{{ $frame->id }}" class="form-inline mb-3">{{ csrf_field() }}
@if($todo_return_url)<input type="hidden" name="ycal_return" value="{{ $todo_return_token }}">@endif
<label for="todo_state_{{ $frame->id }}" class="mr-2">状態変更</label><select class="form-control mr-2" id="todo_state_{{ $frame->id }}" name="state">@foreach(\App\Models\User\YuyuTodo\Todo::STATES as $value => $label)<option value="{{ $value }}" @if($todo->state === $value) selected @endif>{{ $label }}</option>@endforeach</select><button type="submit" class="btn btn-primary">更新</button></form>
@endif
@if ($can_edit)
<a class="btn btn-primary" href="{{ url('/plugin/yuyutodo/todoEdit/'.$page->id.'/'.$frame->id.'/'.$todo->id) }}@if($todo_return_url)?ycal_return={{ rawurlencode($todo_return_token) }}@endif#frame-{{ $frame->id }}">編集</a>
<form method="POST" action="{{ url('/redirect/plugin/yuyutodo/todoDelete/'.$page->id.'/'.$frame->id.'/'.$todo->id) }}#frame-{{ $frame->id }}" class="d-inline" onsubmit="return confirm('このTodoを削除しますか？');">{{ csrf_field() }}
@if($todo_return_url)<input type="hidden" name="ycal_return" value="{{ $todo_return_token }}">@endif
<button class="btn btn-danger" type="submit">削除</button></form>
@endif

<a class="btn btn-secondary" href="{{ $todo_return_url ?: url($page->permanent_link) . '#frame-' . $frame->id }}">一覧へ戻る</a>
</div></div>
@endsection

