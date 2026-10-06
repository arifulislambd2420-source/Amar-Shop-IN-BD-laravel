@extends('layouts.app')

@section('title', 'ব্লগ — '.\App\Support\SiteSettingsHelper::siteName())

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">ব্লগ ও টিপস</h1>

    @if($blogs->isEmpty())
        <p class="text-gray-400">এখনো কোনো ব্লগ পোস্ট নেই।</p>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
            @foreach($blogs as $b)
                <a href="{{ route('blog.show', $b->slug) }}" class="bg-white border border-gray-200 rounded-xl overflow-hidden hover:shadow-md transition-shadow">
                    <div class="aspect-video bg-gray-100">
                        @if($b->cover)
                            <img src="{{ $b->cover }}" alt="{{ $b->title }}" class="w-full h-full object-cover">
                        @endif
                    </div>
                    <div class="p-4">
                        @if($b->category)
                            <span class="text-xs text-brand-500 font-medium">{{ $b->category }}</span>
                        @endif
                        <h3 class="font-semibold mt-1 line-clamp-2">{{ $b->title }}</h3>
                        <p class="text-xs text-gray-400 mt-2">
                            {{ $b->published_at?->format('d M, Y') }}
                            @if($b->read_time) &middot; {{ $b->read_time }} মিনিট পড়া @endif
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-8">{{ $blogs->links() }}</div>
    @endif
</div>
@endsection
