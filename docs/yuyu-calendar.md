# YuyuCalendar 仕様

## 目的

標準Calendarの複数カレンダーとYuyuToDoを、一つの画面に集約する独立プラグインです。予定やToDoは元のテーブルから直接取得し、集約用の予定データは保存しません。対応確認はConnect-CMS 1.44.1です。

## 表示と操作

| 項目 | 仕様 |
| --- | --- |
| 表示形式 | 月・週・日・一覧。週は日曜日から。一覧は選択月の日別一覧。狭い画面では月・週の表を横スクロール |
| 表示元 | フレーム編集の「集約・表示設定」でCalendar／YuyuToDoの元フレームを複数選択 |
| 一時的な表示切替 | 画面の「表示元を選択」でチェックして「表示」を押す。すべてOFFなら予定を表示しない |
| フレーム設定 | 表示元、初期表示形式、ToDoの初期表示対象（自分／閲覧可能）、初期表示状態（未完了／すべて） |
| Calendar | 元フレームで許可された公開・一時保存・承認待ちの予定を表示。保存済み繰り返し予定も通常の予定として表示 |
| ToDo予定 | 開始予定日時～終了予定日時を表示。開始のみは、その日時の予定として表示 |
| ToDo期限 | 期限日に「ToDo期限」として表示。予定期間と期限の両方を持つToDoはそれぞれ表示 |
| 日付未設定ToDo | 期限・開始・終了がすべて未設定のものをカレンダー下部に20件ずつ表示。表示対象・状態フィルターを共用 |
| 重複 | 同じCalendar／ToDo一覧を複数フレームから選択しても、同じ種類・IDの予定は一度だけ表示 |
| 詳細・登録・編集 | タイトルから元フレームの詳細画面へ。「元の一覧へ」から登録・編集を行う。共有ToDoの担当者は状態変更可能 |
| 祝日 | CMS標準の祝日取得処理を利用。独自祝日設定も共用 |

## 閲覧権限

表示元の候補・ラベルを返す前に、元ページと先祖ページの公開・閲覧権限、閲覧パスワード、元フレームの表示条件を確認します。設定に保存されたIDやURLのIDは閲覧権限を与えません。元フレームやカレンダーの削除、権限変更後は取得時に対象から外します。

Calendarの記事取得は標準の `appendAuthWhereBase()` を再利用します。ただしCMSの `role_*` Gateは渡されたフレーム引数を参照せず現在のリクエストページを参照するため、集約用アダプターでは標準の `checkRoleFromFrame()` を使って元フレームのページ権限を明示的に解決します。集約ページの管理権限による下書き・承認待ちの閲覧拡大を防ぎます。同じCalendarに異なるページからアクセスできる場合は、選択した各元フレームで許可された記事を合算して重複を除きます。

YuyuToDoは既存の `TodoQueryService` を利用し、一覧の基準ページ、非公開設定、現在の担当ユーザー・グループを取得時に確認します。担当者設定によるページ権限の拡大は行いません。「閲覧可能なToDo」を選択しても他人の非公開ToDoは表示しません。未ログインでは公開Calendarのみが対象です。

利用者のGETパラメーターによる表示元の切替は、管理者が保存した表示元と現在閲覧可能な表示元の共通部分に限定します。設定保存は `frames.edit` が必要です。複数YuyuCalendarフレームの表示日・表示形式・フィルター・ページングはフレームID付きパラメーターで分離します。

## データと構成

新しいテーブル・migrationは追加しません。フレームごとの設定は標準 `frame_configs` に保存します。

| 設定名 | 値 |
| --- | --- |
| `calendar_sources` | 表示元フレームIDのパイプ区切り。空文字は選択なし |
| `calendar_view` | `month` / `week` / `day` / `list` |
| `calendar_todo_scope` | `mine` / `all` |
| `calendar_todo_state` | `open` / `all` |

主なファイル:

- `app/Plugins/User/Yuyucalendar/YuyucalendarPlugin.php`: 表示・設定保存と入力検証。
- `app/Plugins/User/Yuyucalendar/Services/CalendarSourceService.php`: 元フレームの解決と閲覧確認。
- `app/Plugins/User/Yuyucalendar/Services/CalendarReadAdapter.php`: 標準Calendarの投稿権限の再利用。
- `app/Plugins/User/Yuyucalendar/Services/CalendarProviderService.php`: 予定・期限の共通形式への変換と日付未設定ToDoの取得。
- `app/Plugins/User/Yuyucalendar/Services/CalendarPeriodService.php`: 表示期間・日別割り当て。
- `resources/views/plugins/user/yuyucalendar/`: 表示・設定Blade。
- `database/seeders/YuyuCalendarPluginSeeder.php`: プラグインの登録。

共通イベントは `id/type/title/source_name/source_frame_id/start/end/all_day/status/url` を持ちます。`type` は `calendar` / `todo_schedule` / `todo_due`。標準Calendarの終了日は当日を含む仕様を保持します。日時はCMSのタイムゾーンで扱います。取得範囲は最大63日間とし、ポータル用の先頭10件取得による取りこぼしを避けています。

## 導入

配布ファイルの配置と任意のYuyuToDo連携更新は[README](../README.md)を参照してください。

YuyuCalendarの追加ファイルを同じパスに配置したうえで、CMSルートで実行します。

```bash
php artisan db:seed --class=YuyuCalendarPluginSeeder
php artisan view:clear
```

YuyuCalendar自体の導入に `migrate` は不要です。YuyuToDo連携にはYuyuToDoの導入とmigrationが必要です。YuyuToDoが未導入の場合はCalendarのみの集約ができます。

**統合対象になり得る標準Calendarの各フレームには、`yuyucalendar` テンプレート（表示名「YuyuCalendar連携（月表示）」）を適用する必要があります。** 詳細・編集・保存後にクリック元のYuyuCalendarへ戻るための設定です。適用するのは表示元の標準Calendarフレームで、YuyuCalendarフレームではありません。YuyuToDo側のテンプレート切替は不要です。

1. ページにYuyuCalendarフレームを追加する。
2. フレーム編集の「集約・表示設定」を開く。
3. 表示元のCalendar／YuyuToDo、初期表示形式、ToDo対象・状態を選択して保存する。
4. 利用者アカウントで表示を確認する。

## 検証

### 詳細画面からクリック元へ戻る設定

Calendar用テンプレートを `resources/views/plugins/user/calendars/yuyucalendar/` に追加しています。表示元の**標準Calendarフレーム**で、フレーム設定のテンプレートを **「YuyuCalendar連携（月表示）」** に切り替えてください。YuyuCalendarフレームのテンプレートを変更する操作ではありません。一覧の `index.blade.php`、詳細の `show.blade.php`、編集の `edit.blade.php` を差し替え、それ以外は標準Calendarのデフォルトテンプレートへフォールバックします。

YuyuCalendarの予定リンクには、クリック元のURL・表示条件と元フレームIDを暗号化した戻り先情報を付けます。詳細画面の「一覧に戻る」は、これを検証してクリック元のYuyuCalendarへ戻ります。表示日・月／週／日／一覧・ToDoの対象と状態・表示元選択・日付未設定ToDoのページ位置を保持します。元Calendarから直接開いた詳細、戻り先情報が不正な場合は元Calendarへ戻ります。戻り先は同一CMSのURLに限定します。

YuyuToDoは既存の `resources/views/plugins/user/yuyutodo/default/todo_detail.blade.php` を修正します。YuyuCalendarから開いた日付付きToDo・日付未設定ToDoの両方で、検証済みの戻り先があればクリック元へ戻り、なければ元ToDo一覧へ戻ります。YuyuToDo側のテンプレート切替は不要です。YuyuCalendarが未導入の場合も元の一覧へ戻る動作を保持します。

編集フォームまで検証済みの戻り先を引き継ぎ、保存成功後とキャンセル時にはクリック元のYuyuCalendarへ戻ります。保存後は元データを再取得するため変更内容が反映されます。表示期間外へ日時を移した予定、未完了表示で完了にしたToDo等は表示条件から外れます。入力エラー時は保存せず、編集画面で戻り先を保持します。YuyuToDoの状態変更・削除も戻り先を引き継ぎます。

標準Calendarの保存処理は変更しません。編集テンプレートで保存直前に検証済みの戻り先をタブ単位のsessionStorageへ一時保存します。保存後は標準Calendarの一覧（yuyucalendarテンプレート）を経由し、戻り先を読み取って削除したうえでYuyuCalendarへ移動します。入力エラーで編集画面に戻った場合も一時情報を削除します。一時情報には5分の有効期限を設けます。直接利用時は従来の遷移を維持します。YuyuToDoは独自プラグインの保存後の遷移先に検証済みの戻り先を使用します。

予定ごとのCalendar名・ToDo管理名は表示しません。表示元は選択欄・元一覧へのリンクで確認できます。予定の時間・タイトルとToDo予定／期限の区別は保持します。

配置後に `php artisan view:clear` を実行し、Calendar／ToDo／日付未設定ToDoそれぞれの「詳細 → 一覧に戻る」「詳細 → 編集 → 保存」「詳細 → 編集 → キャンセル」を確認してください。元ページの詳細画面へ直接アクセスした場合は、従来の遷移先へ戻ることも確認してください。

PHP 8.3とLaravel 8.83.27／SQLiteの独立テストを使用します。CMSのPage・Frame・ユーザー／ページ権限データはテスト用アダプターです。Calendarの記事取得SQL、標準の投稿状態フィルター・フレーム起点のロール解決Trait、YuyuToDoのサービス、Bladeコンパイラーとレンダラーは実コードを使います。

```bash
cd tests/Standalone/YuyuCalendar
composer install
composer test
node return-navigation.js
```

テストファイルはYuyuToDo導入済みのConnect-CMSルートへ同じパスで配置して実行します。公開リポジトリ単独ではCMS本体のクラスがないため実行できません。

独立テストは元ページ・パスワード・フレームの除外、集約ページの管理権限による下書き漏洩防止、元ページの編集者・承認者、重複、非公開ToDo、グループ退会、削除、期間重なり、開始のみ、月・年またぎ、日付未設定の総件数、複数件取得、Bladeのコンパイルとエスケープ、設定保存の空選択・不正表示元、戻り先の検証、ToDoの編集内容保存と遷移先を検証します。JavaScriptテストでは実テンプレートのスクリプトを使い、通常保存・一時保存・入力エラー・一度だけの遷移・有効期限・元フレームの分離・同一CMSへの制限を検証します。

実際のCMSルーティング・Gate登録・Page／Frame・MySQL・ブラウザーでの検証は別途必要です。導入環境で次を確認してください。

1. フレーム追加・設定画面・保存・キャンセルが正常に動く。
2. 全体・事業所等のCalendarをまとめて月／週／日／一覧で表示できる。
3. 元Calendarへの詳細リンクと登録・編集、元ToDoへの詳細リンクと担当者の状態変更が動く。
4. ログインユーザー／未ログインで、元ページの非公開・所属グループ・閲覧パスワードと元フレームの公開期間が反映される。
5. ポータルでは管理者、元Calendarでは一般利用者という組合せで、他人の一時保存・承認待ちが表示されない。
6. 他人の非公開ToDo、グループ退会後のToDo、論理削除済みの情報が表示されない。
7. ToDoの予定・期限・開始のみ・日付未設定、完了／中止の初期除外と「すべて」の切替が動く。
8. 同じCalendar／ToDo一覧を複数選択しても重複がなく、表示元すべてOFFで情報が残らない。
9. 月末・年末・うるう年・複数日、標準／独自祝日が正しく表示される。
10. スマートフォンで縦表示でき、長いタイトルが枠外にはみ出さない。
11. 同じページに複数YuyuCalendarを配置し、一方の表示日・フィルター・ページ送りを変更しても他方の選択が維持される。

## 初期実装に含めない機能

YuyuCalendar独自の予定登録、ドラッグによる日時変更、通知、出欠、予定の対象者／担当者を標準Calendarへ追加する変更、YuyuPortal画面への組み込みは含みません。全体・事業所・個人の公開範囲は元ページ・フレームの設定を利用します。ToDoの担当者・担当グループは既存YuyuToDoを利用します。

