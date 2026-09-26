<?php

namespace App\Http\Controllers;

class PageController extends Controller
{
    public function about()
    {
        return view('pages.about');
    }

    public function privacy()
    {
        return view('pages.privacy');
    }

    public function terms()
    {
        return view('pages.terms');
    }

    public function returns()
    {
        return view('pages.returns');
    }

    public function delivery()
    {
        return view('pages.delivery');
    }
}
