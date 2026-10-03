<?php
/**
 * Split-layout brand panel for login / register.
 */
declare(strict_types=1);
$brandVariant = 'stacked';
$brandClass = 'auth-brand-logo';
?>
<aside class="auth-brand" aria-label="About RAPID">
    <div class="auth-brand-bg" aria-hidden="true">
        <div class="auth-brand-wash"></div>
        <span class="auth-brand-streak auth-brand-streak-1"></span>
        <span class="auth-brand-streak auth-brand-streak-2"></span>
        <span class="auth-brand-streak auth-brand-streak-3"></span>
        <div class="auth-brand-dots"></div>
        <div class="auth-brand-glow"></div>
    </div>

    <div class="auth-brand-top">
        <div class="auth-lockup-plate">
            <?php require __DIR__ . '/brand_lockup.php'; ?>
        </div>
        <p class="auth-brand-kicker">
            <span class="auth-brand-kicker-dot" aria-hidden="true"></span>
            Precision device care · Polomolok
        </p>
    </div>

    <div class="auth-brand-copy">
        <p class="auth-brand-name" aria-hidden="true">RAPID</p>
        <h2>Drop-off to pickup,<br>without the phone tag.</h2>
        <p>Book devices, approve quotations, and follow every status update in one place.</p>
        <ul class="auth-points">
            <li>
                <span class="auth-point-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><rect x="4" y="6" width="16" height="12" rx="2.2" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="2.4" stroke="currentColor" stroke-width="1.6"/><path d="M8 6l1.2-1.8h5.6L16 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span>Document damage with photos before work starts</span>
            </li>
            <li>
                <span class="auth-point-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M12 3l7 3v6c0 4.5-2.8 7.4-7 9-4.2-1.6-7-4.5-7-9V6l7-3z" stroke="currentColor" stroke-width="1.6"/><path d="M8.8 12.2l2.1 2.1 4.4-4.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span>Review and approve digital quotations</span>
            </li>
            <li>
                <span class="auth-point-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.2" stroke="currentColor" stroke-width="1.6"/><path d="M12 7.8V12l3 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                </span>
                <span>Live ticket status, warranty, and claims</span>
            </li>
        </ul>
    </div>

    <p class="auth-brand-foot">&copy; <?= date('Y') ?> <?= e(SHOP_NAME) ?></p>
</aside>
