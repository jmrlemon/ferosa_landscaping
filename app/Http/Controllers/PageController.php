<?php

namespace App\Http\Controllers;

use App\Http\Requests\RescheduleAppointmentRequest;
use App\Http\Requests\StoreScheduleRequest;
use App\Mail\AppointmentBooked;
use App\Mail\AppointmentStatusUpdated;
use App\Mail\LowStockAlert;
use App\Mail\OrderPlaced;
use App\Mail\OrderStatusUpdated;
use App\Models\Appointment;
use App\Models\AppSetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\Project;
use App\Models\ServiceType;
use App\Models\User;
use App\Notifications\AdminCancellationNotice;
use App\Notifications\WorkCreatedNotice;
use App\Services\CartService;
use App\Services\EstimatorQuoteService;
use App\Services\InventoryService;
use App\Support\Audit;
use App\Support\PasswordRules;
use App\Support\PhoneNumber;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PageController extends Controller
{
    public function home(): View
    {
        $userId = auth()->id();

        $nextAppointment = Appointment::query()
            ->with('serviceType')
            ->where('user_id', $userId)
            ->whereNull('archived_at')
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->where('appointment_at', '>=', now())
            ->orderBy('appointment_at')
            ->first();

        $latestOrder = Order::query()
            ->where('user_id', $userId)
            ->whereNull('archived_at')
            ->latest()
            ->first();

        $featuredServices = ServiceType::query()
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->orderBy('default_fee')
            ->limit(4)
            ->get();

        $featuredProducts = Product::query()
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->where('stock_qty', '>', 0)
            ->latest()
            ->limit(4)
            ->get();

        $featuredProjects = Project::query()
            ->published()
            ->where('is_featured', true)
            ->orderByDesc('completed_at')
            ->latest('id')
            ->limit(3)
            ->get();

        $activityCounts = [
            'active_orders' => Order::query()
                ->where('user_id', $userId)
                ->whereNull('archived_at')
                ->whereNotIn('status', ['delivered', 'completed', 'cancelled'])
                ->count(),
            'completed_services' => Appointment::query()
                ->where('user_id', $userId)
                ->whereNull('archived_at')
                ->where('status', 'completed')
                ->count(),
            'catalog_items' => Product::query()
                ->where('is_active', true)
                ->whereNull('archived_at')
                ->where('stock_qty', '>', 0)
                ->count(),
        ];

        $businessProfile = AppSetting::getBusinessProfile();

        return view('home', compact(
            'nextAppointment',
            'latestOrder',
            'featuredServices',
            'featuredProducts',
            'featuredProjects',
            'activityCounts',
            'businessProfile',
        ));
    }

    /**
     * The public front door. Deliberately its own action rather than a redirect
     * to the shop: a stranger arriving from a search result needs to learn what
     * Ferosa is and that it books services at all, which a bare product grid
     * never says. Everything here is already-public data — the active
     * catalogue, active services, published projects — so no ownership check
     * applies; the same `is_active` / `archived_at` filters the shop uses keep
     * drafts off a page search engines can now index.
     */
    public function landing(): View
    {
        $featuredProducts = Product::query()
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->where('stock_qty', '>', 0)
            ->latest()
            ->limit(6)
            ->get();

        $featuredServices = ServiceType::query()
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->orderBy('default_fee')
            ->limit(4)
            ->get();

        // The hero needs a photograph of finished work, so the best-ranked
        // published project that actually has a cover image is pulled out first
        // and excluded from the grid below rather than shown twice.
        $heroProject = Project::query()
            ->published()
            ->whereNotNull('cover_image_path')
            ->orderByDesc('is_featured')
            ->orderByDesc('completed_at')
            ->latest('id')
            ->first();

        $featuredProjects = Project::query()
            ->published()
            ->when($heroProject, fn ($query) => $query->whereKeyNot($heroProject->getKey()))
            ->orderByDesc('is_featured')
            ->orderByDesc('completed_at')
            ->latest('id')
            ->limit(3)
            ->get();

        // Client quotes are already captured on the project record, so the
        // proof section needs no separate testimonial table.
        $testimonials = Project::query()
            ->published()
            ->whereNotNull('client_quote')
            ->where('client_quote', '!=', '')
            ->orderByDesc('rating')
            ->orderByDesc('completed_at')
            ->limit(3)
            ->get();

        $arProducts = Product::query()
            ->arEnabled()
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->whereNotNull('image_url')
            ->latest()
            ->limit(3)
            ->get();

        $stats = [
            'projects' => Project::query()->published()->count(),
            'products' => Product::query()->where('is_active', true)->whereNull('archived_at')->count(),
            'services' => ServiceType::query()->where('is_active', true)->whereNull('archived_at')->count(),
            'ar_models' => Product::query()->arEnabled()->where('is_active', true)->whereNull('archived_at')->count(),
        ];

        return view('landing', [
            'featuredProducts' => $featuredProducts,
            'featuredServices' => $featuredServices,
            'featuredProjects' => $featuredProjects,
            'heroProject' => $heroProject,
            'testimonials' => $testimonials,
            'arProducts' => $arProducts,
            'stats' => $stats,
            'businessProfile' => AppSetting::getBusinessProfile(),
        ]);
    }

    public function shop(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));
        $category = (string) $request->get('category', 'all');
        $sort = (string) $request->get('sort', 'name_asc');
        $maxPrice = $request->filled('max_price') ? (float) $request->get('max_price') : null;

        $query = Product::query()
            ->with('plantModel')
            ->where('is_active', true)
            ->whereNull('archived_at');

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', '%'.$q.'%')
                    ->orWhere('description', 'like', '%'.$q.'%')
                    ->orWhere('category', 'like', '%'.$q.'%');
            });
        }

        if ($category !== 'all' && $category !== '') {
            $query->where('category', $category);
        }

        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }

        match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'newest' => $query->latest(),
            default => $query->orderBy('name'),
        };

        $products = $query->get();
        $categories = Product::query()
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('shop', compact('products', 'categories', 'q', 'category', 'sort', 'maxPrice'));
    }

    public function product(Product $product): View
    {
        abort_unless($product->is_active && is_null($product->archived_at), 404);

        $product->load('plantModel');

        $relatedProducts = Product::query()
            ->with('plantModel')
            ->whereKeyNot($product->getKey())
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->where('stock_qty', '>', 0)
            ->when($product->category, fn ($query) => $query->where('category', $product->category))
            ->orderBy('name')
            ->limit(4)
            ->get();

        return view('product-show', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
            'businessProfile' => AppSetting::getBusinessProfile(),
        ]);
    }

    public function checkout(Request $request): View
    {
        $checkoutToken = $request->session()->get('checkout_token') ?? (string) Str::uuid();
        $request->session()->put('checkout_token', $checkoutToken);

        return view('checkout', [
            'gcashSettings' => AppSetting::getGcashSettings(),
            'checkoutToken' => $checkoutToken,
        ]);
    }

    public function storeCheckout(Request $request, CartService $cart, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'checkout_token' => ['nullable', 'uuid'],
            'cart_data' => ['nullable', 'string'],
            'delivery_method' => ['required', 'string', 'in:delivery,pickup'],
            'delivery_name' => ['required_if:delivery_method,delivery', 'nullable', 'string', 'max:255'],
            'delivery_phone' => ['required_if:delivery_method,delivery', 'nullable', 'string', 'max:50'],
            'delivery_address' => ['required_if:delivery_method,delivery', 'nullable', 'string', 'max:500'],
            'delivery_city' => ['required_if:delivery_method,delivery', 'nullable', 'string', 'max:255'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', 'string', 'in:cod,gcash'],
            'payment_reference' => ['required_if:payment_method,gcash', 'nullable', 'string', 'min:8', 'max:100', 'regex:/^[A-Za-z0-9 -]+$/'],
            'payment_proof' => ['required_if:payment_method,gcash', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $data['checkout_token'] = $data['checkout_token'] ?? (string) Str::uuid();

        $existingOrder = Order::query()
            ->where('user_id', $request->user()->id)
            ->where('checkout_token', $data['checkout_token'])
            ->first();
        if ($existingOrder) {
            return redirect()->route('orders.confirmation', $existingOrder);
        }

        if ($data['payment_method'] === 'gcash') {
            $gcashSettings = AppSetting::getGcashSettings();
            if (empty($gcashSettings['number']) && empty($gcashSettings['qr_url'])) {
                return back()->withErrors(['payment_method' => 'GCash payment is not available right now. Please choose Cash on Delivery.']);
            }

            $data['payment_reference_normalized'] = Order::normalizePaymentReference($data['payment_reference']);
            if (Order::query()->where('payment_reference_normalized', $data['payment_reference_normalized'])->exists()) {
                return back()->withErrors([
                    'payment_reference' => 'This GCash reference number has already been submitted. Check your Orders page or enter the correct reference.',
                ])->withInput($request->except('payment_proof'));
            }
        } else {
            // A reference only means something for GCash. The form merely hides
            // the GCash block when Cash on Delivery is chosen, so anything
            // already typed is still submitted - and was being stored, leaving
            // cash orders carrying a payment reference that billing would have
            // to explain. Drop it here rather than trusting the form.
            $data['payment_reference'] = null;
        }

        $summary = $cart->summary($request->user());
        $legacyCart = json_decode($data['cart_data'] ?? '[]', true);
        if ($summary['cart_count'] === 0 && is_array($legacyCart) && $legacyCart !== []) {
            $summary = $cart->merge($request->user(), $legacyCart);
        }

        if ($summary['cart_count'] === 0) {
            return back()->withErrors(['Your cart is empty.']);
        }

        $paymentProofPath = $data['payment_method'] === 'gcash'
            ? $request->file('payment_proof')->store('payment-proofs', 'local')
            : null;

        try {
            $order = DB::transaction(function () use ($summary, $data, $request, $cart, $paymentProofPath, $inventory) {
                $lineItems = [];
                $totalPrice = 0.0;
                // Stock leaves once the order number exists, so the movement can
                // reference it. Collected here, applied below.
                $stockOut = [];

                foreach ($summary['items'] as $cartItem) {
                    $product = Product::query()
                        ->whereKey($cartItem['id'])
                        ->where('is_active', true)
                        ->whereNull('archived_at')
                        ->lockForUpdate()
                        ->first();

                    // ValidationException rather than abort(): a sold-out item is
                    // something the customer can act on, so it belongs back on the
                    // checkout form beside their delivery details. abort(422) threw
                    // them onto the framework error page ("Something is broken"),
                    // which discarded both the reason and everything they typed.
                    if (! $product) {
                        throw ValidationException::withMessages([
                            'cart' => 'A product in your cart is no longer available.',
                        ]);
                    }

                    $qty = (int) $cartItem['qty'];
                    if ($product->stock_qty < $qty) {
                        throw ValidationException::withMessages([
                            'cart' => $product->stock_qty === 0
                                ? "{$product->name} is out of stock."
                                : "Only {$product->stock_qty} unit(s) of {$product->name} are available.",
                        ]);
                    }

                    $price = (float) $product->price;
                    $lineItems[] = [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'price' => $price,
                        'qty' => $qty,
                    ];
                    $totalPrice += $price * $qty;
                    $stockOut[] = [$product, $qty];
                }
                Cache::forget('shop_products_active');

                $order = Order::create([
                    'user_id' => $request->user()->id,
                    'order_number' => 'FRS-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                    'checkout_token' => $data['checkout_token'],
                    'status' => 'pending',
                    'payment_status' => $data['payment_method'] === 'gcash' ? 'pending_verification' : 'unpaid',
                    'total_amount' => $totalPrice,
                    'items' => $lineItems,
                    'delivery_method' => $data['delivery_method'],
                    'delivery_name' => $data['delivery_name'] ?? null,
                    'delivery_phone' => $data['delivery_phone'] ?? null,
                    'delivery_address' => $data['delivery_address'] ?? null,
                    'delivery_city' => $data['delivery_city'] ?? null,
                    'delivery_notes' => $data['delivery_notes'] ?? null,
                    'payment_method' => $data['payment_method'],
                    'payment_reference' => $data['payment_reference'] ?? null,
                    'payment_reference_normalized' => $data['payment_reference_normalized'] ?? null,
                    'payment_proof_path' => $paymentProofPath,
                ]);

                foreach ($lineItems as $item) {
                    $order->orderItems()->create([
                        'product_id' => $item['product_id'],
                        'name' => $item['name'],
                        'price' => $item['price'],
                        'qty' => $item['qty'],
                    ]);
                }

                foreach ($stockOut as [$product, $qty]) {
                    $inventory->recordSale($product, $qty, $order->order_number);
                }

                $cart->clear($request->user());

                return $order;
            }, 3);
        } catch (\Throwable $e) {
            if ($paymentProofPath) {
                Storage::disk('local')->delete($paymentProofPath);
            }

            if ($e instanceof QueryException && $data['payment_method'] === 'gcash') {
                return back()->withErrors([
                    'payment_reference' => 'This GCash reference number has already been submitted. Check your Orders page before trying again.',
                ])->withInput($request->except('payment_proof'));
            }

            throw $e;
        }

        $order->load('user');

        try {
            Mail::to($order->user->email)->send(new OrderPlaced($order));
        } catch (\Throwable $e) {
            report($e);
        }

        $operationsTeam = User::query()->whereIn('role', ['admin', 'staff'])->get();
        if ($operationsTeam->isNotEmpty()) {
            Notification::send($operationsTeam, new WorkCreatedNotice(
                type: 'order_created',
                message: 'New order '.$order->order_number.' from '.$order->user->name.' needs review.',
                url: route('admin.orders.show', $order, absolute: false),
                orderId: $order->id,
            ));
        }

        // After transaction: check for low-stock and alert admins
        $lowStock = Product::whereIn('id', $order->orderItems()->pluck('product_id'))
            ->where('stock_qty', '<=', 5)
            ->get();
        if ($lowStock->isNotEmpty()) {
            $adminEmails = User::where('role', 'admin')->pluck('email');
            foreach ($adminEmails as $email) {
                try {
                    Mail::to($email)->send(new LowStockAlert($lowStock));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        $request->session()->forget('checkout_token');

        return redirect()->route('orders.confirmation', $order)
            ->with('clear_cart', true);
    }

    public function orderConfirmation(Order $order): View
    {
        abort_unless((int) $order->user_id === (int) auth()->id(), 403);
        $order->load(['user', 'orderItems']);

        return view('order-confirmation', [
            'order' => $order,
        ]);
    }

    public function orders(): View
    {
        $data = request()->validate([
            'status' => ['nullable', 'string', 'in:pending,confirmed,out_for_delivery,delivered,completed,cancelled'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $q = Order::query()
            ->with(['feedback', 'returnRequests', 'activeRefunds'])
            ->where('user_id', auth()->id())
            ->whereNull('archived_at')
            ->latest();

        if (! empty($data['status'])) {
            $q->where('status', $data['status']);
        }

        if (! empty($data['from'])) {
            $q->whereDate('created_at', '>=', $data['from']);
        }

        if (! empty($data['to'])) {
            $q->whereDate('created_at', '<=', $data['to']);
        }

        return view('orders', [
            'orders' => $q->paginate(8)->withQueryString(),
        ]);
    }

    public function trackOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:50'],
        ]);

        $order = Order::query()
            ->where('user_id', auth()->id())
            ->whereNull('archived_at')
            ->where('order_number', $data['order_number'])
            ->first();

        if (! $order) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'created_at' => optional($order->created_at)->format('M d, Y h:i A'),
            'delivery_proof_url' => $order->delivery_proof_url
                ? route('orders.delivery-proof', $order)
                : null,
            'dispatch_proof_url' => $order->dispatch_proof_url
                ? route('orders.dispatch-proof', $order)
                : null,
            'dispatched_at' => optional($order->dispatched_at)->format('M d, Y h:i A'),
            'driver_name' => $order->driver_name,
            'driver_phone' => $order->driver_phone,
            'delivery_recipient_name' => $order->delivery_recipient_name,
            'delivered_at' => optional($order->delivered_at)->format('M d, Y h:i A'),
            'customer_confirmed_at' => optional($order->customer_confirmed_at)->format('M d, Y h:i A'),
        ]);
    }

    public function confirmOrderReceived(Request $request, Order $order): RedirectResponse
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 403);

        $isPickupOrder = ($order->delivery_method ?? 'delivery') === 'pickup';

        if ($order->status !== 'delivered') {
            return back()->with('error', 'This order is not ready for confirmation yet.');
        }

        if (! $isPickupOrder && ! $order->delivery_proof_url) {
            return back()->with('error', 'Delivery proof is not available yet. Please wait for staff to upload proof.');
        }

        $before = Audit::snapshot($order, ['status', 'customer_confirmed_at']);
        DB::transaction(function () use ($order, $isPickupOrder): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless($lockedOrder->canTransitionTo('completed'), 422, 'This order is not ready for confirmation yet.');
            abort_unless($isPickupOrder || $lockedOrder->delivery_proof_url, 422, 'Delivery proof is required.');
            $lockedOrder->update([
                'status' => 'completed',
                'customer_confirmed_at' => now(),
            ]);
        }, 3);
        $order->refresh();
        Audit::log($request, 'order.receipt.confirmed', $order, $before, Audit::snapshot($order, ['status', 'customer_confirmed_at']));

        $operationsTeam = User::query()->whereIn('role', ['admin', 'staff'])->get();
        if ($operationsTeam->isNotEmpty()) {
            Notification::send($operationsTeam, new WorkCreatedNotice(
                type: 'order_received',
                message: $isPickupOrder
                    ? 'Customer confirmed pickup of order '.$order->order_number.'.'
                    : 'Customer confirmed receipt of order '.$order->order_number.'.',
                url: route('admin.orders.show', $order, absolute: false),
                orderId: $order->id,
            ));
        }

        $message = $isPickupOrder
            ? "Order {$order->order_number} confirmed as picked up. You can now leave feedback."
            : "Order {$order->order_number} confirmed as received.";

        return back()->with('status', $message);
    }

    public function schedule(Request $request, EstimatorQuoteService $estimator): View|RedirectResponse
    {
        // `?reschedule=<id>` re-uses this whole form to move an existing visit,
        // rather than maintaining a second calendar in a modal. The record is
        // resolved here so the view never has to trust the query string.
        $rescheduling = null;
        if ($request->filled('reschedule')) {
            $rescheduling = Appointment::query()
                ->with('serviceType')
                ->whereKey($request->integer('reschedule'))
                ->first();

            abort_unless($rescheduling !== null, 404);
            abort_unless((int) $rescheduling->user_id === (int) auth()->id(), 403);
            abort_unless($rescheduling->isCustomerReschedulable(), 422);
        }

        $estimate = null;
        $bookingService = $rescheduling?->serviceType;

        if (! $rescheduling) {
            $draft = $estimator->current($request);
            if ($draft) {
                $bookingService = ServiceType::query()
                    ->whereKey($draft['service_type_id'])
                    ->where('is_active', true)
                    ->whereNull('archived_at')
                    ->first();

                if (! $bookingService) {
                    $estimator->forget($request);

                    return redirect()->route('estimator')->withErrors([
                        'project_type' => 'That consultation service is no longer available. Please prepare a new estimate.',
                    ]);
                }

                $estimate = $draft['snapshot'];
            }
        }

        // The one-active-booking limit does not apply while moving that very
        // booking; it would otherwise block the customer from their own visit.
        $activeAppointment = $rescheduling ? null : $this->activeAppointmentForUser(auth()->id());

        return view('schedule', [
            'activeAppointment' => $activeAppointment,
            'rescheduling' => $rescheduling,
            'bookingService' => $bookingService,
            'estimate' => $estimate,
        ]);
    }

    public function prepareEstimate(Request $request, EstimatorQuoteService $estimator): JsonResponse|RedirectResponse
    {
        $projectTypes = array_keys((array) config('estimator.project_types', []));
        $tiers = array_keys((array) config('estimator.tiers', []));
        $addons = array_keys((array) config('estimator.addons', []));

        /** @var array{project_type:string,size:int,tier:string,addons?:list<string>,products?:list<array{id:int,qty:int}>} $data */
        $data = $request->validate([
            'project_type' => ['required', 'string', Rule::in($projectTypes)],
            'size' => ['required', 'integer', 'min:1', 'max:100000'],
            'tier' => ['required', 'string', Rule::in($tiers)],
            'addons' => ['sometimes', 'array', 'max:'.count($addons)],
            'addons.*' => ['string', 'distinct', Rule::in($addons)],
            'products' => ['sometimes', 'array', 'max:12'],
            'products.*' => ['array:id,qty'],
            'products.*.id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'products.*.qty' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $request->session()->put(EstimatorQuoteService::SESSION_KEY, $estimator->prepare($data));

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('schedule');
    }

    public function scheduleAvailability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service_type_id' => ['required', 'integer', 'exists:service_types,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'exclude_appointment_id' => ['sometimes', 'integer'],
        ]);

        $dayStart = Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay();
        $dayEnd = (clone $dayStart)->endOfDay();

        // While moving a visit, its own slot is not a conflict - the customer
        // already holds it. Without this the grid greys out the very time the
        // banner above says they are currently booked for. The id is only
        // honoured for a visit the caller owns, so it cannot be used to make
        // someone else's slot look free.
        $excludeId = null;
        if (isset($data['exclude_appointment_id'])) {
            $excludedAppointment = Appointment::query()
                ->whereKey($data['exclude_appointment_id'])
                ->where('user_id', auth()->id())
                ->first();

            $excludeId = $excludedAppointment?->isCustomerReschedulable()
                ? $excludedAppointment->id
                : null;
        }

        $booked = Appointment::query()
            ->where('service_type_id', $data['service_type_id'])
            ->whereBetween('appointment_at', [$dayStart, $dayEnd])
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->when($excludeId, fn ($q) => $q->whereKeyNot($excludeId))
            ->pluck('appointment_at')
            ->map(fn ($dt) => Carbon::parse($dt)->format('H:i'))
            ->values()
            ->all();

        return response()->json([
            'date' => $data['date'],
            'service_type_id' => (int) $data['service_type_id'],
            'booked_times' => $booked,
        ]);
    }

    public function storeSchedule(StoreScheduleRequest $request, EstimatorQuoteService $estimator): RedirectResponse
    {
        $data = $request->validated();

        $draft = $estimator->current($request);
        if (! $draft) {
            return redirect()->route('estimator')->with('estimator_guidance', true);
        }

        if ($this->activeAppointmentForUser(auth()->id())) {
            return back()->withErrors([
                'appointment_at' => 'You already have an active booking. Please cancel or complete it before booking another service.',
            ]);
        }

        // The client may post an old or forged service id. The server-prepared
        // estimator session is the only source of truth for this booking.
        $serviceType = ServiceType::query()
            ->whereKey($draft['service_type_id'])
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->first();

        if (! $serviceType) {
            $estimator->forget($request);

            return redirect()->route('estimator')->withErrors([
                'project_type' => 'That consultation service is no longer available. Please prepare a new estimate.',
            ]);
        }

        $serviceTypeId = $serviceType->id;

        // Prevent double booking (server-side)
        $appointmentAt = Carbon::parse($data['appointment_at'])->seconds(0);
        $alreadyBooked = Appointment::query()
            ->where('service_type_id', $serviceTypeId)
            ->where('appointment_at', $appointmentAt)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->exists();

        if ($alreadyBooked) {
            return back()->withErrors([
                'appointment_at' => 'That time slot is already booked. Please choose another time.',
            ]);
        }

        try {
            $appointment = Appointment::create([
                'user_id' => auth()->id(),
                'service_type_id' => $serviceTypeId,
                'appointment_at' => $appointmentAt,
                'slot_key' => Appointment::slotKey($serviceTypeId, $appointmentAt),
                'appointment_amount' => $serviceType->default_fee ?? 0,
                'estimate_snapshot' => $draft['snapshot'],
                'status' => 'scheduled',
                'notes' => $data['notes'] ?? null,
            ]);
        } catch (QueryException) {
            return back()->withErrors([
                'appointment_at' => 'That time slot was just booked. Please choose another time.',
            ]);
        }

        $appointment->load(['user', 'serviceType']);

        try {
            Mail::to($appointment->user->email)->send(new AppointmentBooked($appointment));
        } catch (\Throwable $e) {
            report($e);
        }

        $operationsTeam = User::query()->whereIn('role', ['admin', 'staff'])->get();
        if ($operationsTeam->isNotEmpty()) {
            Notification::send($operationsTeam, new WorkCreatedNotice(
                type: 'appointment_created',
                message: 'New '.$appointment->serviceType->name.' booking from '.$appointment->user->name.' needs review.',
                url: route('admin.appointments.show', $appointment, absolute: false),
                appointmentId: $appointment->id,
            ));
        }

        $estimator->forget($request);

        return redirect()->route('appointments')
            ->with('status', 'Booking submitted. Track confirmation and updates in Appointments and Notifications.');
    }

    /**
     * Move an existing visit to another published slot.
     *
     * Cancelling and rebooking used to be the only route, which surrendered the
     * slot and walked the customer through the whole form again. Nothing here
     * is read from the form except the new time: the service, the fee and the
     * owner all stay as recorded, so a posted service_type_id cannot move a
     * booking onto a different service.
     */
    public function rescheduleAppointment(RescheduleAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        abort_unless((int) $appointment->user_id === (int) $request->user()->id, 403);

        if ($appointment->status === 'confirmed') {
            return back()->withErrors([
                'appointment_at' => 'This booking has already been confirmed. Message the team if you need to reschedule it.',
            ]);
        }

        abort_unless(
            $appointment->status === 'scheduled' && $appointment->appointment_at->isFuture(),
            422
        );

        // A customer can be sitting on this form as the window closes, so this
        // one is a message rather than a 422: the UI stops offering the move
        // well before here.
        if (! $appointment->isCustomerReschedulable()) {
            return back()->withErrors([
                'appointment_at' => 'This visit is less than '.Appointment::CHANGE_NOTICE_HOURS
                    .' hours away, so it can no longer be moved online. Message the team and we will sort it out.',
            ]);
        }

        $appointmentAt = Carbon::parse($request->validated()['appointment_at'])->seconds(0);

        if ($appointmentAt->equalTo($appointment->appointment_at)) {
            return back()->withErrors([
                'appointment_at' => 'This visit is already booked for that time. Choose a different slot.',
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
                'appointment_at' => 'That time slot is already booked. Please choose another time.',
            ]);
        }

        // A customer can only reach this point while the visit is scheduled,
        // so the moved time remains pending team confirmation.
        abort_unless($appointment->canTransitionTo('scheduled'), 422);

        $previousAt = $appointment->appointment_at->copy();
        $before = Audit::snapshot($appointment, ['status', 'appointment_at', 'slot_key']);

        try {
            $appointment->update([
                'appointment_at' => $appointmentAt,
                'slot_key' => Appointment::slotKey((int) $appointment->service_type_id, $appointmentAt),
                'status' => 'scheduled',
            ]);
        } catch (QueryException) {
            return back()->withErrors([
                'appointment_at' => 'That time slot was just booked. Please choose another time.',
            ]);
        }

        Audit::log(
            $request,
            'appointment.reschedule.customer',
            $appointment,
            $before,
            Audit::snapshot($appointment, ['status', 'appointment_at', 'slot_key'])
        );

        $appointment->load(['user', 'serviceType']);

        $operationsTeam = User::query()->whereIn('role', ['admin', 'staff'])->get();
        if ($operationsTeam->isNotEmpty()) {
            Notification::send($operationsTeam, new WorkCreatedNotice(
                type: 'appointment_rescheduled',
                message: $request->user()->name.' moved their '.($appointment->serviceType->name ?? 'service')
                    .' visit from '.$previousAt->format('M d, Y g:i A').' to '.$appointmentAt->format('M d, Y g:i A').'.',
                url: route('admin.appointments.show', $appointment, absolute: false),
                appointmentId: $appointment->id,
            ));
        }

        return redirect()->route('appointments')->with(
            'status',
            'Visit moved to '.$appointmentAt->format('M d, Y \a\t g:i A').'. The team will confirm the new time.'
        );
    }

    private function activeAppointmentForUser(?int $userId): ?Appointment
    {
        if (! $userId) {
            return null;
        }

        return Appointment::query()
            ->with('serviceType')
            ->where('user_id', $userId)
            ->whereNull('archived_at')
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->where('appointment_at', '>=', now()->startOfMinute())
            ->orderBy('appointment_at')
            ->first();
    }

    public function orderReceipt(Order $order): View
    {
        abort_unless((int) $order->user_id === (int) auth()->id(), 403);
        abort_unless($order->hasFinalReceipt(), 404);
        $order->load(['user', 'orderItems']);

        return view('order-receipt', compact('order'));
    }

    public function paymentProof(Request $request, Order $order): StreamedResponse
    {
        abort_unless(
            (int) $order->user_id === (int) $request->user()->id || $request->user()->isStaffOrAdmin(),
            403
        );
        abort_unless($order->payment_proof_path, 404);
        abort_unless(Storage::disk('local')->exists($order->payment_proof_path), 404);

        return Storage::disk('local')->response($order->payment_proof_path);
    }

    public function deliveryProof(Request $request, Order $order): StreamedResponse
    {
        abort_unless(
            (int) $order->user_id === (int) $request->user()->id || $request->user()->isStaffOrAdmin(),
            403
        );

        return $this->publicOrderProof($order->delivery_proof_url, 'delivery-proofs');
    }

    public function dispatchProof(Request $request, Order $order): StreamedResponse
    {
        abort_unless(
            (int) $order->user_id === (int) $request->user()->id || $request->user()->isStaffOrAdmin(),
            403
        );

        return $this->publicOrderProof($order->dispatch_proof_url, 'dispatch-proofs');
    }

    private function publicOrderProof(?string $proofUrl, string $directory): StreamedResponse
    {
        abort_unless($proofUrl, 404);

        $urlPath = parse_url($proofUrl, PHP_URL_PATH);
        $normalizedPath = is_string($urlPath) ? '/'.ltrim(str_replace('\\', '/', $urlPath), '/') : '';
        abort_unless(Str::contains($normalizedPath, '/storage/'), 404);

        $storagePath = Str::afterLast($normalizedPath, '/storage/');
        abort_unless(
            Str::startsWith($storagePath, $directory.'/') && ! Str::contains($storagePath, '..'),
            404
        );
        abort_unless(Storage::disk('public')->exists($storagePath), 404);

        return Storage::disk('public')->response($storagePath, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function appointmentReceipt(Appointment $appointment): View
    {
        abort_unless((int) $appointment->user_id === (int) auth()->id(), 403);
        abort_unless(($appointment->payment_status ?? 'unpaid') === 'paid', 404);

        $appointment->load(['user', 'serviceType']);

        return view('appointment-receipt', compact('appointment'));
    }

    public function estimator(): View
    {
        return view('estimator', [
            // Shared with the Android app via GET /api/mobile/estimator-rates,
            // so both surfaces quote from the same numbers. See config/estimator.php.
            'rateCard' => config('estimator'),
            'estimateProducts' => Product::query()
                ->where('is_active', true)
                ->whereNull('archived_at')
                ->where('stock_qty', '>', 0)
                ->orderBy('category')
                ->orderBy('name')
                ->take(12)
                ->get(['id', 'name', 'category', 'price', 'stock_qty']),
        ]);
    }

    public function appointments(Request $request): View
    {
        // Validated like the orders list, so a hand-typed status returns a 422
        // rather than an unexplained empty page.
        $request->validate([
            'status' => ['nullable', 'string', 'in:scheduled,confirmed,completed,cancelled'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $user = auth()->user();

        $query = Appointment::query()
            ->with(['serviceType', 'feedback'])
            ->where('user_id', $user->id)
            ->whereNull('archived_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('from')) {
            $query->whereDate('appointment_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('appointment_at', '<=', $request->to);
        }

        $appointments = $query->orderByDesc('appointment_at')->paginate(8)->withQueryString();

        return view('appointments', compact('appointments'));
    }

    public function account(): View|RedirectResponse
    {
        $user = auth()->user();

        if ($user->isStaffOrAdmin()) {
            return redirect()->route('admin.account.edit');
        }

        return view('account', compact('user'));
    }

    public function updateAccount(Request $request): RedirectResponse
    {
        $user = auth()->user();

        if ($request->filled('phone_number')) {
            $request->merge(['phone_number' => PhoneNumber::normalize((string) $request->input('phone_number'))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone_number' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone_number')->ignore($user->id)],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => PasswordRules::optional(),
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->phone_number = $data['phone_number'] ?? $user->phone_number;

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return back()->with('status', 'Profile updated successfully.');
    }

    public function cancelOrder(Request $request, Order $order, InventoryService $inventory): RedirectResponse
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'cancel_reason' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $order->isCustomerCancellable()) {
            $message = $order->status === 'confirmed'
                ? 'This order has already been confirmed and can no longer be cancelled.'
                : 'This order can no longer be cancelled.';

            return back()->with('error', $message);
        }

        $before = Audit::snapshot($order, ['status', 'cancel_reason', 'cancelled_at', 'cancelled_by']);
        DB::transaction(function () use ($order, $request, $data, $inventory) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless($lockedOrder->isCustomerCancellable(), 422, 'This order can no longer be cancelled.');

            $lockedOrder->update([
                'status' => 'cancelled',
                'cancel_reason' => $data['cancel_reason'] ?? 'Cancelled by customer.',
                'cancelled_at' => now(),
                'cancelled_by' => $request->user()->id,
            ]);

            foreach ($lockedOrder->orderItems()->with('product')->get() as $item) {
                if ($item->product) {
                    $inventory->recordReturn($item->product, $item->qty, $lockedOrder->order_number);
                }
            }
        });

        $order->refresh()->load('user');
        Audit::log($request, 'order.cancel', $order, $before, Audit::snapshot($order, ['status', 'cancel_reason', 'cancelled_at', 'cancelled_by']));

        $this->notifyAdminsOfCancellation(
            "Customer {$order->user->name} cancelled order #{$order->order_number}.",
            route('admin.dashboard', ['tab' => 'orders'], false),
            'order_cancelled',
            orderId: $order->id,
        );

        try {
            Mail::to($order->user->email)->send(new OrderStatusUpdated($order));
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            $admin = User::where('role', 'admin')->first();
            if ($admin) {
                Mail::to($admin->email)->send(new OrderStatusUpdated($order));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('status', 'Your order has been cancelled.');
    }

    private function notifyAdminsOfCancellation(
        string $message,
        string $url,
        string $type,
        ?int $orderId = null,
        ?int $appointmentId = null,
    ): void {
        User::query()
            ->whereIn('role', ['admin', 'staff'])
            ->get()
            ->each(function (User $admin) use ($message, $url, $type, $orderId, $appointmentId) {
                $admin->notify(new AdminCancellationNotice($type, $message, $url, $orderId, $appointmentId));
            });
    }

    public function notifications(Request $request)
    {
        $user = auth()->user();
        $notifications = $user->notifications()->latest()->take(20)->get()->map(function ($n) {
            $data = $n->data;
            $data['url'] = $this->normalizeNotificationUrl($data['url'] ?? null);

            return [
                'id' => $n->id,
                'data' => $data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at->diffForHumans(),
            ];
        });

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['notifications' => $notifications]);
        }

        return view('notifications', ['notifications' => $notifications]);
    }

    public function markNotificationRead(Request $request, string $id): JsonResponse
    {
        $notification = auth()->user()->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json(['ok' => true]);
    }

    public function markAllNotificationsRead(): JsonResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        return response()->json(['ok' => true]);
    }

    private function normalizeNotificationUrl(?string $url): string
    {
        $fallback = route('home', absolute: false);

        if (! $url) {
            return $fallback;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return $fallback;
        }

        $path = $parts['path'] ?? null;
        $query = $parts['query'] ?? null;

        // Notification destinations are internal application routes. Returning
        // only the path makes legacy records written with localhost resolve on
        // the current deployment and prevents a stored external redirect.
        if (! is_string($path)
            || ! str_starts_with($path, '/')
            || str_starts_with($path, '//')) {
            return $fallback;
        }

        return $path.(is_string($query) && $query !== '' ? '?'.$query : '');
    }

    public function cancelAppointment(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validate([
            'cancel_reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        // Customers may only cancel their own upcoming appointments
        abort_unless(
            (int) $appointment->user_id === (int) $request->user()->id,
            403
        );
        abort_unless(
            in_array($appointment->status, ['scheduled', 'confirmed']) && $appointment->appointment_at->isFuture(),
            422
        );

        // Same window as moving a visit, and the same reason: the crew for a
        // visit this close has already been scheduled around it.
        if (! $appointment->isCustomerChangeable()) {
            return back()->with(
                'error',
                'This visit is less than '.Appointment::CHANGE_NOTICE_HOURS
                    .' hours away, so it can no longer be cancelled online. Message the team and we will sort it out.'
            );
        }

        $before = Audit::snapshot($appointment, ['status', 'cancel_reason', 'cancelled_at', 'cancelled_by']);
        $appointment->update([
            'status' => 'cancelled',
            'slot_key' => null,
            'cancel_reason' => $data['cancel_reason'],
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
        ]);
        Audit::log(
            $request,
            'appointment.cancel.customer',
            $appointment,
            $before,
            Audit::snapshot($appointment, ['status', 'cancel_reason', 'cancelled_at', 'cancelled_by'])
        );
        $appointment->load(['user', 'serviceType']);

        $service = $appointment->serviceType->name ?? 'Service';
        $this->notifyAdminsOfCancellation(
            "Customer {$appointment->user->name} cancelled {$service} appointment.",
            route('admin.dashboard', ['tab' => 'appointments'], false),
            'appointment_cancelled',
            appointmentId: $appointment->id,
        );

        // Confirm to the customer
        try {
            Mail::to($appointment->user->email)->send(new AppointmentStatusUpdated($appointment));
        } catch (\Throwable $e) {
            report($e);
        }

        // Notify the first admin
        try {
            $admin = User::where('role', 'admin')->first();
            if ($admin) {
                Mail::to($admin->email)->send(new AppointmentStatusUpdated($appointment));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('status', 'Your appointment has been cancelled.');
    }
}
