{{-- Per-site theme colors from Site Setting (only the ones changed from the defaults). --}}
@if($__themeCss = \App\Support\SiteSettingsHelper::themeCss())
    <style>{!! $__themeCss !!}</style>
@endif
