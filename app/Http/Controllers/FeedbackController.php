<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $myFeedbacks = Feedback::query()
            ->with(['product', 'serviceType', 'order'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        // Completed orders that don't have feedback yet
        $deliveredOrders = Order::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereDoesntHave('feedback')
            ->orderByDesc('created_at')
            ->get();

        return view('feedback', compact('myFeedbacks', 'deliveredOrders'));
    }

    public function store(Request $request): RedirectResponse
    {
        // product_id and service_type_id are deliberately NOT accepted from the
        // request. No form submits them, and taking them on trust let anyone
        // post a 1-star review against any catalogue item - including ones they
        // never bought - which skewed the averages on the admin dashboard.
        // They are derived from the reviewed record instead.
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'order_id' => ['nullable', 'exists:orders,id'],
            'appointment_id' => ['nullable', 'exists:appointments,id'],
        ]);

        if (empty($data['order_id']) && empty($data['appointment_id'])) {
            return back()->withErrors(['feedback' => 'Feedback must be connected to an order or appointment.']);
        }

        $serviceTypeId = null;

        // If tied to an order, verify it belongs to the user and is completed
        if (! empty($data['order_id'])) {
            $order = Order::where('id', $data['order_id'])
                ->where('user_id', auth()->id())
                ->where('status', 'completed')
                ->firstOrFail();

            if ($order->feedback()->exists()) {
                return back()->with('status', 'You have already submitted feedback for this order.');
            }
        }

        // If tied to an appointment, verify it belongs to the user and is completed
        if (! empty($data['appointment_id'])) {
            $appointment = Appointment::where('id', $data['appointment_id'])
                ->where('user_id', auth()->id())
                ->where('status', 'completed')
                ->firstOrFail();

            if ($appointment->feedback()->exists()) {
                return back()->with('status', 'You have already submitted feedback for this appointment.');
            }

            // The rating belongs to the service that was actually performed.
            $serviceTypeId = $appointment->service_type_id;
        }

        Feedback::create([
            'user_id' => auth()->id(),
            'order_id' => $data['order_id'] ?? null,
            'appointment_id' => $data['appointment_id'] ?? null,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'product_id' => null,
            'service_type_id' => $serviceTypeId,
        ]);

        return back()->with('status', 'Thank you for your feedback!');
    }
}
