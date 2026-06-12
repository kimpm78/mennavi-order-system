<?php

use App\Models\User;
use App\Models\UserPaymentMethod;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('payjp:import-payment-methods {path : CSV file path} {--dry-run : Show import result without saving}', function () {
    $path = $this->argument('path');

    if (! is_string($path) || ! is_file($path)) {
        $this->error('CSVファイルが見つかりません。');
        return 1;
    }

    $file = new SplFileObject($path);
    $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

    $headers = null;
    $rows = [];

    foreach ($file as $line) {
        if ($line === [null] || $line === false) {
            continue;
        }

        $values = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $line);

        if ($headers === null) {
            $headers = $values;
            continue;
        }

        if (count(array_filter($values, fn ($value) => $value !== null && $value !== '')) === 0) {
            continue;
        }

        $rows[] = array_combine($headers, array_slice(array_pad($values, count($headers), null), 0, count($headers)));
    }

    $imported = 0;
    $skipped = 0;
    $dryRun = (bool) $this->option('dry-run');

    $callback = function () use ($rows, &$imported, &$skipped) {
        foreach ($rows as $row) {
            $user = null;

            if (! empty($row['user_id'])) {
                $user = User::find($row['user_id']);
            }

            if (! $user && ! empty($row['email'])) {
                $user = User::where('email', $row['email'])->first();
            }

            if (! $user || empty($row['provider_customer_id']) || empty($row['provider_card_id'])) {
                $skipped++;
                continue;
            }

            $isDefault = filter_var($row['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($isDefault) {
                $user->paymentMethods()->update(['is_default' => false]);
            }

            UserPaymentMethod::updateOrCreate(
                [
                    'provider' => 'payjp',
                    'provider_card_id' => $row['provider_card_id'],
                ],
                [
                    'user_id' => $user->id,
                    'provider_customer_id' => $row['provider_customer_id'],
                    'brand' => $row['brand'] ?? null,
                    'last4' => $row['last4'] ?? null,
                    'exp_month' => ! empty($row['exp_month']) ? (int) $row['exp_month'] : null,
                    'exp_year' => ! empty($row['exp_year']) ? (int) $row['exp_year'] : null,
                    'is_default' => $isDefault,
                ],
            );

            $imported++;
        }
    };

    if ($dryRun) {
        try {
            DB::transaction(function () use ($callback) {
                $callback();
                throw new RuntimeException('dry-run rollback');
            });
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== 'dry-run rollback') {
                throw $exception;
            }
        }
    } else {
        DB::transaction($callback);
    }

    $this->info("Imported: {$imported}");
    $this->info("Skipped: {$skipped}");

    return 0;
})->purpose('Import PAY.JP v1 customer/card IDs into user payment methods');
