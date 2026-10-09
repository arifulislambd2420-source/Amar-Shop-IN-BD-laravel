<?php

// Bangla for keys Filament's own bn pack does not have yet (merged over it).
return [
    'column_manager' => [
        'actions' => [
            'reorder' => [
                'label' => 'কলামের ক্রম বদলান',
            ],
        ],
    ],
    'columns' => [
        'icon' => [
            'boolean' => [
                'true' => 'হ্যাঁ',
                'false' => 'না',
            ],
        ],
    ],
    'actions' => [
        'reorder_record' => [
            'label' => 'আইটেম :key-এর ক্রম বদলান',
        ],
        'toggle_record_content' => [
            'label' => 'আইটেম :key খুলুন/গুটান',
        ],
    ],
    'loading' => 'লোড হচ্ছে...',
    'result_count' => '{0} কোনো ফলাফল নেই|{1} :count টি ফলাফল|[2,*] :count টি ফলাফল',
];
