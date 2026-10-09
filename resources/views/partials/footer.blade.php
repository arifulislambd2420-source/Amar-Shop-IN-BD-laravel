<footer class="bg-secondary text-white/80 mt-16 pb-mobile-nav md:pb-0">
    <div class="max-w-7xl mx-auto px-4 py-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 text-sm">
        <div>
            @if($footerLogo = \App\Support\SiteSettingsHelper::get('footer_logo'))
                <img src="{{ $footerLogo }}" alt="{{ \App\Support\SiteSettingsHelper::siteName() }}" loading="lazy" class="mb-3 h-10 w-auto max-w-[200px] object-contain">
            @else
                <div class="text-white text-lg font-bold mb-2">@if(\App\Support\SiteSettingsHelper::hasCustomSiteName()){{ \App\Support\SiteSettingsHelper::siteName() }}@else আমার<span class="text-brand-500">শপ</span>@endif</div>
            @endif
            <p>{{ \App\Support\SiteSettingsHelper::get('footer_description') ?: 'খাঁটি ও প্রাকৃতিক পণ্যের অনলাইন দোকান। মধু, সরিষার তেল, ঘি, খেজুর — সরাসরি আপনার দোরগোড়ায়।' }}</p>
            @php $socials = \App\Support\SiteSettingsHelper::socialLinks(); @endphp
            @if($socials)
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 mt-4">
                    @foreach($socials as $label => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $label }}" class="hover:text-brand-500">{{ $label }}</a>
                    @endforeach
                </div>
            @endif
        </div>
        <div>
            <div class="text-white font-semibold mb-2">গুরুত্বপূর্ণ লিংক</div>
            <ul class="space-y-1">
                <li><a href="{{ route('about') }}" class="hover:text-brand-500">আমাদের সম্পর্কে</a></li>
                <li><a href="{{ route('shop') }}" class="hover:text-brand-500">সকল পণ্য (Shop)</a></li>
                <li><a href="{{ route('offers') }}" class="hover:text-brand-500">অফার সমূহ</a></li>
                @if(\App\Models\Blog::hasPublished())
                <li><a href="{{ route('blog.index') }}" class="hover:text-brand-500">ব্লগ ও টিপস</a></li>
                @endif
                <li><a href="{{ route('track') }}" class="hover:text-brand-500">অর্ডার ট্র্যাকিং</a></li>
            </ul>
        </div>
        <div>
            <div class="text-white font-semibold mb-2">পলিসি ও নিয়মাবলী</div>
            <ul class="space-y-1">
                <li><a href="{{ route('delivery') }}" class="hover:text-brand-500">ডেলিভারি পলিসি</a></li>
                <li><a href="{{ route('returns') }}" class="hover:text-brand-500">রিটার্ন ও রিফান্ড পলিসি</a></li>
                <li><a href="{{ route('privacy') }}" class="hover:text-brand-500">প্রাইভেসি পলিসি</a></li>
                <li><a href="{{ route('terms') }}" class="hover:text-brand-500">ব্যবহারের শর্তাবলী</a></li>
                <li><a href="{{ route('contact') }}" class="hover:text-brand-500">যোগাযোগ ও হেল্পলাইন</a></li>
            </ul>
        </div>
        <div>
            <div class="text-white font-semibold mb-2">যোগাযোগ</div>
            <p>ফোন: <a href="tel:{{ \App\Support\SiteSettingsHelper::phoneRaw() }}" class="hover:text-brand-500">{{ \App\Support\SiteSettingsHelper::phone() }}</a></p>
            <p class="mt-1">ইমেইল: <a href="mailto:{{ \App\Support\SiteSettingsHelper::email() }}" class="hover:text-brand-500">{{ \App\Support\SiteSettingsHelper::email() }}</a></p>
            <p class="mt-1 text-xs text-white/60">ঠিকানা: {{ \App\Support\SiteSettingsHelper::address() }}</p>
            @if($hours = \App\Support\SiteSettingsHelper::contactHours())
                <p class="mt-1 text-xs text-white/60">সময়: {{ $hours }}</p>
            @endif
            <div class="text-white font-semibold mt-4 mb-2">পেমেন্ট মেথড</div>
            <div class="flex flex-wrap gap-2 text-xs">
                @if(app(\App\Services\Payment\BkashService::class)->configured())
                    <span class="bg-white/10 px-2 py-1 rounded">bKash</span>
                @endif
                <span class="bg-white/10 px-2 py-1 rounded">Cash on Delivery</span>
            </div>
        </div>
    </div>
    <div class="border-t border-white/10 py-4 text-center text-xs text-white/50">
        {{ \App\Support\SiteSettingsHelper::copyright() }}
    </div>
</footer>
