<?php

use App\Http\Controllers\AdminAccountController;
use App\Http\Controllers\AdminBusinessProfileController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminProjectController;
use App\Http\Controllers\AdminReturnRequestController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PasswordResetOtpController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RegistrationVerificationController;
use App\Http\Controllers\ReturnRequestController;
use App\Http\Controllers\SocialAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if ($user = auth()->user()) {
        return redirect()->route($user->isStaffOrAdmin() ? 'admin.dashboard' : 'home');
    }

    // Guests get a landing page, not a login wall and not a bare product grid.
    // The Android shell asks for `/login` by name (MainActivity), so its
    // sign-in flow is unaffected.
    return app(PageController::class)->landing();
})->name('landing');

// Shared hosts can deploy without preserving Laravel's public/storage
// symlink. Keep product photos available through the same URLs already stored
// in the database, while exposing only validated public product image names.
Route::get('/storage/products/{filename}', [ProductImageController::class, 'show'])
    ->where('filename', '[A-Za-z0-9_-]+\.(?:jpe?g|png|gif|bmp|webp)')
    ->name('product-images.show');

// ── Public storefront ────────────────────────────────────────────────────
// Browsing the catalogue and the finished-work portfolio needs no account;
// buying does. Every cart, checkout, and messaging route stays behind `auth`,
// and these views swap their Add buttons for a sign-in prompt when the
// visitor is a guest.
Route::get('/shop', [PageController::class, 'shop'])->name('shop');
Route::get('/shop/{product}', [PageController::class, 'product'])->name('products.show');
Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
Route::get('/ferosa-shop.html', [PageController::class, 'shop'])->name('legacy.shop');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.submit');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.submit');
    Route::get('/register/verify', [RegistrationVerificationController::class, 'show'])->name('register.verification');
    Route::post('/register/verify', [RegistrationVerificationController::class, 'verify'])->middleware('throttle:10,1')->name('register.verify');
    Route::post('/register/resend', [RegistrationVerificationController::class, 'resend'])->middleware('throttle:3,10')->name('register.resend');

    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback');

    Route::post('/forgot-password/send-otp', [PasswordResetOtpController::class, 'sendOtp'])->middleware('throttle:10,1')->name('forgot.send-otp');
    Route::post('/forgot-password/verify-otp', [PasswordResetOtpController::class, 'verifyOtp'])->middleware('throttle:10,1')->name('forgot.verify-otp');
    Route::post('/forgot-password/reset', [PasswordResetOtpController::class, 'resetPassword'])->middleware('throttle:10,1')->name('forgot.reset');

    // Legacy URLs (keep your original exact UI links working)
    Route::get('/ferosa-auth.html', [AuthController::class, 'showLogin'])->name('legacy.login');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/logout', [AuthController::class, 'logoutFallback'])->name('logout.fallback');

Route::middleware('auth')->group(function () {
    Route::get('/home', [PageController::class, 'home'])->name('home');
    Route::get('/checkout', [PageController::class, 'checkout'])->name('checkout');
    Route::post('/checkout', [PageController::class, 'storeCheckout'])->name('checkout.store');
    Route::get('/orders/confirmation/{order}', [PageController::class, 'orderConfirmation'])->name('orders.confirmation');
    Route::get('/orders/{order}/receipt', [PageController::class, 'orderReceipt'])->name('orders.receipt');
    Route::get('/orders/{order}/invoice', [BillingController::class, 'orderInvoice'])->name('orders.invoice');
    Route::get('/appointments/{appointment}/invoice', [BillingController::class, 'appointmentInvoice'])->name('appointments.invoice');
    Route::get('/orders/{order}/payment-proof', [PageController::class, 'paymentProof'])->name('orders.payment-proof');
    Route::get('/orders/{order}/dispatch-proof', [PageController::class, 'dispatchProof'])->name('orders.dispatch-proof');
    Route::get('/orders/{order}/delivery-proof', [PageController::class, 'deliveryProof'])->name('orders.delivery-proof');
    Route::get('/orders/{order}/returns/create', [ReturnRequestController::class, 'create'])->name('returns.create');
    Route::post('/orders/{order}/returns', [ReturnRequestController::class, 'store'])->middleware('throttle:5,60')->name('returns.store');
    Route::get('/returns/{returnRequest}', [ReturnRequestController::class, 'show'])->name('returns.show');
    Route::post('/returns/{returnRequest}/information', [ReturnRequestController::class, 'information'])->middleware('throttle:5,60')->name('returns.information');
    Route::post('/returns/{returnRequest}/cancel', [ReturnRequestController::class, 'cancel'])->name('returns.cancel');
    Route::get('/returns/{returnRequest}/evidence/{evidence}', [ReturnRequestController::class, 'evidence'])->name('returns.evidence');
    Route::get('/orders', [PageController::class, 'orders'])->name('orders');
    Route::get('/appointments', [PageController::class, 'appointments'])->name('appointments');
    Route::get('/appointments/{appointment}/receipt', [PageController::class, 'appointmentReceipt'])->name('appointments.receipt');
    Route::post('/orders/track', [PageController::class, 'trackOrder'])->name('orders.track');
    Route::post('/orders/{order}/confirm-received', [PageController::class, 'confirmOrderReceived'])->name('orders.confirm-received');
    Route::get('/schedule', [PageController::class, 'schedule'])->name('schedule');
    Route::post('/schedule', [PageController::class, 'storeSchedule'])->name('schedule.store');
    Route::get('/schedule/availability', [PageController::class, 'scheduleAvailability'])->name('schedule.availability');
    Route::put('/appointments/{appointment}/reschedule', [PageController::class, 'rescheduleAppointment'])->name('appointments.reschedule');
    Route::delete('/appointments/{appointment}/cancel', [PageController::class, 'cancelAppointment'])->name('appointments.cancel');
    Route::delete('/orders/{order}/cancel', [PageController::class, 'cancelOrder'])->name('orders.cancel');
    Route::get('/estimator', [PageController::class, 'estimator'])->name('estimator');
    Route::post('/estimator/prepare', [PageController::class, 'prepareEstimate'])
        ->middleware('throttle:30,1')
        ->name('estimator.prepare');
    Route::get('/account', [PageController::class, 'account'])->name('account');
    Route::put('/account', [PageController::class, 'updateAccount'])->name('account.update');
    Route::get('/feedback', [FeedbackController::class, 'index'])->name('feedback');
    Route::post('/feedback', [FeedbackController::class, 'store'])->name('feedback.store');

    Route::get('/messages', [MessageController::class, 'index'])->name('messages');
    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::get('/messages/poll', [MessageController::class, 'poll'])->name('messages.poll');
    // Chat attachments live on the private disk; this is the only way to read
    // one, and it checks that the caller is a party to the conversation.
    Route::get('/messages/attachment/{message}', [MessageController::class, 'attachment'])->name('messages.attachment');

    Route::get('/notifications', [PageController::class, 'notifications'])->name('notifications');
    Route::post('/notifications/{id}/read', [PageController::class, 'markNotificationRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [PageController::class, 'markAllNotificationsRead'])->name('notifications.read-all');

    // Staff/Admin: daily operations and read-only catalogue context.
    // Financial, catalogue, inventory, reporting, archive, and role controls
    // are declared in the admin-only group below.
    Route::middleware('staff')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/account', [AdminAccountController::class, 'edit'])->name('account.edit');
        Route::put('/account', [AdminAccountController::class, 'update'])->name('account.update');
        Route::get('/service-scheduling', [AdminController::class, 'dashboard'])->name('service-scheduling');
        Route::get('/ordering-delivery', [AdminController::class, 'dashboard'])->name('ordering-delivery');
        Route::get('/service-scheduling/{appointment}', [AdminController::class, 'showAppointment'])->name('appointments.show');
        Route::get('/ordering-delivery/{order}', [AdminController::class, 'showOrder'])->name('orders.show');
        Route::get('/returns', [AdminReturnRequestController::class, 'index'])->name('returns.index');
        Route::get('/returns/{returnRequest}', [AdminReturnRequestController::class, 'show'])->name('returns.show');
        Route::put('/returns/{returnRequest}/review', [AdminReturnRequestController::class, 'review'])->name('returns.review');
        Route::post('/returns/{returnRequest}/dispatch-replacement', [AdminReturnRequestController::class, 'dispatch'])->name('returns.dispatch');
        Route::post('/returns/{returnRequest}/resolve', [AdminReturnRequestController::class, 'resolve'])->name('returns.resolve');

        // Project portfolio content is operational/public content. Staff can
        // maintain it; it does not change prices, stock, or payments.
        Route::get('/projects', [AdminProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/create', [AdminProjectController::class, 'create'])->name('projects.create');
        Route::post('/projects', [AdminProjectController::class, 'store'])->name('projects.store');
        Route::get('/projects/{project}/edit', [AdminProjectController::class, 'edit'])->name('projects.edit');
        Route::put('/projects/{project}', [AdminProjectController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{project}', [AdminProjectController::class, 'destroy'])->name('projects.destroy');

        Route::get('/conversations/{conversation}', [AdminController::class, 'getConversation'])->name('conversations.show');
        Route::post('/conversations/{conversation}/reply', [AdminController::class, 'replyMessage'])->name('conversations.reply');

        Route::put('/appointments/{appointment}/status', [AdminController::class, 'updateAppointmentStatus'])->name('appointments.status');
        Route::put('/appointments/{appointment}/reschedule', [AdminController::class, 'rescheduleAppointment'])->name('appointments.reschedule');
        Route::put('/appointments/{appointment}/cancel', [AdminController::class, 'cancelAppointment'])->name('appointments.cancel');

        // Fulfilment is shared with staff; the controller rejects payment and
        // cancellation fields from staff submissions before persisting.
        Route::put('/orders/{order}/status', [AdminController::class, 'updateOrderStatus'])->name('orders.status');
    });

    // Admin-only: catalogue, financial controls, reporting, archive, and roles.
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/reports/overview', [AdminController::class, 'overviewReport'])->name('reports.overview');
        Route::get('/reports/overview-export', [AdminController::class, 'exportOverviewCsv'])->name('reports.overview-csv');
        Route::get('/reports/orders-export', [AdminController::class, 'exportOrdersCsv'])->name('reports.orders-csv');

        Route::get('/products/create', [AdminController::class, 'createProduct'])->name('products.create');
        Route::post('/products', [AdminController::class, 'storeProduct'])->name('products.store');
        Route::get('/products/{product}/edit', [AdminController::class, 'editProduct'])->name('products.edit');
        Route::put('/products/{product}', [AdminController::class, 'updateProduct'])->name('products.update');
        Route::delete('/products/{product}', [AdminController::class, 'deleteProduct'])->name('products.delete');
        Route::put('/products/{product}/restore', [AdminController::class, 'restoreProduct'])->name('products.restore');
        Route::post('/products/{product}/ar-model', [AdminController::class, 'uploadArModel'])->name('ar-models.upload');
        Route::delete('/products/{product}/ar-model', [AdminController::class, 'deleteArModel'])->name('ar-models.delete');

        Route::get('/services/create', [AdminController::class, 'createService'])->name('services.create');
        Route::post('/services', [AdminController::class, 'storeService'])->name('services.store');
        Route::get('/services/{serviceType}/edit', [AdminController::class, 'editService'])->name('services.edit');
        Route::put('/services/{serviceType}', [AdminController::class, 'updateService'])->name('services.update');
        Route::delete('/services/{serviceType}', [AdminController::class, 'deleteService'])->name('services.delete');
        Route::put('/services/{serviceType}/restore', [AdminController::class, 'restoreService'])->name('services.restore');

        Route::put('/appointments/{appointment}/scope', [AdminController::class, 'updateAppointmentScope'])->name('appointments.scope');
        Route::put('/appointments/{appointment}/archive', [AdminController::class, 'archiveAppointment'])->name('appointments.archive');
        Route::put('/appointments/{appointment}/restore', [AdminController::class, 'restoreAppointment'])->name('appointments.restore');

        Route::post('/orders/bulk-status', [AdminController::class, 'bulkOrderStatus'])->name('orders.bulk-status');
        Route::put('/orders/{order}/archive', [AdminController::class, 'archiveOrder'])->name('orders.archive');
        Route::put('/orders/{order}/restore', [AdminController::class, 'restoreOrder'])->name('orders.restore');
        Route::put('/payment-settings', [AdminController::class, 'updatePaymentSettings'])->name('payment-settings.update');
        Route::get('/business-profile', [AdminBusinessProfileController::class, 'edit'])->name('business-profile.edit');
        Route::put('/business-profile', [AdminBusinessProfileController::class, 'update'])->name('business-profile.update');

        Route::put('/users/{user}/role', [AdminController::class, 'updateUserRole'])->name('users.role');

        // Billing: the payment ledger behind every invoice.
        Route::post('/orders/{order}/payments', [BillingController::class, 'storeOrderPayment'])->name('orders.payments.store');
        Route::post('/appointments/{appointment}/payments', [BillingController::class, 'storeAppointmentPayment'])->name('appointments.payments.store');
        Route::put('/returns/{returnRequest}/decision', [AdminReturnRequestController::class, 'decision'])->name('returns.decision');
        Route::post('/returns/{returnRequest}/refunds', [AdminReturnRequestController::class, 'refund'])->name('returns.refunds.store');
        Route::post('/returns/{returnRequest}/items/{item}/restock', [AdminReturnRequestController::class, 'restock'])->name('returns.items.restock');

        // Inventory: manual stock movements and history.
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/{product}', [InventoryController::class, 'show'])->name('inventory.show');
        Route::post('/inventory/{product}/restock', [InventoryController::class, 'restock'])->name('inventory.restock');
        Route::post('/inventory/{product}/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
    });

    // Legacy URLs (keep your original exact UI links working)
    Route::get('/ferosa-home.html', [PageController::class, 'home'])->name('legacy.home');
    Route::get('/ferosa-orders.html', [PageController::class, 'orders'])->name('legacy.orders');
    Route::get('/ferosa-schedule.html', [PageController::class, 'schedule'])->name('legacy.schedule');
    Route::get('/ferosa-estimator.html', [PageController::class, 'estimator'])->name('legacy.estimator');
});
