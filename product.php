<?php
$cfg = require __DIR__ . '/config.php';
require __DIR__ . '/api.php';
require __DIR__ . '/widget.php';

$sku     = $_GET['sku'] ?? 'DEFAULT-SKU';
$pageUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://'
         . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');

$widgetHtml = rio_render_widget(
    rio_timeline($sku, $cfg),
    rio_schema($sku, $pageUrl, $cfg),
    $sku
);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Product <?= htmlspecialchars($sku) ?></title>
  <link rel="stylesheet" href="styles.css">
  <style>
    /* Demo-page shell only — stands in for the merchant's theme.
       The widget inherits font/colors from its host page by design,
       so it needs a host that sets them, exactly like a real store does. */
    body {
      margin: 0;
      background: #f4f3ef;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      -webkit-font-smoothing: antialiased;
      color: #1a1f1c;
    }
    .demo-page { max-width: 860px; margin: 0 auto; padding: 48px 24px 80px; }
    .demo-product {
      background: #fff;
      border: 1px solid #e7e5df;
      border-radius: 14px;
      padding: 40px 44px;
    }
    .demo-product h1 { margin: 0 0 6px; font-size: 26px; font-weight: 650; letter-spacing: -0.02em; }
    .demo-product .demo-sku { color: #8b8a83; font-size: 13px; margin: 0 0 32px; }
    .demo-reviews-title { font-size: 19px; font-weight: 650; margin: 40px 0 20px; letter-spacing: -0.01em; }
    @media (max-width: 640px) { .demo-product { padding: 26px 20px; } }
  </style>
</head>
<body>
  <div class="demo-page">
    <div class="demo-product">
      <h1>Demo Saddle Pad</h1>
      <p class="demo-sku">SKU <?= htmlspecialchars($sku) ?></p>
      <!-- product content would be here -->
      <h2 class="demo-reviews-title">Customer reviews</h2>
      <?= $widgetHtml ?>
    </div>
  </div>
  <script src="lightbox.js" defer></script>
</body>
</html>
