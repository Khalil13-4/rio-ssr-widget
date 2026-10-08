#!/usr/bin/env bash
# Acceptance tests for the SSR widget. Run while `php -S localhost:8000` is up.
set -u
URL="${1:-http://localhost:8000/product.php?sku=SADDLE-PAD-DLX}"
OUT=$(curl -s "$URL")
pass=0; fail=0
check() { # check <name> <expected> <actual>
  if [ "$2" = "$3" ]; then echo "PASS  $1"; pass=$((pass+1));
  else echo "FAIL  $1 (expected $2, got $3)"; fail=$((fail+1)); fi
}

# 1. Review body text exists in raw HTML — the core AEO claim
check "review text in raw HTML"      "1" "$(echo "$OUT" | grep -qo 'Excellent quality saddle pad' && echo 1 || echo 0)"
# 2. Aggregate summary present
check "summary count rendered"        "1" "$(echo "$OUT" | grep -qo 'Based on 142 reviews' && echo 1 || echo 0)"
# 3. JSON-LD block present
check "JSON-LD script tag"            "1" "$(echo "$OUT" | grep -qo 'application/ld+json' && echo 1 || echo 0)"
# 4. Dedup: duplicate review rendered once in visible HTML
#    (<p>-prefixed to exclude the copy inside the JSON-LD reviewBody)
check "duplicate review deduped"      "1" "$(echo "$OUT" | grep -o '<p class="rio-ssr__body">Good product overall' | wc -l | tr -d ' ')"
# 5. Blank-comment review excluded: 4 items total
check "review item count"             "4" "$(echo "$OUT" | grep -o '<li class="rio-ssr__review">' | wc -l | tr -d ' ')"
# 6. XSS escaped: no live script/onerror from UGC
check "no unescaped <script> from UGC" "0" "$(echo "$OUT" | grep -o '<script>alert' | wc -l | tr -d ' ')"
check "no unescaped onerror from UGC"  "0" "$(echo "$OUT" | grep -o '<img src=x onerror' | wc -l | tr -d ' ')"
# 7. Merchant reply rendered
check "merchant reply rendered"       "1" "$(echo "$OUT" | grep -o 'class="rio-ssr__reply"' | wc -l | tr -d ' ')"

# 9. Author name sanitized: markup stripped from display name
check "author name sanitized"         "1" "$(echo "$OUT" | grep -o '<strong class="rio-ssr__author">Eva</strong>' | wc -l | tr -d ' ')"
# 10. Media thumbnails rendered (2 photos + 1 video = 3)
check "media thumbnails rendered"     "3" "$(echo "$OUT" | grep -o 'class="rio-ssr__thumb' | wc -l | tr -d ' ')"
# 11. Video thumb has play overlay + lightbox script included
check "video play overlay"            "1" "$(echo "$OUT" | grep -o 'rio-ssr__play' | wc -l | tr -d ' ')"
check "lightbox script included"      "1" "$(echo "$OUT" | grep -o 'lightbox.js' | wc -l | tr -d ' ')"

echo "----"
echo "$pass passed, $fail failed"
exit $fail
