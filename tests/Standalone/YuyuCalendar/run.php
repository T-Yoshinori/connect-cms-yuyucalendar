<?php
// CMS adapters intentionally share this isolated entry point and must never autoload in the application.
// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
namespace { require __DIR__ . '/vendor/autoload.php';
}

// Separate process: CMS access/session models are test adapters. Calendar SQL and Todo services are real.
namespace App {
    trait UserableNohistory
    {
    }
    class User
    {
        public $id = 1;
        public $user_roles = ['base' => ['role_guest' => 1]];
        public function getGroupUsers()
        {
            return collect([(object) ['group_id' => 1]]);
        }
        public function __get($name)
        {
            return $name === 'group_users' ? $this->getGroupUsers() : null;
        }
        // Model the real CMS role Gate: it ignores supplied frame args and reads the request page.
        public function can($role, $args = null)
        {
            return in_array($role, \App\Models\Common\Page::$roles[4] ?? [], true);
        }
    }
}

namespace App\Models\Common {
    class Page extends \Illuminate\Database\Eloquent\Model
    {
        public static $roles = [];
        public $timestamps = false;
        protected $guarded = [];
        public function getPageTreeByGoingBackParent($tree)
        {
            return collect([$this]);
        }
        public function isVisibleAncestorsAndSelf($tree)
        {
            return (bool) $this->getAttribute('visible');
        }
        public function isRequestPassword($request, $tree)
        {
            return (bool) $this->password_required;
        }
        public function getPageRolesAttribute()
        {
            return collect(array_map(function ($role) {
                return (object) ['group_id' => 1, 'role' => $role];
            }, self::$roles[$this->id] ?? []));
        }
    }
    class PageRole
    {
        public static function rolesToArray($roles)
        {
            $base = [];
            foreach ($roles as $entry) {
                $base[$entry->role] = 1;
            }
            return $base ? ['base' => $base] : [];
        }
    }
    class Frame extends \Illuminate\Database\Eloquent\Model
    {
        public $timestamps = false;
        protected $guarded = [];
        public function page()
        {
            return $this->belongsTo(Page::class);
        }
        public function isVisible($page, $user)
        {
            return !$this->page_hidden;
        }
        public function isInvisiblePrivateFrame()
        {
            return (bool) $this->private_hidden;
        }
        public function isExpandNarrow()
        {
            return false;
        }
        public function getSettingButtonCaptionClass($size = null)
        {
            return '';
        }
    }
    class Group extends \Illuminate\Database\Eloquent\Model
    {
        use \Illuminate\Database\Eloquent\SoftDeletes;
        public $timestamps = false;
        protected $guarded = [];
    }
    class GroupUser extends Group
    {
        protected $table = 'group_users';
    }
}

namespace App\Models\Core {
    class Configs
    {
        public static function getSharedConfigs()
        {
            return new \Illuminate\Database\Eloquent\Collection();
        }
    }
}

namespace {
    use Illuminate\Container\Container;
    use Illuminate\Database\Capsule\Manager as Capsule;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Facade;
    use Illuminate\Support\Carbon;
    use App\Models\Common\Frame;
    use App\Models\Common\Page;
    use App\Models\User\Calendars\Calendar;
    use App\Models\User\Calendars\CalendarPost;
    use App\Models\User\YuyuTodo\TodoList;
    use App\Models\User\YuyuTodo\Todo;
    use App\Plugins\User\Yuyucalendar\Services\CalendarProviderService;
    use App\Plugins\User\Yuyucalendar\Services\CalendarSourceService;
    use App\Plugins\User\Yuyucalendar\Services\CalendarPeriodService;
    use App\Plugins\User\Yuyucalendar\YuyucalendarPlugin;

    function config($key)
    {
        if ($key === 'cc_role.CC_ROLE_HIERARCHY') {
            return ['role_reporter' => ['role_reporter', 'role_article', 'role_article_admin'], 'role_approval' => ['role_approval', 'role_article_admin'],
                'role_article' => ['role_article', 'role_article_admin'], 'role_article_admin' => ['role_article_admin']];
        }
        return $key === 'app.timezone' ? 'Asia/Tokyo' : null;
    }
    function now()
    {
        return Carbon::now('Asia/Tokyo');
    }
    function today()
    {
        return now()->startOfDay();
    }
    function request()
    {
        return $GLOBALS['calendar_test_request'];
    }
    function app($key)
    {
        return $key === \Illuminate\Http\Request::class ? request() : Container::getInstance()->make($key);
    }
    function url($path = '')
    {
        return 'https://example.test/cms/' . ltrim($path, '/');
    }
    function abort_unless($condition, $status)
    {
        if (!$condition) {
            throw new \RuntimeException((string) $status);
        }
    }
    function csrf_field()
    {
        return new \Illuminate\Support\HtmlString('<input type="hidden" name="_token" value="test">');
    }
    function session()
    {
        return new class {
            public function has($name)
            {
                return false;
            }
        };
    }
    function auth()
    {
        return \Illuminate\Support\Facades\Auth::getFacadeRoot();
    }

    function old($key, $default = null)
    {
        return $GLOBALS['calendar_test_old'][$key] ?? $default;
    }
    function view($name, $data = [])
    {
        return $GLOBALS['calendar_test_views']->make($name, $data);
    }
    function resource_path()
    {
        return dirname(__DIR__, 3) . '/resources';
    }
    function __($key)
    {
        return $key === 'messages.to_list' ? '一覧に戻る' : $key;
    }

    $root = dirname(__DIR__, 3);
    spl_autoload_register(function ($class) use ($root) {
        if (strpos($class, 'App\\') === 0) {
            $file = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
    class_alias(\Illuminate\Support\Facades\Request::class, 'Request');
    date_default_timezone_set('Asia/Tokyo');
    Carbon::setTestNow(Carbon::parse('2026-10-03 15:00:00', 'Asia/Tokyo'));
    $container = new Container();
    Container::setInstance($container);
    $capsule = new Capsule($container);
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    $container->instance('db', $capsule->getDatabaseManager());
    $container->bind('db.schema', function () use ($capsule) {
        return $capsule->schema();
    });
    $user = new \App\User();
    $auth = new class($user) {
        public $current;
        public function __construct($user)
        {
            $this->current = $user;
        }
        public function check()
        {
            return $this->current !== null;
        }
        public function id()
        {
            return $this->current ? $this->current->id : null;
        }
        public function user()
        {
            return $this->current;
        }
    };
    $container->instance('auth', $auth);
    $request = new \Illuminate\Http\Request();
    $request->attributes->set('frame_configs', new \Illuminate\Database\Eloquent\Collection());
    $GLOBALS['calendar_test_request'] = $request;
    $container->instance('request', $request);
    $translator = new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'en');
    $container->instance('validator', new \Illuminate\Validation\Factory($translator, $container));
    $container->instance('files', new \Illuminate\Filesystem\Filesystem());
    $container->instance('encrypter', new \Illuminate\Encryption\Encrypter(str_repeat('k', 32), 'AES-256-CBC'));
    $container->instance(\Illuminate\Contracts\Auth\Access\Gate::class, new class {
        public function check($ability, $args = [])
        {
            return false;
        }
    });
    Facade::setFacadeApplication($container);
    require $root . '/database/migrations/2026_10_03_000001_create_yuyu_todo_tables.php';
    (new \CreateYuyuTodoTables())->up();
    foreach (['pages', 'frames', 'groups', 'group_users', 'calendars', 'buckets', 'calendar_posts', 'frame_configs', 'calendar_frames'] as $table) {
        $capsule->schema()->create($table, function (Blueprint $b) use ($table) {
            $b->increments('id');
            if ($table === 'pages') {
                $b->string('page_name');
                $b->string('permanent_link')->default('portal');
                $b->boolean('visible')->default(true);
                $b->boolean('password_required')->default(false);
            }
            if ($table === 'frames') {
                $b->integer('page_id');
                $b->integer('bucket_id')->nullable();
                $b->string('plugin_name');
                $b->string('template')->default('default');
                $b->boolean('page_hidden')->default(false);
                $b->boolean('private_hidden')->default(false);
            }
            if ($table === 'group_users') {
                $b->integer('user_id');
                $b->integer('group_id');
            }
            if ($table === 'calendars') {
                $b->integer('bucket_id');
                $b->string('name');
            }
            if ($table === 'buckets') {
                $b->string('bucket_name');
                $b->string('plugin_name');
            }
            if ($table === 'frame_configs') {
                $b->integer('frame_id');
                $b->string('name');
                $b->text('value');
            }
            if ($table === 'calendar_posts') {
                $b->integer('calendar_id');
                $b->string('title');
                $b->integer('allday_flag')->default(0);
                $b->date('start_date');
                $b->date('end_date');
                $b->time('start_time')->nullable();
                $b->time('end_time')->nullable();
                $b->integer('status')->default(0);
                $b->integer('created_id')->default(2);
                $b->text('body')->nullable();
                $b->string('location')->nullable();
                $b->string('contact')->nullable();
                $b->string('repeat_group_id')->nullable();
            }
            if ($table === 'calendar_frames') {
                $b->integer('calendar_id');
                $b->integer('frame_id');
            }
            $b->timestamps();
            $b->softDeletes();
        });
    }
    Page::create(['id' => 1, 'page_name' => '公開']);
    Page::create(['id' => 2, 'page_name' => '非公開', 'visible' => false]);
    Page::create(['id' => 3, 'page_name' => 'パスワード', 'password_required' => true]);
    Page::create(['id' => 4, 'page_name' => 'ポータル']);
    Page::create(['id' => 5, 'page_name' => '同じカレンダーの別ページ']);
    $request->attributes->set('page', Page::find(4));
    $capsule->table('buckets')->insert(['id' => 1, 'bucket_name' => '予定', 'plugin_name' => 'calendars']);
    $calendar = Calendar::create(['bucket_id' => 1, 'name' => '全体予定']);
    $list = TodoList::create(['bucket_id' => 2, 'name' => 'ToDo', 'access_page_id' => 1]);
    foreach ([1 => [1, 1, 'calendars'], 2 => [1, 2, 'yuyutodo'], 3 => [2, 1, 'calendars'], 4 => [3, 1, 'calendars'], 5 => [1, 1, 'calendars'], 6 => [1, 1, 'calendars'], 7 => [1, 2, 'yuyutodo'], 8 => [1, null, 'yuyucalendar']] as $id => $values) {
        Frame::create(['id' => $id, 'page_id' => $values[0], 'bucket_id' => $values[1], 'plugin_name' => $values[2]]);
    }
    Frame::find(5)->update(['private_hidden' => true]);
    Frame::find(6)->update(['page_hidden' => true]);
    Frame::find(8)->update(['page_id' => 4]);
    \App\Models\User\Calendars\CalendarFrame::create(['calendar_id' => $calendar->id, 'frame_id' => 1]);
    $checks = 0;
    function check($value, $label)
    {
        if (!$value) {
            throw new \RuntimeException($label);
        }
        $GLOBALS['checks']++;
        echo "PASS $label\n";
    }
    function calendarPost($calendar, $title, $from, $until, $status = 0, $creator = 2, $all_day = 0)
    {
        $post = new CalendarPost(['calendar_id' => $calendar->id, 'title' => $title, 'start_date' => $from, 'end_date' => $until,
            'start_time' => '09:00:00', 'end_time' => '10:00:00', 'allday_flag' => $all_day]);
        $post->status = $status;
        $post->created_id = $creator;
        $post->save();
        return $post;
    }
    function todo($list, $title, array $values = [])
    {
        $todo = new Todo(array_merge(['title' => $title, 'state' => 'pending', 'priority' => 'normal', 'is_private' => false], $values));
        $todo->list_id = $list->id;
        $todo->created_by = 1;
        $todo->updated_by = 1;
        $todo->save();
        return $todo;
    }
    $cross = calendarPost($calendar, '<script>test</script>', '2026-09-30', '2026-10-02', 0, 2, 1);
    $ownDraft = calendarPost($calendar, '自分の一時保存', '2026-10-03', '2026-10-03', 1, 1);
    calendarPost($calendar, '他人の一時保存', '2026-10-03', '2026-10-03', 1);
    calendarPost($calendar, '承認待ち', '2026-10-03', '2026-10-03', 2);
    calendarPost($calendar, '期間外', '2026-11-01', '2026-11-01');
    $provider = new CalendarProviderService();
    $resolver = new CalendarSourceService();
    check($resolver->available([1, 2, 3, 4, 5, 6])->keys()->all() === [1, 2], 'hidden pages, password pages and hidden frames are excluded');
    check($provider->events([1, 3, 4, 5, 6], '2026-10-01', '2026-10-31')->count() === 1, 'reader sees active overlapping Calendar posts only');
    Page::$roles[4] = ['role_article_admin'];
    check($provider->events([1], '2026-10-01', '2026-10-31')->count() === 1, 'aggregate-frame admin cannot expose original-frame drafts');
    Page::$roles[1] = ['role_reporter'];
    check($provider->events([1], '2026-10-01', '2026-10-31')->count() === 2, 'original-frame reporter sees own draft');
    Page::$roles[1] = ['role_approval'];
    check($provider->events([1], '2026-10-01', '2026-10-31')->count() === 2, 'original-frame approver sees pending posts');
    Page::$roles[1] = [];
    Frame::create(['id' => 9, 'page_id' => 5, 'bucket_id' => 1, 'plugin_name' => 'calendars']);
    Page::$roles[5] = ['role_reporter'];
    check($provider->events([1, 9], '2026-10-01', '2026-10-31')->count() === 2, 'duplicate Calendar frames union visible posts under each original role');
    $scheduled = todo($list, '予定と期限', ['starts_at' => '2026-09-30 14:00:00', 'ends_at' => '2026-10-02 16:00:00', 'due_date' => '2026-10-03']);
    $point = todo($list, '開始のみ', ['starts_at' => '2026-10-04 10:00:00']);
    todo($list, '古い開始のみ', ['starts_at' => '2026-09-01 10:00:00']);
    $other_private = todo($list, '他人の秘密', ['due_date' => '2026-10-03', 'is_private' => true]);
    $other_private->created_by = 2;
    $other_private->save();
    $other_private->assignees()->create(['target_type' => 'user', 'target_id' => 1]);
    $private = todo($list, '自分の秘密', ['due_date' => '2026-10-03', 'is_private' => true]);
    todo($list, '完了', ['due_date' => '2026-10-03', 'state' => 'completed']);
    $unrelated = todo($list, '他人の共有', ['due_date' => '2026-10-03']);
    $unrelated->created_by = 2;
    $unrelated->save();
    $events = $provider->events([2], '2026-10-01', '2026-10-31');
    check($events->count() === 4, 'Todo schedule and deadline separate; private/closed/unrelated excluded');
    check($events->where('type', 'todo_schedule')->count() === 2, 'cross-month schedule and start-only point are visible');
    check($provider->events([1, 2, 7], '2026-10-01', '2026-10-31')->count() === 5, 'same Todo bucket on two frames does not duplicate records');
    $all = $provider->events([2], '2026-10-01', '2026-10-31', ['scope' => 'all', 'state' => 'all']);
    check($all->count() === 6 && !$all->contains('title', '他人の秘密'), 'all scope/state still excludes another user private Todo');
    $group = \App\Models\Common\Group::create(['id' => 1]);
    \App\Models\Common\GroupUser::create(['user_id' => 1, 'group_id' => 1]);
    $groupTodo = todo($list, 'グループ担当', ['due_date' => '2026-10-03']);
    $groupTodo->created_by = 2;
    $groupTodo->save();
    $groupTodo->assignees()->create(['target_type' => 'group', 'target_id' => 1]);
    check($provider->events([2], '2026-10-01', '2026-10-31')->contains('title', 'グループ担当'), 'current group assignments included');
    \App\Models\Common\GroupUser::where('user_id', 1)->delete();
    check(!$provider->events([2], '2026-10-01', '2026-10-31')->contains('title', 'グループ担当'), 'group departure applied immediately');
    $scheduled->delete();
    check(!$provider->events([2], '2026-10-01', '2026-10-31')->contains('title', '予定と期限'), 'deleted Todo disappears');
    $cross->delete();
    check($provider->events([1], '2026-10-01', '2026-10-31')->isEmpty(), 'deleted Calendar posts disappear');
    $cross->restore();
    $scheduled->restore();
    for ($i = 0; $i < 31; $i++) {
        todo($list, '多件数' . $i, ['due_date' => '2026-10-20']);
    }
    check($provider->events([2], '2026-10-01', '2026-10-31')->count() === 35, 'calendar range is not limited to portal first ten records');
    for ($i = 0; $i < 25; $i++) {
        todo($list, '日付なし' . $i);
    }
    $none = $provider->unscheduled([2]);
    check($none->total() === 25 && $none->count() === 20, 'unscheduled Todo pagination has accurate totals');
    check($provider->unscheduled([2, 7])->total() === 25, 'unscheduled duplicate source keeps unique records');
    $list->access_page_id = 2;
    $list->save();
    check($provider->events([2], '2026-10-01', '2026-10-31')->isEmpty(), 'Todo original access page enforced despite visible linked frame');
    $list->access_page_id = 1;
    $list->save();
    $auth->current = null;
    check($provider->events([1, 2], '2026-10-01', '2026-10-31')->count() === 1, 'guest gets public Calendar only, no Todo');
    $auth->current = $user;
    $periods = new CalendarPeriodService();
    $month = $periods->period('2026-10-31', 'month');
    check($month['from']->toDateString() === '2026-09-27' && $month['until']->toDateString() === '2026-10-31', 'month grid begins Sunday and ends Saturday');
    check($month['next']->toDateString() === '2026-11-01', 'month navigation does not skip February/short months');
    $week = $periods->period('2026-12-31', 'week');
    check($week['from']->toDateString() === '2026-12-27' && $week['until']->toDateString() === '2027-01-02', 'week range crosses year correctly');
    $days = $periods->byDay($provider->events([1], '2026-09-27', '2026-10-31'), '2026-09-27', '2026-10-31');
    check($days['2026-10-02']->count() === 1 && $days['2026-10-03']->isEmpty(), 'inclusive Calendar end date appears exactly through last day');
    try {
        $provider->events([1], '2026-01-01', '2026-12-31');
        throw new \LogicException('unbounded request');
    } catch (\RuntimeException $e) {
        check($e->getMessage() === '422', 'unbounded range rejected');
    }

    // Compile and render real plugin blades with Laravel 8, using minimal CMS layout shells.
    $cache = sys_get_temp_dir() . '/yuyu-calendar-blade-tests';
    @mkdir($cache);
    $compiler = new \Illuminate\View\Compilers\BladeCompiler(new \Illuminate\Filesystem\Filesystem(), $cache);
    $files = iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/resources/views/plugins/user/yuyucalendar')));
    foreach (array_merge(
        glob($root . '/resources/views/plugins/user/calendars/yuyucalendar/*.blade.php'),
        [$root . '/resources/views/plugins/user/calendars/yuyucalendar_return_scripts.blade.php'],
        glob($root . '/resources/views/plugins/user/yuyutodo/default/todo_*.blade.php')
    ) as $path) {
        $files[] = new \SplFileInfo($path);
    }
    foreach ($files as $file) {
        if (substr($file->getFilename(), -10) !== '.blade.php') {
            continue;
        }
        $compiled = $cache . '/' . md5($file->getPathname()) . '.php';
        file_put_contents($compiled, $compiler->compileString(file_get_contents($file->getPathname())));
        exec(escapeshellarg(PHP_BINARY) . ' -n -l ' . escapeshellarg($compiled), $output, $status);
        check($status === 0, 'Blade syntax ' . $file->getFilename());
    }
    $shell = $cache . '/shell';
    @mkdir($shell . '/core', 0777, true);
    @mkdir($shell . '/plugins/common', 0777, true);
    file_put_contents($shell . '/core/cms_frame_base.blade.php', '@yield("plugin_contents_".$frame->id)');
    file_put_contents($shell . '/core/cms_frame_base_setting.blade.php', '@yield("plugin_setting_".$frame->id)');
    file_put_contents($shell . '/plugins/common/errors_form_line.blade.php', '');
    file_put_contents($shell . '/plugins/common/user_paginate.blade.php', '<nav>{{ $posts->total() }}件</nav>');
    $engine = new \Illuminate\View\Engines\EngineResolver();
    $engine->register('blade', function () use ($compiler) {
        return new \Illuminate\View\Engines\CompilerEngine($compiler);
    });
    $views = new \Illuminate\View\Factory($engine, new \Illuminate\View\FileViewFinder(new \Illuminate\Filesystem\Filesystem(), [$shell, $root . '/resources/views']), new \Illuminate\Events\Dispatcher());
    $GLOBALS['calendar_test_views'] = $views;
    $renderPeriod = $periods->period('2026-10-03', 'month');
    $renderEvents = $provider->events([1, 2], '2026-09-27', '2026-10-31');
    $daily = $periods->byDay($renderEvents, '2026-09-27', '2026-10-31');
    $dates = [];
    foreach ($daily as $key => $value) {
        $dates[$key] = new \App\Models\Common\ConnectCarbon($key);
    }
    $args = ['page' => Page::find(1), 'frame' => Frame::find(8), 'sources' => $resolver->available([1, 2]), 'selected' => [1, 2], 'prefix' => 'ycal_8_',
        'filters' => ['date' => '2026-10-03', 'view' => 'month', 'scope' => 'mine', 'state' => 'open'], 'period' => $renderPeriod,
        'events' => $renderEvents, 'daily_events' => $daily, 'dates' => $dates, 'unscheduled' => $none,
        'link' => function ($changes) {
            return url('portal') . '?' . http_build_query($changes);
        }];
    foreach (['month', 'week', 'day', 'list'] as $mode) {
        $args['filters']['view'] = $mode;
        $html = $views->make('plugins.user.yuyucalendar.default.index', $args)->render();
        check(strpos($html, '<script>test</script>') === false && strpos($html, '&lt;script&gt;test&lt;/script&gt;') !== false, 'escaped event titles in ' . $mode);
        check(strpos($html, 'ToDo期限') !== false && strpos($html, '日付未設定のToDo') !== false, 'deadline and unscheduled rendering in ' . $mode);
    }
    $html = $views->make('plugins.user.yuyucalendar.default.frame_settings', [
        'page' => Page::find(1), 'frame' => Frame::find(8), 'sources' => $resolver->available(),
        'settings' => ['sources' => [1, 2], 'view' => 'month', 'scope' => 'mine', 'state' => 'open'],
    ])->render();
    check(strpos($html, 'name="_token"') !== false && strpos($html, 'calendar_sources[]') !== false, 'settings has CSRF and multiple source selection');
    $plugin = new YuyucalendarPlugin(Page::find(4), Frame::find(8));
    $request->setMethod('POST');
    $request->request->replace(['calendar_sources' => [], 'calendar_view' => 'month', 'calendar_todo_scope' => 'mine', 'calendar_todo_state' => 'open']);
    $plugin->calendarSaveView($request, 1, 8);
    check($capsule->table('frame_configs')->where('frame_id', 8)->where('name', 'calendar_sources')->value('value') === '', 'saving empty selection clears all saved sources');
    $request->request->set('calendar_sources', [3]);
    try {
        $plugin->calendarSaveView($request, 1, 8);
        throw new \LogicException('hidden source saved');
    } catch (\RuntimeException $e) {
        check($e->getMessage() === '403', 'saving hidden source IDs is rejected');
    }
    // Exercise the plugin GET boundary. Holiday loading alone is replaced in this isolated suite.
    $index = new class(Page::find(4), Frame::find(8)) extends YuyucalendarPlugin {
        protected function addHolidaysFromTo(\App\Models\Common\ConnectCarbon $start, \App\Models\Common\ConnectCarbon $end, array $dates): array
        {
            return $dates;
        }
    };
    \App\Models\Core\FrameConfig::where('frame_id', 8)->where('name', 'calendar_sources')->update(['value' => '1|2']);
    $index->frame_configs = \App\Models\Core\FrameConfig::where('frame_id', 8)->get();
    $request->setMethod('GET');
    $request->query->replace(['ycal_8_date' => '2026-10-03', 'ycal_8_selected' => 1, 'ycal_8_sources' => [1, 9], 'ycal_10_sources' => [2], 'ycal_10_date' => '2027-01-03']);
    $pageView = $index->index($request, 4, 8);
    check($pageView->getData()['selected'] === [1] && $pageView->getData()['events']->count() === 1, 'GET source IDs restricted to saved configuration even when another source is accessible');
    check(strpos($pageView->render(), 'name="ycal_10_sources[0]"') !== false, 'filter form preserves other frame source selection');
    $event_url = $pageView->getData()['events']->first()['url'];
    parse_str(parse_url($event_url, PHP_URL_QUERY), $return_params);
    $return_service = new \App\Plugins\User\Yuyucalendar\Services\CalendarReturnService();
    $return_url = $return_service->resolve($return_params['ycal_return'] ?? null, 1);
    check($return_url !== null && substr($return_url, -8) === '#frame-8', 'Calendar detail link returns to clicked aggregate frame');
    parse_str(parse_url($return_url, PHP_URL_QUERY), $return_query);
    check($return_query['ycal_8_date'] === '2026-10-03' && $return_query['ycal_8_sources'] === ['1']
        && $return_query['ycal_10_date'] === '2027-01-03' && $return_query['ycal_10_sources'] === ['2'], 'return context preserves date, selected sources and other aggregate frame state');
    check($return_service->resolve($return_params['ycal_return'], 9) === null, 'return context cannot be reused for another source frame');
    check($return_service->resolve('tampered', 1) === null && $return_service->resolve([], 1) === null, 'invalid and malformed return tokens safely fall back');
    $external = $return_service->token('https://external.example/portal', 1);
    check($return_service->resolve($external, 1) === null, 'return context cannot link to an external origin');
    $outside = $return_service->token('https://example.test/other-cms/portal', 1);
    check($return_service->resolve($outside, 1) === null, 'return context stays inside CMS installation path');
    $detail_args = ['page' => Page::find(1), 'frame' => Frame::find(1), 'frame_id' => 1, 'post' => $cross, 'buckets' => null];
    $request->query->set('ycal_return', $return_params['ycal_return']);
    $detail_html = $views->make('plugins.user.calendars.yuyucalendar.show', $detail_args)->render();
    check(strpos($detail_html, 'href="' . e($return_url) . '"') !== false, 'custom Calendar detail template renders aggregate return link');
    $request->query->remove('ycal_return');
    $direct_html = $views->make('plugins.user.calendars.yuyucalendar.show', $detail_args)->render();
    check(strpos($direct_html, 'href="' . url('portal') . '#frame-1"') !== false, 'direct Calendar detail keeps original Calendar return link');
    $request->query->set('ycal_8_sources', [2]);
    $todo_view = $index->index($request, 4, 8)->getData();
    $todo_event = $todo_view['events']->first();
    parse_str(parse_url($todo_event['url'], PHP_URL_QUERY), $todo_return);
    check($return_service->resolve($todo_return['ycal_return'] ?? null, 2) !== null, 'dated Todo detail receives aggregate return context');
    $no_date_todo = $todo_view['unscheduled']->first();
    parse_str(parse_url($no_date_todo->calendar_detail_url, PHP_URL_QUERY), $no_date_return);
    check($return_service->resolve($no_date_return['ycal_return'] ?? null, 2) !== null, 'unscheduled Todo detail receives aggregate return context');
    $todo_detail_args = ['page' => Page::find(1), 'frame' => Frame::find(2), 'todo' => $no_date_todo,
        'users' => collect(), 'groups' => collect(), 'can_state' => false, 'can_edit' => false];
    $request->query->set('ycal_return', $no_date_return['ycal_return']);
    $todo_input_html = $views->make('plugins.user.yuyutodo.default.todo_input', $todo_detail_args + ['errors' => new \Illuminate\Support\ViewErrorBag()])->render();
    check(strpos($todo_input_html, 'name="ycal_return"') !== false
        && strpos($todo_input_html, 'href="' . e($return_service->resolve($no_date_return['ycal_return'], 2)) . '"') !== false, 'Todo edit preserves return context and cancel destination');
    $GLOBALS['calendar_test_old']['ycal_return'] = $no_date_return['ycal_return'];
    $request->query->remove('ycal_return');
    $todo_error_html = $views->make('plugins.user.yuyutodo.default.todo_input', $todo_detail_args + ['errors' => new \Illuminate\Support\ViewErrorBag()])->render();
    check(strpos($todo_error_html, 'name="ycal_return"') !== false, 'Todo validation old input preserves return context');
    unset($GLOBALS['calendar_test_old']['ycal_return']);
    $request->query->set('ycal_return', $no_date_return['ycal_return']);
    $todo_detail_html = $views->make('plugins.user.yuyutodo.default.todo_detail', $todo_detail_args)->render();
    check(strpos($todo_detail_html, 'href="' . e($return_service->resolve($no_date_return['ycal_return'], 2)) . '"') !== false, 'Todo detail template renders clicked aggregate return link');
    $request->query->remove('ycal_return');
    $todo_direct_html = $views->make('plugins.user.yuyutodo.default.todo_detail', $todo_detail_args)->render();
    check(strpos($todo_direct_html, 'href="' . url('portal') . '#frame-2"') !== false, 'direct Todo detail keeps original Todo return link');
    $request->query->set('ycal_8_sources', []);
    $empty = $index->index($request, 4, 8)->getData();
    check($empty['events']->isEmpty() && $empty['unscheduled'] === null, 'all sources OFF does not fall back to saved sources');
    $request->query->set('ycal_8_date', ['2026-10-03']);
    try {
        $index->index($request, 4, 8);
        throw new \LogicException('array date accepted');
    } catch (\Illuminate\Validation\ValidationException $e) {
        check(true, 'malformed GET date rejected');
    }
    $todo_plugin = new \App\Plugins\User\Yuyutodo\YuyutodoPlugin(Page::find(1), Frame::find(2));
    $request->setMethod('POST');
    $request->query->replace([]);
    $request->request->replace(['title' => '編集後の件名', 'state' => 'pending', 'priority' => 'normal', 'is_private' => 0,
        'ycal_return' => $no_date_return['ycal_return']]);
    $saved = $todo_plugin->todoSave($request, 1, 2, $no_date_todo->id);
    check($saved['redirect_path'] === $return_service->resolve($no_date_return['ycal_return'], 2)
        && $no_date_todo->fresh()->title === '編集後の件名', 'Todo save persists edited content and returns to aggregate');
    $request->request->set('title', '');
    try {
        $todo_plugin->todoSave($request, 1, 2, $no_date_todo->id);
        throw new \LogicException('invalid Todo saved');
    } catch (\Illuminate\Validation\ValidationException $e) {
        check($no_date_todo->fresh()->title === '編集後の件名', 'Todo validation error does not save or redirect');
    }
    $request->request->set('title', '直接編集');
    $request->request->set('ycal_return', 'invalid');
    check($todo_plugin->todoSave($request, 1, 2, $no_date_todo->id)['redirect_path']
        === url('/plugin/yuyutodo/todoShow/1/2/' . $no_date_todo->id) . '#frame-2', 'Todo invalid return context keeps standard save destination');
    echo "Completed {$checks} checks. CMS Page/Frame/Gate adapters and SQLite only; real CMS routing/MySQL/browser remain manual checks.\n";
}
