<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AuthorController;
use App\Http\Controllers\Admin\BookController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\VerifyCodeController;
use App\Http\Controllers\BoldCheckoutController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ReaderController;
use App\Http\Controllers\ReadingProgressController;
use App\Http\Controllers\SandboxCheckoutController;
use App\Http\Controllers\StartReadingController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/planes', [PlanController::class, 'index'])->name('plans.index');

Route::post('/webhooks/pagos/{gateway}', PaymentWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('webhooks.payments');

Route::middleware('signed')->prefix('sandbox/pago/{payment}')->name('sandbox.')->group(function () {
    Route::get('/', [SandboxCheckoutController::class, 'show'])->name('checkout');
    Route::post('/', [SandboxCheckoutController::class, 'complete'])->name('complete');
});

Route::middleware('guest')->group(function () {
    Route::get('/acceso', [LoginController::class, 'create'])->name('login');
    Route::post('/acceso', [LoginController::class, 'store'])->middleware('throttle:otp')->name('login.send');
    Route::post('/acceso/reenviar', [LoginController::class, 'resend'])->middleware('throttle:otp')->name('login.resend');
    Route::get('/acceso/verificar', [VerifyCodeController::class, 'create'])->name('login.verify');
    Route::post('/acceso/verificar', [VerifyCodeController::class, 'store'])->middleware('throttle:otp')->name('login.verify.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/salir', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/empezar', StartReadingController::class)->name('book.start');
    Route::get('/pagar', [CheckoutController::class, 'create'])->name('checkout.create');
    Route::post('/pagar', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
    Route::get('/pagos/{payment}', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::get('/pagar/bold/{payment}', [BoldCheckoutController::class, 'show'])->middleware('signed')->name('checkout.bold');
    Route::get('/pagos/bold/retorno', [BoldCheckoutController::class, 'return'])->name('checkout.bold.return');

    Route::get('/cuenta', [AccountController::class, 'show'])->name('account');
    Route::post('/cuenta/suscripcion/cancelar', [AccountController::class, 'cancel'])->name('account.subscription.cancel');
    Route::post('/cuenta/suscripcion/reanudar', [AccountController::class, 'resume'])->name('account.subscription.resume');

    Route::get('/biblioteca', [CatalogController::class, 'index'])->name('library');
    Route::get('/biblioteca/{book}', [CatalogController::class, 'show'])->name('library.show');

    Route::get('/leer/{book}', [ReaderController::class, 'show'])->name('reader.show');
    Route::middleware('throttle:reader')->group(function () {
        Route::get('/leer/{book}/pagina/{page}', [ReaderController::class, 'page'])->whereNumber('page')->name('reader.page');
        Route::get('/leer/{book}/texto/{page}', [ReaderController::class, 'text'])->whereNumber('page')->name('reader.text');
        Route::get('/leer/{book}/capitulo/{chapter}', [ReaderController::class, 'chapter'])->whereNumber('chapter')->name('reader.chapter');
        Route::put('/leer/{book}/progreso', [ReadingProgressController::class, 'store'])->name('reader.progress');
    });

    Route::prefix('admin')->name('admin.')->middleware('role:admin,editor')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::resource('libros', BookController::class)->except('show')->parameters(['libros' => 'book'])->names('books');
        Route::post('/libros/{book}/publicar', [BookController::class, 'publish'])->name('books.publish');
        Route::post('/libros/{book}/desactivar', [BookController::class, 'deactivate'])->name('books.deactivate');

        Route::resource('categorias', CategoryController::class)->only(['index', 'store', 'update', 'destroy'])->parameters(['categorias' => 'category'])->names('categories');
        Route::resource('autores', AuthorController::class)->only(['index', 'store', 'update', 'destroy'])->parameters(['autores' => 'author'])->names('authors');

        Route::get('/analitica', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('/analitica/libros/{book}', [AnalyticsController::class, 'show'])->name('analytics.show');

        Route::middleware('can:manage-settings')->prefix('configuracion')->name('settings.')->group(function () {
            Route::get('/', [SettingsController::class, 'edit'])->name('edit');
            Route::put('/correo', [SettingsController::class, 'updateMail'])->name('mail');
            Route::post('/correo/prueba', [SettingsController::class, 'testMail'])->middleware('throttle:5,1')->name('mail.test');
            Route::put('/pagos', [SettingsController::class, 'updatePayments'])->name('payments');
            Route::post('/pagos/prueba', [SettingsController::class, 'testBold'])->middleware('throttle:10,1')->name('payments.test');
        });

        Route::middleware('can:view-finances')->group(function () {
            Route::resource('planes', AdminPlanController::class)->only(['index', 'store', 'update'])->parameters(['planes' => 'plan'])->names('plans');
            Route::get('/pagos', [AdminPaymentController::class, 'index'])->name('payments.index');

            Route::get('/clientes', [CustomerController::class, 'index'])->name('customers.index');
            Route::get('/clientes/{customer}', [CustomerController::class, 'show'])->name('customers.show');
            Route::post('/clientes/{customer}/estado', [CustomerController::class, 'toggleActive'])->name('customers.toggle-active');
            Route::post('/clientes/{customer}/extender', [CustomerController::class, 'extend'])->name('customers.extend');
            Route::post('/clientes/{customer}/cancelar', [CustomerController::class, 'cancelSubscription'])->name('customers.cancel');
            Route::post('/clientes/{customer}/revocar', [CustomerController::class, 'revokeSubscription'])->name('customers.revoke');

            Route::resource('gastos', ExpenseController::class)->except('show')->parameters(['gastos' => 'expense'])->names('expenses');
            Route::get('/gastos/{expense}/soporte', [ExpenseController::class, 'receipt'])->name('expenses.receipt');

            Route::get('/finanzas', [FinanceController::class, 'index'])->name('finances.index');
            Route::get('/finanzas/exportar', [FinanceController::class, 'export'])->name('finances.export');
        });
    });
});
