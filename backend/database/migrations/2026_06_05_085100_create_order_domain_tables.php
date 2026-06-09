<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id()->comment('カテゴリID / 自動採番');
            $table->string('name', 100)->comment('カテゴリ名');
            $table->integer('display_order')->default(0)->index()->comment('表示順 / 小さい順に表示');
            $table->boolean('is_active')->default(true)->index()->comment('表示状態 / true: 表示, false: 非表示');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id()->comment('商品ID / 自動採番');
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete()->comment('カテゴリID / categories.id と紐づけ');
            $table->string('name', 150)->comment('商品名');
            $table->text('description')->nullable()->comment('商品説明');
            $table->integer('price')->comment('価格 / 税込価格を想定');
            $table->string('image_path')->nullable()->comment('商品画像 / 画像URLまたは保存パス');
            $table->string('status', 20)->default('active')->index()->comment('販売状態 / active, sold_out, hidden');
            $table->integer('display_order')->default(0)->index()->comment('表示順 / 商品一覧の表示順');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'status', 'display_order']);
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id()->comment('カートID / 自動採番');
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete()->comment('ユーザーID / ゲストの場合はNULL可');
            $table->string('session_id')->nullable()->index()->comment('セッションID / ゲストカート識別用');
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id()->comment('カート詳細ID / 自動採番');
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete()->comment('カートID / carts.id と紐づけ');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete()->comment('商品ID / products.id と紐づけ');
            $table->integer('quantity')->comment('数量 / 1以上');
            $table->timestamps();

            $table->unique(['cart_id', 'product_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id()->comment('注文ID / 自動採番');
            $table->string('order_number', 50)->unique()->comment('注文番号 / 画面表示用の注文番号');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->comment('ユーザーID / ゲスト注文を許可する場合はNULL可');
            $table->string('customer_name', 100)->comment('注文者名 / ゲスト注文にも対応');
            $table->string('customer_email')->comment('メールアドレス / 注文確認用');
            $table->string('customer_phone', 20)->nullable()->comment('電話番号 / 連絡先');
            $table->integer('total_amount')->comment('合計金額 / 注文時点の合計金額');
            $table->string('order_status', 20)->default('received')->index()->comment('注文ステータス / received, cooking, completed, canceled');
            $table->string('payment_method', 20)->nullable()->comment('支払方法 / cash, card など');
            $table->text('note')->nullable()->comment('備考 / アレルギー、要望など');
            $table->timestamp('ordered_at')->index()->comment('注文日時 / 注文確定日時');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'ordered_at']);
            $table->index(['order_status', 'ordered_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id()->comment('注文詳細ID / 自動採番');
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete()->comment('注文ID / orders.id と紐づけ');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete()->comment('商品ID / products.id と紐づけ');
            $table->string('product_name', 150)->comment('商品名 / 注文時点の商品名を保存');
            $table->integer('unit_price')->comment('単価 / 注文時点の価格を保存');
            $table->integer('quantity')->comment('数量 / 1以上');
            $table->integer('subtotal')->comment('小計 / unit_price * quantity');
            $table->timestamps();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->addPostgresTableComments();
            $this->addPostgresCheckConstraints();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }

    private function addPostgresTableComments(): void
    {
        DB::statement("COMMENT ON TABLE categories IS '商品カテゴリ'");
        DB::statement("COMMENT ON TABLE products IS '商品情報'");
        DB::statement("COMMENT ON TABLE carts IS 'カート情報'");
        DB::statement("COMMENT ON TABLE cart_items IS 'カート詳細情報'");
        DB::statement("COMMENT ON TABLE orders IS '注文情報'");
        DB::statement("COMMENT ON TABLE order_items IS '注文詳細情報'");
    }

    private function addPostgresCheckConstraints(): void
    {
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_price_non_negative CHECK (price >= 0)");
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_check CHECK (status IN ('active', 'sold_out', 'hidden'))");
        DB::statement("ALTER TABLE cart_items ADD CONSTRAINT cart_items_quantity_positive CHECK (quantity >= 1)");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_total_amount_non_negative CHECK (total_amount >= 0)");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_order_status_check CHECK (order_status IN ('received', 'cooking', 'completed', 'canceled'))");
        DB::statement("ALTER TABLE order_items ADD CONSTRAINT order_items_unit_price_non_negative CHECK (unit_price >= 0)");
        DB::statement("ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_positive CHECK (quantity >= 1)");
        DB::statement("ALTER TABLE order_items ADD CONSTRAINT order_items_subtotal_non_negative CHECK (subtotal >= 0)");
    }
};
