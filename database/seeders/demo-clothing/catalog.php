<?php

/*
| Demo clothing catalogue used by DemoClothingSeeder (and by the script that drew
| the placeholder pictures next to this file). Everything here is sample data.
|
| Per product: slug (unique, starts with "demo-"), Bengali name, price in taka,
| optional sale price, short description, and stock for sizes [S, M, L, XL].
| The picture for a product is <slug>.png in this folder.
*/

return [
    [
        'slug' => 'demo-punjabi',
        'name' => 'পাঞ্জাবি',
        'icon' => '👘',
        'colors' => ['#0f766e', '#134e4a'],
        'sku' => 'PNJ',
        'products' => [
            ['slug' => 'demo-punjabi-cotton-white', 'name' => 'কটন সাদা পাঞ্জাবি', 'price' => 1450, 'sale' => 1250, 'stock' => [12, 20, 18, 8],
                'description' => 'নরম কটন কাপড়ের সাদা পাঞ্জাবি। জুমা ও দৈনন্দিন পরার জন্য আরামদায়ক।'],
            ['slug' => 'demo-punjabi-embroidery-eid', 'name' => 'এমব্রয়ডারি ঈদ পাঞ্জাবি', 'price' => 2200, 'sale' => null, 'stock' => [6, 10, 10, 4],
                'description' => 'গলা ও বুকে সুন্দর এমব্রয়ডারির কাজ করা ঈদ স্পেশাল পাঞ্জাবি।'],
            ['slug' => 'demo-punjabi-printed-fashion', 'name' => 'প্রিন্টেড ফ্যাশন পাঞ্জাবি', 'price' => 1650, 'sale' => 1490, 'stock' => [9, 14, 12, 0],
                'description' => 'আধুনিক প্রিন্টের হালকা ফ্যাশন পাঞ্জাবি, অনুষ্ঠান ও আড্ডার জন্য।'],
            ['slug' => 'demo-punjabi-designer-fotua', 'name' => 'ডিজাইনার ফতুয়া পাঞ্জাবি', 'price' => 1350, 'sale' => null, 'stock' => [15, 22, 16, 9],
                'description' => 'ছোট কলারের ডিজাইনার ফতুয়া স্টাইল পাঞ্জাবি, গরমে আরামদায়ক।'],
        ],
    ],
    [
        'slug' => 'demo-shirt',
        'name' => 'শার্ট',
        'icon' => '👔',
        'colors' => ['#1d4ed8', '#1e3a8a'],
        'sku' => 'SHT',
        'products' => [
            ['slug' => 'demo-shirt-formal-full-sleeve', 'name' => 'ফর্মাল ফুল স্লিভ শার্ট', 'price' => 1200, 'sale' => null, 'stock' => [10, 18, 20, 11],
                'description' => 'অফিস ও মিটিংয়ের জন্য ক্লাসিক ফিটের ফর্মাল ফুল স্লিভ শার্ট।'],
            ['slug' => 'demo-shirt-check-casual', 'name' => 'চেক ক্যাজুয়াল শার্ট', 'price' => 950, 'sale' => 850, 'stock' => [14, 16, 12, 5],
                'description' => 'নরম কাপড়ের চেক ডিজাইনের ক্যাজুয়াল শার্ট, প্রতিদিনের জন্য।'],
            ['slug' => 'demo-shirt-oxford-cotton', 'name' => 'অক্সফোর্ড কটন শার্ট', 'price' => 1350, 'sale' => null, 'stock' => [8, 13, 13, 7],
                'description' => 'টেকসই অক্সফোর্ড কটনের শার্ট, ধোয়ার পরও সুন্দর থাকে।'],
            ['slug' => 'demo-shirt-linen-half-sleeve', 'name' => 'লিনেন হাফ স্লিভ শার্ট', 'price' => 1100, 'sale' => null, 'stock' => [7, 11, 9, 3],
                'description' => 'হালকা লিনেনের হাফ স্লিভ শার্ট, গরমের দিনে স্বস্তিদায়ক।'],
        ],
    ],
    [
        'slug' => 'demo-tshirt',
        'name' => 'টি-শার্ট',
        'icon' => '👕',
        'colors' => ['#c2410c', '#7c2d12'],
        'sku' => 'TSH',
        'products' => [
            ['slug' => 'demo-tshirt-round-neck-basic', 'name' => 'রাউন্ড নেক বেসিক টি-শার্ট', 'price' => 450, 'sale' => 390, 'stock' => [25, 30, 28, 14],
                'description' => '১০০% কটনের রাউন্ড নেক বেসিক টি-শার্ট, বিভিন্ন রঙে।'],
            ['slug' => 'demo-tshirt-polo', 'name' => 'পোলো টি-শার্ট', 'price' => 650, 'sale' => null, 'stock' => [16, 21, 19, 10],
                'description' => 'কলারযুক্ত পোলো টি-শার্ট, ক্যাজুয়াল ও স্মার্ট দুই লুকেই মানানসই।'],
            ['slug' => 'demo-tshirt-printed-graphic', 'name' => 'প্রিন্টেড গ্রাফিক টি-শার্ট', 'price' => 550, 'sale' => null, 'stock' => [18, 24, 20, 0],
                'description' => 'উজ্জ্বল গ্রাফিক প্রিন্টের টি-শার্ট, নরম ও আরামদায়ক।'],
            ['slug' => 'demo-tshirt-full-sleeve-cotton', 'name' => 'ফুল স্লিভ কটন টি-শার্ট', 'price' => 600, 'sale' => 520, 'stock' => [12, 17, 15, 6],
                'description' => 'শীতের শুরুতে পরার মতো ফুল স্লিভ কটন টি-শার্ট।'],
        ],
    ],
    [
        'slug' => 'demo-pant',
        'name' => 'প্যান্ট',
        'icon' => '👖',
        'colors' => ['#475569', '#1e293b'],
        'sku' => 'PNT',
        'products' => [
            ['slug' => 'demo-pant-slim-fit-jeans', 'name' => 'স্লিম ফিট জিন্স প্যান্ট', 'price' => 1500, 'sale' => 1350, 'stock' => [10, 15, 14, 6],
                'description' => 'স্ট্রেচ ডেনিমের স্লিম ফিট জিন্স, পরতে আরামদায়ক।'],
            ['slug' => 'demo-pant-cotton-chino', 'name' => 'কটন চিনো প্যান্ট', 'price' => 1300, 'sale' => null, 'stock' => [9, 14, 13, 8],
                'description' => 'নরম কটনের চিনো প্যান্ট, শার্ট ও টি-শার্ট দুটোর সাথেই মানায়।'],
            ['slug' => 'demo-pant-formal-gabardine', 'name' => 'ফর্মাল গ্যাবার্ডিন প্যান্ট', 'price' => 1250, 'sale' => null, 'stock' => [8, 12, 12, 5],
                'description' => 'অফিসের জন্য গ্যাবার্ডিন কাপড়ের ফর্মাল প্যান্ট, ভাঁজ সহজে পড়ে না।'],
            ['slug' => 'demo-pant-jogger-trouser', 'name' => 'জগার ট্রাউজার', 'price' => 850, 'sale' => null, 'stock' => [20, 26, 22, 12],
                'description' => 'ইলাস্টিক কোমরের আরামদায়ক জগার ট্রাউজার, ঘরে ও বাইরে পরার জন্য।'],
        ],
    ],
    [
        'slug' => 'demo-borka-abaya',
        'name' => 'বোরকা/আবায়া',
        'icon' => '🧕',
        'colors' => ['#6d28d9', '#3b0764'],
        'sku' => 'BRK',
        'products' => [
            ['slug' => 'demo-borka-classic-black', 'name' => 'ক্লাসিক কালো বোরকা', 'price' => 2400, 'sale' => 2150, 'stock' => [8, 14, 14, 7],
                'description' => 'মোলায়েম কাপড়ের ক্লাসিক কালো বোরকা, ওড়নাসহ।'],
            ['slug' => 'demo-abaya-front-open', 'name' => 'ফ্রন্ট ওপেন আবায়া', 'price' => 2800, 'sale' => null, 'stock' => [6, 10, 10, 5],
                'description' => 'সামনে খোলা ডিজাইনের আবায়া, সহজে পরা ও খোলা যায়।'],
            ['slug' => 'demo-borka-embroidery-designer', 'name' => 'এমব্রয়ডারি ডিজাইনার বোরকা', 'price' => 3200, 'sale' => 2900, 'stock' => [4, 8, 8, 0],
                'description' => 'হাতা ও ওড়নায় সূক্ষ্ম এমব্রয়ডারির কাজ করা ডিজাইনার বোরকা।'],
            ['slug' => 'demo-abaya-with-hijab-set', 'name' => 'হিজাব সেট সহ আবায়া', 'price' => 2600, 'sale' => null, 'stock' => [7, 11, 11, 6],
                'description' => 'মানানসই হিজাবসহ পূর্ণ সেট আবায়া, উপহার দেওয়ার জন্যও চমৎকার।'],
        ],
    ],
];
