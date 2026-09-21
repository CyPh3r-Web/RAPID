<?php
/**
 * Split-layout brand panel for login / register.
 */
declare(strict_types=1);
$brandVariant = 'stacked';
$brandClass = 'auth-brand-logo';
?>
<aside class="auth-brand">
    <div class="auth-lockup-plate">
        <?php require __DIR__ . '/brand_lockup.php'; ?>
    </div>
    <div class="auth-brand-copy">
        <h2>Drop-off to pickup, without the phone tag.</h2>
        <p>Book devices, approve quotations, and follow every status update in one place.</p>
        <ul class="auth-points">
            <li>
                <i class="bi bi-camera" aria-hidden="true"></i>
                <span>Document damage with photos before work starts</span>
            </li>
            <li>
                <i class="bi bi-file-earmark-check" aria-hidden="true"></i>
                <span>Review and approve digital quotations</span>
            </li>
            <li>
                <i class="bi bi-broadcast" aria-hidden="true"></i>
                <span>Live ticket status, warranty, and claims</span>
            </li>
        </ul>
    </div>
    <p class="auth-brand-foot">&copy; <?= date('Y') ?> <?= e(SHOP_NAME) ?></p>
</aside>
