<?php
/**
 * RAPID public landing page
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Home';
$bodyClass = 'landing-page';
$navVariant = 'public';

$shopPhone = get_setting('shop_phone', '09101495174');
$shopEmail = get_setting('shop_email', 'support@rapid.local');
$shopHours = get_setting('shop_hours', 'Mon–Sat, 9:00 AM – 6:00 PM');
$shopAddress = get_setting('shop_address', 'Esposado, Cannery Site, Polomolok, South Cotabato 9505');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="lp">
    <section class="lp-hero">
        <div class="lp-hero-bg" aria-hidden="true" data-lp-hero-bg>
            <div class="lp-dg-wash">
                <span class="lp-dg-streak lp-dg-streak-1"></span>
                <span class="lp-dg-streak lp-dg-streak-2"></span>
                <span class="lp-dg-streak lp-dg-streak-3"></span>
                <span class="lp-dg-streak lp-dg-streak-4"></span>
                <span class="lp-dg-streak lp-dg-streak-5"></span>
            </div>
            <div class="lp-dg-noise"></div>
            <div class="lp-dg-dots"></div>
            <div class="lp-dg-highlight"></div>
        </div>

        <div class="lp-container lp-hero-layout">
            <div class="lp-hero-copy">
                <p class="lp-kicker">
                    <span class="lp-kicker-dot" aria-hidden="true"></span>
                    Precision device care · Polomolok
                </p>
                <h1>
                    Precision repair.
                    <em>Total visibility.</em>
                </h1>
                <p class="lp-lead">
                    RAPID documents every job from drop-off to warranty — diagnostics, quotations,
                    live status, and pickup — so you always know where your device stands.
                </p>
                <div class="lp-hero-actions">
                    <?php if (is_logged_in()): ?>
                        <a class="lp-btn lp-btn-solid" href="<?= e(url(role_home_path(current_user()['role']))) ?>">
                            Go to dashboard
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                    <?php else: ?>
                        <a class="lp-btn lp-btn-solid" href="<?= e(url('auth/register.php')) ?>">
                            Book a repair
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                        <a class="lp-btn lp-btn-ghost" href="<?= e(url('auth/login.php')) ?>">Sign in</a>
                    <?php endif; ?>
                </div>
                <ul class="lp-trust">
                    <li>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l7 3v6c0 4.5-2.8 7.4-7 9-4.2-1.6-7-4.5-7-9V6l7-3z" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8.8 12.2l2.1 2.1 4.4-4.6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        30-day warranty
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.2" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M12 7.8V12l3 2" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        Live ticket status
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="6" width="16" height="12" rx="2.2" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="2.4" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8 6l1.2-1.8h5.6L16 6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Photo documentation
                    </li>
                </ul>
            </div>

            <div class="lp-hero-visual">
                <div class="lp-stage" data-lp-stage>
                    <div class="lp-stage-floor" aria-hidden="true"></div>
                    <div class="lp-cluster" data-lp-cluster>
                        <div class="lp-ring" aria-hidden="true">
                            <svg viewBox="0 0 420 420">
                                <circle cx="210" cy="210" r="168" fill="none" stroke="rgba(61,148,253,0.28)" stroke-width="1.2" stroke-dasharray="6 10"/>
                                <circle cx="210" cy="210" r="128" fill="none" stroke="rgba(255,255,255,0.12)" stroke-width="1"/>
                            </svg>
                        </div>

                        <div class="lp-laptop" aria-hidden="true">
                            <div class="lp-laptop-lid">
                                <div class="lp-laptop-screen">
                                    <div class="lp-os-bar">
                                        <span></span><span></span><span></span>
                                        <strong>Live repair</strong>
                                    </div>
                                    <div class="lp-os-bench">
                                        <span class="lp-os-phone">
                                            <i></i>
                                        </span>
                                        <svg class="lp-wave" viewBox="0 0 148 56" preserveAspectRatio="none">
                                            <path d="M0 32 C12 32 12 12 24 12 S36 44 48 44 60 16 72 16 84 40 96 40 108 10 120 10 132 36 148 36" fill="none" stroke="#3D94FD" stroke-width="2.2"/>
                                            <path d="M0 40 C14 40 10 24 26 24 S42 50 56 50 70 28 84 28 98 52 112 52 126 22 148 22" fill="none" stroke="rgba(61,148,253,0.35)" stroke-width="1.6"/>
                                        </svg>
                                    </div>
                                    <div class="lp-os-stats">
                                        <span>Display <b>Fixing</b></span>
                                        <span>Board <b>Live</b></span>
                                        <span>Test <b>62%</b></span>
                                    </div>
                                </div>
                            </div>
                            <div class="lp-laptop-base"></div>
                        </div>

                        <div class="lp-phone-wrap">
                        <div class="lp-phone" aria-hidden="true">
                            <div class="lp-phone-face lp-phone-back"></div>
                            <div class="lp-phone-face lp-phone-left"></div>
                            <div class="lp-phone-face lp-phone-right"></div>
                            <div class="lp-phone-face lp-phone-top"></div>
                            <div class="lp-phone-face lp-phone-bottom"></div>
                            <div class="lp-phone-face lp-phone-front">
                                <div class="lp-phone-glass">
                                    <span class="lp-island"><i></i></span>
                                    <div class="lp-phone-ui">
                                        <p class="lp-phone-brand">RAPID</p>
                                        <p class="lp-phone-ticket">RPR-2026-000142</p>
                                        <p class="lp-phone-device">iPhone 15 Pro · Deep Blue</p>
                                        <div class="lp-phone-status">
                                            <span class="lp-pulse"></span>
                                            Repairing
                                        </div>
                                        <div class="lp-phone-bar"><span></span></div>
                                        <ul class="lp-phone-steps">
                                            <li class="is-done">Received</li>
                                            <li class="is-done">Diagnosed</li>
                                            <li class="is-now">Repairing</li>
                                            <li>Pickup</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="lp-phone-shadow" aria-hidden="true"></div>
                        </div>

                        <div class="lp-chip-card lp-chip-a" data-lp-depth="0.8">
                            <svg viewBox="0 0 32 32" aria-hidden="true">
                                <rect x="7" y="7" width="18" height="18" rx="3" fill="none" stroke="currentColor" stroke-width="1.6"/>
                                <path d="M11 4v4M21 4v4M11 24v4M21 24v4M4 11h4M24 11h4M4 21h4M24 21h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                <rect x="12" y="12" width="8" height="8" rx="1.2" fill="currentColor" opacity="0.35"/>
                            </svg>
                            Board-level
                        </div>
                        <div class="lp-chip-card lp-chip-b">
                            <span class="lp-chip-dot"></span>
                            Live tracking
                        </div>
                        <div class="lp-chip-card lp-chip-c" data-lp-depth="0.7">
                            <svg viewBox="0 0 32 32" aria-hidden="true">
                                <path d="M16 5l9 4v7.5c0 5.4-3.4 8.8-9 10.7-5.6-1.9-9-5.3-9-10.7V9l9-4z" fill="none" stroke="currentColor" stroke-width="1.6"/>
                                <path d="M12 16.2l2.6 2.6 5.4-5.6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                            </svg>
                            30-day cover
                        </div>

                        <div class="lp-float-tool" aria-hidden="true">
                            <svg viewBox="0 0 64 64">
                                <defs>
                                    <linearGradient id="lpTool" x1="0" y1="0" x2="1" y2="1">
                                        <stop offset="0%" stop-color="#E8EEF6"/>
                                        <stop offset="100%" stop-color="#8B98AB"/>
                                    </linearGradient>
                                </defs>
                                <rect x="28" y="6" width="8" height="34" rx="2" fill="url(#lpTool)"/>
                                <path d="M18 42h28l-4 14H22l-4-14z" fill="#1A3A6B"/>
                                <rect x="30" y="2" width="4" height="8" rx="1" fill="#3D94FD"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="lp-track" id="track-panel">
                    <div class="lp-track-head">
                        <div>
                            <h2>Track my repair</h2>
                            <p>Enter your ticket number. No login required.</p>
                        </div>
                        <span class="lp-track-badge">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12a8 8 0 1016 0A8 8 0 004 12z" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M12 8v4l2.5 1.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                            Guest access
                        </span>
                    </div>
                    <form method="get" action="<?= e(url('track.php')) ?>" data-disable-on-submit>
                        <label class="form-label" for="ticket_number">Ticket number</label>
                        <input type="text" class="form-control mb-3" id="ticket_number" name="ticket"
                               placeholder="RPR-2026-000001" required
                               pattern="RPR-\d{4}-\d{6}" title="Format: RPR-YYYY-000001">
                        <label class="form-label" for="contact">Phone or email</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="contact" name="contact"
                                   placeholder="Used on the booking" required>
                            <button class="btn btn-rapid-primary" type="submit">Track</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <section class="lp-section lp-services" id="services">
        <div class="lp-container">
            <div class="lp-section-head" data-lp-reveal>
                <p class="lp-eyebrow">Capabilities</p>
                <h2>Bench-grade care for the devices you actually use.</h2>
                <p class="lp-section-lead">Documented intake, component-level work, and a ticket you can follow from any phone.</p>
            </div>
            <div class="lp-service-grid">
                <article class="lp-service" data-lp-reveal>
                    <div class="lp-service-ico" aria-hidden="true">
                        <svg viewBox="0 0 64 64">
                            <rect x="20" y="8" width="24" height="48" rx="5" fill="none" stroke="currentColor" stroke-width="2"/>
                            <rect x="23.5" y="13" width="17" height="34" rx="2.5" fill="currentColor" opacity="0.12"/>
                            <circle cx="32" cy="51.5" r="1.8" fill="currentColor"/>
                        </svg>
                    </div>
                    <h3>Smartphones</h3>
                    <p>Screens, batteries, charging ports, and board-level faults — photographed before and after.</p>
                </article>
                <article class="lp-service" data-lp-reveal>
                    <div class="lp-service-ico" aria-hidden="true">
                        <svg viewBox="0 0 64 64">
                            <rect x="10" y="12" width="44" height="28" rx="3" fill="none" stroke="currentColor" stroke-width="2"/>
                            <path d="M18 48h28M24 40v8M40 40v8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <rect x="14" y="16" width="36" height="20" rx="1.5" fill="currentColor" opacity="0.12"/>
                        </svg>
                    </div>
                    <h3>Laptops</h3>
                    <p>Hardware diagnostics, storage, keyboards, and thermal service with a digital quotation first.</p>
                </article>
                <article class="lp-service" data-lp-reveal>
                    <div class="lp-service-ico" aria-hidden="true">
                        <svg viewBox="0 0 64 64">
                            <rect x="12" y="10" width="40" height="44" rx="4" fill="none" stroke="currentColor" stroke-width="2"/>
                            <rect x="16" y="16" width="32" height="28" rx="2" fill="currentColor" opacity="0.12"/>
                            <path d="M28 50h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <h3>Tablets &amp; more</h3>
                    <p>Slate devices, wearables, and accessories tracked on the same RAPID ticket workflow.</p>
                </article>
                <article class="lp-service" data-lp-reveal>
                    <div class="lp-service-ico" aria-hidden="true">
                        <svg viewBox="0 0 64 64">
                            <path d="M32 8l20 8v14c0 12-8.4 19.6-20 24C20.4 49.6 12 42 12 30V16l20-8z" fill="none" stroke="currentColor" stroke-width="2"/>
                            <path d="M22 31l7 7 14-15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h3>Warranty claims</h3>
                    <p>Completed jobs open a warranty window. File a claim against the original ticket, not a new guess.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="lp-section lp-process" id="how-it-works">
        <div class="lp-container">
            <div class="lp-section-head" data-lp-reveal>
                <p class="lp-eyebrow">Workflow</p>
                <h2>Four steps. One thread. Nothing lost on the bench.</h2>
                <p class="lp-section-lead">Customers, technicians, and the shop floor share the same ticket — not a paper trail.</p>
            </div>
            <ol class="lp-steps">
                <li class="lp-step" data-lp-reveal>
                    <span class="lp-step-num">01</span>
                    <div class="lp-step-art" aria-hidden="true">
                        <svg viewBox="0 0 120 88">
                            <rect x="18" y="16" width="84" height="56" rx="10" fill="#E8F2FE" stroke="#091C39" stroke-width="1.6"/>
                            <rect x="28" y="28" width="40" height="8" rx="4" fill="#091C39" opacity="0.2"/>
                            <rect x="28" y="42" width="64" height="6" rx="3" fill="#0072FC" opacity="0.35"/>
                            <rect x="28" y="54" width="52" height="6" rx="3" fill="#0072FC" opacity="0.2"/>
                            <circle cx="96" cy="22" r="10" fill="#0072FC"/>
                            <path d="M92 22l3 3 6-7" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <h3>Book &amp; document</h3>
                    <p>Submit device details with before-repair photos or videos so the bench sees what you see.</p>
                </li>
                <li class="lp-step" data-lp-reveal>
                    <span class="lp-step-num">02</span>
                    <div class="lp-step-art" aria-hidden="true">
                        <svg viewBox="0 0 120 88">
                            <rect x="22" y="14" width="76" height="52" rx="6" fill="#091C39"/>
                            <path d="M30 48c8-16 12-8 18-8s8-16 16-16 10 20 18 20 8-10 16-6" fill="none" stroke="#3D94FD" stroke-width="2"/>
                            <rect x="36" y="70" width="48" height="6" rx="3" fill="#D5DDE8"/>
                        </svg>
                    </div>
                    <h3>Diagnose &amp; quote</h3>
                    <p>Technicians inspect, diagnose, and send a digital quotation you can approve or decline.</p>
                </li>
                <li class="lp-step" data-lp-reveal>
                    <span class="lp-step-num">03</span>
                    <div class="lp-step-art" aria-hidden="true">
                        <svg viewBox="0 0 120 88">
                            <circle cx="60" cy="40" r="26" fill="none" stroke="#D5DDE8" stroke-width="8"/>
                            <circle cx="60" cy="40" r="26" fill="none" stroke="#0072FC" stroke-width="8" stroke-dasharray="120 164" stroke-linecap="round" transform="rotate(-90 60 40)"/>
                            <text x="60" y="45" text-anchor="middle" font-size="14" font-weight="700" fill="#091C39" font-family="IBM Plex Mono, monospace">64%</text>
                        </svg>
                    </div>
                    <h3>Repair &amp; notify</h3>
                    <p>Follow status updates from approval through repair — no chasing the counter for news.</p>
                </li>
                <li class="lp-step" data-lp-reveal>
                    <span class="lp-step-num">04</span>
                    <div class="lp-step-art" aria-hidden="true">
                        <svg viewBox="0 0 120 88">
                            <rect x="34" y="18" width="52" height="52" rx="12" fill="#E8F2FE" stroke="#091C39" stroke-width="1.6"/>
                            <path d="M60 30v16M52 38h16" stroke="#0072FC" stroke-width="2.4" stroke-linecap="round"/>
                            <path d="M44 62h32" stroke="#091C39" stroke-width="1.6" stroke-linecap="round" opacity="0.35"/>
                            <circle cx="86" cy="26" r="12" fill="#059669"/>
                            <path d="M81 26l3.2 3.2 7-7" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <h3>Pickup &amp; warranty</h3>
                    <p>Completed jobs open a warranty window with linked claims if anything comes back.</p>
                </li>
            </ol>
        </div>
    </section>

    <section class="lp-section lp-contact" id="contact">
        <div class="lp-container lp-contact-grid">
            <div data-lp-reveal>
                <p class="lp-eyebrow">Visit</p>
                <h2>The bench is in <?= e($shopAddress) ?> — the ticket lives online.</h2>
                <p class="lp-section-lead">Walk in for drop-off, then follow every update from your phone. Same shop. Clearer paper trail.</p>
                <div class="lp-contact-actions">
                    <a class="lp-btn lp-btn-navy" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $shopPhone)) ?>">
                        Call <?= e($shopPhone) ?>
                    </a>
                    <a class="lp-btn lp-btn-line" href="mailto:<?= e($shopEmail) ?>">Email the shop</a>
                </div>
            </div>
            <div class="lp-contact-card" data-lp-reveal>
                <div class="lp-map" aria-hidden="true">
                    <svg viewBox="0 0 360 160" preserveAspectRatio="none">
                        <defs>
                            <pattern id="lpMapGrid" width="20" height="20" patternUnits="userSpaceOnUse">
                                <path d="M20 0H0V20" fill="none" stroke="rgba(9,28,57,0.08)" stroke-width="1"/>
                            </pattern>
                        </defs>
                        <rect width="360" height="160" fill="#E8EEF6"/>
                        <rect width="360" height="160" fill="url(#lpMapGrid)"/>
                        <path d="M0 110 C40 90 70 130 120 100 S200 60 260 88 320 140 360 120" fill="none" stroke="#0072FC" stroke-opacity="0.35" stroke-width="8"/>
                        <path d="M0 70 C80 40 140 90 200 58 S300 20 360 48" fill="none" stroke="#091C39" stroke-opacity="0.12" stroke-width="6"/>
                    </svg>
                    <span class="lp-pin">
                        <svg viewBox="0 0 32 40">
                            <path d="M16 0C8 0 2 6.2 2 14.2 2 24 16 40 16 40s14-16 14-25.8C30 6.2 24 0 16 0z" fill="#0072FC"/>
                            <circle cx="16" cy="14" r="5" fill="#fff"/>
                        </svg>
                    </span>
                </div>
                <dl class="lp-contact-dl">
                    <div>
                        <dt>Studio</dt>
                        <dd><?= e(SHOP_NAME) ?></dd>
                    </div>
                    <div>
                        <dt>Address</dt>
                        <dd><?= e($shopAddress) ?></dd>
                    </div>
                    <div>
                        <dt>Phone</dt>
                        <dd><?= e($shopPhone) ?></dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd><a href="mailto:<?= e($shopEmail) ?>"><?= e($shopEmail) ?></a></dd>
                    </div>
                    <div>
                        <dt>Hours</dt>
                        <dd><?= e($shopHours) ?></dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
