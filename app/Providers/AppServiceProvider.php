<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\AppSetting;
use App\Models\Conversation;
use App\Models\Feedback;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Laravel's stock `tailwind` pagination view carries `dark:` variants,
        // so page links rendered black for anyone whose OS is in dark mode
        // while the rest of the UI stayed light.
        Paginator::defaultView('vendor.pagination.ferosa');
        Paginator::defaultSimpleView('vendor.pagination.ferosa');

        // A worker keeps one PHP process alive across many jobs, so settings
        // memoised while handling one job would otherwise leak into the next.
        Queue::before(fn () => AppSetting::flushMemo());

        // The admin header cluster renders on pages served by several different
        // controllers, so its unread counts come from here rather than from
        // whichever controller happens to own the page.
        View::composer('admin.partials.workspace-header-actions', function ($view) {
            $user = auth()->user();

            if (! $user?->isStaffOrAdmin()) {
                $view->with(['totalUnreadMessages' => 0, 'adminUnreadNotifications' => 0]);

                return;
            }

            if (! app()->has('_adminHeaderCounts')) {
                app()->instance('_adminHeaderCounts', [
                    // Mirrors AdminController::dashboard() so the badge does not
                    // change value as you move between admin screens.
                    'totalUnreadMessages' => Message::query()
                        ->whereNull('read_at')
                        ->where('sender_id', '!=', $user->id)
                        ->whereHas('conversation.customer', fn ($q) => $q->where('role', 'user'))
                        ->count(),
                    'adminUnreadNotifications' => $user->unreadNotifications()->count(),
                ]);
            }

            $view->with(app('_adminHeaderCounts'));
        });

        // The sidebar renders on the dashboard and on every standalone admin
        // page, so its badges are computed here — otherwise Inventory, Project
        // Portfolio and Business Profile showed a sidebar without the counts
        // the dashboard shows.
        View::composer('admin.partials.workspace-sidebar', function ($view) {
            $user = auth()->user();

            $empty = [
                'appointments_overdue' => 0,
                'appointments_pending' => 0,
                'orders_pending' => 0,
                'low_stock' => 0,
                'unread_messages' => 0,
                'feedback' => 0,
            ];

            if (! $user?->isStaffOrAdmin()) {
                $view->with('sidebarBadges', $empty);

                return;
            }

            if (! app()->has('_adminSidebarBadges')) {
                app()->instance('_adminSidebarBadges', [
                    'appointments_overdue' => Appointment::query()
                        ->whereNull('archived_at')
                        ->whereIn('status', ['scheduled', 'confirmed'])
                        ->where('appointment_at', '<', now())
                        ->count(),
                    'appointments_pending' => Appointment::query()
                        ->whereNull('archived_at')
                        ->where('status', 'scheduled')
                        ->count(),
                    'orders_pending' => Order::query()
                        ->whereNull('archived_at')
                        ->whereIn('status', ['pending', 'confirmed'])
                        ->count(),
                    'low_stock' => Product::query()
                        ->whereNull('archived_at')
                        ->where('is_active', true)
                        ->where('stock_qty', '<=', 5)
                        ->count(),
                    'unread_messages' => Message::query()
                        ->whereNull('read_at')
                        ->where('sender_id', '!=', $user->id)
                        ->whereHas('conversation.customer', fn ($q) => $q->where('role', 'user'))
                        ->count(),
                    'feedback' => Feedback::query()->count(),
                ]);
            }

            $view->with('sidebarBadges', app('_adminSidebarBadges'));
        });

        $layouts = ['layouts.customer', 'partials.mobile-bottom-customer'];

        // Unread message count
        View::composer($layouts, function ($view) {
            if (! app()->has('_customerUnreadMessages')) {
                $count = 0;
                if ($user = auth()->user()) {
                    $convo = Conversation::where('customer_id', $user->id)->first();
                    if ($convo) {
                        $count = $convo->messages()
                            ->where('sender_id', '!=', $user->id)
                            ->whereNull('read_at')
                            ->count();
                    }
                }
                app()->instance('_customerUnreadMessages', $count);
            }
            $view->with('customerUnreadMessages', app('_customerUnreadMessages'));
        });

        // Unread notification count
        View::composer($layouts, function ($view) {
            if (! app()->has('_customerUnreadNotifications')) {
                $count = 0;
                if ($user = auth()->user()) {
                    $count = $user->unreadNotifications()->count();
                }
                app()->instance('_customerUnreadNotifications', $count);
            }
            $view->with('customerUnreadNotifications', app('_customerUnreadNotifications'));
        });

        // Orders that need customer confirmation after staff uploaded delivery proof.
        View::composer($layouts, function ($view) {
            if (! app()->has('_customerPendingConfirmations')) {
                $count = 0;
                if ($user = auth()->user()) {
                    $count = Order::query()
                        ->where('user_id', $user->id)
                        ->whereNull('archived_at')
                        ->where('status', 'delivered')
                        ->whereNotNull('delivery_proof_url')
                        ->whereNull('customer_confirmed_at')
                        ->count();
                }
                app()->instance('_customerPendingConfirmations', $count);
            }
            $view->with('customerPendingConfirmations', app('_customerPendingConfirmations'));
        });
    }
}
