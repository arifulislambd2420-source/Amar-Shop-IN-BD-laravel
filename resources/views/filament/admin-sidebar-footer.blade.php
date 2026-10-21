@php
    $admin = auth('admin')->user();
    $roleLabel = $admin ? __(\App\Models\AdminUser::ROLE_OPTIONS[$admin->role] ?? $admin->role) : '';
    // "সুপার অ্যাডমিন (সব)" → "সুপার অ্যাডমিন": the hint in brackets is for the user form, not for here.
    $roleLabel = trim(preg_replace('/\s*[\(（].*$/u', '', $roleLabel));
@endphp
@if ($admin)
    <div class="admin-sb-foot">
        <div class="admin-sb-user">
            <span class="admin-sb-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($admin->getFilamentName(), 0, 1)) }}</span>
            <span class="admin-sb-who">
                <span class="admin-sb-name">{{ $admin->getFilamentName() }}</span>
                <span class="admin-sb-role">{{ $roleLabel }}</span>
            </span>
        </div>

        <a href="{{ url('/') }}" target="_blank" rel="noopener" class="admin-sb-link">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.5 6H18v4.5M18 6l-7.5 7.5M11 6H7.5A1.5 1.5 0 0 0 6 7.5v9A1.5 1.5 0 0 0 7.5 18h9a1.5 1.5 0 0 0 1.5-1.5V13"/></svg>
            <span>{{ __('ওয়েবসাইট দেখুন') }}</span>
        </a>

        <form method="POST" action="{{ filament()->getLogoutUrl() }}">
            @csrf
            <button type="submit" class="admin-sb-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/></svg>
                <span>{{ __('লগ আউট') }}</span>
            </button>
        </form>
    </div>
@endif
