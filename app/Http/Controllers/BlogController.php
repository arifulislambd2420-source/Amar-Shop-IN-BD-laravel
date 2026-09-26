<?php

namespace App\Http\Controllers;

use App\Models\Blog;

class BlogController extends Controller
{
    public function index()
    {
        $blogs = Blog::whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->paginate(9);

        return view('blog.index', compact('blogs'));
    }

    public function show(string $slug)
    {
        $blog = Blog::whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('slug', $slug)
            ->firstOrFail();

        $recent = Blog::whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('id', '!=', $blog->id)
            ->orderByDesc('published_at')
            ->take(4)
            ->get();

        return view('blog.show', compact('blog', 'recent'));
    }
}
