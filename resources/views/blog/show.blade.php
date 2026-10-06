@extends('layouts.app')

@section('title', $blog->title.' — '.\App\Support\SiteSettingsHelper::siteName())

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    @if($blog->cover)
        <div class="aspect-video bg-gray-100 rounded-xl overflow-hidden mb-6">
            <img src="{{ $blog->cover }}" alt="{{ $blog->title }}" class="w-full h-full object-cover">
        </div>
    @endif
    @if($blog->category)
        <span class="text-xs text-brand-500 font-medium">{{ $blog->category }}</span>
    @endif
    <h1 class="text-2xl font-bold mt-1 mb-2">{{ $blog->title }}</h1>
    <p class="text-xs text-gray-400 mb-6">
        {{ $blog->published_at?->format('d M, Y') }}
        @if($blog->read_time) &middot; {{ $blog->read_time }} মিনিট পড়া @endif
    </p>
    <div class="prose max-w-none whitespace-pre-line text-gray-700">{{ $blog->content }}</div>

    @if($recent->isNotEmpty())
        <div class="mt-12 border-t border-gray-200 pt-8">
            <h2 class="font-bold text-lg mb-4">আরও পড়ুন</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                @foreach($recent as $r)
                    <a href="{{ route('blog.show', $r->slug) }}" class="text-sm font-medium hover:text-brand-500 line-clamp-2">{{ $r->title }}</a>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
