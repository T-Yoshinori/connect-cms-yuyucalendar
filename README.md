# Connect-CMS YuyuCalendar

複数の標準CalendarとYuyuToDoを、月・週・日・一覧へ集約表示するConnect-CMS用の非公式プラグインです。

- バージョン：0.1.0-beta.1
- 開発・提供：ゆうゆう企画
- 対応確認：Connect-CMS 1.44.1
- 状態：ベータ版。新規インストール用

## ダウンロード

[connect-cms-yuyucalendar-0.1.0-beta.1.zip](downloads/connect-cms-yuyucalendar-0.1.0-beta.1.zip)

ZIPには導入用ファイルと任意のYuyuToDo連携更新を収録しています。Connect-CMS本体・サイトデータ・テスト依存パッケージは含みません。

## 主な機能

- 集約する標準Calendar／YuyuToDoの元フレームを設定画面で複数選択
- 月・週・日・一覧の切替と、表示元の一時的な選択
- 標準カレンダーと同じ年月見出し・曜日色。月・週の先頭行は日～土の一文字表示
- ToDoの予定期間と期限を区別。日付未設定ToDoは別一覧に表示
- 元ページ・元フレームの閲覧権限と、ToDoの公開範囲を反映
- 詳細・編集・保存・キャンセル後に、クリック元のYuyuCalendarと表示条件へ戻る

予定・ToDoは元データから取得します。独自の予定テーブルやmigrationは追加しません。標準Calendarの保存アクションは変更しません。

## インストール

1. Connect-CMSのファイルとデータベースをバックアップします。
2. ZIPを展開し、ルート直下の `app`、`database`、`resources` をConnect-CMSのルートへ同じ構成で重ねて配置します。
3. CMSルートで以下を実行します。

```sh
composer dump-autoload
php artisan db:seed --class=YuyuCalendarPluginSeeder
php artisan view:clear
```

4. **統合対象になり得る標準Calendarの各フレームに、`yuyucalendar` テンプレート（表示名「YuyuCalendar連携（月表示）」）を適用してください。** これがないと、詳細・編集・保存後にクリック元のYuyuCalendarへ戻れません。設定するのは表示元の標準Calendarフレームです。
5. ページにYuyuCalendarフレームを追加し、「集約・表示設定」で表示元と初期表示を選択します。
6. 利用者アカウントで表示、詳細からの戻り先、編集・保存・キャンセルを確認します。

`resources/views/plugins/user/calendars/yuyucalendar_return_scripts.blade.php` は、`yuyucalendar` フォルダーの一つ上の `calendars` 直下に配置します。

## YuyuToDoとの連携（任意）

標準Calendarだけの集約ではこの手順は不要です。

1. [YuyuToDo](https://github.com/T-Yoshinori/connect-cms-yuyutodo)を先に導入します（対応確認：0.1.0-beta.1）。YuyuToDoのテーブルを作成するmigrationも完了させてください。
2. ZIPの `integrations/yuyutodo/` 内の `app` と `resources` をCMSルートへ同じ構成で配置します。既存の次の3ファイルを更新します。

   - `app/Plugins/User/Yuyutodo/YuyutodoPlugin.php`
   - `resources/views/plugins/user/yuyutodo/default/todo_detail.blade.php`
   - `resources/views/plugins/user/yuyutodo/default/todo_input.blade.php`

3. `php artisan view:clear` を実行し、YuyuCalendarの設定で対象のToDo管理フレームを選択します。

YuyuToDo側のテンプレート切替は不要です。独自テンプレートを使用している場合は、戻り先情報を引き継ぐ変更をそのテンプレートにも反映してください。後からYuyuToDoを導入・更新して上記ファイルを上書きした場合は、連携更新を再適用してください。

## 戻り先と表示内容

詳細リンクの戻り先情報を暗号化し、元フレームと同一CMSのURLであることを検証します。標準Calendarでは保存直前にタブ単位の `sessionStorage` へ一時保存し、保存後の標準一覧を連携テンプレートで経由してYuyuCalendarへ戻ります。一時情報は使用時・入力エラー時に削除し、5分で失効します。

保存後は元データを再取得するので編集内容が反映されます。表示期間外へ移した予定や、未完了表示で完了にしたToDoは表示条件から外れます。元Calendar／ToDoから直接開いた場合は従来の戻り先を維持します。

## 権限と対象範囲

ページの閲覧権限やパスワード、元フレームの公開条件、投稿状態、ToDoの非公開設定を確認します。集約先の管理権限によって元の下書きや他人の非公開ToDoを閲覧できるようにはしません。

独自予定登録、ドラッグによる日時変更、通知・出欠、YuyuPortal画面への組み込みは含みません。

[詳細仕様と確認項目](docs/yuyu-calendar.md)

## 開発・パッケージ作成

テストはYuyuToDoを導入したConnect-CMS環境のルートへ `tests/Standalone/YuyuCalendar/` を配置して実行します。公開リポジトリ単独ではCMS本体のクラスが不足するためPHPテストを実行できません。

```sh
cd tests/Standalone/YuyuCalendar
composer install
composer test
node return-navigation.js
```

配布ZIPはこのリポジトリのルートで再生成できます。

```sh
python3 scripts/build_package.py
```

## ライセンス

MIT License。標準Calendar由来のテンプレートの著作権表示とライセンスを保持しています。本プラグインはConnect-CMS開発元による公式提供・認定・保証を受けたものではありません。

設計・実装にはChatGPT（OpenAI）の開発支援を利用しています。仕様決定・動作確認・公開・保守の責任は開発・提供者が負います。
