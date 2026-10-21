<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('বিক্রি ও লাভের রিপোর্ট') }} — {{ $label }}</x-slot>
        <x-slot name="description">{{ __('প্রকৃত বিক্রি = কনফার্ম হওয়া অর্ডার (এর পরের ধাপসহ)। বাতিল অর্ডার কখনো প্রকৃত বিক্রির হিসাবে আসে না।') }}</x-slot>

        {{-- Inline styles only: the admin CSS bundle is prebuilt. --}}
        <style>
            .rpt { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
            .rpt th, .rpt td { padding: 0.55rem 0.6rem; text-align: right; white-space: nowrap; border-bottom: 1px solid rgba(127, 127, 127, .2); }
            .rpt th:first-child, .rpt td:first-child { text-align: left; white-space: normal; }
            .rpt thead th { font-weight: 600; opacity: .75; }
            .rpt tr.rpt-key td { font-weight: 700; background: rgba(34, 197, 94, .08); }
            .rpt tr.rpt-muted td { opacity: .7; }
            .rpt .neg { color: rgb(220, 38, 38); }
            .rpt-wrap { overflow-x: auto; }
            .rpt-note { margin-top: 0.75rem; font-size: 0.8rem; opacity: .75; }
        </style>

        <div class="rpt-wrap">
            <table class="rpt">
                <thead>
                    <tr>
                        <th>{{ __('ধরন') }}</th>
                        <th>{{ __('অর্ডার') }}</th>
                        <th>{{ __('অর্ডারের মূল্য') }}</th>
                        <th>{{ __('পণ্যের খরচ') }}</th>
                        <th>{{ __('ডেলিভারি খরচ') }}</th>
                        <th>{{ __('আনুমানিক লাভ') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $key => $row)
                        <tr class="{{ $key === 'confirmed' ? 'rpt-key' : '' }} {{ $key === 'cancelled' ? 'rpt-muted' : '' }}">
                            <td>{{ __($row['label']) }}</td>
                            <td>{{ number_format($row['orders']) }}</td>
                            <td>{{ \App\Support\Money::taka($row['total']) }}</td>
                            @if ($key === 'cancelled' || $key === 'placed' || $key === 'awaiting')
                                <td>—</td>
                                <td>—</td>
                                <td>—</td>
                            @else
                                <td>{{ \App\Support\Money::taka($row['cost']) }}</td>
                                <td>{{ \App\Support\Money::taka($row['delivery_cost']) }}</td>
                                <td class="{{ $row['profit'] < 0 ? 'neg' : '' }}">{{ \App\Support\Money::taka($row['profit']) }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="rpt-note">
            {{ __('লাভ = অর্ডারের মূল্য (কাস্টমারের ডেলিভারি চার্জসহ) − পণ্যের খরচ − আপনার নিজের ডেলিভারি খরচ (প্রতি অর্ডারে :cost, সেটিংস → ডেলিভারি ও অর্ডার থেকে বদলান)।', ['cost' => \App\Support\Money::taka($perOrderDeliveryCost)]) }}
            @if ($noCost)
                <br><strong>{{ __('⚠ :countটি অর্ডার-লাইনে পণ্যের কস্ট প্রাইস দেওয়া নেই, তাই লাভ আসলের চেয়ে বেশি দেখাচ্ছে।', ['count' => $noCost]) }}</strong>
            @endif
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
