<?php
/**
 * Sign in / Register switcher. Expects $authTab = 'login' | 'register'.
 */
declare(strict_types=1);
$authTab = $authTab ?? 'login';
?>
<nav class="auth-tabs" aria-label="Account">
    <a href="<?= e(url('auth/login.php')) ?>" class="<?= $authTab === 'login' ? 'is-active' : '' ?>"<?= $authTab === 'login' ? ' aria-current="page"' : '' ?>>Sign in</a>
    <a href="<?= e(url('auth/register.php')) ?>" class="<?= $authTab === 'register' ? 'is-active' : '' ?>"<?= $authTab === 'register' ? ' aria-current="page"' : '' ?>>Create account</a>
</nav>
