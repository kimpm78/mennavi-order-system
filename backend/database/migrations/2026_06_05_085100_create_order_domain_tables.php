<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 店舗のテーブルを作成
        Schema::create('stores', function (Blueprint $table) {
            $table->id()->comment('店舗ID');
            $table->string('name', 150)->comment('店舗名');
            $table->text('description')->nullable()->comment('店舗説明');
            $table->string('address')->nullable()->comment('所在地');
            $table->string('phone', 20)->nullable()->comment('電話番号');
            $table->string('weekday_hours', 100)->nullable()->comment('平日営業時間');
            $table->string('weekend_hours', 100)->nullable()->comment('土日祝営業時間');
            $table->string('holiday', 100)->nullable()->comment('休日');
            $table->string('image_path')->nullable()->comment('店舗画像');
            $table->decimal('rating', 2, 1)->default(0)->comment('レビュー平均評価');
            $table->integer('review_count')->default(0)->comment('レビュー数');
            $table->string('budget_label', 100)->nullable()->comment('予算表示');
            $table->integer('display_order')->default(0)->index()->comment('表示順');
            $table->boolean('is_active')->default(true)->index()->comment('表示状態');
            $table->timestamps();
            $table->softDeletes()->comment('削除日時');
        });

        // お気に入り店舗のテーブルを作成
        Schema::create('favorite_stores', function (Blueprint $table) {
            $table->id()->comment('お気に入り店舗ID');
            $table->foreignId('user_id')->comment('ユーザーID')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->comment('店舗ID')->constrained('stores')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'store_id']);
            $table->index(['user_id', 'created_at']);
        });

        // カテゴリのテーブルを作成
        Schema::create('categories', function (Blueprint $table) {
            $table->id()->comment('カテゴリID');
            $table->string('name', 100)->comment('カテゴリ名');
            $table->integer('display_order')->default(0)->index()->comment('表示順 / 小さい順に表示');
            $table->boolean('is_active')->default(true)->index()->comment('表示状態');
            $table->timestamps();
            $table->softDeletes()->comment('削除日時');
        });

        // 商品のテーブルを作成
        Schema::create('products', function (Blueprint $table) {
            $table->id()->comment('商品ID');
            $table->foreignId('store_id')->nullable()->comment('店舗ID')->constrained('stores')->nullOnDelete();
            $table->foreignId('category_id')->comment('カテゴリID')->constrained('categories')->restrictOnDelete();
            $table->string('name', 150)->comment('商品名');
            $table->text('description')->nullable()->comment('商品説明');
            $table->integer('price')->comment('価格');
            $table->string('image_path')->nullable()->comment('商品画像');
            $table->string('status', 20)->default('active')->index()->comment('販売状態');
            $table->integer('display_order')->default(0)->index()->comment('表示順');
            $table->timestamps();
            $table->softDeletes()->comment('削除日時 / 論理削除');
            $table->index(['store_id', 'status', 'display_order']);
            $table->index(['category_id', 'status', 'display_order']);
        });

        // カートのテーブルを作成
        Schema::create('carts', function (Blueprint $table) {
            $table->id()->comment('カートID / 自動採番');
            $table->foreignId('user_id')->nullable()->comment('ユーザーID / ゲストの場合はNULL可')->constrained('users')->cascadeOnDelete();
            $table->string('session_id')->nullable()->index()->comment('セッションID / ゲストカート識別用');
            $table->string('store_name', 150)->nullable()->comment('店舗名 / カート内の商品を同一店舗に制限');
            $table->timestamp('expires_at')->nullable()->index()->comment('カート有効期限 / 最終操作から30分');
            $table->timestamps();
            $table->softDeletes()->comment('削除日時');
        });

        // カート詳細のテーブルを作成
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id()->comment('カート詳細ID / 自動採番');
            $table->foreignId('cart_id')->comment('カートID / carts.id と紐づけ')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('product_id')->comment('商品ID / products.id と紐づけ')->constrained('products')->restrictOnDelete();
            $table->integer('quantity')->comment('数量 / 1以上');
            $table->timestamps();
            $table->softDeletes()->comment('削除日時 / 論理削除');

            $table->unique(['cart_id', 'product_id']);
        });

        // 麺ナビ Plusのプランテーブルを作成
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id()->comment('サブスクリプションプランID');
            $table->string('code', 50)->unique()->comment('プランコード / mennavi_plus');
            $table->string('name', 100)->comment('プラン名');
            $table->integer('price')->comment('月額料金 / 円');
            $table->string('currency', 3)->default('jpy')->comment('通貨');
            $table->string('billing_cycle', 20)->default('monthly')->comment('請求周期 / monthly');
            $table->decimal('discount_rate', 5, 2)->default(0)->comment('注文割引率');
            $table->boolean('free_delivery')->default(false)->comment('配送料無料フラグ');
            $table->boolean('is_active')->default(true)->index()->comment('受付状態');
            $table->timestamps();
        });

        // ユーザーの麺ナビ Plus契約テーブルを作成
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id()->comment('ユーザーサブスクリプションID');
            $table->foreignId('user_id')->comment('ユーザーID')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->comment('サブスクリプションプランID')->constrained('subscription_plans')->restrictOnDelete();
            $table->string('status', 20)->default('pending')->index()->comment('契約状態 / pending, active, canceled, expired, payment_failed');
            $table->timestamp('started_at')->nullable()->comment('契約開始日時');
            $table->timestamp('current_period_start')->nullable()->comment('現在の契約期間開始日時');
            $table->timestamp('current_period_end')->nullable()->index()->comment('現在の契約期間終了日時');
            $table->boolean('cancel_at_period_end')->default(false)->comment('契約期間終了時の解約予約フラグ');
            $table->timestamp('canceled_at')->nullable()->comment('解約申請日時');
            $table->timestamp('ended_at')->nullable()->comment('契約終了日時');
            $table->string('provider', 20)->default('payjp')->comment('決済プロバイダ');
            $table->string('provider_customer_id', 100)->nullable()->comment('決済プロバイダ側のCustomer ID');
            $table->string('provider_subscription_id', 100)->nullable()->unique()->comment('決済プロバイダ側のSubscription ID');
            $table->timestamps();
            $table->softDeletes()->comment('削除日時 / 論理削除');

            $table->index(['user_id', 'status']);
            $table->index(['subscription_plan_id', 'status']);
        });

        // 注文のテーブルを作成
        Schema::create('orders', function (Blueprint $table) {
            $table->id()->comment('注文ID / 自動採番');
            $table->string('order_number', 50)->unique()->comment('注文番号 / 画面表示用の注文番号');
            $table->foreignId('user_id')->nullable()->comment('ユーザーID / ゲスト注文を許可する場合はNULL可')->constrained('users')->nullOnDelete();
            $table->foreignId('user_subscription_id')->nullable()->comment('注文時に適用したユーザーサブスクリプションID')->constrained('user_subscriptions')->nullOnDelete();
            $table->string('customer_name', 100)->comment('注文者名 / ゲスト注文にも対応');
            $table->string('customer_email')->comment('メールアドレス / 注文確認用');
            $table->string('customer_phone', 20)->nullable()->comment('電話番号 / 連絡先');
            $table->string('receipt_type', 20)->default('delivery')->comment('受け取り方法 / delivery, pickup');
            $table->integer('subtotal_amount')->default(0)->comment('商品小計 / 税込・割引適用前');
            $table->decimal('membership_discount_rate', 5, 2)->default(0)->comment('麺ナビ Plus割引率');
            $table->integer('membership_discount_amount')->default(0)->comment('麺ナビ Plus商品割引額');
            $table->integer('delivery_fee')->default(0)->comment('配送料 / 割引適用前');
            $table->integer('delivery_discount_amount')->default(0)->comment('麺ナビ Plus配送料割引額');
            $table->string('applied_subscription_code', 50)->nullable()->comment('注文時に適用したプランコード');
            $table->decimal('tax_rate', 5, 2)->default(8)->comment('税率');
            $table->integer('tax_amount')->default(0)->comment('税額');
            $table->integer('total_amount')->comment('合計金額 / 注文時点の合計金額');
            $table->integer('earned_points')->default(0)->comment('獲得ポイント');
            $table->string('order_status', 20)->default('received')->index()->comment('注文ステータス');
            $table->string('payment_method', 20)->nullable()->comment('支払方法');
            $table->string('payment_status', 20)->default('pending')->index()->comment('決済状態, refunded');
            $table->string('delivery_staff_name', 50)->nullable()->comment('配送担当者名');
            $table->timestamp('delivered_at')->nullable()->comment('配送開始日時');
            $table->timestamp('received_at')->nullable()->comment('受け取り完了日時');
            $table->text('note')->nullable()->comment('備考 / アレルギー、要望など');
            $table->timestamp('ordered_at')->index()->comment('注文日時 / 注文確定日時');
            $table->timestamps();
            $table->softDeletes()->comment('削除日時');

            $table->index(['user_id', 'ordered_at']);
            $table->index(['order_status', 'ordered_at']);
        });

        // 注文詳細のテーブルを作成
        Schema::create('order_items', function (Blueprint $table) {
            $table->id()->comment('注文詳細ID');
            $table->foreignId('order_id')->comment('注文ID')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->comment('商品ID')->constrained('products')->restrictOnDelete();
            $table->string('product_name', 150)->comment('商品名');
            $table->integer('unit_price')->comment('単価');
            $table->integer('quantity')->comment('数量');
            $table->integer('subtotal')->comment('小計');
            $table->timestamps();
            $table->softDeletes()->comment('削除日時');
        });

        // ユーザーの決済方法テーブルを作成
        Schema::create('user_payment_methods', function (Blueprint $table) {
            $table->id()->comment('ユーザー決済方法ID');
            $table->foreignId('user_id')->comment('ユーザーID')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 20)->default('payjp')->index()->comment('決済プロバイダ / payjp');
            $table->string('payment_type', 20)->default('card')->comment('決済種別 / card');
            $table->string('provider_customer_id', 100)->comment('決済プロバイダ側のCustomer ID');
            $table->string('provider_card_id', 100)->nullable()->comment('決済プロバイダ側のCard ID');
            $table->string('brand', 50)->nullable()->comment('カードブランド');
            $table->string('last4', 4)->nullable()->comment('カード番号下4桁');
            $table->unsignedTinyInteger('exp_month')->nullable()->comment('有効期限月');
            $table->unsignedSmallInteger('exp_year')->nullable()->comment('有効期限年');
            $table->boolean('is_default')->default(false)->index()->comment('デフォルトカード');
            $table->timestamps();
            $table->softDeletes()->comment('削除日時');

            $table->index(['user_id', 'is_default']);
            $table->unique(['provider', 'provider_card_id']);
        });

        // 麺ナビ Plusの決済履歴テーブルを作成
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id()->comment('サブスクリプション決済ID');
            $table->foreignId('user_subscription_id')->comment('ユーザーサブスクリプションID')->constrained('user_subscriptions')->cascadeOnDelete();
            $table->foreignId('user_payment_method_id')->nullable()->comment('ユーザー決済方法ID / 使用したカード')->constrained('user_payment_methods')->nullOnDelete();
            $table->string('provider', 20)->default('payjp')->index()->comment('決済プロバイダ / payjp');
            $table->string('provider_payment_id', 100)->nullable()->unique()->comment('決済プロバイダ側の決済ID');
            $table->integer('amount')->comment('決済金額');
            $table->string('currency', 3)->default('jpy')->comment('通貨');
            $table->string('payment_status', 20)->default('pending')->index()->comment('決済状態 / pending, paid, failed, refunded');
            $table->timestamp('period_start')->nullable()->comment('決済対象期間開始日時');
            $table->timestamp('period_end')->nullable()->comment('決済対象期間終了日時');
            $table->timestamp('paid_at')->nullable()->index()->comment('決済完了日時');
            $table->timestamp('failed_at')->nullable()->comment('決済失敗日時');
            $table->timestamp('refunded_at')->nullable()->comment('返金日時');
            $table->json('provider_response')->nullable()->comment('決済プロバイダのレスポンスJSON');
            $table->timestamps();
            $table->softDeletes()->comment('削除日時 / 論理削除');

            $table->index(['user_subscription_id', 'payment_status']);
        });

        // 決済のテーブルを作成
        Schema::create('payments', function (Blueprint $table) {
            $table->id()->comment('決済ID / 自動採番');
            $table->foreignId('order_id')->comment('注文ID / orders.id と紐づけ')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->comment('ユーザーID / users.id と紐づけ')->constrained('users')->nullOnDelete();
            $table->foreignId('user_payment_method_id')->nullable()->comment('ユーザー決済方法ID / 使用したカード')->constrained('user_payment_methods')->nullOnDelete();
            $table->string('provider', 20)->default('payjp')->index()->comment('決済プロバイダ / payjp, paypay, cash');
            $table->string('provider_customer_id', 100)->nullable()->comment('決済時点のCustomer ID');
            $table->string('provider_card_id', 100)->nullable()->comment('決済時点のCard ID');
            $table->string('provider_charge_id', 100)->nullable()->unique()->comment('決済プロバイダ側のCharge ID');
            $table->string('payment_method', 20)->default('card')->comment('支払方法 / card など');
            $table->string('payment_status', 20)->default('pending')->index()->comment('決済状態 / pending, paid, failed, partial_refunded, refunded');
            $table->integer('amount')->comment('決済金額');
            $table->string('currency', 3)->default('jpy')->comment('通貨');
            $table->string('card_brand', 50)->nullable()->comment('決済時カードブランド');
            $table->string('card_last4', 4)->nullable()->comment('決済時カード番号下4桁');
            $table->unsignedTinyInteger('card_exp_month')->nullable()->comment('決済時カード有効期限月');
            $table->unsignedSmallInteger('card_exp_year')->nullable()->comment('決済時カード有効期限年');
            $table->json('provider_response')->nullable()->comment('決済プロバイダのレスポンスJSON');
            $table->timestamp('paid_at')->nullable()->index()->comment('決済完了日時');
            $table->timestamp('failed_at')->nullable()->comment('決済失敗日時');
            $table->timestamp('refunded_at')->nullable()->comment('返金日時');
            $table->timestamps();
            $table->softDeletes()->comment('削除日時 / 論理削除');

            $table->index(['order_id', 'payment_status']);
            $table->index(['user_id', 'created_at']);
        });

        // レビューのテーブルを作成
        Schema::create('reviews', function (Blueprint $table) {
            $table->id()->comment('レビューID');
            $table->foreignId('user_id')->comment('ユーザーID')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->comment('店舗ID')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('order_id')->comment('注文ID')->constrained('orders')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating')->comment('評価');
            $table->text('content')->nullable()->comment('レビュー内容');
            $table->timestamps();
            $table->softDeletes()->comment('削除日時');

            $table->unique(['user_id', 'order_id']);
            $table->index(['store_id', 'created_at']);
        });

        // お問い合わせのテーブルを作成
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id()->comment('お問い合わせID');
            $table->foreignId('user_id')->nullable()->comment('ユーザーID / ログインユーザーの場合')->constrained('users')->nullOnDelete();
            $table->string('name', 100)->comment('お名前');
            $table->string('email')->comment('メールアドレス');
            $table->string('category', 50)->comment('お問い合わせ種別');
            $table->string('order_number', 50)->nullable()->comment('注文番号 / 任意');
            $table->text('message')->comment('お問い合わせ内容');
            $table->string('status', 20)->default('new')->index()->comment('対応ステータス / new, in_progress, resolved, closed');
            $table->text('admin_note')->nullable()->comment('管理者メモ');
            $table->string('ip_address', 45)->nullable()->comment('送信元IPアドレス');
            $table->string('user_agent')->nullable()->comment('ユーザーエージェント');
            $table->timestamps();
            $table->softDeletes()->comment('削除日時');

            $table->index(['user_id', 'created_at']);
            $table->index(['email', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        // 管理者通知既読のテーブルを作成
        Schema::create('admin_notification_reads', function (Blueprint $table) {
            $table->id()->comment('管理者通知既読ID');
            $table->foreignId('user_id')->comment('管理者ユーザーID')->constrained('users')->cascadeOnDelete();
            $table->string('notification_id', 120)->comment('通知ID');
            $table->timestamp('read_at')->useCurrent()->comment('既読日時');
            $table->timestamps();

            $table->unique(['user_id', 'notification_id']);
            $table->index(['user_id', 'read_at']);
        });

        // メイン画面表示設定のテーブルを作成
        Schema::create('main_visual_settings', function (Blueprint $table) {
            $table->id()->comment('メイン画面設定ID');
            $table->string('title', 120)->comment('メイン画面タイトル');
            $table->text('description')->nullable()->comment('メイン画面説明文');
            $table->string('image_path')->nullable()->comment('メイン画面画像');
            $table->boolean('is_active')->default(true)->index()->comment('利用状態');
            $table->timestamps();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->addPostgresTableComments();
            $this->addPostgresCheckConstraints();
        }
    }

    // テーブルの削除は依存関係を考慮して逆順で行う
    public function down(): void
    {
        Schema::dropIfExists('main_visual_settings');
        Schema::dropIfExists('admin_notification_reads');
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('user_payment_methods');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('user_subscriptions');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('favorite_stores');
        Schema::dropIfExists('stores');
    }

    // PostgreSQLのテーブルコメントを追加してスキーマの理解を助ける
    private function addPostgresTableComments(): void
    {
        DB::statement("COMMENT ON TABLE stores IS '店舗情報'");
        DB::statement("COMMENT ON TABLE favorite_stores IS 'お気に入り店舗情報'");
        DB::statement("COMMENT ON TABLE categories IS '商品カテゴリ'");
        DB::statement("COMMENT ON TABLE products IS '商品情報'");
        DB::statement("COMMENT ON TABLE carts IS 'カート情報'");
        DB::statement("COMMENT ON TABLE cart_items IS 'カート詳細情報'");
        DB::statement("COMMENT ON TABLE subscription_plans IS '麺ナビ Plusプラン情報'");
        DB::statement("COMMENT ON TABLE user_subscriptions IS 'ユーザーの麺ナビ Plus契約情報'");
        DB::statement("COMMENT ON TABLE orders IS '注文情報'");
        DB::statement("COMMENT ON TABLE order_items IS '注文詳細情報'");
        DB::statement("COMMENT ON TABLE user_payment_methods IS 'ユーザー決済方法'");
        DB::statement("COMMENT ON TABLE subscription_payments IS '麺ナビ Plus決済履歴'");
        DB::statement("COMMENT ON TABLE payments IS '決済情報'");
        DB::statement("COMMENT ON TABLE reviews IS 'レビュー情報'");
        DB::statement("COMMENT ON TABLE contact_messages IS 'お問い合わせ情報'");
        DB::statement("COMMENT ON TABLE admin_notification_reads IS '管理者通知既読情報'");
        DB::statement("COMMENT ON TABLE main_visual_settings IS 'メイン画面表示設定'");
    }

    // PostgreSQLのCHECK制約を追加してデータの整合性を強化
    private function addPostgresCheckConstraints(): void
    {
        DB::statement('ALTER TABLE stores ADD CONSTRAINT stores_rating_range CHECK (rating >= 0 AND rating <= 5)');
        DB::statement('ALTER TABLE stores ADD CONSTRAINT stores_review_count_non_negative CHECK (review_count >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_non_negative CHECK (price >= 0)');
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_check CHECK (status IN ('active', 'sold_out', 'hidden'))");
        DB::statement('ALTER TABLE cart_items ADD CONSTRAINT cart_items_quantity_positive CHECK (quantity >= 1)');
        DB::statement('ALTER TABLE subscription_plans ADD CONSTRAINT subscription_plans_price_non_negative CHECK (price >= 0)');
        DB::statement('ALTER TABLE subscription_plans ADD CONSTRAINT subscription_plans_discount_rate_range CHECK (discount_rate >= 0 AND discount_rate <= 100)');
        DB::statement("ALTER TABLE subscription_plans ADD CONSTRAINT subscription_plans_billing_cycle_check CHECK (billing_cycle IN ('monthly'))");
        DB::statement("ALTER TABLE user_subscriptions ADD CONSTRAINT user_subscriptions_status_check CHECK (status IN ('pending', 'active', 'canceled', 'expired', 'payment_failed'))");
        DB::statement("ALTER TABLE user_subscriptions ADD CONSTRAINT user_subscriptions_provider_check CHECK (provider IN ('payjp'))");
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_subtotal_amount_non_negative CHECK (subtotal_amount >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_membership_discount_rate_range CHECK (membership_discount_rate >= 0 AND membership_discount_rate <= 100)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_membership_discount_amount_non_negative CHECK (membership_discount_amount >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_delivery_fee_non_negative CHECK (delivery_fee >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_delivery_discount_amount_non_negative CHECK (delivery_discount_amount >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_tax_amount_non_negative CHECK (tax_amount >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_amount_non_negative CHECK (total_amount >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_earned_points_non_negative CHECK (earned_points >= 0)');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_receipt_type_check CHECK (receipt_type IN ('delivery', 'pickup'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_order_status_check CHECK (order_status IN ('received', 'cooking', 'delivering', 'completed', 'canceled'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_status_check CHECK (payment_status IN ('pending', 'paid', 'failed', 'partial_refunded', 'refunded'))");
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_unit_price_non_negative CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_positive CHECK (quantity >= 1)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_subtotal_non_negative CHECK (subtotal >= 0)');
        DB::statement("ALTER TABLE user_payment_methods ADD CONSTRAINT user_payment_methods_provider_check CHECK (provider IN ('payjp'))");
        DB::statement('ALTER TABLE subscription_payments ADD CONSTRAINT subscription_payments_amount_non_negative CHECK (amount >= 0)');
        DB::statement("ALTER TABLE subscription_payments ADD CONSTRAINT subscription_payments_provider_check CHECK (provider IN ('payjp'))");
        DB::statement("ALTER TABLE subscription_payments ADD CONSTRAINT subscription_payments_status_check CHECK (payment_status IN ('pending', 'paid', 'failed', 'refunded'))");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_non_negative CHECK (amount >= 0)');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_provider_check CHECK (provider IN ('payjp', 'paypay', 'cash'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_payment_status_check CHECK (payment_status IN ('pending', 'paid', 'failed', 'partial_refunded', 'refunded'))");
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_rating_range CHECK (rating >= 1 AND rating <= 5)');
        DB::statement("ALTER TABLE contact_messages ADD CONSTRAINT contact_messages_status_check CHECK (status IN ('new', 'in_progress', 'resolved', 'closed'))");
    }
};
