<?php
/**
 * Closing layout scripts
 */
?>
<footer class="rapid-footer">
    <div class="<?= !empty($showSidebar) ? 'container-fluid-rapid' : 'container-rapid' ?>">
        <div class="flex flex-col md:flex-row justify-between gap-2">
            <div>
                <strong><?= e(APP_NAME) ?></strong>
                <span> · <?= e(APP_SUBTITLE) ?></span>
            </div>
            <div class="text-sm">&copy; <?= date('Y') ?> <?= e(SHOP_NAME) ?></div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
window.RAPID = {
  baseUrl: <?= json_encode(BASE_URL, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
  csrf: <?= json_encode(is_logged_in() ? csrf_token() : '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};
</script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php if (!empty($bodyClass) && str_contains((string) $bodyClass, 'landing-page')): ?>
<script src="<?= e(asset('js/landing.js')) ?>"></script>
<?php endif; ?>
<?php if (!empty($pageScripts) && is_array($pageScripts)): ?>
    <?php foreach ($pageScripts as $src): ?>
<script src="<?= e(asset($src)) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
