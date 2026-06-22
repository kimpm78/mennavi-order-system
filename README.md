# Mennavi Order System

Laravel + Vue.js + Tailwind CSS + PostgreSQL で作成している飲食店オンライン注文システムです。

## 技術構成

- Backend: Laravel
- Frontend: Vue.js / Tailwind CSS / Vite
- Database: PostgreSQL
- DB Viewer: CloudBeaver
- Payment: PAY.JP

## 実行している環境

| サービス | 用途 |
| --- | --- |
| [GitHub Pages](https://pages.github.com/) | Vue.js フロントエンドの公開 |
| [GitHub Actions](https://github.com/features/actions) | フロントエンドのビルド・デプロイ、デモDBの定期リセット |
| [Render](https://render.com/) | Laravel バックエンドAPIのホスティング |
| [Neon](https://neon.com/) | PostgreSQL データベース |
| [Cloudinary](https://cloudinary.com/) | 店舗・メニュー・メインビジュアル画像の保存と配信 |

## ローカル実行方法

Docker を使う場合は、プロジェクト直下で起動します。

```bash
docker compose up -d
```

初回、またはマイグレーション追加後は Laravel 側で DB を更新します。

```bash
docker compose exec backend php artisan migrate
```

必要に応じてテストを実行します。

```bash
docker compose exec backend php artisan test
docker compose exec frontend npm run build
```

## アクセス URL

- Frontend: http://127.0.0.1:5173
- Backend API: http://127.0.0.1:8000/api
- CloudBeaver: http://127.0.0.1:8978

## 実行確認

- 公開 URL: https://kimpm78.github.io/mennavi-order-system/

## デモアカウント

公開環境では、以下のデモアカウントでログインして各機能を確認できます。これらは検証用アカウントのため、個人情報や実際の決済情報は入力しないでください。

| 種別 | ログインID（メールアドレス） | パスワード |
| --- | --- | --- |
| 利用者 | `test@example.com` | `password123` |
| 管理者 | `admin@mennavi.local` | `admin1234` |

デモDBは3日に1回リセットされるため、登録・注文などのデータは保持されません。

## デモDBリセット

GitHub Actions の `Reset Demo Database` workflow で、3日に1回 Neon DB を初期化して Seeder を実行します。

GitHub の `Settings > Secrets and variables > Actions` に以下を設定します。

Secrets:

```text
APP_KEY
NEON_DB_HOST
NEON_DB_PORT
NEON_DB_DATABASE
NEON_DB_USERNAME
NEON_DB_PASSWORD
```

Variables:

```text
APP_URL
FRONTEND_URL
```

## DB 接続情報

Docker Compose の PostgreSQL は以下で接続できます。

```text
Host: localhost
Port: 5433
Database: mennavi
User: mennavi
Password: Mennavi1234
```

Docker コンテナ内から接続する場合は `Host: postgres`, `Port: 5432` を使います。

## 本番画像ストレージ

画像ファイルは Neon DB には保存せず、DB の `image_path` には画像URLのみを保存します。ローカル環境では従来どおり Laravel の `public` ディスクを使用し、Render に次の環境変数がある場合のみ Cloudinary へアップロードします。

```text
CLOUDINARY_CLOUD_NAME=...
CLOUDINARY_API_KEY=...
CLOUDINARY_API_SECRET=...
```

これらは Render の Environment Variables にのみ設定します。`CLOUDINARY_API_SECRET` は GitHub Pages、GitHub Actions のフロントエンド用Variables、Vue の `VITE_*` 変数には設定しません。
