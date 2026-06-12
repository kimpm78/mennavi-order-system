<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id()->comment('ジョブID');
            $table->string('queue')->index()->comment('キュー名');
            $table->longText('payload')->comment('ジョブ実行データ');
            $table->unsignedTinyInteger('attempts')->comment('実行試行回数');
            $table->unsignedInteger('reserved_at')->nullable()->comment('予約日時');
            $table->unsignedInteger('available_at')->comment('実行可能日時');
            $table->unsignedInteger('created_at')->comment('作成日時');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary()->comment('ジョブバッチID');
            $table->string('name')->comment('ジョブバッチ名');
            $table->integer('total_jobs')->comment('ジョブ総数');
            $table->integer('pending_jobs')->comment('未処理ジョブ数');
            $table->integer('failed_jobs')->comment('失敗ジョブ数');
            $table->longText('failed_job_ids')->comment('失敗ジョブID一覧');
            $table->mediumText('options')->nullable()->comment('バッチオプション');
            $table->integer('cancelled_at')->nullable()->comment('キャンセル日時');
            $table->integer('created_at')->comment('作成日時');
            $table->integer('finished_at')->nullable()->comment('完了日時');
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id()->comment('失敗ジョブID');
            $table->string('uuid')->unique()->comment('ジョブUUID');
            $table->text('connection')->comment('接続名');
            $table->text('queue')->comment('キュー名');
            $table->longText('payload')->comment('ジョブ実行データ');
            $table->longText('exception')->comment('例外内容');
            $table->timestamp('failed_at')->useCurrent()->comment('失敗日時');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
    }
};
