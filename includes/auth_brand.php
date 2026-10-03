<?php
/**
 * Split-layout brand panel for login / register.
 */
declare(strict_types=1);
?>
<aside class="auth-brand" aria-label="About RAPID">
    <a class="auth-brand-logo" href="<?= e(url('index.php')) ?>">
        <img src="<?= e(asset('images/rapid.png')) ?>" alt="" width="44" height="44">
        <span>RAPID</span>
    </a>

    <div class="auth-brand-copy">
        <h2>Every repair, documented from drop-off to warranty.</h2>
        <ul class="auth-points">
            <li>
                <span class="auth-point-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.2" stroke="currentColor" stroke-width="1.6"/><path d="M12 7.8V12l3 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                </span>
                <span><strong>Live status</strong>Follow each stage from your phone.</span>
            </li>
            <li>
                <span class="auth-point-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M5 12l5 5 9-10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span><strong>Approve quotes online</strong>Nothing is repaired without your OK.</span>
            </li>
            <li>
                <span class="auth-point-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M12 3l7 3v6c0 4.5-2.8 7.4-7 9-4.2-1.6-7-4.5-7-9V6l7-3z" stroke="currentColor" stroke-width="1.6"/></svg>
                </span>
                <span><strong>Warranty on every job</strong>Claims link to the original ticket.</span>
            </li>
        </ul>
        <div class="auth-preview" aria-hidden="true">
            <div class="auth-preview-head">
                <span class="auth-preview-no">RPR-2026-000142</span>
                <span class="auth-preview-status">Ready for pickup</span>
            </div>
            <div class="auth-preview-device">iPhone 15 Pro · screen replacement</div>
            <div class="auth-preview-segs"><span></span><span></span><span></span><span></span><span class="is-off"></span></div>
            <div class="auth-preview-time">Updated 2 min ago</div>
        </div>
    </div>

    <p class="auth-brand-foot"><?= e(get_setting('shop_address', 'Esposado, Cannery Site, Polomolok, South Cotabato 9505')) ?> · <?= e(get_setting('shop_hours', 'Mon–Sat, 9:00 AM – 6:00 PM')) ?></p>
</aside>
