{{-- Storefront pagination in Bangla: prev/next + "পাতা X / Y" on phones, page numbers on bigger screens. --}}
@if ($paginator->hasPages())
    @php
        $btn = 'inline-flex h-11 min-w-11 items-center justify-center rounded-xl border px-3 text-sm font-semibold transition-colors';
        $off = 'border-gray-200 bg-white text-gray-700 hover:border-brand-500 hover:text-brand-600';
        $dis = 'border-gray-100 bg-gray-50 text-gray-300 cursor-not-allowed';
    @endphp
    <nav role="navigation" aria-label="পাতা নির্বাচন" class="flex items-center justify-between gap-3">
        @if ($paginator->onFirstPage())
            <span class="{{ $btn }} {{ $dis }}" aria-disabled="true">‹ আগের</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $btn }} {{ $off }}">‹ আগের</a>
        @endif

        <span class="text-sm text-gray-600 sm:hidden">পাতা {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

        <div class="hidden flex-wrap justify-center gap-2 sm:flex">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="{{ $btn }} border-transparent text-gray-400" aria-disabled="true">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="{{ $btn }} border-brand-500 bg-brand-500 text-white" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="{{ $btn }} {{ $off }}" aria-label="পাতা {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </div>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $btn }} {{ $off }}">পরের ›</a>
        @else
            <span class="{{ $btn }} {{ $dis }}" aria-disabled="true">পরের ›</span>
        @endif
    </nav>
@endif
