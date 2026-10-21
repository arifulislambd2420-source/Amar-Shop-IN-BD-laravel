<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('পেমেন্ট ও ডেলিভারি রিপোর্ট') }} — {{ $label }}</x-slot>
        <x-slot name="description">{{ __('কনফার্মড বিক্রির হিসাব; বাতিল অর্ডার আলাদা।') }}</x-slot>

        <style>
            .pay-cols { display: grid; gap: 1.25rem; grid-template-columns: 1fr; }
            @media (min-width: 768px) { .pay-cols { grid-template-columns: 1fr 1fr; } }
            .pay-h { font-weight: 700; margin-bottom: 0.4rem; }
            .pay-t { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
            .pay-t td { padding: 0.45rem 0.4rem; border-bottom: 1px solid rgba(127, 127, 127, .2); }
            .pay-t td:nth-child(n+2) { text-align: right; white-space: nowrap; }
            .pay-empty { opacity: .65; font-size: 0.875rem; }
        </style>

        <div class="pay-cols">
            <div>
                <div class="pay-h">💳 {{ __('পেমেন্টের ধরন অনুযায়ী') }}</div>
                @if ($methods)
                    <table class="pay-t">
                        @foreach ($methods as $m)
                            <tr>
                                <td>{{ __($m['label']) }}</td>
                                <td>{{ number_format($m['orders']) }} {{ __('অর্ডার') }}</td>
                                <td>{{ \App\Support\Money::taka($m['total']) }}</td>
                            </tr>
                        @endforeach
                    </table>
                @else
                    <div class="pay-empty">{{ __('এই সময়ে কোনো কনফার্মড বিক্রি নেই।') }}</div>
                @endif
            </div>
            <div>
                <div class="pay-h">🚚 {{ __('ডেলিভারি ও বাতিল') }}</div>
                <table class="pay-t">
                    <tr>
                        <td>{{ __('কাস্টমারের দেওয়া ডেলিভারি চার্জ') }}</td>
                        <td>{{ \App\Support\Money::taka($delivery_charge) }}</td>
                    </tr>
                    <tr>
                        <td>{{ __('আমার ডেলিভারি খরচ (আনুমানিক)') }}</td>
                        <td>{{ \App\Support\Money::taka($delivery_cost) }}</td>
                    </tr>
                    <tr>
                        <td>{{ __('বাতিল অর্ডার') }}</td>
                        <td>{{ number_format($cancelled_orders) }} · {{ \App\Support\Money::taka($cancelled_total) }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
