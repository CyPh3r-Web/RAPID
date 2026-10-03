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
$warrantyDays = warranty_days_setting();

// Exploded-view layers, back to front: key => [label, service it maps to]
$phoneParts = [
    'screen' => ['Display & touch', 'Cracked or dead screens'],
    'frame' => ['Mid-frame', 'Housing, buttons, ports'],
    'board' => ['Logic board', 'Board-level diagnostics'],
    'battery' => ['Battery', 'Swelling, fast drain'],
    'back' => ['Back glass', 'Rear glass & cameras'],
];

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="lp">
    <section class="lp-wrap lp-hero" aria-labelledby="heroTitle">
        <div class="lp-hero-copy">
            <p class="lp-tag"><b>Live</b> Repair tracking · Polomolok, South Cotabato</p>
            <h1 id="heroTitle">Repairs you can see <span>right through.</span></h1>
            <p class="lp-lead">Every device is photographed at drop-off, diagnosed at the board, quoted before work starts, and tracked live until pickup.</p>
            <div class="lp-actions">
                <?php if (is_logged_in()): ?>
                    <a class="lp-pill lp-pill-dark lp-pill-lg" href="<?= e(url(role_home_path(current_user()['role']))) ?>">
                        Go to dashboard <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                <?php else: ?>
                    <a class="lp-pill lp-pill-dark lp-pill-lg" href="<?= e(url('auth/register.php')) ?>">
                        Book a repair <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>
                <a class="lp-pill lp-pill-line lp-pill-lg" href="#track-panel">Track a repair</a>
            </div>
            <ul class="lp-chips">
                <li>Photo-documented intake</li>
                <li>Quote before any work</li>
                <li><?= (int) $warrantyDays ?>-day warranty</li>
            </ul>
        </div>

        <div class="lp-stage is-exploded" data-rig-stage>
            <div class="lp-stage-glow" aria-hidden="true"></div>
            <div class="lp-scene" aria-hidden="true">
                <div class="lp-floor"></div>
                <div class="lp-float">
                    <div class="lp-rig" data-rig>
                        <div class="lp-layer lp-l-back" data-layer="back"><div class="lp-cam"><i></i><i></i><i></i><i></i></div></div>
                        <div class="lp-layer lp-l-battery" data-layer="battery"><div class="lp-batt"><span class="lp-batt-ico"></span>Battery</div></div>
                        <div class="lp-layer lp-l-board" data-layer="board"><div class="lp-board"><span class="lp-chip lp-chip-soc"></span><span class="lp-chip lp-chip-1"></span><span class="lp-chip lp-chip-2"></span><span class="lp-chip lp-chip-3"></span><span class="lp-pads"></span></div></div>
                        <div class="lp-layer lp-l-frame" data-layer="frame"></div>
                        <div class="lp-layer lp-l-screen" data-layer="screen">
                            <div class="lp-glass">
                                <div class="lp-island">
                                    <svg viewBox="0 0 20 20"><circle cx="10" cy="10" r="7" fill="none" stroke="rgba(255,255,255,.2)" stroke-width="3"/><circle cx="10" cy="10" r="7" fill="none" stroke="#6E9BFF" stroke-width="3" stroke-dasharray="27.3 44" stroke-linecap="round" transform="rotate(-90 10 10)"/></svg>
                                    <b>62%</b>
                                </div>
                                <div class="lp-ui">
                                    <div class="lp-ui-top"><span>RAPID</span><span class="lp-ui-live">Live</span></div>
                                    <div class="lp-ui-card">
                                        <span class="lp-mono">RPR-2026-000142</span>
                                        <span class="lp-ui-status">Repairing</span>
                                        <span class="lp-ui-eta">Ready today · 5:00 PM</span>
                                        <div class="lp-segs"><span class="on"></span><span class="on"></span><span class="on"></span><span></span><span></span></div>
                                    </div>
                                    <div class="lp-ui-card"><span class="lp-ui-dim">Quotation · approved</span><span class="lp-ui-amt">₱3,450.00</span></div>
                                    <div class="lp-ui-btn">View ticket</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lp-callouts">
                <?php foreach ($phoneParts as $key => [$label, $service]): ?>
                    <button type="button" class="lp-callout" data-rig-focus="<?= e($key) ?>" aria-pressed="false">
                        <i aria-hidden="true"></i><span><?= e($label) ?><small><?= e($service) ?></small></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="lp-seg" role="group" aria-label="Phone view">
                <button type="button" class="is-on" data-rig-mode="exploded" aria-pressed="true">Exploded view</button>
                <button type="button" data-rig-mode="assembled" aria-pressed="false">Assembled</button>
            </div>
            <span class="lp-hint">Move your pointer to rotate</span>
        </div>
    </section>

    <section class="lp-wrap lp-bento" id="services" aria-label="Track your repair and what we do">
        <form class="lp-cell lp-cell-dark lp-span-2" id="track-panel" method="get" action="<?= e(url('track.php')) ?>" data-disable-on-submit aria-labelledby="trackTitle">
            <span class="lp-kicker">No login needed</span>
            <h2 id="trackTitle">Track your repair</h2>
            <div class="lp-track-fields">
                <label class="lp-field" for="ticket_number">Ticket number
                    <input type="text" class="lp-input lp-mono" id="ticket_number" name="ticket"
                           placeholder="RPR-2026-000001" required
                           pattern="RPR-\d{4}-\d{6}" title="Format: RPR-YYYY-000001">
                </label>
                <label class="lp-field" for="contact">Phone or email
                    <input type="text" class="lp-input" id="contact" name="contact" placeholder="Used on the booking" required>
                </label>
            </div>
            <button class="lp-pill lp-pill-blue" type="submit">Track repair</button>
        </form>

        <article class="lp-cell" data-lp-reveal>
            <span class="lp-kicker">Live status</span>
            <h3>Know the stage, not just “in progress”</h3>
            <div class="lp-cell-foot">
                <div class="lp-bars"><span class="on"></span><span class="on"></span><span class="on"></span><span></span><span></span></div>
                <div class="lp-bar-labels"><span>Received</span><b>Repairing</b><span>Pickup</span></div>
            </div>
        </article>

        <article class="lp-cell" data-lp-reveal>
            <span class="lp-kicker">Quotation</span>
            <h3>Approve the price before we start</h3>
            <div class="lp-cell-foot lp-quote">
                <span><small>Example total</small><b>₱3,450.00</b></span>
                <span class="lp-pill lp-pill-dark lp-pill-sm" aria-hidden="true">Approve</span>
            </div>
        </article>

        <article class="lp-cell" data-lp-reveal>
            <span class="lp-kicker">Warranty</span>
            <h3><?= (int) $warrantyDays ?> days on every repair</h3>
            <div class="lp-cell-foot lp-warranty">
                <svg viewBox="0 0 72 72" aria-hidden="true"><circle cx="36" cy="36" r="30" fill="none" stroke="#E3E5EA" stroke-width="8"/><circle cx="36" cy="36" r="30" fill="none" stroke="#1F5BFF" stroke-width="8" stroke-dasharray="132 189" stroke-linecap="round" transform="rotate(-90 36 36)"/></svg>
                <p>Starts when you pick up. File a claim against the original ticket if the problem comes back.</p>
            </div>
        </article>

        <article class="lp-cell lp-span-2" data-lp-reveal>
            <span class="lp-kicker">What we fix</span>
            <h3>Phones, laptops, tablets and wearables — down to the board.</h3>
            <ul class="lp-chips lp-cell-foot">
                <li>Screens &amp; touch</li>
                <li>Batteries</li>
                <li>Charging ports</li>
                <li>Board-level faults</li>
                <li>Keyboards</li>
                <li>Water damage</li>
                <li>Back glass &amp; cameras</li>
            </ul>
        </article>
    </section>

    <section class="lp-wrap lp-how" id="how-it-works" aria-labelledby="howTitle">
        <div class="lp-how-head" data-lp-reveal>
            <h2 id="howTitle">Four steps. One ticket. Nothing lost.</h2>
            <p>You, the technician and the front desk all look at the same ticket.</p>
        </div>
        <ol class="lp-steps">
            <li data-lp-reveal><span>01</span><h3>Book &amp; photograph</h3><p>Device details and before-repair photos of each side.</p></li>
            <li data-lp-reveal><span>02</span><h3>Diagnose &amp; quote</h3><p>A technician finds the fault and sends a price you approve.</p></li>
            <li data-lp-reveal><span>03</span><h3>Repair live</h3><p>Each stage updates your ticket the moment it changes.</p></li>
            <li data-lp-reveal><span>04</span><h3>Pickup &amp; warranty</h3><p>Collect it with a warranty you can claim against.</p></li>
        </ol>
    </section>

    <section class="lp-wrap lp-visit" id="contact" aria-labelledby="visitTitle">
        <div class="lp-visit-band">
            <div>
                <h2 id="visitTitle">Bring it in. Watch it get fixed.</h2>
                <p><?= e($shopAddress) ?> · <?= e($shopHours) ?></p>
            </div>
            <div class="lp-actions">
                <a class="lp-pill lp-pill-white lp-pill-lg" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $shopPhone)) ?>">Call <?= e($shopPhone) ?></a>
                <a class="lp-pill lp-pill-outline-white lp-pill-lg" href="mailto:<?= e($shopEmail) ?>"><?= e($shopEmail) ?></a>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
