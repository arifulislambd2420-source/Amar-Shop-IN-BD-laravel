<?php

namespace App\Livewire\Track;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class TrackForm extends Component
{
    public string $orderId = '';

    public string $phone = '';

    public string $error = '';

    public ?Order $result = null;

    public bool $searched = false;

    /**
     * Invariant #1: phone is ALWAYS required and is matched against the
     * order's phone before any data is returned — see OrderService::track().
     */
    public function search(OrderService $orderService): void
    {
        $this->error = '';
        $this->result = null;
        $this->searched = true;

        // Lookups are guessable (phone numbers), so cap them per IP.
        $key = 'track:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 15)) {
            $this->error = 'অনেকবার চেষ্টা করা হয়েছে। '.RateLimiter::availableIn($key).' সেকেন্ড পর আবার চেষ্টা করুন।';

            return;
        }

        RateLimiter::hit($key, 600);

        $outcome = $orderService->track($this->phone, $this->orderId ?: null);

        if (is_string($outcome)) {
            $this->error = $outcome;

            return;
        }

        $this->result = $outcome;
    }

    public function render()
    {
        return view('livewire.track.track-form');
    }
}
