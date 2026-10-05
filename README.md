# BcDbMigrator plugin for baserCMS
Database Migrator for baserCMS   

## Installation
You can install this plugin into your baserCMS application using [composer](https://getcomposer.org).

The recommended way to install composer packages is:

```
composer require baserproject/bc-db-migrator
```

## Documentation
See [baserCMS４のデータベースを変換](https://baserproject.github.io/5/migration_db_from_ver4)

## コマンドライン実行

コマンドラインからマイグレーションを実行できます。

`bin/cake bc_db_migrator <zipファイルパス>`

### 引数
- `path`（必須）: baserCMS 4 のバックアップZipファイルのパス。絶対パス・相対パスどちらでも指定可能です。

### 実行例
```bash
bin/cake bc_db_migrator basercms4_backup.zip
```

### 出力
- **データベース**: データは現在のbaserCMSデータベースに直接インポートされます。
- **ファイル**: 変換されたCSVファイルとスキーマファイルは、`tmp/baserbackup_<バージョン>_<日時>.zip` に圧縮されて保存されます（例: `/var/www/html/tmp/baserbackup_5.1.0_20230101_120000.zip`）。保存先パスはコンソールに出力されます。
- **一時ディレクトリ**: マイグレーション完了後、作業用一時ディレクトリ（`tmp/dbmigrator/`）は自動的に削除されます。

### パスワードの取り扱い

ユーザーパスワードの移行方法は、環境変数 `HASH_TYPE` の設定により異なります。

- **`HASH_TYPE` が `sha1` の場合**:  
  バックアップデータのパスワードがそのまま保持されます。マイグレーション後も従来のパスワードでログイン可能です。

- **上記以外の場合**:  
  セキュリティ上の理由から、すべてのユーザーのパスワードが新しいランダムな文字列に変更されます。新しいパスワードはマイグレーション完了時にコンソールに表示されますので、ログイン後に必ず変更してください。

## 変換時に引き継がれないデータ

次のテーブルは、baserCMS 5 に対応するテーブルが無い、または
baserCMS 5 側で作り直す必要があるため、**意図的に変換の対象外**としています。
変換後、これらのデータは引き継がれません。

| テーブル | 引き継がれない理由 | 移行後に起きること |
|---|---|---|
| `dblogs` | baserCMS 5 では利用状況の記録方法が異なる | **ダッシュボードの「最近の動き」が空になります** |
| `blog_configs` | baserCMS 5 ではブログの設定がブログごとの設定に統合されている | ブログ全体の設定を入れ直す必要があります |
| `feed_configs` / `feed_details` | Feed プラグインは baserCMS 5 で廃止 | RSS 取り込みの設定は引き継がれません |
| `mail_messages` | メールフォームごとの受信データは、フォーム単位のテーブル（`mail_message_1` など）に分かれており、スキーマが環境ごとに異なる | 受信履歴は別途移行してください |

いずれも想定どおりの動作です。
とくに `dblogs` は、移行直後に**ダッシュボードの「最近の動き」が空**になるため
不具合に見えますが、仕様です。

## License
Lincensed under the MIT lincense since version 2.0
