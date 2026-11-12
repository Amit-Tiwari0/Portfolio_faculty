<?php
declare(strict_types=1);
$contentPath = is_file(__DIR__ . '/data/content.php') ? __DIR__ . '/data/content.php' : __DIR__ . '/data/content.example.php';
$content = require $contentPath;
$profile = $content['profile'];
$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Faculty profile, research interests, teaching, and publications.">
  <meta name="theme-color" content="#f7f8fa">
  <title><?= htmlspecialchars($profile['name'] ?? 'Faculty Portfolio', ENT_QUOTES, 'UTF-8') ?> — Faculty Portfolio</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&amp;family=Roboto:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/site.css?v=19">
  <link rel="stylesheet" href="assets/mobile.css?v=6">
  <link rel="stylesheet" href="assets/reference-theme.css?v=2">
  <script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
  <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
  <script>window.facultyPortfolioData = <?= json_encode($content, $jsonFlags) ?>;</script>
  <script defer src="assets/site.js?v=19"></script>
</head>
<body>
  <div id="site-root"><main class="loading-page">Loading faculty portfolio…</main></div>
</body>
</html>
