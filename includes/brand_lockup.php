<?php
/**
 * Brand mark + wordmark.
 *
 * Expects optional: $brandVariant (compact|stacked), $brandHref, $brandClass
 */
declare(strict_types=1);

$brandVariant = $brandVariant ?? 'compact';
$brandHref = $brandHref ?? url('index.php');
$brandClass = $brandClass ?? '';
$isStacked = $brandVariant === 'stacked';
$wordSrc = $isStacked
    ? asset('images/rapid-wordmark.png')
    : asset('images/rapid-wordmark-name.png');
$alt = 'RAPID — Repair Assessment, Progress, and Issue Documentation';
?>
<a class="brand-lockup brand-lockup-<?= e($brandVariant) ?><?= $brandClass !== '' ? ' ' . e($brandClass) : '' ?>" href="<?= e($brandHref) ?>">
    <img class="brand-logo" src="<?= e(asset('images/rapid.png')) ?>" alt="" width="1254" height="1254">
    <img class="brand-wordmark" src="<?= e($wordSrc) ?>" alt="<?= e($alt) ?>" width="1082" height="<?= $isStacked ? '378' : '208' ?>">
</a>
