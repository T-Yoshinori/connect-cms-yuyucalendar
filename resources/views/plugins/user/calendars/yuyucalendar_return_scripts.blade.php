<script>
(function () {
    var key = 'yuyucalendar_pending_return_{{ (int) $frame_id }}';
    var mode = @json($calendar_return_mode);
    var returnUrl = @json($calendar_return_url ?? null);
    var baseUrl = @json(url('/'));
    try {
        if (mode === 'edit') {
            sessionStorage.removeItem(key);
            if (!returnUrl) { return; }
            var arm = function () {
                try {
                    sessionStorage.setItem(key, JSON.stringify({url: returnUrl, savedAt: Date.now()}));
                } catch (error) { /* 保存自体は継続する */ }
            };
            window['yuyuCalendarArmReturn_{{ (int) $frame_id }}'] = arm;
            var form = document.forms['form_calendars_posts{{ (int) $frame_id }}'];
            if (form) { form.addEventListener('submit', arm); }
            return;
        }
        var stored = sessionStorage.getItem(key);
        sessionStorage.removeItem(key);
        if (!stored) { return; }
        var pending = JSON.parse(stored);
        var age = Date.now() - pending.savedAt;
        if (!Number.isFinite(age) || age < 0 || age > 300000 || typeof pending.url !== 'string') { return; }
        var target = new URL(pending.url);
        var base = new URL(baseUrl);
        var basePath = base.pathname.replace(/\/$/, '');
        if (target.origin !== base.origin || target.username || target.password) { return; }
        if (basePath && target.pathname !== basePath && target.pathname.indexOf(basePath + '/') !== 0) { return; }
        window.location.replace(target.href);
    } catch (error) { /* 一時情報を使えない場合は標準の一覧を表示する */ }
}());
</script>
