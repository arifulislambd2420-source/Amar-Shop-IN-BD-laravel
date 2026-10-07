@php
    $logo = \App\Support\SiteSettingsHelper::get('site_logo');
    $navLinks = [
        ['href' => url('/'), 'label' => 'Home'],
        ['href' => route('offers'), 'label' => 'Offers'],
        ['href' => route('shop'), 'label' => 'Shop'],
        ['href' => route('brands'), 'label' => 'Brands'],
        ...(\App\Models\Blog::hasPublished() ? [['href' => route('blog.index'), 'label' => 'Blog']] : []),
        ['href' => route('contact'), 'label' => 'Contact'],
    ];
@endphp
<header class="sticky top-0 z-40 bg-white shadow-sm">
    <div class="max-w-7xl mx-auto px-4 flex items-center gap-3 md:gap-4 h-16">
        <button type="button" onclick="document.getElementById('mobile-drawer').classList.remove('hidden')"
            aria-label="মেনু খুলুন" class="md:hidden p-2 -ml-2 text-gray-700 hover:text-brand-500">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>

        <a href="{{ url('/') }}" class="text-xl font-bold tracking-tight shrink-0">
            @if($logo)
                <img src="{{ $logo }}" alt="{{ \App\Support\SiteSettingsHelper::siteName() }}" width="120" height="32" class="h-8 w-auto object-contain">
            @elseif(\App\Support\SiteSettingsHelper::hasCustomSiteName())
                {{ \App\Support\SiteSettingsHelper::siteName() }}
            @else
                আমার<span class="text-brand-500">শপ</span>
            @endif
        </a>

        <form action="{{ route('shop') }}" method="GET" class="hidden md:flex flex-1 max-w-xl">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="পণ্য খুঁজুন..."
                class="w-full rounded-l-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-brand-500">
            <button type="submit" class="bg-brand-500 text-white px-4 rounded-r-lg hover:bg-brand-600" aria-label="সার্চ">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            </button>
        </form>

        <div class="flex items-center gap-3 md:gap-4 ml-auto text-gray-600">
            <a href="tel:{{ \App\Support\SiteSettingsHelper::phoneRaw() }}" class="hidden lg:flex items-center gap-2 text-sm hover:text-brand-500">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                <span>{{ \App\Support\SiteSettingsHelper::phone() }}</span>
            </a>
            <a href="{{ route('track') }}" aria-label="অর্ডার ট্র্যাকিং" class="hover:text-brand-500">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="1.5"/><circle cx="18.5" cy="18.5" r="1.5"/></svg>
            </a>
            <span aria-hidden="true" class="hidden sm:inline-flex hover:text-brand-500 cursor-default" title="পছন্দের তালিকা (শীঘ্রই আসছে)">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.8 1-1a5.5 5.5 0 0 0 0-7.6z"/></svg>
            </span>
            <a href="{{ route('cart.index') }}" aria-label="কার্ট" class="relative hover:text-brand-500">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                @livewire('cart.cart-badge')
            </a>
        </div>
    </div>

    <div class="md:hidden px-4 pb-3">
        <form action="{{ route('shop') }}" method="GET" class="flex">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="পণ্য খুঁজুন..."
                class="w-full rounded-l-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-brand-500">
            <button type="submit" class="bg-brand-500 text-white px-4 rounded-r-lg" aria-label="সার্চ">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            </button>
        </form>
    </div>

    <nav class="hidden md:block border-t border-gray-100 bg-white">
        <div class="max-w-7xl mx-auto px-4 flex items-center h-11 gap-6 text-sm font-medium">
            <div class="relative group">
                <button type="button" class="flex items-center gap-1.5 bg-brand-500 text-white px-3 h-11 font-semibold">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
                    All Categories
                </button>
                <div class="absolute left-0 top-full w-64 bg-white border border-gray-200 rounded-b-lg shadow-lg z-50 hidden group-hover:block">
                    <ul class="py-2">
                        @foreach(\App\Support\StorefrontCache::categories() as $c)
                            <li>
                                <a href="{{ route('shop', ['category' => $c->slug]) }}" class="block px-4 py-2 text-sm hover:bg-surface hover:text-brand-500">{{ $c->name }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @foreach($navLinks as $l)
                <a href="{{ $l['href'] }}" class="hover:text-brand-500">{{ $l['label'] }}</a>
            @endforeach
            <div class="ml-auto flex items-center gap-4">
                @auth
                    <a href="{{ route('customer.account') }}" class="text-gray-600 hover:text-brand-500">হ্যালো, {{ auth()->user()->name }}</a>
                    <form action="{{ route('customer.logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="hover:text-brand-500">লগআউট</button>
                    </form>
                @else
                    <a href="{{ route('customer.login') }}" class="hover:text-brand-500">লগইন</a>
                    <a href="{{ route('customer.register') }}" class="hover:text-brand-500">রেজিস্টার</a>
                @endauth
            </div>
        </div>
    </nav>
</header>

{{-- Mobile drawer --}}
<div id="mobile-drawer" class="hidden fixed inset-0 z-50 md:hidden">
    <div class="fixed inset-0 bg-black/60" onclick="document.getElementById('mobile-drawer').classList.add('hidden')"></div>
    <div class="relative w-4/5 max-w-xs bg-white h-full flex flex-col shadow-2xl z-10 overflow-y-auto">
        <div class="p-4 bg-secondary text-white flex items-center justify-between">
            <a href="{{ url('/') }}" class="text-xl font-bold">@if(\App\Support\SiteSettingsHelper::hasCustomSiteName()){{ \App\Support\SiteSettingsHelper::siteName() }}@else আমার<span class="text-brand-500">শপ</span>@endif</a>
            <button type="button" onclick="document.getElementById('mobile-drawer').classList.add('hidden')" aria-label="বন্ধ করুন" class="text-white/80">
                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="bg-surface border-b border-gray-200 p-3 flex gap-2 text-xs font-semibold">
            @auth
                <a href="{{ route('customer.account') }}" class="flex-1 text-center py-1.5 text-gray-700 font-semibold">আমার অ্যাকাউন্ট ({{ auth()->user()->name }})</a>
            @else
                <a href="{{ route('customer.login') }}" class="flex-1 bg-white border border-gray-300 py-1.5 px-3 rounded-lg text-center text-gray-700">লগইন</a>
                <a href="{{ route('customer.register') }}" class="flex-1 bg-secondary text-white py-1.5 px-3 rounded-lg text-center">রেজিস্টার</a>
            @endauth
        </div>
        <div class="flex-1 p-4 space-y-1 text-sm">
            @foreach($navLinks as $l)
                <a href="{{ $l['href'] }}" class="block py-2 px-2.5 rounded-lg font-medium text-gray-700 hover:bg-brand-50 hover:text-brand-500">{{ $l['label'] }}</a>
            @endforeach
            <a href="{{ route('track') }}" class="block py-2 px-2.5 rounded-lg font-medium text-gray-700 hover:bg-brand-50 hover:text-brand-500">📦 অর্ডার ট্র্যাক করুন</a>
        </div>
        <div class="p-4 border-t border-gray-200 bg-surface text-xs space-y-2">
            <a href="tel:{{ \App\Support\SiteSettingsHelper::phoneRaw() }}" class="flex items-center gap-2 text-gray-700">হেল্পলাইন: {{ \App\Support\SiteSettingsHelper::phone() }}</a>
            <a href="https://wa.me/{{ \App\Support\SiteSettingsHelper::whatsapp() }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 text-green-600 font-medium">💬 হোয়াটসঅ্যাপে চ্যাট করুন</a>
        </div>
    </div>
</div>
