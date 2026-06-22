# Mennavi Order System

Laravel + Vue.js + Tailwind CSS + PostgreSQL で作成している飲食店オンライン注文システムです。

## 技術構成

- Backend: Laravel
- Frontend: Vue.js / Tailwind CSS / Vite
- Database: PostgreSQL
- DB Viewer: CloudBeaver
- Payment: PAY.JP

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

## GitHub Pages について

GitHub Pages は静的サイトホスティングです。そのため、GitHub Pages だけでは以下は動きません。

- Laravel API
- PostgreSQL
- ログイン認証 API
- カート API
- 注文 API
- PAY.JP 決済 API

つまり、GitHub Pages に公開できるのは Vue の画面部分だけです。DB を使う機能を動かすには、Laravel + PostgreSQL を Render、Railway、Fly.io、VPS など別のサーバーにデプロイし、フロントの `VITE_API_URL` をその API URL に向ける必要があります。

## GitHub Pages で画面だけ見せる場合

Vite はビルドが必要なので、GitHub の Settings で `main / root` を選ぶだけでは基本的に正しく表示されません。おすすめは GitHub Actions で `frontend` をビルドして Pages にデプロイする方法です。

1. GitHub の `Settings > Pages` を開く
2. `Source` を `GitHub Actions` にする
3. `main` ブランチに push する
4. `.github/workflows/deploy-pages.yml` が `frontend` をビルドする
5. `frontend/dist` が Pages に公開される

GitHub Pages 用にビルドする場合、リポジトリ名が `mennavi-order-system` なら Vite の `base` は通常 `/mennavi-order-system/` が必要です。

README が表示されている場合は、Pages の Source が `Deploy from a branch` の `main / root` になっています。この設定では Vue アプリではなくリポジトリ直下の README が表示されます。`GitHub Actions` に変更してください。

ただし、この状態では API がないため、ログインや注文など DB が必要な機能は動きません。ポートフォリオとして画面だけ見せる用途なら問題ありません。

## 実運用に近い公開をしたい場合

実際にログイン、注文、管理画面、DB 集計まで見せたい場合は以下の構成にします。

```text
Frontend: GitHub Pages / Vercel / Netlify
Backend: Render / Railway / Fly.io / VPS
Database: PostgreSQL managed DB
```

この場合、フロント側の環境変数を本番 API に変更します。

```text
VITE_API_URL=https://your-backend-domain.example.com/api
```

PAY.JP の秘密鍵は GitHub Pages 側には置かず、必ず Laravel 側の環境変数に設定します。

## 本番画像ストレージ

画像ファイルは Neon DB には保存せず、DB の `image_path` には画像URLのみを保存します。ローカル環境では従来どおり Laravel の `public` ディスクを使用し、Render に次の環境変数がある場合のみ Cloudinary へアップロードします。

```text
CLOUDINARY_CLOUD_NAME=...
CLOUDINARY_API_KEY=...
CLOUDINARY_API_SECRET=...
```

これらは Render の Environment Variables にのみ設定します。`CLOUDINARY_API_SECRET` は GitHub Pages、GitHub Actions のフロントエンド用Variables、Vue の `VITE_*` 変数には設定しません。
