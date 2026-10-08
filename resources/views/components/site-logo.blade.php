{{--
    The shop's logo: the image uploaded in Site Setting → "Site logo", or the
    shop name as text when there is none. Used by the header, the mobile
    drawer and the login/register cards so every place stays in step.

    Size it with `img-class` (height / max-width of the image) and `text-class`
    (font size of the text fallback).
--}}
@props([
    'imgClass' => 'h-9 max-w-[150px]',
    'textClass' => 'text-xl',
])
@php
    $logo = \App\Support\SiteSettingsHelper::get('site_logo');
    $name = \App\Support\SiteSettingsHelper::siteName();
@endphp
@if($logo)
    <img src="{{ $logo }}" alt="{{ $name }}" class="{{ $imgClass }} w-auto object-contain" decoding="async">
@elseif(\App\Support\SiteSettingsHelper::hasCustomSiteName())
    <span class="{{ $textClass }} block truncate font-bold tracking-tight leading-tight">{{ $name }}</span>
@else
    <span class="{{ $textClass }} font-bold tracking-tight leading-none whitespace-nowrap">আমার<span class="text-brand-500">শপ</span></span>
@endif
