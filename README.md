# Reviews.io SSR Widget — test environment

Server-side rendered review widget: same `timeline/data` and `json-ld/richsnippet`
endpoints Polaris calls, but rendered into the initial HTML response so review
content is visible to AI crawlers (ClaudeBot, GPTBot, etc.) that don't execute JS.

## Files
- `config.php`   — store ID, cache TTL, Polaris-parity flags, mock switch
- `api.php`      — cached HTTP layer (stale-on-error) + mock-fixture routing
- `widget.php`   — renderer: summary, rating breakdown, review list, replies, JSON-LD
- `product.php`  — demo product page
- `styles.css`   — scoped widget styles
- `fixtures/`    — mock API responses (includes edge cases: blank comment,
                   duplicate review, XSS payload, merchant reply, Dutch UTF-8)
- `test.sh`      — acceptance suite

## Run
    php -S localhost:8000
    open http://localhost:8000/product.php?sku=SADDLE-PAD-DLX
    ./test.sh          # 8 checks, exits non-zero on failure

## Switch to live API
In `config.php` set:
    'mock'  => false,
    'store' => '<real store id>',
Then request any real SKU:
    curl -s "localhost:8000/product.php?sku=REAL-SKU" | grep -i "out of 5"
Cache lives in `cache/` (md5 of URL, TTL from config). Delete to force refresh.

## The demo that matters
    curl -s <url> | grep "<review text>"     # crawler's view: text IS in raw HTML
Compare against a Polaris-only page, where the same curl returns an empty div.

## Notes
- Escape-then-nl2br in widget.php is the XSS boundary for review bodies (UGC).
- Dedup fingerprint = md5(body|author), mirroring Polaris's isDuplicate.
- Schema is echoed verbatim from the richsnippet endpoint; guarded so no
  schema block is emitted for zero-review products.
- must_have_body / enable_syndication flags mirror the Polaris bundle's query
  construction so counts match the JS widget.
