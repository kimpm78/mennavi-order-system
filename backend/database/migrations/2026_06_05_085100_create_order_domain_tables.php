<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id()->comment('店舗ID / 自動採番');
            $table->string('name', 150)->comment('店舗名');
            $table->text('description')->nullable()->comment('店舗説明');
            $table->string('address')->nullable()->comment('所在地');
            $table->string('phone', 20)->nullable()->comment('電話番号');
            $table->string('image_path')->nullable()->comment('店舗画像');
            $table->decimal('rating', 2, 1)->default(4.5)->comment('評価');
            $table->integer('review_count')->default(0)->comment('レビュー数');
            $table->string('budget_label', 100)->nullable()->comment('予算表示');
            $table->integer('display_order')->default(0)->index()->comment('表示順');
            $table->boolean('is_active')->default(true)->index()->comment('表示状態');
            $table->timestamps();
            $table->softDeletes()->comment('削除日時');
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id()->comment('カテゴリID');
            $table->string('name', 100)->comment('カテゴリ名');
            $table->integer('display_order')->default(0)->index()->comment('表示順 / 小さい順に表示');
            $table->boolean('is_active')->default(true)->index()->comment('表示状態 / true: 表示, false: 非表示');
            $table->timestamps();
            $table->softDeletes()->comment('削除日時');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id()->comment('商品ID');
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete()->comment('店舗ID');
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete()->comment('カテゴリID');
            $table->string('name', 150)->comment('商品名');
            $table->text('description')->nullable()->comment('商品説明');
            $table->integer('price')->comment('価格');
            $table->string('image_path')->nullable()->comment('商品画像');
            $table->string('status', 20)->default('active')->index()->comment('販売状態');
            $table->integer('display_order')->default(0)->index()->comment('表示順');
            $table->timestamps();
            $table->softDeletes()->comment('削除日時');
            $table->index(['store_id', 'status', 'display_order']);
            $table->index(['category_id', 'status', 'display_order']);
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id()->comment('カートID / 自動採番');
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete()->comment('ユーザーID / ゲストの場合はNULL可');
            $table->string('session_id')->nullable()->index()->comment('セッションID / ゲストカート識別用');
            $table->string('store_name', 150)->nullable()->comment('店舗名 / カート内の商品を同一店舗に制限');
            $table->timestamp('expires_at')->nullable()->index()->comment('カート有効期限 / 最終操作から30分');
            $table->timestamps();
            $table->softDeletes()->comment('削除日時');
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id()->comment('カート詳細ID / 自動採番');
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete()->comment('カートID / carts.id と紐づけ');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete()->comment('商品ID / products.id と紐づけ');
            $table->integer('quantity')->comment('数量 / 1以上');
            $table->timestamps();
            $table->softDeletes()->comment('削除日時 / 論理削除');

            $table->unique(['cart_id', 'product_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id()->comment('注文ID / 自動採番');
            $table->string('order_number', 50)->unique()->comment('注文番号 / 画面表示用の注文番号');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->comment('ユーザーID / ゲスト注文を許可する場合はNULL可');
            $table->string('customer_name', 100)->comment('注文者名 / ゲスト注文にも対応');
            $table->string('customer_email')->comment('メールアドレス / 注文確認用');
            $table->string('customer_phone', 20)->nullable()->comment('電話番号 / 連絡先');
            $table->string('receipt_type', 20)->default('delivery')->comment('受け取り方法 / delivery, pickup');
            $table->integer('subtotal_amount')->default(0)->comment('商品小計 / 税込');
            $table->integer('delivery_fee')->default(0)->comment('配送料');
            $table->decimal('tax_rate', 5, 2)->default(8)->comment('税率');
            $table->integer('tax_amount')->default(0)->comment('税額');
            $table->integer('total_amount')->comment('合計金額 / 注文時点の合計金額');
            $table->string('order_status', 20)->default('received')->index()->comment('注文ステータス / received, cooking, completed, canceled');
            $table->string('payment_method', 20)->nullable()->comment('支払方法 / cash, card など');
            $table->string('payment_status', 20)->default('pending')->index()->comment('決済状態 / pending, paid, failed, partial_refunded, refunded');
            $table->text('note')->nullable()->comment('備考 / アレルギー、要望など');
            $table->timestamp('ordered_at')->index()->comment('注文日時 / 注文確定日時');
            $table->timestamps();
            $table->softDeletes()->comment('削除日時 / 論理削除');

            $table->index(['user_id', 'ordered_at']);
            $table->index(['order_status', 'ordered_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id()->comment('注文詳細ID');
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete()->comment('注文ID');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete()->comment('商品ID');
            $table->string('product_name', 150)->comment('商品名');
            $table->integer('unit_price')->comment('単価');
            $table->integer('quantity')->comment('数量');
            $table->integer('subtotal')->comment('小計');
            $table->timestamps();
            $table->softDeletes()->comment('削除日時');
        });

        Schema::create('user_payment_methods', function (Blueprint $table) {
            $table->id()->comment('ユーザー決済方法ID');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->comment('ユーザーID');
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

        Schema::create('payments', function (Blueprint $table) {
            $table->id()->comment('決済ID / 自動採番');
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete()->comment('注文ID / orders.id と紐づけ');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->comment('ユーザーID / users.id と紐づけ');
            $table->foreignId('user_payment_method_id')->nullable()->constrained('user_payment_methods')->nullOnDelete()->comment('ユーザー決済方法ID / 使用したカード');
            $table->string('provider', 20)->default('payjp')->index()->comment('決済プロバイダ / payjp');
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

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->addPostgresTableComments();
            $this->addPostgresCheckConstraints();
        }
    }

    // テーブルの削除は依存関係を考慮して逆順で行う
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('user_payment_methods');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('stores');
    }

    // PostgreSQLのテーブルコメントを追加してスキーマの理解を助ける
    private function addPostgresTableComments(): void
    {
        DB::statement("COMMENT ON TABLE stores IS '店舗情報'");
        DB::statement("COMMENT ON TABLE categories IS '商品カテゴリ'");
        DB::statement("COMMENT ON TABLE products IS '商品情報'");
        DB::statement("COMMENT ON TABLE carts IS 'カート情報'");
        DB::statement("COMMENT ON TABLE cart_items IS 'カート詳細情報'");
        DB::statement("COMMENT ON TABLE orders IS '注文情報'");
        DB::statement("COMMENT ON TABLE order_items IS '注文詳細情報'");
        DB::statement("COMMENT ON TABLE user_payment_methods IS 'ユーザー決済方法'");
        DB::statement("COMMENT ON TABLE payments IS '決済情報'");
    }

    // PostgreSQLのCHECK制約を追加してデータの整合性を強化
    private function addPostgresCheckConstraints(): void
    {
        DB::statement('ALTER TABLE stores ADD CONSTRAINT stores_rating_range CHECK (rating >= 0 AND rating <= 5)');
        DB::statement('ALTER TABLE stores ADD CONSTRAINT stores_review_count_non_negative CHECK (review_count >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_non_negative CHECK (price >= 0)');
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_check CHECK (status IN ('active', 'sold_out', 'hidden'))");
        DB::statement('ALTER TABLE cart_items ADD CONSTRAINT cart_items_quantity_positive CHECK (quantity >= 1)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_subtotal_amount_non_negative CHECK (subtotal_amount >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_delivery_fee_non_negative CHECK (delivery_fee >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_tax_amount_non_negative CHECK (tax_amount >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_amount_non_negative CHECK (total_amount >= 0)');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_receipt_type_check CHECK (receipt_type IN ('delivery', 'pickup'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_order_status_check CHECK (order_status IN ('received', 'cooking', 'completed', 'canceled'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_status_check CHECK (payment_status IN ('pending', 'paid', 'failed', 'partial_refunded', 'refunded'))");
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_unit_price_non_negative CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_positive CHECK (quantity >= 1)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_subtotal_non_negative CHECK (subtotal >= 0)');
        DB::statement("ALTER TABLE user_payment_methods ADD CONSTRAINT user_payment_methods_provider_check CHECK (provider IN ('payjp'))");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_non_negative CHECK (amount >= 0)');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_provider_check CHECK (provider IN ('payjp'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_payment_status_check CHECK (payment_status IN ('pending', 'paid', 'failed', 'partial_refunded', 'refunded'))");
    }
};
