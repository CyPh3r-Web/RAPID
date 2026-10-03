<?php
/**
 * Split-layout brand panel for login / register.
 */
declare(strict_types=1);
?>
<aside class="auth-brand" aria-label="About RAPID">
    <a class="auth-brand-logo" href="<?= e(url('index.php')) ?>">
        <img src="<?= e(asset('images/rapid.png')) ?>" alt="" width="40" height="40">
        <span>RAPID</span>
    </a>

    <div class="auth-stack" aria-hidden="true">
        <div class="auth-stack-rig">
            <div class="auth-note auth-note-1">
                <span class="auth-note-ico"><i class="bi bi-camera"></i></span>
                <span><strong>Photos on file</strong><small>Front · back · screen</small></span>
            </div>
            <div class="auth-note auth-note-2">
                <span class="auth-note-ico"><i class="bi bi-wrench-adjustable"></i></span>
                <span><strong>Repairing</strong><small>Every stage updates your ticket</small></span>
            </div>
            <div class="auth-note auth-note-3">
                <span class="auth-note-ico"><i class="bi bi-check-lg"></i></span>
                <span><strong>Ready for pickup</strong><small>We notify you when it’s done</small></span>
            </div>
        </div>
    </div>

    <div class="auth-brand-copy">
        <h2>Track every repair, from bench to pickup.</h2>
        <p>Photos at drop-off, a quote before work starts, and a warranty when it’s done.</p>
    </div>
</aside>
