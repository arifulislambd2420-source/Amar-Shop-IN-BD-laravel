{{--
    A large (48px) form field with a label, an optional leading icon, a hint,
    and its validation error from the default error bag. type="password" gets
    a show/hide button. All colours come from the theme (brand / error).

    <x-form-input name="phone" label="মোবাইল নম্বর" type="tel" icon="phone" required />
--}}
@props([
    'name',
    'label',
    'type' => 'text',
    'icon' => null,
    'hint' => null,
    'value' => null,
])
@php
    $id = $attributes->get('id', 'f-'.$name);
    $hasError = $errors->has($name);
    $icons = [
        'phone' => '<rect x="6" y="2" width="12" height="20" rx="2.5"/><path d="M11 18h2"/>',
        'lock' => '<rect x="4" y="10" width="16" height="11" rx="2.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    ];
    $isPassword = $type === 'password';
@endphp
<div {{ $attributes->only('class')->merge(['class' => 'flex flex-col gap-1.5']) }}>
    <label for="{{ $id }}" class="text-sm font-medium text-gray-700">
        {{ $label }}
        @if($attributes->has('required'))<span class="text-error-500" aria-hidden="true">*</span>@endif
    </label>
    <div class="relative" @if($isPassword) x-data="{ show: false }" @endif>
        @if($icon && isset($icons[$icon]))
            <span @class(['pointer-events-none absolute inset-y-0 left-0 flex w-12 items-center justify-center', 'text-error-500' => $hasError, 'text-gray-400' => ! $hasError])>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$icon] !!}</svg>
            </span>
        @endif
        <input id="{{ $id }}" name="{{ $name }}"
            @if($isPassword) :type="show ? 'text' : 'password'" type="password" @else type="{{ $type }}" value="{{ old($name, $value) }}" @endif
            @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif($hint) aria-describedby="{{ $id }}-hint" @endif
            {{ $attributes->except(['class', 'id'])->class([
                'block h-12 w-full rounded-xl border bg-white text-base text-gray-900 placeholder:text-gray-400 transition focus:outline-none focus:ring-4',
                'pl-12' => $icon,
                'pl-4' => ! $icon,
                'pr-12' => $isPassword,
                'pr-4' => ! $isPassword,
                'border-error-500 focus:border-error-500 focus:ring-error-50 bg-error-50/40' => $hasError,
                'border-gray-300 focus:border-brand-500 focus:ring-brand-100' => ! $hasError,
            ]) }}>
        @if($isPassword)
            <button type="button" @click="show = ! show" :aria-pressed="show.toString()"
                :aria-label="show ? 'পাসওয়ার্ড লুকান' : 'পাসওয়ার্ড দেখুন'" aria-label="পাসওয়ার্ড দেখুন"
                class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-xl text-gray-400 hover:text-brand-500 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand-500">
                <svg x-show="! show" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg x-show="show" x-cloak width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 5.1A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-3.2 4.2M6.6 6.6C3.8 8.4 2 12 2 12s3.5 7 10 7a10 10 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
            </button>
        @endif
    </div>
    @if($hasError)
        <p id="{{ $id }}-error" class="flex items-start gap-1.5 text-sm text-error-500" role="alert">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="mt-0.5 shrink-0" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
            <span>{{ $errors->first($name) }}</span>
        </p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-xs text-gray-500">{{ $hint }}</p>
    @endif
</div>
