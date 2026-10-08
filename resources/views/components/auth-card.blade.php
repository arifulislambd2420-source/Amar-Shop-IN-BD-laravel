{{--
    Card layout for the customer login / register pages: shop logo, title,
    subtitle, an error summary when the form came back with errors, the form
    (slot) and a footer line (e.g. "no account? register").
--}}
@props(['title', 'subtitle' => null])
<div class="px-4 py-8 sm:py-14">
    <div class="mx-auto w-full max-w-md">
        <div class="rounded-3xl border border-gray-200/80 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(15,23,42,0.18)] sm:p-8">
            <div class="mb-6 text-center">
                <a href="{{ url('/') }}" class="mb-5 inline-flex items-center justify-center">
                    <x-site-logo img-class="h-10 max-w-[180px]" text-class="text-2xl" />
                </a>
                <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
                @if($subtitle)
                    <p class="mt-1.5 text-sm text-gray-500">{{ $subtitle }}</p>
                @endif
            </div>

            {{-- One error is shown under its own field; several get a summary too. --}}
            @if($errors->count() > 1)
                <div class="mb-5 flex items-start gap-3 rounded-2xl border border-error-200 bg-error-50 p-3.5 text-sm text-error-500" role="alert">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="mt-px shrink-0" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
                    <p>কয়েকটি তথ্য ঠিক করতে হবে — নিচে দেখুন।</p>
                </div>
            @endif

            {{ $slot }}
        </div>

        @isset($footer)
            <p class="mt-6 text-center text-sm text-gray-600">{{ $footer }}</p>
        @endisset
    </div>
</div>
