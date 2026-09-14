<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\MoveAppointmentRequest;
use App\Jobs\SendSmsJob;
use App\Models\Appointment;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Feedback;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlantModel;
use App\Models\Product;
use App\Models\ServiceType;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\AppointmentMovedByTeam;
use App\Notifications\AppointmentScopeUpdated;
use App\Notifications\AppointmentStatusChanged;
use App\Notifications\OrderPaymentReviewed;
use App\Notifications\OrderStatusChanged;
use App\Services\BillingService;
use App\Services\GlbValidator;
use App\Services\InventoryService;
use App\Support\ArAssetBudgets;
use App\Support\Audit;
use App\Support\MessageAttachment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    public function archiveOrder(Request $request, Order $order): RedirectResponse
    {
        $before = Audit::snapshot($order, ['status', 'total_amount', 'archived_at']);
        $order->update(['archived_at' => now()]);

        $order->refresh();
        Audit::log($request, 'order.archive', $order, $before, Audit::snapshot($order, ['status', 'total_amount', 'archived_at']));

        return redirect()->route('admin.dashboard', ['tab' => 'orders'])
            ->with('status', "Order {$order->order_number} archived.");
    }

    public function restoreOrder(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->archived_at !== null, 404);

        $before = Audit::snapshot($order, ['status', 'total_amount', 'archived_at']);
        $order->update(['archived_at' => null]);

        $order->refresh();
        Audit::log($request, 'order.restore', $order, $before, Audit::snapshot($order, ['status', 'total_amount', 'archived_at']));

        return redirect()->route('admin.dashboard', ['tab' => 'archived'])
            ->with('status', "Order {$order->order_number} restored.");
    }

    public function archiveAppointment(Request $request, Appointment $appointment): RedirectResponse
    {
        $before = Audit::snapshot($appointment, ['status', 'appointment_at', 'archived_at']);
        $appointment->update(['archived_at' => now()]);

        $appointment->refresh();
        Audit::log($request, 'appointment.archive', $appointment, $before, Audit::snapshot($appointment, ['status', 'appointment_at', 'archived_at']));

        return redirect()->route('admin.dashboard', ['tab' => 'appointments'])
            ->with('status', 'Appointment archived.');
    }

    public function restoreAppointment(Request $request, Appointment $appointment): RedirectResponse
    {
        abort_unless($appointment->archived_at !== null, 404);

        $before = Audit::snapshot($appointment, ['status', 'appointment_at', 'archived_at']);
        $appointment->update(['archived_at' => null]);

        $appointment->refresh();
        Audit::log($request, 'appointment.restore', $appointment, $before, Audit::snapshot($appointment, ['status', 'appointment_at', 'archived_at']));

        return redirect()->route('admin.dashboard', ['tab' => 'archived'])
            ->with('status', 'Appointment restored.');
    }

    private const ORDER_STATUSES = ['pending', 'confirmed', 'out_for_delivery', 'delivered', 'completed', 'cancelled'];

    /** Statuses a staff member may move while running day-to-day fulfilment. */
    private const STAFF_ORDER_STATUSES = ['pending', 'confirmed', 'out_for_delivery', 'delivered'];

    /**
     * Tabs the dashboard can show. Kept here (rather than only in the Blade) so
     * the controller can load just the active tab's data instead of all of it.
     */
    private const DASHBOARD_TABS = [
        'overview', 'appointments', 'orders', 'services', 'products',
        'messages', 'archived', 'audit', 'users', 'feedbacks', 'payment',
    ];

    /**
     * Resolve which dashboard tab is being viewed. The dedicated module URLs
     * (service-scheduling / ordering-delivery) pin their own tab; otherwise it
     * comes from ?tab=, defaulting to overview.
     */
    private function resolveDashboardTab(): string
    {
        $routeTab = match (request()->route()?->getName()) {
            'admin.service-scheduling' => 'appointments',
            'admin.ordering-delivery' => 'orders',
            default => null,
        };

        if ($routeTab !== null) {
            return $routeTab;
        }

        $requested = (string) request('tab', 'overview');

        if (! in_array($requested, self::DASHBOARD_TABS, true)) {
            return 'overview';
        }

        // These tabs contain sensitive records and must fail closed if a staff
        // member attempts to reach them by typing the URL directly.
        if (in_array($requested, ['payment', 'audit', 'users', 'archived'], true)
            && ! auth()->user()?->isAdmin()) {
            abort(403, 'Administrator access is required for this workspace area.');
        }

        return $requested;
    }

    /** An empty paginator so inactive tabs still receive a valid paginator. */
    private function emptyPage(string $pageName): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 10, 1, ['pageName' => $pageName]);
    }

    public function dashboard(): View
    {
        $range = request()->validate([
            'sales_from' => ['nullable', 'date'],
            'sales_to' => ['nullable', 'date', 'after_or_equal:sales_from'],
        ]);

        $salesFrom = $range['sales_from'] ?? null;
        $salesTo = $range['sales_to'] ?? null;

        $activeTab = $this->resolveDashboardTab();
        $isAdmin = auth()->user()?->isAdmin() === true;
        // True when the current tab needs a given block of data.
        $on = fn (string ...$tabs) => in_array($activeTab, $tabs, true);

        $ordersBase = Order::query()
            ->when($salesFrom, fn (Builder $q) => $q->whereDate('created_at', '>=', $salesFrom))
            ->when($salesTo, fn (Builder $q) => $q->whereDate('created_at', '<=', $salesTo));

        $totalSales = $on('overview') ? (float) (clone $ordersBase)->sum('total_amount') : 0.0;
        $recognizedRevenue = $on('overview')
            ? (float) (clone $ordersBase)
                ->where('payment_status', 'paid')
                ->where('status', '!=', 'cancelled')
                ->sum('total_amount')
            : 0.0;
        $totalOrders = $on('overview') ? (clone $ordersBase)->count() : 0;
        $deliveredOrders = $on('overview') ? (clone $ordersBase)->whereIn('status', ['delivered', 'completed'])->count() : 0;
        $pendingOrders = $on('overview') ? (clone $ordersBase)->whereIn('status', ['pending', 'confirmed', 'out_for_delivery'])->count() : 0;

        $salesByStatus = $on('overview')
            ? (clone $ordersBase)
                ->selectRaw('status, COUNT(*) as order_count, COALESCE(SUM(total_amount), 0) as sales_total')
                ->groupBy('status')
                ->orderBy('status')
                ->get()
            : collect();

        $adminOrders = $on('orders') ? $this->filteredOrdersQuery()->take(200)->get() : collect();

        $monthlySales = collect();

        if ($on('overview')) {
            $monthlySalesRows = Order::query()
                ->selectRaw($this->monthKeyExpression().' as ym, COALESCE(SUM(total_amount), 0) as total')
                ->where('created_at', '>=', now()->startOfMonth()->subMonths(5))
                ->groupBy('ym')
                ->orderBy('ym')
                ->get()
                ->keyBy('ym');

            $monthlySales = collect(range(5, 0, -1))
                ->map(function (int $monthsAgo) use ($monthlySalesRows) {
                    $dt = now()->startOfMonth()->subMonths($monthsAgo);
                    $ym = $dt->format('Y-m');

                    return [
                        'label' => $dt->format('M Y'),
                        'total' => (float) ($monthlySalesRows[$ym]->total ?? 0),
                    ];
                });
        }

        $productQ = trim((string) request('product_q', ''));
        $productCategory = trim((string) request('product_category', ''));
        $productCategories = $on('products')
            ? Product::query()
                ->whereNull('archived_at')
                ->whereNotNull('category')
                ->select('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
            : collect();
        $productStats = $on('products')
            ? [
                'total' => Product::query()->whereNull('archived_at')->count(),
                'active' => Product::query()->whereNull('archived_at')->where('is_active', true)->count(),
                'low_stock' => Product::query()->whereNull('archived_at')->where('stock_qty', '<=', 5)->count(),
                'ar_ready' => Product::query()->whereNull('archived_at')->whereHas('plantModel')->count(),
            ]
            : ['total' => 0, 'active' => 0, 'low_stock' => 0, 'ar_ready' => 0];

        $products = $on('products')
            ? Product::query()
                ->with('plantModel')
                ->whereNull('archived_at')
                ->when($productQ !== '', function (Builder $q) use ($productQ) {
                    $q->where(function (Builder $qq) use ($productQ) {
                        $qq->where('name', 'like', '%'.$productQ.'%')
                            ->orWhere('category', 'like', '%'.$productQ.'%');
                    });
                })
                ->when($productCategory !== '', fn (Builder $q) => $q->where('category', $productCategory))
                ->orderBy('name')
                ->paginate(10, ['*'], 'products_page')
                ->withQueryString()
            : $this->emptyPage('products_page');

        $archivedProducts = $on('archived')
            ? Product::query()
                ->whereNotNull('archived_at')
                ->orderByDesc('archived_at')
                ->take(500)
                ->get()
            : collect();

        $lowStockProducts = Product::query()
            ->whereNull('archived_at')
            ->where('stock_qty', '<=', 5)
            ->where('is_active', true)
            ->orderBy('stock_qty', 'asc')
            ->get();

        $pendingAppointments = $on('overview')
            ? Appointment::query()
                ->whereNull('archived_at')
                ->whereIn('status', ['scheduled', 'confirmed'])
                ->count()
            : 0;

        // Sidebar badge — needed on every tab.
        $appointmentsNeedingConfirmation = Appointment::query()
            ->whereNull('archived_at')
            ->where('status', 'scheduled')
            ->count();

        $todayAppointments = $on('overview')
            ? Appointment::query()
                ->whereNull('archived_at')
                ->whereIn('status', ['scheduled', 'confirmed'])
                ->whereDate('appointment_at', today())
                ->count()
            : 0;

        // Sidebar badge — needed on every tab.
        $overdueAppointments = Appointment::query()
            ->whereNull('archived_at')
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->where('appointment_at', '<', now())
            ->count();

        $priorityAppointments = $on('overview')
            ? Appointment::query()
                ->with(['user:id,name,email', 'serviceType:id,name'])
                ->whereNull('archived_at')
                ->whereIn('status', ['scheduled', 'confirmed'])
                ->orderByRaw('CASE WHEN appointment_at < ? THEN 0 ELSE 1 END', [now()])
                ->orderBy('appointment_at')
                ->take(5)
                ->get()
            : collect();

        $ordersAwaitingConfirmation = $on('overview')
            ? Order::query()
                ->whereNull('archived_at')
                ->where('status', 'delivered')
                ->whereNotNull('delivery_proof_url')
                ->whereNull('customer_confirmed_at')
                ->count()
            : 0;

        $priorityOrders = $on('overview')
            ? Order::query()
                ->with('user:id,name,email')
                ->whereNull('archived_at')
                ->where(function (Builder $q) {
                    $q->whereIn('status', ['pending', 'confirmed', 'out_for_delivery'])
                        ->orWhere(function (Builder $delivered) {
                            $delivered->where('status', 'delivered')
                                ->whereNull('customer_confirmed_at');
                        });
                })
                ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'confirmed' THEN 1 WHEN 'out_for_delivery' THEN 2 ELSE 3 END")
                ->orderByDesc('created_at')
                ->take(5)
                ->get()
            : collect();
        $totalUsers = $on('overview') ? User::query()->count() : 0;

        $apptQ = trim((string) request('appt_q', ''));
        $apptStatus = trim((string) request('appt_status', ''));
        $appointments = $on('appointments')
            ? Appointment::query()
                ->with(['user', 'serviceType', 'feedback'])
                ->whereNull('archived_at')
                ->when($apptStatus !== '', fn (Builder $q) => $q->where('status', $apptStatus))
                ->when($apptQ !== '', function (Builder $q) use ($apptQ) {
                    $q->where(function (Builder $qq) use ($apptQ) {
                        $qq->where('notes', 'like', '%'.$apptQ.'%')
                            ->orWhereHas('user', function (Builder $u) use ($apptQ) {
                                $u->where('email', 'like', '%'.$apptQ.'%')
                                    ->orWhere('name', 'like', '%'.$apptQ.'%');
                            })
                            ->orWhereHas('serviceType', function (Builder $s) use ($apptQ) {
                                $s->where('name', 'like', '%'.$apptQ.'%');
                            });
                    });
                })
                ->orderByRaw("CASE status WHEN 'scheduled' THEN 0 WHEN 'confirmed' THEN 1 ELSE 2 END")
                ->orderByRaw("CASE WHEN status IN ('scheduled', 'confirmed') THEN appointment_at END ASC")
                ->latest('appointment_at')
                ->paginate(10, ['*'], 'appts_page')
                ->withQueryString()
            : $this->emptyPage('appts_page');

        $serviceQ = trim((string) request('service_q', ''));
        $services = $on('services')
            ? ServiceType::query()
                ->whereNull('archived_at')
                ->when($serviceQ !== '', function (Builder $q) use ($serviceQ) {
                    $q->where('name', 'like', '%'.$serviceQ.'%');
                })
                ->orderBy('name')
                ->paginate(10, ['*'], 'services_page')
                ->withQueryString()
            : $this->emptyPage('services_page');
        $serviceStats = $on('services')
            ? [
                'total' => ServiceType::query()->whereNull('archived_at')->count(),
                'active' => ServiceType::query()->whereNull('archived_at')->where('is_active', true)->count(),
            ]
            : ['total' => 0, 'active' => 0];

        $archivedServices = $on('archived')
            ? ServiceType::query()
                ->whereNotNull('archived_at')
                ->orderByDesc('archived_at')
                ->take(500)
                ->get()
            : collect();
        $users = $on('users') ? User::query()->orderBy('name')->get() : collect();

        $archivedOrders = $on('archived')
            ? Order::query()
                ->whereNotNull('archived_at')
                ->with('user:id,name,email')
                ->orderByDesc('archived_at')
                ->paginate(10, ['*'], 'arch_orders_page')
                ->withQueryString()
            : $this->emptyPage('arch_orders_page');

        $archivedAppointments = $on('archived')
            ? Appointment::query()
                ->whereNotNull('archived_at')
                ->with(['user:id,name,email', 'serviceType:id,name'])
                ->orderByDesc('archived_at')
                ->paginate(10, ['*'], 'arch_appts_page')
                ->withQueryString()
            : $this->emptyPage('arch_appts_page');

        $thisWeekSales = 0.0;
        $lastWeekSales = 0.0;
        $weekSalesDeltaPct = null;
        $topProducts = collect();

        if ($on('overview')) {
            $thisWeekStart = now()->startOfWeek();
            $thisWeekEnd = now();
            $lastWeekStart = now()->subWeek()->startOfWeek();
            $lastWeekEnd = now()->subWeek()->endOfWeek();

            $thisWeekSales = (float) Order::query()->whereBetween('created_at', [$thisWeekStart, $thisWeekEnd])->sum('total_amount');
            $lastWeekSales = (float) Order::query()->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])->sum('total_amount');
            $weekSalesDeltaPct = $lastWeekSales > 0
                ? round((($thisWeekSales - $lastWeekSales) / $lastWeekSales) * 100, 1)
                : null;

            $topProducts = $this->topSellingProducts();
        }
        $orderFlowStats = [
            'pending' => Order::query()->whereNull('archived_at')->whereIn('status', ['pending', 'confirmed'])->count(),
            'for_delivery' => Order::query()->whereNull('archived_at')->whereIn('status', ['out_for_delivery', 'delivered'])->count(),
            'completed' => Order::query()->whereNull('archived_at')->where('status', 'completed')->count(),
            'unpaid' => Order::query()->whereNull('archived_at')->whereIn('payment_status', ['unpaid', 'pending_verification', 'rejected'])->count(),
        ];

        $auditQ = trim((string) request('audit_q', ''));
        $auditLogs = $on('audit')
            ? AuditLog::query()
                ->with('actor:id,name,email')
                ->when($auditQ !== '', function (Builder $q) use ($auditQ) {
                    $q->where(function (Builder $qq) use ($auditQ) {
                        $qq->where('action', 'like', '%'.$auditQ.'%')
                            ->orWhere('auditable_type', 'like', '%'.$auditQ.'%')
                            ->orWhere('auditable_id', 'like', '%'.$auditQ.'%')
                            ->orWhereHas('actor', function (Builder $u) use ($auditQ) {
                                $u->where('email', 'like', '%'.$auditQ.'%')
                                    ->orWhere('name', 'like', '%'.$auditQ.'%');
                            });
                    });
                })
                ->latest('id')
                ->paginate(15, ['*'], 'audit_page')
                ->withQueryString()
            : $this->emptyPage('audit_page');

        $feedbackQ = trim((string) request('feedback_q', ''));
        $feedbacks = Feedback::query()
            ->where(function (Builder $q) {
                $q->whereNotNull('order_id')
                    ->orWhereNotNull('appointment_id')
                    ->orWhereNotNull('product_id')
                    ->orWhereNotNull('service_type_id');
            })
            ->with(['user:id,name,email', 'product:id,name', 'serviceType:id,name', 'order:id,order_number', 'appointment:id,appointment_at'])
            ->when($feedbackQ !== '', function (Builder $q) use ($feedbackQ) {
                $q->where(function (Builder $qq) use ($feedbackQ) {
                    $qq->where('comment', 'like', '%'.$feedbackQ.'%')
                        ->orWhereHas('user', function (Builder $u) use ($feedbackQ) {
                            $u->where('name', 'like', '%'.$feedbackQ.'%')
                                ->orWhere('email', 'like', '%'.$feedbackQ.'%');
                        })
                        ->orWhereHas('order', fn (Builder $o) => $o->where('order_number', 'like', '%'.$feedbackQ.'%'))
                        ->orWhereHas('product', fn (Builder $p) => $p->where('name', 'like', '%'.$feedbackQ.'%'))
                        ->orWhereHas('serviceType', fn (Builder $s) => $s->where('name', 'like', '%'.$feedbackQ.'%'));
                });
            })
            ->latest()
            ->paginate(15, ['*'], 'fb_page')
            ->withQueryString();

        $avgRating = $on('overview', 'feedbacks')
            ? Feedback::query()
                ->where(function (Builder $q) {
                    $q->whereNotNull('order_id')
                        ->orWhereNotNull('appointment_id')
                        ->orWhereNotNull('product_id')
                        ->orWhereNotNull('service_type_id');
                })
                ->avg('rating')
            : null;

        // The full thread list is only needed by the messages panel; every other
        // tab just needs the unread badge, which is a single count.
        $conversations = $on('messages')
            ? Conversation::with([
                'customer:id,name,email',
                'latestMessage.sender:id,name,role',
            ])
                ->whereHas('customer', fn (Builder $q) => $q->where('role', 'user'))
                ->withCount(['messages as unread_count' => fn ($q) => $q
                    ->whereNull('read_at')
                    ->where('sender_id', '!=', auth()->id()),
                ])
                ->orderByDesc('last_message_at')
                ->get()
            : collect();

        $totalUnreadMessages = $on('messages')
            ? $conversations->sum('unread_count')
            : Message::query()
                ->whereNull('read_at')
                ->where('sender_id', '!=', auth()->id())
                ->whereHas('conversation.customer', fn (Builder $q) => $q->where('role', 'user'))
                ->count();
        $adminUnreadNotifications = auth()->user()->unreadNotifications()->count();
        // Payment configuration is intentionally not hydrated for staff. The
        // UI can still render its read-only operational cards without learning
        // the merchant account details.
        $gcashSettings = $isAdmin
            ? AppSetting::getGcashSettings()
            : ['name' => null, 'number' => null, 'qr_url' => null];
        $moduleCards = [
            [
                'name' => 'AR Visualizer',
                'status' => $productStats['ar_ready'] > 0 ? 'Active' : 'Needs Setup',
                'metric' => $productStats['ar_ready'].' AR-ready',
                'description' => 'Products with uploaded 3D models for mobile AR viewing.',
                'tab' => 'products',
                'tone' => 'emerald',
            ],
            [
                'name' => 'Cost Estimator',
                'status' => ($services->total() + $productStats['active']) > 0 ? 'Active' : 'Needs Setup',
                'metric' => $services->total().' services',
                'description' => 'Customers can estimate landscaping cost from services and products.',
                'tab' => null,
                'tone' => 'brand',
            ],
            [
                'name' => 'Ordering',
                'status' => 'Active',
                'metric' => $totalOrders.' orders',
                'description' => 'Customers can place product orders and track their status.',
                'tab' => 'orders',
                'tone' => 'blue',
            ],
            [
                'name' => 'Billing',
                'status' => ! empty($gcashSettings['name']) || ! empty($gcashSettings['number']) ? 'Configured' : 'Needs Setup',
                'metric' => $orderFlowStats['unpaid'].' need attention',
                'description' => 'Payment status, receipts, and GCash checkout details.',
                'tab' => $isAdmin ? 'payment' : null,
                'tone' => 'amber',
            ],
            [
                'name' => 'Service Scheduling',
                'status' => 'Active',
                'metric' => $pendingAppointments.' active',
                'description' => 'Staff can review, confirm, complete, and coordinate schedules.',
                'tab' => 'appointments',
                'tone' => 'indigo',
            ],
            [
                'name' => 'Site Visit Booking',
                'status' => $services->total() > 0 ? 'Active' : 'Needs Services',
                'metric' => $appointments->total().' bookings',
                'description' => 'Customers can book site visits with date/time availability checks.',
                'tab' => 'appointments',
                'tone' => 'sky',
            ],
            [
                'name' => 'Delivery Management',
                'status' => 'Active',
                'metric' => Order::query()->whereNull('archived_at')->whereIn('status', ['confirmed', 'out_for_delivery', 'delivered'])->count().' in flow',
                'description' => 'Delivery status, proof upload, and customer receipt confirmation.',
                'tab' => 'orders',
                'tone' => 'purple',
            ],
            [
                'name' => 'Inventory',
                'status' => $productStats['total'] > 0 ? 'Active' : 'Needs Products',
                'metric' => $productStats['low_stock'].' low stock',
                'description' => 'Product stock, availability, low-stock warnings, and AR readiness.',
                'tab' => 'products',
                'tone' => 'green',
            ],
            [
                'name' => 'Feedback',
                'status' => 'Active',
                'metric' => $feedbacks->total().' reviews',
                'description' => 'Customer ratings, comments, and service/order feedback history.',
                'tab' => 'feedbacks',
                'tone' => 'rose',
            ],
        ];

        return view('admin.dashboard', compact(
            'totalSales',
            'recognizedRevenue',
            'totalOrders',
            'deliveredOrders',
            'pendingOrders',
            'salesByStatus',
            'adminOrders',
            'products',
            'archivedProducts',
            'monthlySales',
            'pendingAppointments',
            'appointmentsNeedingConfirmation',
            'todayAppointments',
            'overdueAppointments',
            'priorityAppointments',
            'ordersAwaitingConfirmation',
            'priorityOrders',
            'totalUsers',
            'appointments',
            'services',
            'serviceStats',
            'archivedServices',
            'users',
            'thisWeekSales',
            'lastWeekSales',
            'weekSalesDeltaPct',
            'topProducts',
            'orderFlowStats',
            'productQ',
            'productCategory',
            'productCategories',
            'productStats',
            'serviceQ',
            'apptQ',
            'apptStatus',
            'auditLogs',
            'auditQ',
            'salesFrom',
            'salesTo',
            'archivedOrders',
            'archivedAppointments',
            'feedbacks',
            'feedbackQ',
            'avgRating',
            'lowStockProducts',
            'conversations',
            'totalUnreadMessages',
            'adminUnreadNotifications',
            'gcashSettings',
            'moduleCards',
            'activeTab'
        ));
    }

    public function showOrder(Order $order): View
    {
        $isAdmin = auth()->user()?->isAdmin() === true;
        abort_unless($order->archived_at === null || $isAdmin, 404);

        $order->load(['user', 'orderItems.product', 'feedback', 'paymentVerifiedBy:id,name']);
        $history = $isAdmin
            ? AuditLog::query()->with('actor')
                ->where('auditable_type', Order::class)
                ->where('auditable_id', $order->id)
                ->latest('created_at')
                ->limit(50)
                ->get()
            : collect();

        return view('admin.order-show', [
            'order' => $order,
            'isAdmin' => $isAdmin,
            'history' => $history,
        ]);
    }

    public function showAppointment(Appointment $appointment): View
    {
        $isAdmin = auth()->user()?->isAdmin() === true;
        abort_unless($appointment->archived_at === null || $isAdmin, 404);

        $appointment->load(['user', 'serviceType', 'feedback']);
        $history = $isAdmin
            ? AuditLog::query()->with('actor')
                ->where('auditable_type', Appointment::class)
                ->where('auditable_id', $appointment->id)
                ->latest('created_at')
                ->limit(50)
                ->get()
            : collect();

        return view('admin.appointment-show', [
            'appointment' => $appointment,
            'isStaffOrAdmin' => auth()->user()?->isStaffOrAdmin(),
            'isAdmin' => $isAdmin,
            'history' => $history,
        ]);
    }

    public function updatePaymentSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'gcash_name' => ['nullable', 'string', 'max:255'],
            'gcash_number' => ['nullable', 'string', 'max:50'],
            'gcash_qr' => ['nullable', 'image', 'max:5120'],
            'remove_gcash_qr' => ['nullable', 'boolean'],
        ]);

        AppSetting::setValue('gcash_name', $data['gcash_name'] ?? null);
        AppSetting::setValue('gcash_number', $data['gcash_number'] ?? null);

        if ($request->boolean('remove_gcash_qr')) {
            AppSetting::setValue('gcash_qr_url', null);
        }

        if ($request->hasFile('gcash_qr')) {
            AppSetting::setValue('gcash_qr_url', Storage::disk('public')->url(
                $request->file('gcash_qr')->store('payment', 'public')
            ));
        }

        return redirect()->route('admin.dashboard', ['tab' => 'payment'])
            ->with('status', 'GCash payment details updated.');
    }

    public function getConversation(Conversation $conversation): JsonResponse
    {
        $conversation->loadMissing('customer:id,name,role');
        abort_unless($conversation->customer?->isUser(), 403, 'Only customer conversations are available in the admin inbox.');

        $messages = $conversation->messages()
            ->with('sender:id,name,role')
            ->oldest()
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'created_at' => $m->created_at->format('M j, Y g:i A'),
                'is_admin' => $m->sender->isStaffOrAdmin(),
                'sender' => $m->sender->name,
                'attachment' => $m->attachmentPayload(),
            ]);

        // Mark customer messages as read
        $conversation->messages()
            ->where('sender_id', '!=', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'messages' => $messages,
            'customer' => $conversation->customer->name,
        ]);
    }

    public function replyMessage(Request $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        $conversation->loadMissing('customer:id,name,role');
        abort_unless($conversation->customer?->isUser(), 403, 'Admins can only reply to customer conversations.');

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:2000', 'required_without:attachment'],
            'attachment' => MessageAttachment::rules(),
        ], [
            'body.required_without' => 'Type a reply or attach a file.',
        ]);

        $attachment = $request->hasFile('attachment')
            ? MessageAttachment::store($request->file('attachment'))
            : [];

        $message = $conversation->messages()->create([
            'sender_id' => auth()->id(),
            'body' => trim((string) ($data['body'] ?? '')) ?: null,
            ...$attachment,
        ]);

        $conversation->update(['last_message_at' => now()]);

        // The inbox replies over fetch(). Answering with a redirect made a
        // rejected upload look successful, because fetch follows the redirect
        // and reports the resulting page as 200.
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'attachment' => $message->attachmentPayload(),
                ],
            ], 201);
        }

        return redirect()->route('admin.dashboard', ['tab' => 'messages', 'convo' => $conversation->id])
            ->with('status', 'Reply sent.');
    }

    /**
     * Distinct categories already in use, for the category dropdowns.
     *
     * @return list<string>
     */
    private function productCategories(): array
    {
        $used = Product::query()
            ->whereNull('archived_at')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category');

        // Seeded so a fresh shop still offers the usual landscaping categories
        // instead of an empty dropdown.
        return $used
            ->merge(['plants', 'trees', 'shrubs', 'flowers', 'seeds', 'soil', 'fertilizer', 'pots', 'tools', 'materials'])
            ->map(fn ($category) => strtolower(trim((string) $category)))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function createProduct(): View
    {
        return view('admin.product-create', [
            'isStaffOrAdmin' => auth()->user()?->isStaffOrAdmin(),
            'categories' => $this->productCategories(),
        ]);
    }

    public function storeProduct(Request $request, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'name')->whereNull('archived_at'),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:2048'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock_qty' => ['nullable', 'integer', 'min:0'],
            'category' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        [$imagePath, $imageUrl] = $this->storeProductImage($request);

        $openingStock = (int) ($data['stock_qty'] ?? 0);

        // The product row and its opening stock movement commit together, so a
        // failed movement can never leave a product with phantom stock.
        try {
            $product = DB::transaction(function () use ($data, $imageUrl, $openingStock, $inventory, $request): Product {
                $product = Product::query()->create([
                    'name' => $data['name'],
                    'description' => $data['description'] ?? '',
                    'image_url' => $imageUrl,
                    'price' => $data['price'],
                    // Created at zero so the opening stock is booked through the ledger
                    // rather than appearing from nowhere.
                    'stock_qty' => 0,
                    'category' => strtolower(trim($data['category'])),
                    'is_active' => (bool) ($data['is_active'] ?? false),
                    'archived_at' => null,
                ]);

                if ($openingStock > 0) {
                    $inventory->record($product, StockMovement::TYPE_OPENING, $openingStock, [
                        'user_id' => $request->user()->id,
                        'note' => 'Opening stock entered when the product was created.',
                    ]);
                }

                return $product;
            }, 3);
        } catch (\Throwable $exception) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $exception;
        }

        Audit::log(
            $request,
            'product.create',
            $product,
            null,
            Audit::snapshot($product, ['name', 'description', 'image_url', 'price', 'stock_qty', 'category', 'is_active', 'archived_at'])
        );

        Cache::forget('shop_products_active');

        return redirect()->route('admin.dashboard', ['tab' => 'products'])
            ->with('status', 'Product added successfully.');
    }

    public function editProduct(Product $product): View
    {
        abort_unless($product->archived_at === null, 404);

        $product->load('plantModel');

        // Stock movements and catalogue edits are admin-only, matching the
        // route boundary; staff only see the read-only catalogue cards.
        $canManageStock = (bool) auth()->user()?->isAdmin();

        return view('admin.product-edit', [
            'product' => $product,
            'isStaffOrAdmin' => auth()->user()?->isStaffOrAdmin(),
            'canManageStock' => $canManageStock,
        ]);
    }

    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->archived_at === null, 404);

        $before = Audit::snapshot($product, ['name', 'description', 'image_url', 'price', 'stock_qty', 'category', 'is_active', 'archived_at']);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'name')
                    ->ignore($product->id)
                    ->whereNull('archived_at'),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:2048'],
            'price' => ['required', 'numeric', 'min:0'],
            // stock_qty is deliberately absent: stock only moves through the
            // ledger, where every change carries a reason. A posted value is
            // ignored rather than silently applied.
            'category' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $previousImageUrl = $product->image_url;
        [$newImagePath, $newImageUrl] = $this->storeProductImage($request);
        $imageUrl = $newImageUrl ?? $previousImageUrl;

        try {
            $product->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'image_url' => $imageUrl,
                'price' => $data['price'],
                'category' => strtolower(trim($data['category'])),
                'is_active' => (bool) ($data['is_active'] ?? false),
            ]);
        } catch (\Throwable $exception) {
            if ($newImagePath) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $exception;
        }

        if ($newImagePath) {
            $this->deleteProductImage($previousImageUrl);
        }

        Cache::forget('shop_products_active');

        $product->refresh();
        Audit::log(
            $request,
            'product.update',
            $product,
            $before,
            Audit::snapshot($product, ['name', 'description', 'image_url', 'price', 'stock_qty', 'category', 'is_active', 'archived_at'])
        );

        if ($request->input('redirect_to') === 'edit') {
            return redirect()->route('admin.products.edit', $product)
                ->with('status', 'Product updated successfully.');
        }

        return redirect()->route('admin.dashboard', ['tab' => 'products'])
            ->with('status', 'Product updated successfully.');
    }

    /** @return array{0: ?string, 1: ?string} */
    private function storeProductImage(Request $request): array
    {
        if (! $request->hasFile('image')) {
            return [null, null];
        }

        $path = $request->file('image')->store('products', 'public');

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'image' => 'The product image could not be saved. Please try again.',
            ]);
        }

        return [$path, Storage::disk('public')->url($path)];
    }

    private function deleteProductImage(?string $imageUrl): void
    {
        if (! $imageUrl) {
            return;
        }

        $urlPath = parse_url($imageUrl, PHP_URL_PATH);
        if (! is_string($urlPath)) {
            return;
        }

        $normalizedPath = '/'.ltrim(str_replace('\\', '/', $urlPath), '/');
        if (! Str::contains($normalizedPath, '/storage/products/')) {
            return;
        }

        $filename = Str::afterLast($normalizedPath, '/storage/products/');
        if (! preg_match('/\A[A-Za-z0-9_-]+\.(?:jpe?g|png|gif|bmp|webp)\z/i', $filename)) {
            return;
        }

        Storage::disk('public')->delete('products/'.$filename);
    }

    public function deleteProduct(Request $request, Product $product): RedirectResponse
    {
        $before = Audit::snapshot($product, ['name', 'description', 'image_url', 'price', 'stock_qty', 'category', 'is_active', 'archived_at']);
        $product->update(['archived_at' => now()]);

        Cache::forget('shop_products_active');

        $product->refresh();
        Audit::log(
            $request,
            'product.archive',
            $product,
            $before,
            Audit::snapshot($product, ['name', 'description', 'image_url', 'price', 'stock_qty', 'category', 'is_active', 'archived_at'])
        );

        return redirect()->route('admin.dashboard', ['tab' => 'products'])
            ->with('status', 'Product archived successfully.');
    }

    public function restoreProduct(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->archived_at !== null, 404);

        $before = Audit::snapshot($product, ['name', 'description', 'image_url', 'price', 'stock_qty', 'category', 'is_active', 'archived_at']);
        $product->update(['archived_at' => null]);

        Cache::forget('shop_products_active');

        $product->refresh();
        Audit::log(
            $request,
            'product.restore',
            $product,
            $before,
            Audit::snapshot($product, ['name', 'description', 'image_url', 'price', 'stock_qty', 'category', 'is_active', 'archived_at'])
        );

        return redirect()->route('admin.dashboard', ['tab' => 'archived'])
            ->with('status', 'Product restored successfully.');
    }

    public function updateOrderStatus(Request $request, Order $order, InventoryService $inventory, BillingService $billing): RedirectResponse|JsonResponse
    {
        $isAdmin = $request->user()->isAdmin();
        abort_unless($order->archived_at === null || $isAdmin, 404);

        // Cancellation returns stock and may trigger a refund decision. Fail
        // before validation so a forged staff request cannot turn this into a
        // validation-only bypass by adding unrelated payment fields.
        if (! $isAdmin && $request->input('status') === 'cancelled') {
            abort(403, 'Administrator access is required to cancel an order.');
        }

        $isDeliveryOrder = ($order->delivery_method ?? 'delivery') === 'delivery';

        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', $isAdmin ? self::ORDER_STATUSES : self::STAFF_ORDER_STATUSES)],
            'payment_status' => $isAdmin
                ? ['required', 'string', 'in:unpaid,pending_verification,paid,rejected,refunded']
                : ['prohibited'],
            'payment_review_notes' => $isAdmin
                ? ['nullable', 'string', 'max:1000', Rule::requiredIf(
                    fn () => $request->input('payment_status') === 'rejected'
                        && $order->payment_status !== 'rejected'
                        && blank($order->payment_review_notes)
                )]
                : ['prohibited'],
            'delivery_proof' => ['nullable', 'image', 'max:5120'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'driver_phone' => ['nullable', 'string', 'regex:/^\d{11}$/'],
            'dispatch_notes' => ['nullable', 'string', 'max:1000'],
            'delivery_recipient_name' => ['nullable', 'string', 'max:255'],
        ], [
            'driver_phone.regex' => 'Driver contact must contain exactly 11 digits.',
        ]);

        // Payment state is always the current server-side value for staff.
        // Only the admin branch below is allowed to verify or settle money.
        $paymentStatus = $isAdmin ? $data['payment_status'] : ($order->payment_status ?? 'unpaid');

        if (! $order->canTransitionTo($data['status'])) {
            $message = 'Order cannot move from '.str_replace('_', ' ', $order->status).' to '.str_replace('_', ' ', $data['status']).'.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->withErrors(['status' => $message]);
        }

        if ($isAdmin
            && ($order->payment_method ?? 'cod') === 'gcash'
            && $paymentStatus === 'paid'
            && ! $order->payment_proof_path) {
            return $this->orderStatusError(
                $request,
                'payment_status',
                'A GCash payment receipt is required before the payment can be verified.'
            );
        }

        if ($isDeliveryOrder
            && in_array($data['status'], ['out_for_delivery', 'delivered'], true)
            && blank($data['driver_name'] ?? $order->driver_name)) {
            return $this->orderStatusError($request, 'driver_name', 'Please provide the assigned driver or rider name.');
        }

        if ($isDeliveryOrder
            && in_array($data['status'], ['out_for_delivery', 'delivered'], true)
            && blank($data['driver_phone'] ?? $order->driver_phone)) {
            return $this->orderStatusError($request, 'driver_phone', 'Please provide the driver contact number.');
        }

        if ($isDeliveryOrder
            && $data['status'] === 'delivered'
            && ! $request->hasFile('delivery_proof')
            && ! $order->delivery_proof_url) {
            $message = 'Please upload a delivery proof photo before marking this order as delivered.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['delivery_proof' => $message]);
        }

        if ($isDeliveryOrder
            && $data['status'] === 'delivered'
            && blank($data['delivery_recipient_name'] ?? $order->delivery_recipient_name)) {
            return $this->orderStatusError(
                $request,
                'delivery_recipient_name',
                'Please record the name of the person who received the delivery.'
            );
        }

        $updates = ['status' => $data['status']];

        if ($isAdmin) {
            $updates['payment_status'] = $paymentStatus;

            if (array_key_exists('payment_review_notes', $data)) {
                $updates['payment_review_notes'] = $data['payment_review_notes'];
            }

            if ($paymentStatus === 'paid' && $order->payment_status !== 'paid') {
                $updates['payment_verified_at'] = now();
                $updates['payment_verified_by'] = $request->user()->id;
            } elseif (in_array($paymentStatus, ['unpaid', 'pending_verification', 'rejected'], true)) {
                $updates['payment_verified_at'] = null;
                $updates['payment_verified_by'] = null;
            }
        }

        if ($isDeliveryOrder) {
            foreach (['driver_name', 'driver_phone', 'dispatch_notes', 'delivery_recipient_name'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[$field] = $data[$field];
                }
            }

            if ($request->hasFile('delivery_proof')) {
                $updates['delivery_proof_url'] = Storage::disk('public')->url(
                    $request->file('delivery_proof')->store('delivery-proofs', 'public')
                );
            }
        }

        if ($data['status'] === 'delivered') {
            $updates['delivered_at'] = $order->delivered_at ?? now();
            $updates['customer_confirmed_at'] = null;
        }

        if ($isDeliveryOrder && $data['status'] === 'out_for_delivery') {
            $updates['dispatched_at'] = $order->dispatched_at ?? now();
        }

        if ($data['status'] === 'completed') {
            $updates['delivered_at'] = $order->delivered_at ?? now();
            $updates['customer_confirmed_at'] = $order->customer_confirmed_at ?? now();
        }

        if ($isAdmin) {
            if ($data['status'] === 'cancelled' && $order->status !== 'cancelled') {
                $updates['cancel_reason'] = 'Cancelled by admin.';
                $updates['cancelled_at'] = now();
                $updates['cancelled_by'] = $request->user()->id;
            }

            if ($data['status'] !== 'cancelled') {
                $updates['cancel_reason'] = null;
                $updates['cancelled_at'] = null;
                $updates['cancelled_by'] = null;
            }
        }

        $workflowFields = ['status', 'payment_status', 'payment_review_notes', 'payment_verified_at', 'payment_verified_by', 'dispatch_proof_url', 'dispatched_at', 'driver_name', 'driver_phone', 'dispatch_notes', 'delivery_proof_url', 'delivery_recipient_name', 'delivered_at', 'customer_confirmed_at', 'cancel_reason', 'cancelled_at', 'cancelled_by'];
        $before = Audit::snapshot($order, $workflowFields);
        $orderStatusChanged = $order->status !== $data['status'];
        $paymentStatusChanged = $isAdmin && $order->payment_status !== $paymentStatus;
        DB::transaction(function () use ($order, $updates, $data, $inventory): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless($lockedOrder->canTransitionTo($data['status']), 422, 'Order status changed. Refresh and try again.');

            if ($data['status'] === 'cancelled' && $lockedOrder->status !== 'cancelled') {
                foreach ($lockedOrder->orderItems()->with('product')->get() as $item) {
                    if ($item->product) {
                        $inventory->recordReturn($item->product, $item->qty, $lockedOrder->order_number);
                    }
                }
            }

            $lockedOrder->update($updates);
        }, 3);

        // Marking an order paid writes a settling entry, so the status the admin
        // chose and the payment ledger can never disagree. Staff never enters
        // this branch because their payment status is server-derived.
        if ($isAdmin && $paymentStatus === 'paid') {
            $billing->settle($order->refresh(), $request->user()->id, $order->payment_method ?: 'cash');
        }

        $order->refresh()->load('user');
        Audit::log($request, 'order.status.update', $order, $before, Audit::snapshot($order, $workflowFields));

        if ($orderStatusChanged) {
            try {
                $order->user->notify(new OrderStatusChanged($order));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($paymentStatusChanged) {
            try {
                $order->user->notify(new OrderPaymentReviewed($order));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($orderStatusChanged && $order->user->phone_number) {
            try {
                $label = match ($order->status) {
                    'confirmed' => 'confirmed',
                    'out_for_delivery' => 'out for delivery',
                    'delivered' => 'delivered',
                    'completed' => 'completed',
                    'cancelled' => 'cancelled',
                    default => $order->status,
                };
                SendSmsJob::dispatch(
                    $order->user->phone_number,
                    "Ferosa: Your order #{$order->order_number} has been {$label}."
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $message = "Order {$order->order_number} updated to ".str_replace('_', ' ', $data['status']).'.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'status' => $order->status,
                'status_label' => ucfirst(str_replace('_', ' ', $order->status)),
                'payment_status' => $order->payment_status ?? 'unpaid',
                'payment_label' => ucfirst(str_replace('_', ' ', $order->payment_status ?? 'unpaid')),
                'delivery_proof_url' => $order->delivery_proof_url
                    ? route('orders.delivery-proof', $order)
                    : null,
                'dispatch_proof_url' => $order->dispatch_proof_url
                    ? route('orders.dispatch-proof', $order)
                    : null,
                'dispatched_at' => optional($order->dispatched_at)->format('M d, Y h:i A'),
                'driver_name' => $order->driver_name,
                'delivery_recipient_name' => $order->delivery_recipient_name,
                'delivered_at' => optional($order->delivered_at)->format('M d, Y h:i A'),
                'customer_confirmed_at' => optional($order->customer_confirmed_at)->format('M d, Y h:i A'),
            ]);
        }

        if ($request->input('redirect_to') === 'show') {
            return redirect()->route('admin.orders.show', $order)
                ->with('status', $message);
        }

        return redirect()->route('admin.dashboard', ['tab' => 'orders'])
            ->with('status', $message);
    }

    public function bulkOrderStatus(Request $request, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer', 'exists:orders,id'],
            'status' => ['required', 'string', 'in:'.implode(',', self::ORDER_STATUSES)],
        ]);

        if (in_array($data['status'], ['out_for_delivery', 'delivered', 'completed'], true)) {
            return redirect()->route('admin.dashboard', ['tab' => 'orders'])
                ->withErrors(['status' => 'Dispatch and delivery updates must be completed one order at a time with the required proof and recipient details.']);
        }

        $orders = Order::query()
            ->with('user')
            ->whereIn('id', $data['order_ids'])
            ->get();

        $bulkUpdates = ['status' => $data['status']];

        if ($data['status'] === 'cancelled') {
            $bulkUpdates['cancel_reason'] = 'Cancelled by admin.';
            $bulkUpdates['cancelled_at'] = now();
            $bulkUpdates['cancelled_by'] = $request->user()->id;
        } else {
            $bulkUpdates['cancel_reason'] = null;
            $bulkUpdates['cancelled_at'] = null;
            $bulkUpdates['cancelled_by'] = null;
        }

        $updatedOrders = collect();
        foreach ($orders as $order) {
            if (! $order->canTransitionTo($data['status'])) {
                continue;
            }

            $before = Audit::snapshot($order, ['status', 'cancel_reason', 'cancelled_at', 'cancelled_by']);
            DB::transaction(function () use ($order, $bulkUpdates, $data, $inventory): void {
                $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
                if (! $lockedOrder->canTransitionTo($data['status'])) {
                    return;
                }

                if ($data['status'] === 'cancelled' && $lockedOrder->status !== 'cancelled') {
                    foreach ($lockedOrder->orderItems()->with('product')->get() as $item) {
                        if ($item->product) {
                            $inventory->recordReturn($item->product, $item->qty, $lockedOrder->order_number);
                        }
                    }
                }

                $lockedOrder->update($bulkUpdates);
            }, 3);

            $order->refresh();
            if ($order->status === $data['status']) {
                Audit::log($request, 'order.status.bulk-update', $order, $before, Audit::snapshot($order, ['status', 'cancel_reason', 'cancelled_at', 'cancelled_by']));
                $updatedOrders->push($order);
            }
        }

        $orders = $updatedOrders;

        $label = match ($data['status']) {
            'confirmed' => 'confirmed',
            'out_for_delivery' => 'out for delivery',
            'delivered' => 'delivered',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            default => $data['status'],
        };

        foreach ($orders as $order) {
            try {
                $order->user->notify(new OrderStatusChanged($order));
            } catch (\Throwable $e) {
                report($e);
            }
            if ($order->user->phone_number) {
                SendSmsJob::dispatch(
                    $order->user->phone_number,
                    "Ferosa: Your order #{$order->order_number} has been {$label}."
                );
            }
        }

        return redirect()->route('admin.dashboard', ['tab' => 'orders'])
            ->with('status', "Updated {$orders->count()} order(s) to ".str_replace('_', ' ', $data['status']).'.');
    }

    /**
     * Write one CSV row with spreadsheet formulas defused.
     *
     * Customer names are free text, so a customer who registers as
     * `=cmd|'/c calc'!A1` would otherwise ship a live formula to whichever
     * admin opens the export. fputcsv quotes the field but Excel still parses
     * a leading =, +, -, @, tab or CR as the start of a formula, so those cells
     * get a leading apostrophe - which Excel treats as "this is text" and does
     * not display.
     */
    private function writeCsvRow($out, array $row): void
    {
        fputcsv($out, array_map(static function ($cell) {
            if (! is_string($cell) || $cell === '') {
                return $cell;
            }

            return str_contains("=+-@\t\r", $cell[0]) ? "'".$cell : $cell;
        }, $row));
    }

    public function exportOrdersCsv(): StreamedResponse
    {
        $filename = 'orders-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            $this->writeCsvRow($out, ['Order #', 'Customer', 'Email', 'Amount', 'Status', 'Created']);

            foreach ($this->filteredOrdersQuery()->cursor() as $order) {
                $this->writeCsvRow($out, [
                    $order->order_number,
                    $order->user->name ?? '',
                    $order->user->email ?? '',
                    $order->total_amount,
                    $order->status,
                    $order->created_at?->toAtomString() ?? '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function overviewReport(Request $request): View
    {
        return view('admin.overview-report', $this->overviewReportData($request));
    }

    public function exportOverviewCsv(Request $request): StreamedResponse
    {
        $data = $this->overviewReportData($request);
        $filename = 'overview-report-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            $this->writeCsvRow($out, ['Ferosa Revenue Overview Report']);
            $this->writeCsvRow($out, ['Generated', $data['generatedAt']->format('M d, Y h:i A')]);
            $this->writeCsvRow($out, ['From', $data['salesFrom'] ?: 'Any']);
            $this->writeCsvRow($out, ['To', $data['salesTo'] ?: 'Any']);
            $this->writeCsvRow($out, []);

            $this->writeCsvRow($out, ['Summary']);
            $this->writeCsvRow($out, ['Total Sales', $data['totalSales']]);
            $this->writeCsvRow($out, ['Total Orders', $data['totalOrders']]);
            $this->writeCsvRow($out, ['Delivered Orders', $data['deliveredOrders']]);
            $this->writeCsvRow($out, ['Pending Orders', $data['pendingOrders']]);
            $this->writeCsvRow($out, []);

            $this->writeCsvRow($out, ['Sales by Status']);
            $this->writeCsvRow($out, ['Status', 'Orders', 'Sales']);
            foreach ($data['salesByStatus'] as $row) {
                $this->writeCsvRow($out, [ucfirst(str_replace('_', ' ', $row->status)), $row->order_count, (float) $row->sales_total]);
            }
            $this->writeCsvRow($out, []);

            $this->writeCsvRow($out, ['Monthly Sales']);
            $this->writeCsvRow($out, ['Month', 'Sales']);
            foreach ($data['monthlySales'] as $row) {
                $this->writeCsvRow($out, [$row['label'], $row['total']]);
            }
            $this->writeCsvRow($out, []);

            $this->writeCsvRow($out, ['Week vs Week Revenue']);
            $this->writeCsvRow($out, ['This Week', $data['thisWeekSales']]);
            $this->writeCsvRow($out, ['Last Week', $data['lastWeekSales']]);
            $this->writeCsvRow($out, ['Change %', $data['weekSalesDeltaPct'] === null ? 'N/A' : $data['weekSalesDeltaPct']]);
            $this->writeCsvRow($out, []);

            $this->writeCsvRow($out, ['Top Products']);
            $this->writeCsvRow($out, ['Product', 'Units Sold']);
            foreach ($data['topProducts'] as $row) {
                $this->writeCsvRow($out, [$row['name'], $row['qty']]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function overviewReportData(Request $request): array
    {
        $range = $request->validate([
            'sales_from' => ['nullable', 'date'],
            'sales_to' => ['nullable', 'date', 'after_or_equal:sales_from'],
        ]);

        $salesFrom = $range['sales_from'] ?? null;
        $salesTo = $range['sales_to'] ?? null;
        $ordersBase = Order::query()
            ->when($salesFrom, fn (Builder $q) => $q->whereDate('created_at', '>=', $salesFrom))
            ->when($salesTo, fn (Builder $q) => $q->whereDate('created_at', '<=', $salesTo));

        $salesByStatus = (clone $ordersBase)
            ->selectRaw('status, COUNT(*) as order_count, COALESCE(SUM(total_amount), 0) as sales_total')
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        $monthlySalesRows = Order::query()
            ->selectRaw($this->monthKeyExpression().' as ym, COALESCE(SUM(total_amount), 0) as total')
            ->where('created_at', '>=', now()->startOfMonth()->subMonths(5))
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->keyBy('ym');

        $monthlySales = collect(range(5, 0, -1))
            ->map(function (int $monthsAgo) use ($monthlySalesRows) {
                $dt = now()->startOfMonth()->subMonths($monthsAgo);
                $ym = $dt->format('Y-m');

                return [
                    'label' => $dt->format('M Y'),
                    'total' => (float) ($monthlySalesRows[$ym]->total ?? 0),
                ];
            });

        $thisWeekStart = now()->startOfWeek();
        $thisWeekEnd = now();
        $lastWeekStart = now()->subWeek()->startOfWeek();
        $lastWeekEnd = now()->subWeek()->endOfWeek();

        $thisWeekSales = (float) Order::query()->whereBetween('created_at', [$thisWeekStart, $thisWeekEnd])->sum('total_amount');
        $lastWeekSales = (float) Order::query()->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])->sum('total_amount');
        $weekSalesDeltaPct = $lastWeekSales > 0
            ? round((($thisWeekSales - $lastWeekSales) / $lastWeekSales) * 100, 1)
            : null;

        return [
            'generatedAt' => now(),
            'salesFrom' => $salesFrom,
            'salesTo' => $salesTo,
            'totalSales' => (float) (clone $ordersBase)->sum('total_amount'),
            'totalOrders' => (clone $ordersBase)->count(),
            'deliveredOrders' => (clone $ordersBase)->whereIn('status', ['delivered', 'completed'])->count(),
            'pendingOrders' => (clone $ordersBase)->whereIn('status', ['pending', 'confirmed', 'out_for_delivery'])->count(),
            'salesByStatus' => $salesByStatus,
            'monthlySales' => $monthlySales,
            'thisWeekSales' => $thisWeekSales,
            'lastWeekSales' => $lastWeekSales,
            'weekSalesDeltaPct' => $weekSalesDeltaPct,
            'topProducts' => $this->topSellingProducts(),
        ];
    }

    public function createService(): View
    {
        return view('admin.service-create', [
            'isStaffOrAdmin' => auth()->user()?->isStaffOrAdmin(),
        ]);
    }

    public function storeService(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('service_types', 'name')->whereNull('archived_at'),
            ],
            'default_fee' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $serviceType = ServiceType::query()->create([
            'name' => $data['name'],
            'default_fee' => $data['default_fee'],
            'is_active' => (bool) ($data['is_active'] ?? false),
            'archived_at' => null,
        ]);

        Audit::log(
            $request,
            'service.create',
            $serviceType,
            null,
            Audit::snapshot($serviceType, ['name', 'default_fee', 'is_active', 'archived_at'])
        );

        return redirect()->route('admin.dashboard', ['tab' => 'services'])
            ->with('status', 'Service added successfully.');
    }

    public function editService(ServiceType $serviceType): View
    {
        abort_unless($serviceType->archived_at === null, 404);

        return view('admin.service-edit', [
            'service' => $serviceType,
            'isStaffOrAdmin' => auth()->user()?->isStaffOrAdmin(),
        ]);
    }

    public function updateService(Request $request, ServiceType $serviceType): RedirectResponse
    {
        abort_unless($serviceType->archived_at === null, 404);

        $before = Audit::snapshot($serviceType, ['name', 'default_fee', 'is_active', 'archived_at']);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('service_types', 'name')
                    ->ignore($serviceType->id)
                    ->whereNull('archived_at'),
            ],
            'default_fee' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $serviceType->update([
            'name' => $data['name'],
            'default_fee' => $data['default_fee'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        $serviceType->refresh();
        Audit::log(
            $request,
            'service.update',
            $serviceType,
            $before,
            Audit::snapshot($serviceType, ['name', 'default_fee', 'is_active', 'archived_at'])
        );

        if ($request->input('redirect_to') === 'edit') {
            return redirect()->route('admin.services.edit', $serviceType)
                ->with('status', 'Service updated successfully.');
        }

        return redirect()->route('admin.dashboard', ['tab' => 'services'])
            ->with('status', 'Service updated successfully.');
    }

    public function deleteService(Request $request, ServiceType $serviceType): RedirectResponse
    {
        $before = Audit::snapshot($serviceType, ['name', 'default_fee', 'is_active', 'archived_at']);
        $serviceType->update(['archived_at' => now()]);

        $serviceType->refresh();
        Audit::log(
            $request,
            'service.archive',
            $serviceType,
            $before,
            Audit::snapshot($serviceType, ['name', 'default_fee', 'is_active', 'archived_at'])
        );

        return redirect()->route('admin.dashboard', ['tab' => 'services'])
            ->with('status', 'Service archived successfully.');
    }

    public function restoreService(Request $request, ServiceType $serviceType): RedirectResponse
    {
        abort_unless($serviceType->archived_at !== null, 404);

        $before = Audit::snapshot($serviceType, ['name', 'default_fee', 'is_active', 'archived_at']);
        $serviceType->update(['archived_at' => null]);

        $serviceType->refresh();
        Audit::log(
            $request,
            'service.restore',
            $serviceType,
            $before,
            Audit::snapshot($serviceType, ['name', 'default_fee', 'is_active', 'archived_at'])
        );

        return redirect()->route('admin.dashboard', ['tab' => 'archived'])
            ->with('status', 'Service restored successfully.');
    }

    public function archived(): View
    {
        $archivedProducts = Product::query()
            ->whereNotNull('archived_at')
            ->orderByDesc('archived_at')
            ->take(500)
            ->get();

        $archivedServices = ServiceType::query()
            ->whereNotNull('archived_at')
            ->orderByDesc('archived_at')
            ->take(500)
            ->get();

        return view('admin.archived', [
            'archivedProducts' => $archivedProducts,
            'archivedServices' => $archivedServices,
        ]);
    }

    public function cancelAppointment(Request $request, Appointment $appointment): RedirectResponse
    {
        abort_unless($appointment->archived_at === null, 404);
        abort_unless($appointment->status !== 'cancelled' && $appointment->canTransitionTo('cancelled'), 422);

        $data = $request->validate([
            'cancel_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $isAdmin = $request->user()->isAdmin();
        $cancelReason = filled($data['cancel_reason'] ?? null)
            ? $data['cancel_reason']
            : ($isAdmin ? 'Cancelled by admin.' : 'Cancelled by staff.');
        $before = Audit::snapshot($appointment, ['status', 'appointment_at', 'cancel_reason', 'cancelled_at', 'cancelled_by', 'archived_at']);
        $updates = [
            'status' => 'cancelled',
            'slot_key' => null,
            'cancel_reason' => $cancelReason,
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
        ];

        DB::transaction(function () use ($appointment, $updates): void {
            $lockedAppointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            abort_unless(
                $lockedAppointment->status !== 'cancelled'
                    && $lockedAppointment->canTransitionTo('cancelled'),
                422,
                'Appointment status changed. Refresh and try again.'
            );
            $lockedAppointment->update($updates);
        }, 3);

        $appointment->refresh()->load(['user', 'serviceType']);

        try {
            $appointment->user->notify(new AppointmentStatusChanged($appointment));
        } catch (\Throwable $e) {
            report($e);
        }

        if ($appointment->user->phone_number) {
            try {
                $service = $appointment->serviceType->name ?? 'Service';
                SendSmsJob::dispatch(
                    $appointment->user->phone_number,
                    "Ferosa: Your {$service} appointment has been cancelled."
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $appointment->refresh();
        Audit::log($request, 'appointment.cancel', $appointment, $before, Audit::snapshot($appointment, ['status', 'appointment_at', 'cancel_reason', 'cancelled_at', 'cancelled_by', 'archived_at']));

        return redirect()->route('admin.dashboard', ['tab' => 'appointments'])
            ->with('status', 'Appointment cancelled and customer notified.');
    }

    public function updateAppointmentStatus(Request $request, Appointment $appointment, BillingService $billing): RedirectResponse|JsonResponse
    {
        $isAdmin = $request->user()->isAdmin();
        abort_unless($appointment->archived_at === null || $isAdmin, 404);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:scheduled,confirmed,completed,cancelled'],
            // `partial` is derived from the ledger, never chosen here. Staff
            // may change the visit status but cannot submit a payment state.
            'payment_status' => $isAdmin
                ? ['required', 'string', 'in:unpaid,paid']
                : ['prohibited'],
        ]);

        $paymentStatus = $isAdmin ? $data['payment_status'] : ($appointment->payment_status ?? 'unpaid');

        if (! $appointment->canTransitionTo($data['status'])) {
            $message = 'Appointment cannot move from '.$appointment->status.' to '.$data['status'].'.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->withErrors(['status' => $message]);
        }

        $updates = [
            'status' => $data['status'],
            'slot_key' => in_array($data['status'], ['scheduled', 'confirmed'], true)
                ? Appointment::slotKey($appointment->service_type_id, $appointment->appointment_at)
                : null,
        ];

        if ($isAdmin) {
            $updates['payment_status'] = $paymentStatus;
        }

        if ($data['status'] === 'cancelled' && $appointment->status !== 'cancelled') {
            $updates['cancel_reason'] = $isAdmin ? 'Cancelled by admin.' : 'Cancelled by staff.';
            $updates['cancelled_at'] = now();
            $updates['cancelled_by'] = $request->user()->id;
        }

        if ($data['status'] !== 'cancelled') {
            $updates['cancel_reason'] = null;
            $updates['cancelled_at'] = null;
            $updates['cancelled_by'] = null;
        }

        $before = Audit::snapshot($appointment, ['status', 'payment_status', 'slot_key', 'cancel_reason', 'cancelled_at', 'cancelled_by']);
        DB::transaction(function () use ($appointment, $updates, $data): void {
            $lockedAppointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            abort_unless($lockedAppointment->canTransitionTo($data['status']), 422, 'Appointment status changed. Refresh and try again.');
            $lockedAppointment->update($updates);
        }, 3);

        // Same rule as orders: marking it paid books a settling ledger entry.
        if ($isAdmin && $paymentStatus === 'paid') {
            $billing->settle($appointment->refresh(), $request->user()->id);
        }

        $appointment->refresh()->load(['user', 'serviceType']);
        Audit::log($request, 'appointment.status.update', $appointment, $before, Audit::snapshot($appointment, ['status', 'payment_status', 'slot_key', 'cancel_reason', 'cancelled_at', 'cancelled_by']));

        try {
            $appointment->user->notify(new AppointmentStatusChanged($appointment));
        } catch (\Throwable $e) {
            report($e);
        }

        if ($appointment->user->phone_number) {
            try {
                $service = $appointment->serviceType->name ?? 'Service';
                $label = match ($appointment->status) {
                    'confirmed' => 'confirmed',
                    'completed' => 'completed',
                    'cancelled' => 'cancelled',
                    default => $appointment->status,
                };
                SendSmsJob::dispatch(
                    $appointment->user->phone_number,
                    "Ferosa: Your {$service} appointment has been {$label}."
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $message = 'Appointment updated to '.ucfirst($data['status']).'.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'status' => $appointment->status,
                'status_label' => ucfirst(str_replace('_', ' ', $appointment->status)),
                'payment_status' => $appointment->payment_status ?? 'unpaid',
                'payment_label' => ucfirst($appointment->payment_status ?? 'unpaid'),
            ]);
        }

        if ($request->input('redirect_to') === 'show') {
            return redirect()->route('admin.appointments.show', $appointment)
                ->with('status', $message);
        }

        return redirect()->route('admin.dashboard', ['tab' => 'appointments'])
            ->with('status', $message);
    }

    /**
     * Widen (or narrow) an existing visit instead of booking a second one.
     *
     * A customer who wants hardscaping *and* lawn care on the same slot is
     * asking for one crew visit with two jobs in it. Booking that as two
     * appointments would pass every existing guard -- `slot_key` is
     * `service_type_id|datetime`, so two different services never collide --
     * and would then burn two slots, raise two invoices and appear twice on
     * the scheduler. The booking form promises the team confirms final scope
     * and cost; this is the action that lets them actually do it.
     */
    public function updateAppointmentScope(Request $request, Appointment $appointment, BillingService $billing): RedirectResponse
    {
        $data = $request->validate([
            'appointment_amount' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'scope_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Scope is agreed before the crew rolls out. A completed or cancelled
        // visit is history, and its invoice has already been settled against.
        if (! in_array($appointment->status, ['scheduled', 'confirmed'], true)) {
            return back()->withErrors([
                'appointment_amount' => 'Scope can only be adjusted while the visit is still scheduled or confirmed.',
            ]);
        }

        // BillingService clamps a negative balance to zero, so quoting below
        // what the customer already handed over would silently swallow the
        // difference instead of showing it as a refund owed.
        $alreadyPaid = $billing->totalPaid($appointment);
        if ((float) $data['appointment_amount'] < $alreadyPaid) {
            return back()->withErrors([
                'appointment_amount' => 'The customer has already paid PHP '.number_format($alreadyPaid, 2).'. Refund the difference before quoting less than that.',
            ]);
        }

        $before = Audit::snapshot($appointment, ['appointment_amount', 'scope_notes', 'payment_status']);

        DB::transaction(function () use ($appointment, $data): void {
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            abort_unless(
                in_array($locked->status, ['scheduled', 'confirmed'], true),
                422,
                'Appointment status changed. Refresh and try again.'
            );
            $locked->update([
                'appointment_amount' => $data['appointment_amount'],
                'scope_notes' => $data['scope_notes'] ?? null,
            ]);
        }, 3);

        // A wider scope reopens a balance that was previously settled, so the
        // paid/partial/unpaid label has to be recomputed from the ledger.
        $appointment->refresh();
        $billing->syncPaymentStatus($appointment);

        $appointment->refresh()->load(['user', 'serviceType']);
        Audit::log(
            $request,
            'appointment.scope.update',
            $appointment,
            $before,
            Audit::snapshot($appointment, ['appointment_amount', 'scope_notes', 'payment_status'])
        );

        try {
            $appointment->user->notify(new AppointmentScopeUpdated($appointment));
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('admin.appointments.show', $appointment)
            ->with('status', 'Scope updated. The customer has been notified of the new total.');
    }

    /**
     * Move a visit on the customer's behalf.
     *
     * Customers can move their own visit until it is
     * Appointment::CHANGE_NOTICE_HOURS away, and are told to message the team
     * after that. This is what the team uses to honour that - so it is
     * deliberately not bound by the notice window, only by the visit still
     * being ahead of us and the slot still being a real dispatch time.
     *
     * The status is left alone, unlike a customer move. A confirmed visit the
     * team itself moved is still a time the team has agreed to; making them
     * re-confirm their own change would be theatre.
     */
    public function rescheduleAppointment(MoveAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        abort_unless($appointment->archived_at === null || $request->user()->isAdmin(), 404);

        $data = $request->validated();

        if (! in_array($appointment->status, ['scheduled', 'confirmed'], true)) {
            return back()->withErrors([
                'appointment_at' => 'Only a scheduled or confirmed visit can be moved.',
            ]);
        }

        $appointmentAt = Carbon::parse($data['appointment_at'])->seconds(0);

        if ($appointmentAt->equalTo($appointment->appointment_at)) {
            return back()->withErrors([
                'appointment_at' => 'This visit is already booked for that time.',
            ]);
        }

        $taken = Appointment::query()
            ->where('service_type_id', $appointment->service_type_id)
            ->where('appointment_at', $appointmentAt)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->whereKeyNot($appointment->getKey())
            ->exists();

        if ($taken) {
            return back()->withErrors([
                'appointment_at' => 'That slot is already taken by another visit for this service.',
            ]);
        }

        $previousAt = $appointment->appointment_at->copy();
        $before = Audit::snapshot($appointment, ['appointment_at', 'slot_key']);

        try {
            $appointment->update([
                'appointment_at' => $appointmentAt,
                'slot_key' => Appointment::slotKey((int) $appointment->service_type_id, $appointmentAt),
            ]);
        } catch (QueryException) {
            return back()->withErrors([
                'appointment_at' => 'That slot was just taken. Choose another time.',
            ]);
        }

        $appointment->refresh()->load(['user', 'serviceType']);
        Audit::log(
            $request,
            'appointment.reschedule.staff',
            $appointment,
            $before,
            Audit::snapshot($appointment, ['appointment_at', 'slot_key'])
        );

        // The customer did not ask for this, so silence would leave them
        // turning up on the old day.
        try {
            $appointment->user->notify(new AppointmentMovedByTeam($appointment, $previousAt));
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('admin.appointments.show', $appointment)->with(
            'status',
            'Visit moved to '.$appointmentAt->format('M d, Y \a\t g:i A').'. The customer has been notified.'
        );
    }

    public function updateUserRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'string', 'in:admin,staff,user'],
        ]);

        if ((int) $user->id === (int) $request->user()->id) {
            return redirect()->route('admin.dashboard', ['tab' => 'users'])
                ->with('status', 'You cannot change your own role.');
        }

        // Granting someone admin is the most powerful thing anyone can do in
        // here, and it was the one action that left no trace: archiving an
        // order is audited, handing over the keys was not.
        $before = Audit::snapshot($user, ['role']);

        $user->update([
            'role' => $data['role'],
        ]);

        Audit::log($request, 'user.role.update', $user, $before, Audit::snapshot($user->refresh(), ['role']));

        return redirect()->route('admin.dashboard', ['tab' => 'users'])
            ->with('status', "User {$user->name} role updated to ".ucfirst($data['role']).'.');
    }

    private function filteredOrdersQuery(): Builder
    {
        $ordersQuery = Order::query()
            ->with('user:id,name,email')
            ->whereNull('archived_at');

        if ($status = request('order_status')) {
            $ordersQuery->where('status', $status);
        }

        if ($q = trim((string) request('order_q', ''))) {
            $ordersQuery->where(function ($qry) use ($q) {
                $qry->where('order_number', 'like', '%'.$q.'%')
                    ->orWhereHas('user', function ($u) use ($q) {
                        $u->where('email', 'like', '%'.$q.'%')
                            ->orWhere('name', 'like', '%'.$q.'%');
                    });
            });
        }

        return $ordersQuery
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'confirmed' THEN 1 WHEN 'out_for_delivery' THEN 2 WHEN 'delivered' THEN 3 WHEN 'completed' THEN 4 ELSE 5 END")
            ->orderByDesc('created_at');
    }

    private function monthKeyExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";
    }

    private function topSellingProducts(): Collection
    {
        try {
            return OrderItem::query()
                ->selectRaw('name, SUM(qty) as total_qty')
                ->groupBy('name')
                ->orderByDesc('total_qty')
                ->limit(10)
                ->get()
                ->map(fn ($row) => ['name' => $row->name, 'qty' => (int) $row->total_qty])
                ->values();
        } catch (\Throwable) {
            return collect();
        }
    }

    /**
     * Upload or replace an AR 3D model for a product.
     */
    public function uploadArModel(Request $request, Product $product, GlbValidator $glb): RedirectResponse
    {
        $rules = [
            'height_cm' => ['required', 'numeric', 'min:1', 'max:500'],
        ];

        // File is required only when no existing model (new upload)
        if (! $product->plantModel) {
            $rules['ar_model'] = ['required', 'file', 'max:'.ArAssetBudgets::MAX_FILE_KILOBYTES];
        } else {
            $rules['ar_model'] = ['nullable', 'file', 'max:'.ArAssetBudgets::MAX_FILE_KILOBYTES];
        }

        $data = $request->validate($rules);
        $validationWarnings = [];

        // Validate file extension if a file is uploaded
        if ($request->hasFile('ar_model')) {
            $file = $request->file('ar_model');
            $extension = strtolower($file->getClientOriginalExtension());

            if ($extension !== 'glb') {
                return redirect()->route('admin.products.edit', $product)
                    ->withErrors(['ar_model' => 'Upload a self-contained .glb file. Separate .gltf assets are not supported.'])
                    ->withInput();
            }

            if ($validationError = $glb->validate($file, $validationWarnings)) {
                return redirect()->route('admin.products.edit', $product)
                    ->withErrors(['ar_model' => $validationError])
                    ->withInput();
            }

            $oldPath = $product->plantModel?->file_path;
            $storedPath = $file->store('ar-models', 'public');
            if (! $storedPath) {
                return redirect()->route('admin.products.edit', $product)
                    ->withErrors(['ar_model' => 'The model could not be stored. Please try again.'])
                    ->withInput();
            }
            $fileName = $file->getClientOriginalName();
            $fileSize = $file->getSize();

            try {
                $product->plantModel()->updateOrCreate(
                    ['product_id' => $product->id],
                    [
                        'file_path' => $storedPath,
                        'file_name' => $fileName,
                        'file_size' => $fileSize,
                        'height_cm' => $data['height_cm'],
                    ]
                );
            } catch (\Throwable $exception) {
                Storage::disk('public')->delete($storedPath);
                throw $exception;
            }

            // Keep the existing working model until the replacement is stored
            // and its database record has been updated successfully.
            if ($oldPath && $oldPath !== $storedPath) {
                Storage::disk('public')->delete($oldPath);
            }
        } else {
            // Only updating height_cm (no new file uploaded)
            if ($product->plantModel) {
                $product->plantModel->update([
                    'height_cm' => $data['height_cm'],
                ]);
            }
        }

        $redirect = redirect()->route('admin.products.edit', $product)
            ->with('status', "AR model for \"{$product->name}\" updated successfully.");

        if ($validationWarnings !== []) {
            $redirect->with('ar_model_warnings', $validationWarnings);
        }

        return $redirect;
    }

    /**
     * Remove the AR 3D model from a product.
     */
    public function deleteArModel(Request $request, Product $product): RedirectResponse
    {
        if ($product->plantModel) {
            // Delete the file from storage
            if ($product->plantModel->file_path) {
                Storage::disk('public')->delete($product->plantModel->file_path);
            }

            // Delete the PlantModel record
            $product->plantModel->delete();
        }

        return redirect()->route('admin.products.edit', $product)
            ->with('status', "AR model removed from \"{$product->name}\".");
    }

    private function orderStatusError(Request $request, string $field, string $message): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422)
            : back()->withErrors([$field => $message])->withInput();
    }
}
