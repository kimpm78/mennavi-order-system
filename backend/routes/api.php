<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminSalesController;
use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PostalCodeController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

// 認証関連
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/me', [AuthController::class, 'me']);
Route::patch('/me', [AuthController::class, 'updateMe']);
Route::post('/logout', [AuthController::class, 'logout']);

// 郵便番号住所検索
Route::get('/postal-code/{postalCode}', [PostalCodeController::class, 'show']);

// 管理者認証関連
Route::post('/admin/login', [AuthController::class, 'adminLogin']);
Route::get('/admin/me', [AuthController::class, 'adminMe']);

// 管理者画面関連
Route::get('/admin/dashboard', [AdminDashboardController::class, 'index']);
Route::get('/admin/orders', [AdminOrderController::class, 'index']);
Route::patch('/admin/orders/{order}/status', [AdminOrderController::class, 'updateStatus']);
Route::get('/admin/products', [AdminProductController::class, 'index']);
Route::post('/admin/stores', [AdminProductController::class, 'storeStore']);
Route::patch('/admin/stores/{store}', [AdminProductController::class, 'updateStore']);
Route::post('/admin/stores/{store}/image', [AdminProductController::class, 'uploadStoreImage']);
Route::post('/admin/products', [AdminProductController::class, 'storeProduct']);
Route::patch('/admin/products/{product}', [AdminProductController::class, 'updateProduct']);
Route::delete('/admin/products/{product}', [AdminProductController::class, 'destroyProduct']);
Route::get('/admin/sales', [AdminSalesController::class, 'index']);
Route::get('/admin/settings', [AdminSettingController::class, 'show']);

// カート関連
Route::get('/cart', [CartController::class, 'show']);
Route::post('/cart/items', [CartController::class, 'addItem']);
Route::patch('/cart/items/{product}', [CartController::class, 'updateItem']);
Route::delete('/cart/items/{product}', [CartController::class, 'removeItem']);
Route::delete('/cart', [CartController::class, 'clear']);

// 店舗・メニュー公開API
Route::get('/stores', [StoreController::class, 'index']);
Route::get('/stores/{store}', [StoreController::class, 'show']);

// 注文関連
Route::get('/orders', [OrderController::class, 'index']);
Route::post('/orders', [OrderController::class, 'store']);

// 決済方法関連
Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
Route::post('/payment-methods', [PaymentMethodController::class, 'store']);

// PAY.JP Webhook
Route::post('/webhooks/payjp', [OrderController::class, 'webhook']);
