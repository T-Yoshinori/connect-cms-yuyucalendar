@extends('core.cms_frame_base_setting')
@section("core.cms_frame_edit_tab_$frame->id")
@include('plugins.user.yuyucalendar.yuyucalendar_frame_edit_tab')
@endsection
@section("plugin_setting_$frame->id")
@include('plugins.common.errors_form_line')
<form method="POST" action="{{ url('/redirect/plugin/yuyucalendar/calendarSaveView/'.$page->id.'/'.$frame->id) }}#frame-{{ $frame->id }}">
{{ csrf_field() }}
<p>標準カレンダーとYuyuToDoから表示元を選択します。利用者が元ページ・元フレームを閲覧できる情報だけを表示します。</p>
<p class="alert alert-info">統合対象になり得る標準Calendarの各フレームには、テンプレート「YuyuCalendar連携（月表示）」（yuyucalendar）を適用してください。詳細・編集・保存後にクリック元のYuyuCalendarへ戻るために必要です。YuyuToDo側のテンプレート切替は不要です。</p>
<fieldset class="form-group"><legend class="h6">表示元</legend>
@php($chosen = array_map('intval', (array)(session()->has('_old_input') ? old('calendar_sources', []) : $settings['sources'])))
@forelse ($sources as $id => $source)
<div class="form-check"><input class="form-check-input" type="checkbox" id="ycal_source_{{ $frame->id }}_{{ $id }}" name="calendar_sources[]" value="{{ $id }}" @if(in_array($id, $chosen, true)) checked @endif><label class="form-check-label" for="ycal_source_{{ $frame->id }}_{{ $id }}">{{ $source['type'] === 'calendars' ? 'カレンダー' : 'ToDo' }}：{{ $source['label'] }}</label></div>
@empty
<p class="text-muted">選択できる表示元がありません。元のCalendar／YuyuToDoフレームの配置と閲覧権限を確認してください。</p>
@endforelse
</fieldset>
@foreach (['calendar_view' => ['初期表示形式', 'view', ['month' => '月', 'week' => '週', 'day' => '日', 'list' => '一覧']], 'calendar_todo_scope' => ['ToDoの初期表示対象', 'scope', ['mine' => '自分に関係するToDo', 'all' => '閲覧可能なToDo']], 'calendar_todo_state' => ['ToDoの初期表示状態', 'state', ['open' => '未完了', 'all' => 'すべての状態']]] as $name => $definition)
<div class="form-group"><label for="{{ $name }}_{{ $frame->id }}">{{ $definition[0] }}</label><select id="{{ $name }}_{{ $frame->id }}" class="form-control" name="{{ $name }}">
@foreach ($definition[2] as $value => $label)
<option value="{{ $value }}" @if(old($name, $settings[$definition[1]]) === $value) selected @endif>{{ $label }}</option>
@endforeach
</select></div>
@endforeach
<p class="text-muted">同じカレンダー／ToDo一覧を複数選択しても予定は重複表示しません。ToDoに予定期間と期限の両方がある場合は、それぞれを区別して表示します。</p>
<div class="text-center"><a class="btn btn-secondary" href="{{ url($page->permanent_link) }}#frame-{{ $frame->id }}">キャンセル</a> <button class="btn btn-primary" type="submit">保存</button></div>
</form>
@endsection
