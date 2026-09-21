<?php
/**
 * Logout
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

logout_user();
start_app_session();
flash_set('success', 'You have been signed out.');
redirect('auth/login.php');
