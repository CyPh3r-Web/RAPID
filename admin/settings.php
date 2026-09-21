<?php
/**
 * Admin — shop AI settings (Gemini API)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$user = current_user();
ai_ensure_schema();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

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
                redirect('admin/settings.php');
            }
            $error = $ping['error'] ?? 'Connection test failed.';
        } else {
            log_activity((int) $user['id'], 'ai_settings', 'Updated AI assistant settings.');
            flash_set('success', 'AI settings saved.');
            redirect('admin/settings.php');
        }
    }
}

$cfg = ai_config();
$storedKey = trim((string) get_setting('ai_gemini_api_key', ''));
$keyMasked = $storedKey !== '' ? ai_mask_key($storedKey) : ($cfg['api_key'] !== '' ? ai_mask_key($cfg['api_key']) . ' (from server config)' : '');

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
            <p>Connect the free Gemini API used by the technician repair assistant.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="rapid-card max-w-[720px]" data-disable-on-submit>
            <?= csrf_field() ?>

            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" id="ai_enabled" name="ai_enabled" value="1" <?= $cfg['enabled'] ? 'checked' : '' ?>>
                <label class="text-sm text-navy-800" for="ai_enabled">
                    Enable AI repair suggestions for technicians
                </label>
            </div>

            <div class="mb-3">
                <label class="form-label" for="ai_gemini_api_key">Gemini API key</label>
                <input type="password" class="form-control" id="ai_gemini_api_key" name="ai_gemini_api_key"
                       autocomplete="off" placeholder="<?= $keyMasked !== '' ? 'Leave blank to keep current key' : 'Paste key from Google AI Studio' ?>">
                <?php if ($keyMasked !== ''): ?>
                    <p class="form-text mb-0">Saved key: <?= e($keyMasked) ?></p>
                <?php else: ?>
                    <p class="form-text mb-0">
                        Free key (no credit card):
                        <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">aistudio.google.com/apikey</a>
                    </p>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label" for="ai_gemini_model">Model</label>
                <input class="form-control" id="ai_gemini_model" name="ai_gemini_model"
                       value="<?= e($cfg['model']) ?>" placeholder="gemini-flash-lite-latest">
                <p class="form-text mb-0">
                    Use <code>gemini-flash-lite-latest</code> on the free tier. Full Flash models often return “high demand”.
                    RAPID will automatically try other Lite models if one is busy.
                </p>
            </div>

            <div class="alert alert-info mb-4">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <div>
                    Suggestions are generated from the ticket’s device, problem, photos, and similar RAPID jobs.
                    Customer name, phone, email, address, IMEI, and serial are not sent to Gemini.
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn btn-rapid-primary" name="form_action" value="save">Save settings</button>
                <button type="submit" class="btn btn-rapid-outline" name="form_action" value="test">Test connection</button>
            </div>
        </form>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
