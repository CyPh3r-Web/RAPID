<?php
/**
 * Admin — shop, notifications, AI settings
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$user = current_user();
ai_ensure_schema();
notify_ensure_settings();

$error = '';
$section = (string) ($_POST['section'] ?? $_GET['tab'] ?? 'notify');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $section = (string) ($_POST['section'] ?? 'notify');

    if ($section === 'shop') {
        set_setting('shop_name', trim((string) ($_POST['shop_name'] ?? SHOP_NAME)));
        set_setting('shop_phone', trim((string) ($_POST['shop_phone'] ?? '')));
        set_setting('shop_email', trim((string) ($_POST['shop_email'] ?? '')));
        set_setting('shop_hours', trim((string) ($_POST['shop_hours'] ?? '')));
        set_setting('shop_address', trim((string) ($_POST['shop_address'] ?? '')));
        $days = max(1, (int) ($_POST['warranty_days'] ?? DEFAULT_WARRANTY_DAYS));
        set_setting('warranty_days', (string) $days);
        log_activity((int) $user['id'], 'shop_settings', 'Updated shop profile settings.');
        flash_set('success', 'Shop settings saved.');
        redirect('admin/settings.php?tab=shop');
    }

    if ($section === 'notify') {
        set_setting('notify_email_enabled', isset($_POST['notify_email_enabled']) ? '1' : '0');
        set_setting('notify_sms_enabled', isset($_POST['notify_sms_enabled']) ? '1' : '0');
        $from = trim((string) ($_POST['notify_email_from'] ?? ''));
        if ($from !== '') {
            set_setting('notify_email_from', $from);
        }
        set_setting('notify_sms_provider', 'semaphore');
        set_setting('notify_sms_sender', trim((string) ($_POST['notify_sms_sender'] ?? 'RAPID')));
        $smsKey = trim((string) ($_POST['notify_sms_api_key'] ?? ''));
        if ($smsKey !== '') {
            set_setting('notify_sms_api_key', $smsKey);
        }
        log_activity((int) $user['id'], 'notify_settings', 'Updated email/SMS alert settings.');
        flash_set('success', 'Notification settings saved.');
        redirect('admin/settings.php?tab=notify');
    }

    if ($section === 'ai') {
        $action = (string) ($_POST['form_action'] ?? 'save');
        $enabled = isset($_POST['ai_enabled']) ? '1' : '0';
        $model = trim((string) ($_POST['ai_gemini_model'] ?? ''));
        $newKey = trim((string) ($_POST['ai_gemini_api_key'] ?? ''));

        if ($model === '') {
            $model = ai_default_model();
        }
        if (strlen($model) > 80 || !preg_match('/^[a-zA-Z0-9._-]+$/', $model)) {
            $error = 'Enter a valid Gemini model id (letters, numbers, dots, dashes).';
        } else {
            set_setting('ai_enabled', $enabled);
            set_setting('ai_gemini_model', $model);
            if ($newKey !== '') {
                set_setting('ai_gemini_api_key', $newKey);
            }

            if ($action === 'test') {
                $ping = ai_gemini_ping();
                if (!empty($ping['ok'])) {
                    log_activity((int) $user['id'], 'ai_settings', 'Gemini connection test succeeded.');
                    flash_set('success', 'Gemini connected using ' . ($ping['model'] ?? ai_default_model()) . '.');
                    redirect('admin/settings.php?tab=ai');
                }
                $error = $ping['error'] ?? 'Connection test failed.';
            } else {
                log_activity((int) $user['id'], 'ai_settings', 'Updated AI assistant settings.');
                flash_set('success', 'AI settings saved.');
                redirect('admin/settings.php?tab=ai');
            }
        }
    }
}

$tab = (string) ($_GET['tab'] ?? ($section ?: 'notify'));
if (!in_array($tab, ['shop', 'notify', 'ai'], true)) {
    $tab = 'notify';
}

$cfg = ai_config();
$storedKey = trim((string) get_setting('ai_gemini_api_key', ''));
$keyMasked = $storedKey !== '' ? ai_mask_key($storedKey) : ($cfg['api_key'] !== '' ? ai_mask_key($cfg['api_key']) . ' (from server config)' : '');
$smsKeyStored = trim((string) get_setting('notify_sms_api_key', ''));
$smsKeyMasked = $smsKeyStored !== '' ? ai_mask_key($smsKeyStored) : '';

$pageTitle = 'Settings';
$showSidebar = true;
$navVariant = 'app';
$activeNav = 'settings';
$bodyClass = 'app-body';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <h1>Settings</h1>
            <p>Shop profile, customer email/SMS alerts, and AI assistant.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="settings-layout">
        <nav class="rapid-card settings-nav" aria-label="Settings sections">
            <?php foreach (['shop' => ['Shop profile', 'bi-shop'], 'notify' => ['Email &amp; SMS alerts', 'bi-bell'], 'ai' => ['AI suggestions', 'bi-stars']] as $key => [$label, $icon]): ?>
                <a class="sidebar-link <?= $tab === $key ? 'active' : '' ?>" href="<?= e(url('admin/settings.php?tab=' . $key)) ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>>
                    <i class="bi <?= $icon ?>" aria-hidden="true"></i><span><?= $label ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="settings-body">

        <?php if ($tab === 'notify'): ?>
            <form method="post" class="rapid-card max-w-[720px]" data-disable-on-submit>
                <?= csrf_field() ?>
                <input type="hidden" name="section" value="notify">
                <h2 class="text-sm font-semibold text-rapid mb-3">Customer email &amp; SMS</h2>
                <p class="text-sm text-rapid-muted mb-3">Sent for quotation ready, quotation approved/declined, and ready for pickup (plus in-app).</p>

                <label class="switch mb-3">
                    <input type="checkbox" id="notify_email_enabled" name="notify_email_enabled" value="1" <?= get_setting('notify_email_enabled', '0') === '1' ? 'checked' : '' ?>>
                    <span class="switch-track" aria-hidden="true"></span>
                    <span>Enable email alerts (PHP mail)</span>
                </label>
                <div class="mb-3">
                    <label class="form-label" for="notify_email_from">From email</label>
                    <input type="email" class="form-control" id="notify_email_from" name="notify_email_from"
                           value="<?= e((string) get_setting('notify_email_from', get_setting('shop_email', 'support@rapid.local'))) ?>">
                </div>

                <label class="switch mb-3">
                    <input type="checkbox" id="notify_sms_enabled" name="notify_sms_enabled" value="1" <?= get_setting('notify_sms_enabled', '0') === '1' ? 'checked' : '' ?>>
                    <span class="switch-track" aria-hidden="true"></span>
                    <span>Enable SMS alerts (Semaphore)</span>
                </label>
                <div class="mb-3">
                    <label class="form-label" for="notify_sms_api_key">Semaphore API key</label>
                    <input type="password" class="form-control" id="notify_sms_api_key" name="notify_sms_api_key" autocomplete="off"
                           placeholder="<?= $smsKeyMasked !== '' ? 'Leave blank to keep current key' : 'Paste Semaphore API key' ?>">
                    <?php if ($smsKeyMasked !== ''): ?>
                        <p class="form-text mb-0">Saved key: <?= e($smsKeyMasked) ?></p>
                    <?php else: ?>
                        <p class="form-text mb-0">Get a key at <a href="https://semaphore.co" target="_blank" rel="noopener">semaphore.co</a></p>
                    <?php endif; ?>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="notify_sms_sender">SMS sender name</label>
                    <input class="form-control" id="notify_sms_sender" name="notify_sms_sender" maxlength="11"
                           value="<?= e((string) get_setting('notify_sms_sender', 'RAPID')) ?>">
                </div>
                <button type="submit" class="btn btn-rapid-primary">Save alert settings</button>
            </form>
        <?php elseif ($tab === 'shop'): ?>
            <form method="post" class="rapid-card max-w-[720px]" data-disable-on-submit>
                <?= csrf_field() ?>
                <input type="hidden" name="section" value="shop">
                <h2 class="text-sm font-semibold text-rapid mb-3">Shop profile</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="md:col-span-2">
                        <label class="form-label" for="shop_name">Shop name</label>
                        <input class="form-control" id="shop_name" name="shop_name" value="<?= e((string) get_setting('shop_name', SHOP_NAME)) ?>">
                    </div>
                    <div>
                        <label class="form-label" for="shop_phone">Phone</label>
                        <input class="form-control" id="shop_phone" name="shop_phone" value="<?= e((string) get_setting('shop_phone', '')) ?>">
                    </div>
                    <div>
                        <label class="form-label" for="shop_email">Email</label>
                        <input type="email" class="form-control" id="shop_email" name="shop_email" value="<?= e((string) get_setting('shop_email', '')) ?>">
                    </div>
                    <div class="md:col-span-2">
                        <label class="form-label" for="shop_hours">Hours</label>
                        <input class="form-control" id="shop_hours" name="shop_hours" value="<?= e((string) get_setting('shop_hours', '')) ?>">
                    </div>
                    <div class="md:col-span-2">
                        <label class="form-label" for="shop_address">Address</label>
                        <textarea class="form-control" id="shop_address" name="shop_address" rows="2"><?= e((string) get_setting('shop_address', '')) ?></textarea>
                    </div>
                    <div>
                        <label class="form-label" for="warranty_days">Default warranty days</label>
                        <input type="number" min="1" class="form-control" id="warranty_days" name="warranty_days"
                               value="<?= e((string) get_setting('warranty_days', (string) DEFAULT_WARRANTY_DAYS)) ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-rapid-primary mt-3">Save shop settings</button>
            </form>
        <?php else: ?>
            <form method="post" class="rapid-card max-w-[720px]" data-disable-on-submit>
                <?= csrf_field() ?>
                <input type="hidden" name="section" value="ai">
                <label class="switch mb-4">
                    <input type="checkbox" id="ai_enabled" name="ai_enabled" value="1" <?= $cfg['enabled'] ? 'checked' : '' ?>>
                    <span class="switch-track" aria-hidden="true"></span>
                    <span>Enable AI repair suggestions for technicians</span>
                </label>
                <div class="mb-3">
                    <label class="form-label" for="ai_gemini_api_key">Gemini API key</label>
                    <input type="password" class="form-control" id="ai_gemini_api_key" name="ai_gemini_api_key"
                           autocomplete="off" placeholder="<?= $keyMasked !== '' ? 'Leave blank to keep current key' : 'Paste key from Google AI Studio' ?>">
                    <?php if ($keyMasked !== ''): ?>
                        <p class="form-text mb-0">Saved key: <?= e($keyMasked) ?></p>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="ai_gemini_model">Model</label>
                    <input class="form-control" id="ai_gemini_model" name="ai_gemini_model" value="<?= e($cfg['model']) ?>">
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-rapid-primary" name="form_action" value="save">Save AI settings</button>
                    <button type="submit" class="btn btn-rapid-outline" name="form_action" value="test">Test connection</button>
                </div>
            </form>
        <?php endif; ?>
        </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
