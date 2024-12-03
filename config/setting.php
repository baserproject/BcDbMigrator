<?php
return [
    'BcDbMigrator' => [
        // 追加indexが貼られているパターン
        'sqlRelationNames' => [
            'blog_post_id',
            'blog_content_id',
        ],
    ]
];