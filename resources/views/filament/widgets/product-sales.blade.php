<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('পণ্যের বিক্রি') }} — {{ $label }}</x-slot>
        <x-slot name="description">{{ __('শুধু কনফার্মড বিক্রি ধরা হয় (বাতিল বাদে)।') }}</x-slot>

        <style>
            .prod-cols { display: grid; gap: 1.25rem; grid-template-columns: 1fr; }
            @media (min-width: 768px) { .prod-cols { grid-template-columns: 1fr 1fr; } }
            .prod-h { font-weight: 700; margin-bottom: 0.4rem; }
            .prod-t { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
            .prod-t td { padding: 0.45rem 0.4rem; border-bottom: 1px solid rgba(127, 127, 127, .2); }
            .prod-t td:nth-child(n+2) { text-align: right; white-space: nowrap; }
            .prod-t a { color: inherit; text-decoration: none; }
            .prod-t a:hover { text-decoration: underline; }
            .prod-empty { opacity: .65; font-size: 0.875rem; }
        </style>

        <div class="prod-cols">
            <div>
                <div class="prod-h">🏆 {{ __('সবচেয়ে বেশি বিক্রি হচ্ছে') }}</div>
                @if ($best)
                    <table class="prod-t">
                        @foreach ($best as $row)
                            <tr>
                                <td><a href="{{ $row['url'] }}">{{ $loop->iteration }}. {{ \Illuminate\Support\Str::limit($row['name'], 40) }}</a></td>
                                <td>{{ number_format($row['qty']) }} {{ __('পিস') }}</td>
                                <td>{{ \App\Support\Money::taka($row['revenue']) }}</td>
                            </tr>
                        @endforeach
                    </table>
                @else
                    <div class="prod-empty">{{ __('এই সময়ে কোনো কনফার্মড বিক্রি নেই।') }}</div>
                @endif
            </div>
            <div>
                <div class="prod-h">🐢 {{ __('সবচেয়ে কম বিক্রি হচ্ছে') }}</div>
                @if ($slow)
                    <table class="prod-t">
                        @foreach ($slow as $row)
                            <tr>
                                <td><a href="{{ $row['url'] }}">{{ \Illuminate\Support\Str::limit($row['name'], 40) }}</a></td>
                                <td>{{ $row['qty'] > 0 ? number_format($row['qty']).' '.__('পিস') : __('বিক্রি নেই') }}</td>
                                <td>{{ \App\Support\Money::taka($row['revenue']) }}</td>
                            </tr>
                        @endforeach
                    </table>
                @else
                    <div class="prod-empty">{{ __('কোনো প্রোডাক্ট নেই।') }}</div>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
