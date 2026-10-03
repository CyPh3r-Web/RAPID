<?php
/**
 * Customer email / SMS channel alerts (in-app remains primary).
 */

declare(strict_types=1);

function notify_ensure_settings(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    try {
        $insert = db()->prepare(
            'INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES (?, ?)'
        );
        $defaults = [
            'notify_email_enabled' => '0',
            'notify_sms_enabled' => '0',
            'notify_email_from' => (string) (get_setting('shop_email', 'support@rapid.local') ?: 'support@rapid.local'),
            'notify_sms_provider' => 'semaphore',
            'notify_sms_api_key' => '',
            'notify_sms_sender' => 'RAPID',
            'shop_name' => SHOP_NAME,
            'shop_phone' => '09101495174',
            'shop_email' => 'support@rapid.local',
            'shop_hours' => 'Mon–Sat, 9:00 AM – 6:00 PM',
            'shop_address' => 'Esposado, Cannery Site, Polomolok, South Cotabato 9505',
            'warranty_days' => (string) DEFAULT_WARRANTY_DAYS,
        ];
        foreach ($defaults as $key => $value) {
            $insert->execute([$key, $value]);
        }
        $done = true;
    } catch (Throwable $e) {
        error_log('notify_ensure_settings failed: ' . $e->getMessage());
    }
}

/**
 * @return array{email:bool,sms:bool,from:string,sms_key:string,sms_sender:string,provider:string}
 */
function notify_channel_config(): array
{
    notify_ensure_settings();
    return [
        'email' => get_setting('notify_email_enabled', '0') === '1',
        'sms' => get_setting('notify_sms_enabled', '0') === '1',
        'from' => trim((string) get_setting('notify_email_from', get_setting('shop_email', 'support@rapid.local'))),
        'sms_key' => trim((string) get_setting('notify_sms_api_key', '')),
        'sms_sender' => trim((string) get_setting('notify_sms_sender', 'RAPID')),
        'provider' => trim((string) get_setting('notify_sms_provider', 'semaphore')),
    ];
}

/**
 * Absolute URL for QR / email links.
 */
function absolute_url(string $path = ''): string
{
    $rel = url($path);
    if (preg_match('#^https?://#i', $rel)) {
        return $rel;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($https ? 'https' : 'http') . '://' . $host . $rel;
}

/**
 * @return array{id:int,email:string,phone:string,first_name:string,last_name:string}|null
 */
function get_user_contact(int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT id, email, phone, first_name, last_name FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * In-app + optional email/SMS for important customer events.
 *
 * @param 'quote_ready'|'quote_approved'|'quote_declined'|'ready_for_pickup'|'completed'|'custom' $event
 */
function notify_customer_alert(
    int $userId,
    string $event,
    string $title,
    string $message,
    ?int $ticketId = null
): void {
    create_notification($userId, $title, $message, $ticketId);

    $important = ['quote_ready', 'quote_approved', 'quote_declined', 'ready_for_pickup', 'completed'];
    if (!in_array($event, $important, true) && $event !== 'custom') {
        return;
    }

    $cfg = notify_channel_config();
    $contact = get_user_contact($userId);
    if (!$contact) {
        return;
    }

    $shop = (string) (get_setting('shop_name', SHOP_NAME) ?: SHOP_NAME);
    $body = $message . "\n\n— " . $shop . " / " . APP_NAME;

    if ($cfg['email'] && trim((string) $contact['email']) !== '') {
        notify_send_email((string) $contact['email'], $title, $body, $cfg['from']);
    }
    if ($cfg['sms'] && $cfg['sms_key'] !== '' && trim((string) ($contact['phone'] ?? '')) !== '') {
        $smsText = $title . ': ' . $message;
        if (function_exists('mb_strlen') ? mb_strlen($smsText) > 160 : strlen($smsText) > 160) {
            $smsText = (function_exists('mb_substr') ? mb_substr($smsText, 0, 157) : substr($smsText, 0, 157)) . '...';
        }
        notify_send_sms((string) $contact['phone'], $smsText, $cfg);
    }
}

function notify_send_email(string $to, string $subject, string $body, string $from): bool
{
    $to = trim($to);
    $from = trim($from);
    if ($to === '' || $from === '') {
        return false;
    }
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/plain; charset=UTF-8',
        'From: ' . $from,
        'Reply-To: ' . $from,
        'X-Mailer: RAPID',
    ];
    try {
        $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
        if (!$ok) {
            error_log('notify_send_email failed to ' . $to);
        }
        return (bool) $ok;
    } catch (Throwable $e) {
        error_log('notify_send_email exception: ' . $e->getMessage());
        return false;
    }
}

/**
 * @param array{sms_key:string,sms_sender:string,provider:string} $cfg
 */
function notify_send_sms(string $phone, string $message, array $cfg): bool
{
    $phone = preg_replace('/[^\d+]/', '', $phone) ?? '';
    if ($phone === '' || $cfg['sms_key'] === '') {
        return false;
    }

    // Normalize PH mobiles: 09XXXXXXXXX → 639XXXXXXXXX
    if (preg_match('/^0(9\d{9})$/', $phone, $m)) {
        $phone = '63' . $m[1];
    }

    $provider = strtolower((string) ($cfg['provider'] ?? 'semaphore'));
    if ($provider === 'semaphore') {
        $payload = http_build_query([
            'apikey' => $cfg['sms_key'],
            'number' => $phone,
            'message' => $message,
            'sendername' => $cfg['sms_sender'] !== '' ? $cfg['sms_sender'] : 'RAPID',
        ]);
        $url = 'https://api.semaphore.co/api/v4/messages';
        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code < 200 || $code >= 300) {
            error_log('notify_send_sms HTTP ' . $code . ' ' . (string) $resp);
            return false;
        }
        return true;
    }

    error_log('notify_send_sms unknown provider: ' . $provider);
    return false;
}

/**
 * Map ticket status / quote events to channel alerts.
 */
function notify_ticket_event(int $ticketId, string $event, string $title, string $message): void
{
    $userId = get_customer_user_id_for_ticket($ticketId);
    if (!$userId) {
        return;
    }
    notify_customer_alert($userId, $event, $title, $message, $ticketId);
}
