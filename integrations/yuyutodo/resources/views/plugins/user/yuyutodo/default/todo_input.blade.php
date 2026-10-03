@extends('core.cms_frame_base')
@section("plugin_contents_$frame->id")
@php
    $todo_return_token = old('ycal_return', request()->input('ycal_return'));
    $todo_return_url = class_exists(\App\Plugins\User\Yuyucalendar\Services\CalendarReturnService::class)
        ? (new \App\Plugins\User\Yuyucalendar\Services\CalendarReturnService())->resolve($todo_return_token, (int) $frame->id) : null;
@endphp

@if ($errors && $errors->any())
<div class="alert alert-danger" role="alert">
<strong>入力内容を確認してください。</strong>
<ul class="mb-0">
@foreach ($errors->all() as $error)
<li>{{ $error }}</li>
@endforeach
</ul>
</div>
@endif
<form id="todo_input_{{ $frame->id }}" method="POST" action="{{ url('/redirect/plugin/yuyutodo/todoSave/'.$page->id.'/'.$frame->id.($todo->exists ? '/'.$todo->id : '')) }}#frame-{{ $frame->id }}">
{{ csrf_field() }}
@if($todo_return_url)<input type="hidden" name="ycal_return" value="{{ $todo_return_token }}">@endif
<div class="form-group"><label for="todo_title_{{ $frame->id }}">件名</label><input class="form-control" id="todo_title_{{ $frame->id }}" name="title" maxlength="255" required value="{{ old('title', $todo->title) }}"></div>
<div class="form-group"><label for="todo_body_{{ $frame->id }}">内容</label><textarea class="form-control" id="todo_body_{{ $frame->id }}" name="body" rows="5">{{ old('body', $todo->body) }}</textarea></div>
@foreach (['state' => \App\Models\User\YuyuTodo\Todo::STATES, 'priority' => \App\Models\User\YuyuTodo\Todo::PRIORITIES, 'is_private' => [1 => '非公開（登録者本人のみ）', 0 => '共有（ページの閲覧対象者）']] as $field => $options)
<div class="form-group"><label for="todo_{{ $field }}_{{ $frame->id }}">{{ ['state' => '状態', 'priority' => '優先度', 'is_private' => '公開範囲'][$field] }}</label><select class="form-control" id="todo_{{ $field }}_{{ $frame->id }}" name="{{ $field }}">@foreach($options as $value => $label)<option value="{{ $value }}" @if((string)old($field, $field === 'is_private' ? (int)$todo->$field : $todo->$field) === (string)$value) selected @endif>{{ $label }}</option>@endforeach</select></div>
@endforeach
@foreach (['due_date' => ['期限', 'date', 'Y-m-d'], 'starts_at' => ['開始予定日時', 'datetime-local', 'Y-m-d\TH:i'], 'ends_at' => ['終了予定日時', 'datetime-local', 'Y-m-d\TH:i']] as $field => $definition)
<div class="form-group"><label for="todo_{{ $field }}_{{ $frame->id }}">{{ $definition[0] }}</label><input class="form-control" id="todo_{{ $field }}_{{ $frame->id }}" type="{{ $definition[1] }}" name="{{ $field }}" value="{{ old($field, $todo->$field ? $todo->$field->format($definition[2]) : '') }}"></div>
@endforeach
<p class="text-muted" id="todo_assignee_help_{{ $frame->id }}">非公開では担当者・担当グループを選択できず、担当設定は保存されません。担当者を指定する場合は「共有」を選んでください。共有Todoの状態は、登録者・担当者・担当グループのメンバーが変更できます。内容・期限・担当設定の編集と削除は登録者のみ可能です。</p>
@foreach (['user' => ['担当者', $users], 'group' => ['担当グループ', $groups]] as $type => $definition)
@php($selected_ids = array_map('intval', (array)(session()->has('_old_input') ? old($type.'_ids', []) : ($todo->exists ? $todo->assignees->where('target_type', $type)->pluck('target_id')->all() : []))))
<fieldset class="form-group" data-todo-assignees aria-describedby="todo_assignee_help_{{ $frame->id }}" @if((string)old('is_private', (int)$todo->is_private) === '1') disabled @endif><legend class="h6">{{ $definition[0] }}</legend><div class="border rounded p-2" style="max-height: 200px; overflow: auto">
@foreach ($definition[1] as $target)
<div class="form-check"><input class="form-check-input" type="checkbox" id="todo_{{ $type }}_{{ $frame->id }}_{{ $target->id }}" name="{{ $type }}_ids[]" value="{{ $target->id }}" @if(in_array((int)$target->id, $selected_ids, true)) checked @endif><label class="form-check-label" for="todo_{{ $type }}_{{ $frame->id }}_{{ $target->id }}">{{ $target->name }}</label></div>
@endforeach
</div></fieldset>
@endforeach
<button class="btn btn-primary" type="submit">保存</button><a class="btn btn-secondary" href="{{ $todo_return_url ?: url($page->permanent_link) . '#frame-' . $frame->id }}">キャンセル</a>
</form>
<script>
(function () {
    var form = document.getElementById('todo_input_{{ $frame->id }}');
    if (!form) return;
    var privacy = form.querySelector('[name="is_private"]');
    var assignees = form.querySelectorAll('[data-todo-assignees]');
    function updateAssignees() {
        var isPrivate = privacy.value === '1';
        for (var i = 0; i < assignees.length; i++) {
            assignees[i].disabled = isPrivate;
            assignees[i].classList.toggle('text-muted', isPrivate);
        }
    }
    privacy.addEventListener('change', updateAssignees);
    window.addEventListener('pageshow', updateAssignees);
    updateAssignees();
})();
</script>
@endsection

