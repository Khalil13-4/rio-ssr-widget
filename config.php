<?php
return [
    'store'      => getenv('RIO_STORE') ?: 'YOUR-STORE-ID',  // Reviews.io store ID — set per deployment
    'cache_dir'  => __DIR__ . '/cache',
    'cache_ttl'  => 3600,                  // seconds
    'per_page'   => 10,                    // reviews rendered into HTML

    // Mirror the Polaris config flags so counts match the JS widget:
    'enable_syndication' => false,
    'must_have_body'     => true,          // = review_content_filter: no_comments
    'lang'               => null,          // e.g. 'en' — null omits the param

    // MOCK MODE: serve local fixtures instead of calling api.reviews.io.
    // Set to false on a machine with network access to test live.
    'mock'        => true,
    'fixture_dir' => __DIR__ . '/fixtures',
];
