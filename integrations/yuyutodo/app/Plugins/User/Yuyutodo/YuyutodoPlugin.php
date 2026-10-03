<?php

namespace App\Plugins\User\Yuyutodo;

use App\Plugins\User\UserPluginBase;
use App\Plugins\User\Yuyutodo\Services\TodoAccessService;
use App\Plugins\User\Yuyutodo\Services\TodoQueryService;
use App\Plugins\User\Yuyutodo\Services\TodoRecordService;
use App\Plugins\User\Yuyucalendar\Services\CalendarReturnService;
use App\Models\User\YuyuTodo\TodoList;
use App\Models\User\YuyuTodo\Todo;
use App\Models\Common\Frame;
use App\Models\Common\Buckets;
use App\Models\Common\Group;
use App\Models\Core\FrameConfig;
use App\User;
use App\Enums\UserStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @plugin_title YuyuToDo
 * @plugin_desc 個人・グループのTodoを管理します。
 */
class YuyutodoPlugin extends UserPluginBase
{
    public $use_getpost = false;

    public function getPublicFunctions()
    {
        return [
            'get' => ['todoCreate', 'todoEdit', 'todoShow'],
            'post' => ['todoSave', 'todoDelete', 'todoState', 'todoSaveView'],
        ];
    }

    public function declareRole()
    {
        return [
            // Todo actions use login/page/privacy/ownership checks in the services.
            // CMS content-role assignment is not a prerequisite for using Todo.
            'todoCreate' => [], 'todoEdit' => [], 'todoShow' => [],
            'todoSave' => [], 'todoDelete' => [], 'todoState' => [],
            'saveBuckets' => ['frames.edit'], 'changeBuckets' => ['frames.edit'],
            'destroyBuckets' => ['frames.delete'], 'editView' => ['frames.edit'], 'todoSaveView' => ['frames.edit'],
        ];
    }

    private function currentList(): TodoList
    {
        abort_unless(auth()->check(), 403);
        $list = TodoList::where('bucket_id', $this->frame->bucket_id)->firstOrFail();
        abort_unless((new TodoAccessService())->canViewList($list), 403);
        return $list;
    }

    public function index($request, $page_id, $frame_id)
    {
        if (!auth()->check()) {
            return $this->viewError('403_inframe');
        }
        if (!$this->frame->bucket_id) {
            return $this->commonView('empty_bucket');
        }
        $list = $this->currentList();
        $prefix = 'todo_' . $frame_id . '_';
        $filters = [];
        foreach (['scope', 'state', 'priority', 'keyword', 'due', 'sort', 'user_id', 'group_id'] as $key) {
            $default = $key === 'scope'
                ? FrameConfig::getConfigValue($this->frame_configs, 'todo_scope', 'mine')
                : ($key === 'state' ? 'open' : '');
            $filters[$key] = $request->input($prefix . $key, $default);
            if (!is_scalar($filters[$key])) {
                $filters[$key] = $default;
            }
        }
        $view_count = (int) FrameConfig::getConfigValue($this->frame_configs, 'todo_view_count', 10);
        $todos = (new TodoQueryService())->query([$list->id], $filters)
            ->paginate(max(1, min(100, $view_count)), ['*'], $prefix . 'page');
        $todos->appends($request->query())->fragment('frame-' . $frame_id);
        return $this->view('yuyutodo', compact('list', 'todos', 'filters', 'prefix'));
    }

    public function todoShow($request, $page_id, $frame_id, $id = null)
    {
        $list = $this->currentList();
        $todo = Todo::with('assignees')->where('list_id', $list->id)->findOrFail($id);
        $access = new TodoAccessService();
        abort_unless($access->canView($todo), 403);
        $users = User::whereIn('id', $todo->assignees->where('target_type', 'user')->pluck('target_id'))->get();
        $groups = Group::whereIn('id', $todo->assignees->where('target_type', 'group')->pluck('target_id'))->get();
        return $this->view('todo_detail', [
            'todo' => $todo, 'users' => $users, 'groups' => $groups,
            'can_edit' => $access->canEdit($todo), 'can_state' => $access->canChangeState($todo),
        ]);
    }

    public function todoCreate($request, $page_id, $frame_id)
    {
        $this->currentList();
        return $this->renderTodoInput(new Todo(['state' => 'pending', 'priority' => 'normal', 'is_private' => true]));
    }

    public function todoEdit($request, $page_id, $frame_id, $id = null)
    {
        $list = $this->currentList();
        $todo = Todo::with('assignees')->where('list_id', $list->id)->findOrFail($id);
        abort_unless((new TodoAccessService())->canEdit($todo), 403);
        return $this->renderTodoInput($todo);
    }

    private function renderTodoInput(Todo $todo)
    {
        return $this->view('todo_input', [
            'todo' => $todo,
            'users' => User::where('status', UserStatus::active)->orderBy('name')->get(),
            'groups' => Group::orderBy('name')->get(),
        ]);
    }

    public function todoSave($request, $page_id, $frame_id, $id = null)
    {
        $todo = (new TodoRecordService())->save($this->currentList(), $request->all(), $id ? (int) $id : null);
        return collect(['redirect_path' => $this->todoReturnUrl($request)
            ?: url('/plugin/yuyutodo/todoShow/' . $page_id . '/' . $frame_id . '/' . $todo->id) . '#frame-' . $frame_id]);
    }

    public function todoState($request, $page_id, $frame_id, $id = null)
    {
        $values = Validator::make($request->all(), ['state' => 'required|string'])->validate();
        (new TodoRecordService())->changeState($this->currentList(), (int) $id, $values['state']);
        return collect(['redirect_path' => $this->todoReturnUrl($request)
            ?: url('/plugin/yuyutodo/todoShow/' . $page_id . '/' . $frame_id . '/' . $id) . '#frame-' . $frame_id]);
    }

    public function todoDelete($request, $page_id, $frame_id, $id = null)
    {
        (new TodoRecordService())->delete($this->currentList(), (int) $id);
        return collect(['redirect_path' => $this->todoReturnUrl($request) ?: url($this->page->permanent_link) . '#frame-' . $frame_id]);
    }

    private function todoReturnUrl($request): ?string
    {
        return class_exists(CalendarReturnService::class)
            ? CalendarReturnService::fromRequest($request, (int) $this->frame->id) : null;
    }

    public function listBuckets($request, $page_id, $frame_id, $id = null)
    {
        $lists = TodoList::orderBy('name')->get()->filter(function ($list) {
            return (new TodoAccessService())->canViewList($list);
        });
        return $this->view('list_buckets', compact('lists'));
    }

    public function createBuckets($request, $page_id, $frame_id)
    {
        return $this->editBuckets($request, $page_id, $frame_id);
    }

    public function editBuckets($request, $page_id, $frame_id)
    {
        $list = $this->action === 'createBuckets' ? new TodoList()
            : TodoList::firstOrNew(['bucket_id' => $this->frame->bucket_id]);
        if ($list->exists) {
            abort_unless((new TodoAccessService())->canViewList($list), 403);
        }
        return $this->view('bucket', compact('list'));
    }

    public function saveBuckets($request, $page_id, $frame_id, $bucket_id = null)
    {
        // A URL-supplied bucket must never permit editing a different frame's list.
        abort_if($bucket_id && (int) $bucket_id !== (int) $this->frame->bucket_id, 403);
        $data = Validator::make($request->all(), [
            'name' => 'required|string|max:255', 'todo_scope' => 'required|in:mine,all',
            'todo_view_count' => 'required|integer|min:1|max:100',
        ])->validate();
        if ($bucket_id) {
            $list = $this->currentList();
            // Only a frame on the access-page may rename this shared list.
            abort_unless((int) $list->access_page_id === (int) $page_id, 403);
        }
        DB::transaction(function () use ($data, $bucket_id, $page_id, $frame_id) {
            $bucket = Buckets::updateOrCreateWithDefaultPostRoles(
                ['id' => $bucket_id], ['bucket_name' => $data['name'], 'plugin_name' => 'yuyutodo']
            );
            TodoList::updateOrCreate(['bucket_id' => $bucket->id], $bucket_id
                ? ['name' => $data['name']] : ['name' => $data['name'], 'access_page_id' => $page_id]);
            Frame::findOrFail($frame_id)->update(['bucket_id' => $bucket->id]);
            foreach (['todo_scope', 'todo_view_count'] as $name) {
                FrameConfig::updateOrCreate(['frame_id' => $frame_id, 'name' => $name], ['value' => $data[$name]]);
            }
        });
        return collect(['redirect_path' => url('/plugin/yuyutodo/editBuckets/' . $page_id . '/' . $frame_id) . '#frame-' . $frame_id]);
    }

    public function editView($request, $page_id, $frame_id)
    {
        return $this->view('frame_settings');
    }

    public function todoSaveView($request, $page_id, $frame_id)
    {
        $data = Validator::make($request->all(), [
            'todo_scope' => 'required|in:mine,all',
            'todo_view_count' => 'required|integer|min:1|max:100',
        ])->validate();
        DB::transaction(function () use ($data, $frame_id) {
            foreach ($data as $name => $value) {
                FrameConfig::updateOrCreate(['frame_id' => $frame_id, 'name' => $name], ['value' => $value]);
            }
        });
        return collect(['redirect_path' => url('/plugin/yuyutodo/editView/' . $page_id . '/' . $frame_id) . '#frame-' . $frame_id]);
    }

    public function changeBuckets($request, $page_id, $frame_id)
    {
        $data = Validator::make($request->all(), ['select_bucket' => 'required|integer'])->validate();
        $list = TodoList::where('bucket_id', $data['select_bucket'])->firstOrFail();
        abort_unless((new TodoAccessService())->canViewList($list), 403);
        Frame::findOrFail($frame_id)->update(['bucket_id' => $list->bucket_id]);
        return collect(['redirect_path' => url('/plugin/yuyutodo/listBuckets/' . $page_id . '/' . $frame_id) . '#frame-' . $frame_id]);
    }

    public function destroyBuckets($request, $page_id, $frame_id, $id)
    {
        // Deleting a frame must not implicitly destroy other users' Todo records.
        abort(403, 'Todo一覧の一括削除には対応していません。Todoを個別に削除してください。');
    }
}
