{{-- Carries the current filters (and search) through a form that only changes the sort order. --}}
@foreach (\App\Support\ShopFilters::LISTS as $key)
    @foreach ($filters[$key] as $value)
        <input type="hidden" name="{{ $key }}[]" value="{{ $value }}">
    @endforeach
@endforeach
@if ($filters['price_min'] !== null)<input type="hidden" name="price_min" value="{{ $filters['price_min'] }}">@endif
@if ($filters['price_max'] !== null)<input type="hidden" name="price_max" value="{{ $filters['price_max'] }}">@endif
@if ($filters['in_stock'])<input type="hidden" name="in_stock" value="1">@endif
@if ($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
