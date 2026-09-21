<?php
/**
 * HTML head / opening layout
 * Expects optional: $pageTitle, $bodyClass, $showSidebar
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    require_once __DIR__ . '/auth.php';
}

$pageTitle = $pageTitle ?? APP_NAME;
$bodyClass = $bodyClass ?? '';
$showSidebar = $showSidebar ?? false;
$currentUser = current_user();
$flash = flash_get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/png" href="<?= e(asset('images/rapid.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset('images/rapid.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:ital,wght@0,500;0,600;0,700;0,800;1,700;1,800&family=IBM+Plex+Mono:wght@500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
    <?php if (str_contains((string) $bodyClass, 'landing-page')): ?>
    <link href="<?= e(asset('css/landing.css')) ?>" rel="stylesheet">
    <?php endif; ?>
</head>
<body class="<?= e($bodyClass) ?><?= $showSidebar ? ' has-sidebar' : '' ?>">
<?php if ($flash): ?>
<script>
window.__RAPID_FLASH__ = <?= json_encode($flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
<?php endif; ?>
