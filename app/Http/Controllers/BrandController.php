<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        // Count only storefront-visible products, so the number matches what
        // /shop?brand=... actually lists (hidden/draft ones used to count).
        $brands = Brand::withCount(['products' => fn ($q) => $q->storefront()])
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->q.'%'))
            ->orderBy('name')
            ->get();

        return view('brands.index', compact('brands'));
    }
}
