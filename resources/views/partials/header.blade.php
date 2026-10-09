@php
    $navLinks = [
        ['href' => url('/'), 'label' => 'হোম', 'is' => ['/']],
        ['href' => route('offers'), 'label' => 'অফার', 'is' => ['offers']],
        ['href' => route('shop'), 'label' => 'শপ', 'is' => ['shop', 'shop/*']],
        ['href' => route('brands'), 'label' => 'ব্র্যান্ড', 'is' => ['brands', 'brands/*']],
        ...(\App\Models\Blog::hasPublished() ? [['href' => route('blog.index'), 'label' => 'ব্লগ', 'is' => ['blog', 'blog/*']]] : []),
        ['href' => route('contact'), 'label' => 'যোগাযোগ', 'is' => ['contact']],
    ];
    // Round 40px icon buttons in the brand colour (tracking, cart).
    $iconBtn = 'relative inline-flex h-10 w-10 items-center justify-center rounded-full bg-brand-50 text-brand-500 transition-colors hover:bg-brand-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500';
    $searchInput = 'w-full min-w-0 rounded-l-xl border border-gray-300 bg-white px-4 text-base md:text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100';
    $searchBtn = 'inline-flex shrink-0 items-center justify-center rounded-r-xl bg-brand-500 px-4 text-white hover:bg-brand-600';
@endphp
<header class="sticky top-0 z-40 bg-white shadow-sm">
    <div class="max-w-7xl mx-auto px-3 sm:px-4 flex items-center gap-2 md:gap-6 h-14 md:h-[4.5rem]">
        <button type="button" data-drawer-open aria-label="মেনু খুলুন" aria-controls="mobile-drawer" aria-expanded="false"
            class="md:hidden -ml-1 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-gray-700 hover:bg-gray-100">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        <a href="{{ url('/') }}" class="flex min-h-11 min-w-0 items-center" aria-label="{{ \App\Support\SiteSettingsHelper::siteName() }} — হোম">
            <x-site-logo img-class="h-9 md:h-11 max-w-[140px] md:max-w-[200px]" text-class="text-xl md:text-2xl" />
        </a>

        <form action="{{ route('shop') }}" method="GET" role="search" class="hidden md:flex flex-1 max-w-xl h-11">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="পণ্য খুঁজুন..." aria-label="পণ্য খুঁজুন" class="{{ $searchInput }}">
            <button type="submit" class="{{ $searchBtn }}" aria-label="সার্চ">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            </button>
        </form>

        <div class="ml-auto flex shrink-0 items-center gap-2 md:gap-3">
            @if($phone = \App\Support\SiteSettingsHelper::phone())
                <a href="tel:{{ \App\Support\SiteSettingsHelper::phoneRaw() }}" class="hidden lg:flex items-center gap-2 text-sm font-medium text-gray-700 hover:text-brand-500 mr-1">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-600">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    </span>
                    <span class="leading-tight"><span class="block text-xs font-normal text-gray-500">হেল্পলাইন</span>{{ $phone }}</span>
                </a>
            @endif
            <a href="{{ route('track') }}" aria-label="অর্ডার ট্র্যাকিং" title="অর্ডার ট্র্যাকিং" class="{{ $iconBtn }}">
                <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h11v10H3z"/><path d="M14 9h4l3 3v4h-7"/><circle cx="7" cy="18" r="1.75"/><circle cx="17" cy="18" r="1.75"/></svg>
            </a>
            <a href="{{ route('cart.index') }}" aria-label="কার্ট" title="কার্ট" class="{{ $iconBtn }}">
                <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1.25"/><circle cx="18" cy="20" r="1.25"/><path d="M2 3h3l2.6 12.1a1.5 1.5 0 0 0 1.5 1.2h8.7a1.5 1.5 0 0 0 1.5-1.2L21 7H6.2"/></svg>
                @livewire('cart.cart-badge', ['header' => true])
            </a>
            <div class="hidden md:block">
                @auth
                    <a href="{{ route('customer.account') }}" class="{{ $iconBtn }}" aria-label="আমার অ্যাকাউন্ট" title="আমার অ্যাকাউন্ট">
                        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                    </a>
                @else
                    <a href="{{ route('customer.login') }}" class="inline-flex h-10 items-center gap-2 rounded-full border border-gray-300 px-4 text-sm font-semibold text-gray-700 hover:border-brand-500 hover:text-brand-500">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                        লগইন
                    </a>
                @endauth
            </div>
        </div>
    </div>

    <div class="md:hidden px-3 pb-3">
        <form action="{{ route('shop') }}" method="GET" role="search" class="flex h-11">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="পণ্য খুঁজুন..." aria-label="পণ্য খুঁজুন" class="{{ $searchInput }}">
            <button type="submit" class="{{ $searchBtn }}" aria-label="সার্চ">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            </button>
        </form>
    </div>

    <nav class="hidden md:block border-t border-gray-100 bg-white" aria-label="প্রধান মেনু">
        <div class="max-w-7xl mx-auto px-4 flex items-center h-12 gap-1 text-sm font-medium">
            <div class="relative group mr-3">
                <button type="button" aria-haspopup="true" class="flex items-center gap-2 rounded-lg bg-brand-500 text-white px-4 h-9 font-semibold">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    সব ক্যাটাগরি
                </button>
                <div class="absolute left-0 top-full pt-2 w-64 z-50 hidden group-hover:block group-focus-within:block">
                    <ul class="py-2 bg-white border border-gray-200 rounded-xl shadow-lg max-h-[70vh] overflow-y-auto">
                        @forelse(\App\Support\StorefrontCache::categories() as $c)
                            <li>
                                <a href="{{ route('shop', ['category' => $c->slug]) }}" class="block px-4 py-2.5 text-sm hover:bg-brand-50 hover:text-brand-600">{{ $c->name }}</a>
                            </li>
                        @empty
                            <li class="px-4 py-2.5 text-sm text-gray-500">এখনও কোনো ক্যাটাগরি নেই</li>
                        @endforelse
                    </ul>
                </div>
            </div>
            @foreach($navLinks as $l)
                @php $on = request()->is(...$l['is']); @endphp
                <a href="{{ $l['href'] }}" @if($on) aria-current="page" @endif
                    @class(['rounded-lg px-3 py-2 transition-colors', 'text-brand-600 bg-brand-50' => $on, 'text-gray-700 hover:text-brand-600 hover:bg-gray-50' => ! $on])>{{ $l['label'] }}</a>
            @endforeach
            <div class="ml-auto flex items-center gap-1">
                @auth
                    <a href="{{ route('customer.account') }}" class="rounded-lg px-3 py-2 text-gray-600 hover:text-brand-600">হ্যালো, {{ \Illuminate\Support\Str::limit(auth()->user()->name, 20) }}</a>
                    <form action="{{ route('customer.logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="rounded-lg px-3 py-2 text-gray-600 hover:text-brand-600">লগআউট</button>
                    </form>
                @else
                    <a href="{{ route('customer.register') }}" class="rounded-lg px-3 py-2 text-gray-600 hover:text-brand-600">নতুন অ্যাকাউন্ট খুলুন</a>
                @endauth
            </div>
        </div>
    </nav>
</header>

{{-- Mobile drawer --}}
<div id="mobile-drawer" class="hidden fixed inset-0 z-50 md:hidden" role="dialog" aria-modal="true" aria-label="মেনু">
    <div class="fixed inset-0 bg-black/50" data-drawer-close></div>
    <div class="relative flex h-full w-[85%] max-w-xs flex-col overflow-y-auto bg-white shadow-2xl pb-[env(safe-area-inset-bottom)]">
        <div class="flex h-14 items-center justify-between gap-3 border-b border-gray-100 px-4">
            <a href="{{ url('/') }}" class="flex min-w-0 items-center">
                <x-site-logo img-class="h-8 max-w-[160px]" text-class="text-xl" />
            </a>
            <button type="button" data-drawer-close aria-label="মেনু বন্ধ করুন" class="-mr-2 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100">
                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" stroke-linecap="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="p-4">
            @auth
                <a href="{{ route('customer.account') }}" class="flex items-center gap-3 rounded-xl bg-brand-50 p-3">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-500 text-white font-bold">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                    <span class="min-w-0"><span class="block truncate font-semibold text-gray-800">{{ auth()->user()->name }}</span><span class="text-xs text-brand-600">আমার অ্যাকাউন্ট দেখুন</span></span>
                </a>
                <form action="{{ route('customer.logout') }}" method="POST" class="mt-2">
                    @csrf
                    <button type="submit" class="flex min-h-11 w-full items-center rounded-xl px-3 text-sm font-medium text-gray-600 hover:bg-gray-50">লগআউট</button>
                </form>
            @else
                <div class="grid grid-cols-2 gap-2">
                    <a href="{{ route('customer.login') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-gray-300 text-sm font-semibold text-gray-700">লগইন</a>
                    <a href="{{ route('customer.register') }}" class="inline-flex h-11 items-center justify-center rounded-xl bg-brand-500 text-sm font-semibold text-white">রেজিস্টার</a>
                </div>
            @endauth
        </div>

        <nav class="flex-1 px-3 text-[15px]" aria-label="মোবাইল মেনু লিংক">
            @foreach($navLinks as $l)
                @php $on = request()->is(...$l['is']); @endphp
                <a href="{{ $l['href'] }}" @if($on) aria-current="page" @endif
                    @class(['flex items-center min-h-11 px-3 rounded-xl font-medium', 'bg-brand-50 text-brand-600' => $on, 'text-gray-700 hover:bg-gray-50' => ! $on])>{{ $l['label'] }}</a>
            @endforeach
            <a href="{{ route('track') }}" class="flex items-center min-h-11 px-3 rounded-xl font-medium text-gray-700 hover:bg-gray-50">অর্ডার ট্র্যাক করুন</a>

            @if(($cats = \App\Support\StorefrontCache::categories())->isNotEmpty())
                <p class="mt-4 mb-1 px-3 text-xs font-semibold uppercase tracking-wide text-gray-400">ক্যাটাগরি</p>
                @foreach($cats as $c)
                    <a href="{{ route('shop', ['category' => $c->slug]) }}" class="flex items-center min-h-11 px-3 rounded-xl text-gray-700 hover:bg-gray-50">{{ $c->name }}</a>
                @endforeach
            @endif
        </nav>

        <div class="mt-4 space-y-1 border-t border-gray-100 p-3 text-sm">
            @if($phone = \App\Support\SiteSettingsHelper::phone())
                <a href="tel:{{ \App\Support\SiteSettingsHelper::phoneRaw() }}" class="flex items-center gap-3 min-h-11 px-3 rounded-xl text-gray-700 hover:bg-gray-50">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-brand-500"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    হেল্পলাইন: {{ $phone }}
                </a>
            @endif
            @if($wa = \App\Support\SiteSettingsHelper::whatsapp())
                <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 min-h-11 px-3 rounded-xl font-medium text-success-700 hover:bg-gray-50">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12.01 2C6.49 2 2 6.48 2 11.99c0 1.94.55 3.75 1.5 5.29L2 22l4.86-1.44c1.48.81 3.18 1.28 4.99 1.28h.01c5.52 0 10.01-4.48 10.01-9.99C21.87 6.48 17.53 2 12.01 2z"/></svg>
                    হোয়াটসঅ্যাপে চ্যাট করুন
                </a>
            @endif
        </div>
    </div>
</div>
<script>
    (() => {
        const drawer = document.getElementById('mobile-drawer');
        const opener = document.querySelector('[data-drawer-open]');
        if (! drawer || ! opener) return;
        const toggle = (open) => {
            drawer.classList.toggle('hidden', ! open);
            document.documentElement.classList.toggle('overflow-hidden', open);
            opener.setAttribute('aria-expanded', String(open));
            if (open) drawer.querySelector('[data-drawer-close]:not(.fixed)')?.focus(); else opener.focus();
        };
        opener.addEventListener('click', () => toggle(true));
        drawer.querySelectorAll('[data-drawer-close]').forEach((el) => el.addEventListener('click', () => toggle(false)));
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && ! drawer.classList.contains('hidden')) toggle(false); });
    })();
</script>
