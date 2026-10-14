<?php

/*
| The six demo landing pages — one per page template — each with its own demo
| product that has 3-4 colours/models (every colour: own picture, price, stock).
| Pictures are demo-landing/<slug>-<colour key>.png (made by generate-images.php).
|
| template: green | purple | cream | template-1 | template-2 | template-3
| price:    the product's regular price; each colour's "price" is what it sells for.
*/

return [
    [
        'slug' => 'demo-lp-green',
        'template' => 'green',
        'shape' => 'watch',
        'bg' => '#d9f0e1',
        'title' => 'ডেমো ল্যান্ডিং — সবুজ (ঘড়ি)',
        'product' => 'ডেমো প্রিমিয়াম ঘড়ি',
        'price' => 2990,
        'colors' => [
            ['key' => 'gold', 'name' => 'গোল্ড', 'hex' => '#c9a227', 'price' => 1990, 'stock' => 12],
            ['key' => 'silver', 'name' => 'সিলভার', 'hex' => '#b8c0cc', 'price' => 1890, 'stock' => 9],
            ['key' => 'black', 'name' => 'ব্ল্যাক', 'hex' => '#1f2937', 'price' => 1950, 'stock' => 15],
            ['key' => 'blue', 'name' => 'নেভি ব্লু', 'hex' => '#1e40af', 'price' => 2090, 'stock' => 6],
        ],
    ],
    [
        'slug' => 'demo-lp-purple',
        'template' => 'purple',
        'shape' => 'hijab',
        'bg' => '#f3dcf0',
        'title' => 'ডেমো ল্যান্ডিং — বেগুনি (হিজাব)',
        'product' => 'ডেমো প্রিমিয়াম হিজাব সেট',
        'price' => 1450,
        'colors' => [
            ['key' => 'black', 'name' => 'কালো', 'hex' => '#1c1c24', 'price' => 990, 'stock' => 20],
            ['key' => 'maroon', 'name' => 'মেরুন', 'hex' => '#7f1d3a', 'price' => 1050, 'stock' => 14],
            ['key' => 'navy', 'name' => 'নেভি', 'hex' => '#1e3a5f', 'price' => 1050, 'stock' => 10],
            ['key' => 'olive', 'name' => 'অলিভ', 'hex' => '#5b6b2f', 'price' => 1090, 'stock' => 8],
        ],
    ],
    [
        'slug' => 'demo-lp-cream',
        'template' => 'cream',
        'shape' => 'kurta',
        'bg' => '#f5e9d3',
        'title' => 'ডেমো ল্যান্ডিং — ক্রিম (পাঞ্জাবি)',
        'product' => 'ডেমো কটন পাঞ্জাবি',
        'price' => 2200,
        'colors' => [
            ['key' => 'white', 'name' => 'অফ হোয়াইট', 'hex' => '#ece6d8', 'price' => 1590, 'stock' => 18],
            ['key' => 'sky', 'name' => 'আকাশি', 'hex' => '#7fb4d6', 'price' => 1650, 'stock' => 11],
            ['key' => 'brown', 'name' => 'কফি', 'hex' => '#7a5230', 'price' => 1690, 'stock' => 7],
        ],
    ],
    [
        'slug' => 'demo-lp-t1',
        'template' => 'template-1',
        'shape' => 'bag',
        'bg' => '#fde7c8',
        'title' => 'ডেমো ল্যান্ডিং — টেমপ্লেট ১ (ব্যাগ)',
        'product' => 'ডেমো টোট ব্যাগ',
        'price' => 1200,
        'colors' => [
            ['key' => 'tan', 'name' => 'ট্যান', 'hex' => '#b9733a', 'price' => 850, 'stock' => 16],
            ['key' => 'black', 'name' => 'কালো', 'hex' => '#222222', 'price' => 850, 'stock' => 13],
            ['key' => 'red', 'name' => 'লাল', 'hex' => '#b3261e', 'price' => 890, 'stock' => 5],
        ],
    ],
    [
        'slug' => 'demo-lp-t2',
        'template' => 'template-2',
        'shape' => 'shoe',
        'bg' => '#d7dbe6',
        'title' => 'ডেমো ল্যান্ডিং — টেমপ্লেট ২ (জুতা)',
        'product' => 'ডেমো স্নিকার',
        'price' => 2800,
        'colors' => [
            ['key' => 'red', 'name' => 'রেড', 'hex' => '#d1332f', 'price' => 1990, 'stock' => 10],
            ['key' => 'blue', 'name' => 'ব্লু', 'hex' => '#2f6bd1', 'price' => 1990, 'stock' => 10],
            ['key' => 'green', 'name' => 'গ্রিন', 'hex' => '#2f9e5b', 'price' => 2050, 'stock' => 8],
        ],
    ],
    [
        'slug' => 'demo-lp-t3',
        'template' => 'template-3',
        'shape' => 'tee',
        'bg' => '#e5e7eb',
        'title' => 'ডেমো ল্যান্ডিং — টেমপ্লেট ৩ (টি-শার্ট)',
        'product' => 'ডেমো কটন টি-শার্ট',
        'price' => 650,
        'colors' => [
            ['key' => 'white', 'name' => 'সাদা', 'hex' => '#f1f1f1', 'price' => 450, 'stock' => 30],
            ['key' => 'black', 'name' => 'কালো', 'hex' => '#262626', 'price' => 450, 'stock' => 25],
            ['key' => 'mustard', 'name' => 'সরষে', 'hex' => '#d4a017', 'price' => 480, 'stock' => 12],
        ],
    ],
];
