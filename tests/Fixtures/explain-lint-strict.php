<?php

declare(strict_types=1);

return [
    'mode' => 'strict',
    'connections' => [
        'default' => [
            'driver' => 'mysql',
            'rules' => [
                'full_table_scan' => 'strict',
                'no_index_available' => 'strict',
            ],
            'row_estimate_threshold' => 1000,
            'table_size_tiers' => ['tiny' => 50, 'small' => 500],
        ],
    ],
];
