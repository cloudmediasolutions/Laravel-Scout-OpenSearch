<?php

return [
    'client' => [
        'hosts' => explode(',', env('OPENSEARCH_HOSTS')),
    ],
//    'indices' => [
//        'default' => [
//            'settings' => [
//                'index' => [
//                    'number_of_shards' => 3,
//                ],
//            ],
//        ],
//        'table' => [
//            'mappings' => [
//                'properties' => [
//                    'id' => [
//                        'type' => 'keyword',
//                    ],
//                ],
//            ],
//        ],
//    ],
];