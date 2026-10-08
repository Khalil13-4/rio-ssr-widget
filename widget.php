<?php

/** Fractional-fill stars: full track of empty stars, clipped overlay of filled ones. */
function rio_stars(float $rating, string $label): string {
    $pct = max(0, min(100, $rating / 5 * 100));
    return '<span class="rio-stars" role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES) . '">'
         . '<span class="rio-stars__track" aria-hidden="true">★★★★★</span>'
         . '<span class="rio-stars__fill" aria-hidden="true" style="width:' . round($pct, 1) . '%">★★★★★</span>'
         . '</span>';
}

/** Display-name sanitizer: no legitimate author name contains markup.
    Removes script/style blocks with their content, then any remaining tags.
    (Output escaping via htmlspecialchars still applies afterwards — this is
    cleanup, not the security boundary.) */
function rio_clean_author(string $name): string {
    $name = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $name);
    $name = strip_tags($name);
    $name = preg_replace('/\s+/', ' ', trim($name));
    return $name;
}

/** Stable pastel hue per author, so avatars vary but never change between renders. */
function rio_avatar_style(string $name): string {
    $h = crc32($name) % 360;
    return 'background:hsl(' . $h . ',42%,91%);color:hsl(' . $h . ',38%,32%)';
}

function rio_initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $ini = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        if ($p !== '' && preg_match('/^\p{L}/u', $p, $m)) {
            $ini .= function_exists('mb_strtoupper') ? mb_strtoupper($m[0]) : strtoupper($m[0]);
        }
    }
    return $ini !== '' ? $ini : '•';
}

function rio_date_human(string $iso): string {
    $ts = strtotime($iso);
    return $ts ? date('j M Y', $ts) : '';
}

function rio_render_widget(?array $data, ?string $schemaJson, string $sku): string {
    if (!$data || empty($data['stats'])) return '';

    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

    $stats   = $data['stats'];
    $avg     = round((float)($stats['average_rating'] ?? 0), 2);
    $count   = (int)($stats['review_count'] ?? $stats['results_count'] ?? 0);
    $reviews = array_map(fn($t) => $t['_source'], $data['timeline'] ?? []);

    if ($count === 0) return '';

    // --- Rating breakdown from aggregations ---
    $buckets = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    foreach ($data['aggregations']['ratings']['buckets'] ?? [] as $b) {
        $buckets[(int)$b['key']] = (int)$b['doc_count'];
    }
    $bucketTotal = max(1, array_sum($buckets));

    // --- Summary header: score block + breakdown side by side ---
    $html  = '<section class="rio-ssr">';
    $html .= '<header class="rio-ssr__summary">';
    $html .= '<div class="rio-ssr__score">';
    $html .= '<span class="rio-ssr__avg">' . $h(number_format($avg, 2)) . '</span>';
    $html .= rio_stars($avg, $avg . ' out of 5 stars');
    $html .= '<span class="rio-ssr__count">Based on ' . $h(number_format($count)) . ' reviews</span>';
    $html .= '</div>';

    $html .= '<ul class="rio-ssr__breakdown">';
    foreach ($buckets as $star => $n) {
        $pct = round($n / $bucketTotal * 100);
        $html .= '<li class="rio-ssr__breakdown-row">'
               . '<span class="rio-ssr__breakdown-label">' . $star . '<span class="rio-ssr__breakdown-star" aria-hidden="true">★</span></span>'
               . '<span class="rio-ssr__bar"><span class="rio-ssr__bar-fill" style="width:' . $pct . '%"></span></span>'
               . '<span class="rio-ssr__breakdown-count">' . $h(number_format($n)) . '</span>'
               . '</li>';
    }
    $html .= '</ul>';
    $html .= '</header>';

    // --- Reviews ---
    $html .= '<ul class="rio-ssr__list">';
    $seen = [];
    foreach ($reviews as $r) {
        $body = trim((string)($r['comments'] ?? ''));
        if ($body === '') continue;

        // Dedup: mirrors Polaris isDuplicate (same body + author)
        $fp = md5($body . '|' . ($r['author'] ?? ''));
        if (isset($seen[$fp])) continue;
        $seen[$fp] = true;

        $author = rio_clean_author((string)($r['author'] ?? '')) ?: 'Verified Customer';
        $rating = (int)($r['rating'] ?? 0);
        $date   = substr((string)($r['date_created'] ?? ''), 0, 10);

        $html .= '<li class="rio-ssr__review">'
               . '<div class="rio-ssr__avatar" aria-hidden="true" style="' . rio_avatar_style($author) . '">' . $h(rio_initials($author)) . '</div>'
               . '<div class="rio-ssr__review-main">'
               . '<div class="rio-ssr__review-head">'
               . '<strong class="rio-ssr__author">' . $h($author) . '</strong>'
               . (!empty($r['verified']) ? '<span class="rio-ssr__verified">&#10003; Verified</span>' : '')
               . ($date ? '<time class="rio-ssr__date" datetime="' . $h($date) . '">' . $h(rio_date_human($date)) . '</time>' : '')
               . '</div>'
               . rio_stars($rating, $rating . ' out of 5 stars')
               . '<p class="rio-ssr__body">' . nl2br($h($body)) . '</p>';  // escape FIRST, then nl2br — UGC/XSS boundary

        // --- Review media: crawlable <img> thumbs in plain links; JS upgrades to lightbox ---
        // Field names vary across Reviews.io payloads; accept the known shapes.
        // Verify against a real media-rich SKU and tighten once confirmed.
        $media = [];
        $imageSets = array_merge($r['images'] ?? [], $r['photos'] ?? []);
        foreach ($imageSets as $m) {
            if (is_string($m)) $m = ['url' => $m];               // bare URL entries
            $full  = $m['url'] ?? $m['image_url'] ?? $m['full'] ?? null;
            $thumb = $m['thumbnail'] ?? $m['thumbnail_url'] ?? $full;
            if ($full) $media[] = ['type' => 'image', 'full' => $full, 'thumb' => $thumb];
        }
        foreach ($r['videos'] ?? [] as $m) {
            if (is_string($m)) $m = ['url' => $m];
            $full  = $m['url'] ?? $m['video_url'] ?? null;
            $thumb = $m['thumbnail'] ?? $m['thumbnail_url'] ?? $m['poster'] ?? null;
            if ($full) $media[] = ['type' => 'video', 'full' => $full, 'thumb' => $thumb];
        }
        foreach ($r['media'] ?? [] as $m) {                      // generic media array
            if (is_string($m)) $m = ['url' => $m];
            $full = $m['url'] ?? $m['src'] ?? null;
            if (!$full) continue;
            $isVideo = ($m['type'] ?? '') === 'video' || preg_match('/\.(mp4|webm|mov)(\?|$)/i', $full);
            $media[] = ['type' => $isVideo ? 'video' : 'image', 'full' => $full,
                        'thumb' => $m['thumbnail'] ?? $m['thumbnail_url'] ?? ($isVideo ? null : $full)];
        }
        if ($media) {
            $html .= '<div class="rio-ssr__media">';
            foreach ($media as $m) {
                $html .= '<a class="rio-ssr__thumb' . ($m['type'] === 'video' ? ' rio-ssr__thumb--video' : '') . '"'
                       . ' href="' . $h($m['full']) . '" data-rio-media="' . $m['type'] . '"'
                       . ' target="_blank" rel="noopener">';
                if ($m['thumb']) {
                    $html .= '<img src="' . $h($m['thumb']) . '" alt="' . ($m['type'] === 'video' ? 'Video' : 'Photo') . ' from ' . $h($author) . '" loading="lazy">';
                }
                if ($m['type'] === 'video') {
                    $html .= '<span class="rio-ssr__play" aria-hidden="true">&#9654;</span>';
                }
                $html .= '</a>';
            }
            $html .= '</div>';
        }

        foreach ($r['responses'] ?? [] as $resp) {
            $rc = trim((string)($resp['comments'] ?? ''));
            if ($rc !== '') {
                $html .= '<div class="rio-ssr__reply">'
                       . '<span class="rio-ssr__reply-label">Reply from store</span>'
                       . '<p class="rio-ssr__body">' . nl2br($h($rc)) . '</p>'
                       . '</div>';
            }
        }
        $html .= '</div></li>';
    }
    $html .= '</ul>';

    if ($schemaJson) {
        $html .= '<script type="application/ld+json">' . $schemaJson . '</script>';
    }

    $html .= '</section>';
    return $html;
}
