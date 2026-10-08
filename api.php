<?php

/**
 * Low-level cached GET. Returns decoded JSON array or null.
 * On API failure, serves stale cache rather than nothing.
 */
function rio_get(string $url, array $cfg): ?array {
    // ---- Mock mode: route known endpoints to local fixtures ----
    if (!empty($cfg['mock'])) {
        $fixture = null;
        if (str_contains($url, '/timeline/data'))            $fixture = 'timeline.json';
        if (str_contains($url, '/json-ld/product/richsnippet')) $fixture = 'richsnippet.json';
        if ($fixture) {
            $path = $cfg['fixture_dir'] . '/' . $fixture;
            return is_file($path) ? json_decode(file_get_contents($path), true) : null;
        }
        return null;
    }

    // ---- Live mode ----
    $cacheFile = $cfg['cache_dir'] . '/' . md5($url) . '.json';

    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cfg['cache_ttl']) {
        return json_decode(file_get_contents($cacheFile), true);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'rio-ssr-widget/1.0',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$body) {
        return is_file($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : null;
    }

    if (!is_dir($cfg['cache_dir'])) mkdir($cfg['cache_dir'], 0755, true);
    file_put_contents($cacheFile, $body);
    return json_decode($body, true);
}

/** Timeline endpoint — same one Polaris calls, same flags, so numbers match the JS widget. */
function rio_timeline(string $sku, array $cfg): ?array {
    $params = [
        'type'     => 'product_review',
        'store'    => $cfg['store'],
        'sku'      => $sku,
        'sort'     => 'date_desc',
        'page'     => 1,
        'per_page' => $cfg['per_page'],
        'widget'   => 'ssr',
    ];
    if ($cfg['enable_syndication']) $params['enable_syndication'] = 'true';
    if ($cfg['must_have_body'])     $params['must_have_body'] = 1;
    if ($cfg['lang'])               $params['lang'] = $cfg['lang'];

    return rio_get('https://api.reviews.io/timeline/data?' . http_build_query($params), $cfg);
}

/** Pre-built JSON-LD from Reviews.io's own richsnippet endpoint. Returns JSON string or null. */
function rio_schema(string $sku, string $pageUrl, array $cfg): ?string {
    $params = ['sku' => $sku, 'store' => $cfg['store'], 'url' => $pageUrl];
    if ($cfg['enable_syndication']) $params['enable_syndication'] = 'true';
    if ($cfg['must_have_body'])     $params['must_have_body'] = 1;

    $data = rio_get('https://api.reviews.io/json-ld/product/richsnippet?' . http_build_query($params), $cfg);
    if (!$data || empty($data['aggregateRating'])) return null;

    return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
