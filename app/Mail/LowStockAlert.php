<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class LowStockAlert extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Collection $products) {}

    public function build(): self
    {
        return $this->subject('⚠️ Low Stock Alert — Ferosa')
            ->view('mail.low-stock-alert');
    }
}
