<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use PHPUnit\Framework\TestCase;

class AuditLogDescriptionTest extends TestCase
{
    public function test_it_describes_a_created_product_in_plain_language(): void
    {
        $log = new AuditLog([
            'action' => 'product.create',
            'auditable_type' => Product::class,
            'auditable_id' => 7,
            'after' => [
                'name' => 'Pink Muhly Grass',
                'price' => '350.00',
                'stock_qty' => 12,
            ],
        ]);

        $this->assertSame('Created product “Pink Muhly Grass”.', $log->description);
        $this->assertSame('Product Create', $log->action_label);
        $this->assertSame('product “Pink Muhly Grass”', $log->target_label);
    }

    public function test_it_names_the_fields_changed_on_a_product(): void
    {
        $log = new AuditLog([
            'action' => 'product.update',
            'auditable_type' => Product::class,
            'auditable_id' => 7,
            'before' => ['name' => 'Fern', 'price' => '100.00', 'stock_qty' => 3],
            'after' => ['name' => 'Fern', 'price' => '125.00', 'stock_qty' => 5],
        ]);

        $this->assertSame(
            'Updated product “Fern” (changed price and stock quantity).',
            $log->description
        );
    }

    public function test_it_explains_an_order_status_change(): void
    {
        $log = new AuditLog([
            'action' => 'order.status.update',
            'auditable_type' => Order::class,
            'auditable_id' => 42,
            'before' => ['status' => 'pending', 'payment_status' => 'unpaid'],
            'after' => ['status' => 'confirmed', 'payment_status' => 'paid'],
        ]);

        $this->assertSame(
            'Changed order #42 status from Pending to Confirmed and payment status from Unpaid to Paid.',
            $log->description
        );
    }
}
