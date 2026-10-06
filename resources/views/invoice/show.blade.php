@php
    use App\Support\SiteSettingsHelper;
    $logo = SiteSettingsHelper::get('site_logo');
    $siteName = SiteSettingsHelper::get('site_name') ?: \App\Support\SiteSettingsHelper::siteName();
    $paymentLabels = ['cod' => 'ক্যাশ অন ডেলিভারি', 'bkash' => 'বিকাশ', 'advance' => 'অগ্রিম পেমেন্ট'];
    $statusLabels = [
        'pending' => 'পেন্ডিং', 'processing' => 'প্রসেসিং', 'shipped' => 'শিপড',
        'out_for_delivery' => 'ডেলিভারিতে', 'delivered' => 'ডেলিভারড',
        'completed' => 'সম্পন্ন', 'cancelled' => 'বাতিল', 'on_hold' => 'হোল্ডে',
    ];
@endphp
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ইনভয়েস {{ $order->invoice_no ?? $order->id }} — {{ $siteName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    @include('partials.theme-vars')
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Hind Siliguri', Arial, sans-serif; color: #1f2937; background: #f3f4f6; font-size: 14px; }
        .sheet { width: 210mm; min-height: 297mm; margin: 16px auto; background: #fff; padding: 18mm; box-shadow: 0 1px 6px rgba(0,0,0,.12); }
        .toolbar { max-width: 210mm; margin: 16px auto 0; display: flex; gap: 10px; justify-content: flex-end; }
        .btn { background: var(--brand, #ea580c); color: #fff; border: 0; padding: 10px 18px; border-radius: 8px; font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn.secondary { background: #1e293b; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid var(--brand, #ea580c); padding-bottom: 16px; margin-bottom: 20px; }
        .brand img { max-height: 56px; max-width: 200px; object-fit: contain; }
        .brand .name { font-size: 24px; font-weight: 700; color: var(--brand, #ea580c); }
        .brand .meta { color: #6b7280; font-size: 12px; margin-top: 4px; line-height: 1.5; }
        .inv-title { text-align: right; }
        .inv-title h1 { font-size: 26px; letter-spacing: 1px; color: #111827; }
        .inv-title .row { color: #6b7280; font-size: 13px; margin-top: 4px; }
        .inv-title .row b { color: #111827; }
        .parties { display: flex; justify-content: space-between; gap: 24px; margin-bottom: 22px; }
        .parties .box { flex: 1; }
        .parties h3 { font-size: 12px; text-transform: uppercase; color: #9ca3af; letter-spacing: .5px; margin-bottom: 6px; }
        .parties .box div { line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        thead th { background: #f9fafb; text-align: left; padding: 10px 12px; font-size: 12px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
        thead th.num, tbody td.num { text-align: right; }
        tbody td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        .totals { width: 300px; margin-left: auto; }
        .totals .line { display: flex; justify-content: space-between; padding: 6px 0; color: #4b5563; }
        .totals .grand { border-top: 2px solid #e5e7eb; margin-top: 6px; padding-top: 10px; font-size: 18px; font-weight: 700; color: #111827; }
        .totals .grand span:last-child { color: var(--brand, #ea580c); }
        .badges { margin: 18px 0 0; display: flex; gap: 10px; flex-wrap: wrap; }
        .badge { border: 1px solid #e5e7eb; border-radius: 999px; padding: 5px 14px; font-size: 13px; }
        .foot { margin-top: 26px; border-top: 1px solid #e5e7eb; padding-top: 14px; color: #9ca3af; font-size: 12px; text-align: center; line-height: 1.6; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; width: auto; min-height: auto; padding: 0; }
            @page { size: A4; margin: 14mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn" onclick="window.print()">🖨️ প্রিন্ট / PDF ডাউনলোড</button>
        <a class="btn secondary" href="{{ url()->previous() }}">← ফিরে যান</a>
    </div>

    <div class="sheet">
        <div class="head">
            <div class="brand">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $siteName }}">
                @else
                    <div class="name">{{ $siteName }}</div>
                @endif
                <div class="meta">
                    {{ SiteSettingsHelper::address() }}<br>
                    ফোন: {{ SiteSettingsHelper::phone() }}@if(SiteSettingsHelper::email()) · {{ SiteSettingsHelper::email() }}@endif
                </div>
            </div>
            <div class="inv-title">
                <h1>ইনভয়েস</h1>
                <div class="row">নম্বর: <b>{{ $order->invoice_no ?? '#'.$order->id }}</b></div>
                <div class="row">তারিখ: <b>{{ $order->created_at?->format('d M Y, h:i A') }}</b></div>
            </div>
        </div>

        <div class="parties">
            <div class="box">
                <h3>গ্রাহক</h3>
                <div>
                    <strong>{{ $order->customer_name }}</strong><br>
                    ফোন: {{ $order->phone }}@if($order->email)<br>{{ $order->email }}@endif<br>
                    {{ $order->address }}@if($order->thana), {{ $order->thana }}@endif@if($order->district), {{ $order->district }}@endif@if($order->postcode) — {{ $order->postcode }}@endif
                </div>
            </div>
            <div class="box" style="text-align:right;">
                <h3>পেমেন্ট</h3>
                <div>
                    মাধ্যম: <strong>{{ $paymentLabels[$order->payment_method] ?? strtoupper($order->payment_method) }}</strong><br>
                    স্ট্যাটাস: {{ $statusLabels[$order->status] ?? $order->status }}
                    @if($order->tracking_code)<br>ট্র্যাকিং: {{ $order->tracking_code }}@endif
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:44px;">#</th>
                    <th>পণ্য</th>
                    <th class="num" style="width:70px;">পরিমাণ</th>
                    <th class="num" style="width:110px;">দাম</th>
                    <th class="num" style="width:120px;">মোট</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $item->product_name }}</td>
                        <td class="num">{{ $item->quantity }}</td>
                        <td class="num">@taka($item->unit_price)</td>
                        <td class="num">@taka($item->line_total)</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="line"><span>সাবটোটাল</span><span>@taka($order->subtotal)</span></div>
            <div class="line"><span>ডেলিভারি চার্জ</span><span>@taka($order->shipping_fee)</span></div>
            @if ((float) $order->discount > 0)
                <div class="line"><span>ডিসকাউন্ট</span><span>− @taka($order->discount)</span></div>
            @endif
            @if ((float) $order->advance_amount > 0)
                <div class="line"><span>অগ্রিম পরিশোধিত</span><span>− @taka($order->advance_amount)</span></div>
            @endif
            <div class="line grand"><span>সর্বমোট</span><span>@taka($order->total)</span></div>
        </div>

        <div class="foot">
            {{ $siteName }} — আপনার অর্ডারের জন্য ধন্যবাদ।<br>
            এটি একটি কম্পিউটার-জেনারেটেড ইনভয়েস।
        </div>
    </div>
</body>
</html>
