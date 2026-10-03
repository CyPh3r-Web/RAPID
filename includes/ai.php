<?php
/**
 * Gemini-backed repair assistant (technician suggestions).
 * Sends device/problem context only — never customer PII.
 */

declare(strict_types=1);

const AI_KIND_TECHNICIAN_REPAIR = 'technician_repair';

function ai_default_model(): string
{
    return defined('GEMINI_MODEL') && GEMINI_MODEL !== '' ? GEMINI_MODEL : 'gemini-flash-lite-latest';
}

function ai_ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    try {
        $pdo = db();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `ai_suggestions` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `ticket_id` INT UNSIGNED NOT NULL,
              `kind` VARCHAR(40) NOT NULL DEFAULT 'technician_repair',
              `context_hash` CHAR(64) NOT NULL,
              `payload_json` MEDIUMTEXT NOT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_ai_ticket_kind` (`ticket_id`, `kind`),
              KEY `idx_ai_kind` (`kind`),
              CONSTRAINT `fk_ai_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `repair_tickets` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $insert = $pdo->prepare(
            'INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES (?, ?)'
        );
        $insert->execute(['ai_enabled', '1']);
        $insert->execute(['ai_gemini_model', ai_default_model()]);
        $insert->execute(['ai_gemini_api_key', '']);

        $currentModel = trim((string) get_setting('ai_gemini_model', ''));
        $preferLite = [
            'gemini-2.5-flash',
            'gemini-2.0-flash',
            'gemini-1.5-flash',
            'gemini-pro',
            'gemini-1.5-pro',
            'gemini-flash-latest',
        ];
        if ($currentModel === '' || in_array($currentModel, $preferLite, true)) {
            set_setting('ai_gemini_model', ai_default_model());
        }
        $done = true;
    } catch (Throwable $e) {
        error_log('ai_ensure_schema failed: ' . $e->getMessage());
    }
}

/**
 * @return array{enabled:bool, api_key:string, model:string}
 */
function ai_config(): array
{
    $key = trim((string) get_setting('ai_gemini_api_key', ''));
    if ($key === '' && defined('GEMINI_API_KEY')) {
        $key = trim((string) GEMINI_API_KEY);
    }

    $model = trim((string) get_setting('ai_gemini_model', ''));
    if ($model === '') {
        $model = ai_default_model();
    }

    $enabled = get_setting('ai_enabled', '1') === '1';

    return [
        'enabled' => $enabled,
        'api_key' => $key,
        'model' => $model,
    ];
}

function ai_is_configured(): bool
{
    $cfg = ai_config();
    return $cfg['enabled'] && $cfg['api_key'] !== '';
}

function ai_mask_key(string $key): string
{
    $key = trim($key);
    $len = strlen($key);
    if ($len === 0) {
        return '';
    }
    if ($len <= 8) {
        return str_repeat('•', $len);
    }
    return substr($key, 0, 4) . str_repeat('•', max(4, $len - 8)) . substr($key, -4);
}

/**
 * @param array<string,mixed> $ticket
 * @param array<int,array<string,mixed>> $mediaRows
 * @param array<string,mixed>|null $diagnosisRow
 */
function ai_ticket_context_hash(array $ticket, array $mediaRows = [], ?array $diagnosisRow = null): string
{
    $mediaIds = [];
    foreach ($mediaRows as $m) {
        $mediaIds[] = (int) ($m['id'] ?? 0);
    }
    sort($mediaIds);

    $payload = [
        (string) ($ticket['device_type'] ?? ''),
        (string) ($ticket['brand'] ?? ''),
        (string) ($ticket['model'] ?? ''),
        (string) ($ticket['color'] ?? ''),
        (string) ($ticket['accessories'] ?? ''),
        (string) ($ticket['physical_condition'] ?? ''),
        (string) ($ticket['problem_description'] ?? ''),
        (string) ($ticket['current_status'] ?? ''),
        (string) ($ticket['priority'] ?? ''),
        (string) ($diagnosisRow['diagnosis'] ?? ''),
        (string) ($diagnosisRow['recommended_action'] ?? ''),
        implode(',', $mediaIds),
    ];

    return hash('sha256', implode("\n", $payload));
}

/**
 * @return array{payload:array, context_hash:string, created_at:?string}|null
 */
function ai_get_cached_suggestion(int $ticketId, string $kind = AI_KIND_TECHNICIAN_REPAIR): ?array
{
    ai_ensure_schema();
    try {
        $stmt = db()->prepare(
            'SELECT payload_json, context_hash, created_at
             FROM ai_suggestions WHERE ticket_id = ? AND kind = ? LIMIT 1'
        );
        $stmt->execute([$ticketId, $kind]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $payload = json_decode((string) $row['payload_json'], true);
        if (!is_array($payload)) {
            return null;
        }
        return [
            'payload' => $payload,
            'context_hash' => (string) $row['context_hash'],
            'created_at' => $row['created_at'] ?? null,
        ];
    } catch (Throwable $e) {
        error_log('ai_get_cached_suggestion failed: ' . $e->getMessage());
        return null;
    }
}

function ai_store_suggestion(int $ticketId, string $kind, string $contextHash, array $payload): void
{
    ai_ensure_schema();
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return;
    }
    $stmt = db()->prepare(
        'INSERT INTO ai_suggestions (ticket_id, kind, context_hash, payload_json)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE context_hash = VALUES(context_hash), payload_json = VALUES(payload_json)'
    );
    $stmt->execute([$ticketId, $kind, $contextHash, $json]);
}

/**
 * @return array{ok:bool, cached?:bool, stale?:bool, suggestion?:array, generated_at?:?string, error?:string}
 */
function ai_suggest_technician_repair(int $ticketId, bool $force = false): array
{
    ai_ensure_schema();

    $ticket = get_ticket_full($ticketId);
    if (!$ticket) {
        return ['ok' => false, 'error' => 'Ticket not found.'];
    }

    $mediaStmt = db()->prepare(
        'SELECT id, file_name, file_path, file_type, media_category
         FROM device_media WHERE ticket_id = ? ORDER BY uploaded_at ASC, id ASC'
    );
    $mediaStmt->execute([$ticketId]);
    $mediaRows = $mediaStmt->fetchAll();

    $diagStmt = db()->prepare(
        'SELECT diagnosis, recommended_action FROM diagnoses WHERE ticket_id = ? ORDER BY id DESC LIMIT 1'
    );
    $diagStmt->execute([$ticketId]);
    $diagnosisRow = $diagStmt->fetch() ?: null;

    $hash = ai_ticket_context_hash($ticket, $mediaRows, $diagnosisRow);
    $cached = ai_get_cached_suggestion($ticketId, AI_KIND_TECHNICIAN_REPAIR);

    if (!$force && $cached && hash_equals($cached['context_hash'], $hash)) {
        return [
            'ok' => true,
            'cached' => true,
            'stale' => false,
            'suggestion' => ai_enrich_suggested_parts($cached['payload']),
            'generated_at' => $cached['created_at'],
        ];
    }

    if (!ai_is_configured()) {
        return [
            'ok' => false,
            'error' => 'AI is not configured. An admin must add a free Gemini API key under Settings.',
        ];
    }

    $similar = ai_similar_repairs($ticket, $ticketId);
    $images = ai_ticket_images_for_prompt($mediaRows, 2);
    $catalog = list_parts_catalog(true);
    $userText = ai_build_technician_user_prompt($ticket, $diagnosisRow, $similar, count($images), $catalog);

    $parts = [['text' => $userText]];
    foreach ($images as $img) {
        $parts[] = [
            'inlineData' => [
                'mimeType' => $img['mime'],
                'data' => $img['data'],
            ],
        ];
    }

    $result = ai_gemini_generate($parts, ai_technician_system_prompt());
    if (!$result['ok']) {
        if ($cached) {
            return [
                'ok' => true,
                'cached' => true,
                'stale' => $cached['context_hash'] !== $hash,
                'suggestion' => ai_enrich_suggested_parts($cached['payload']),
                'generated_at' => $cached['created_at'],
                'error' => $result['error'],
            ];
        }
        return $result;
    }

    $normalized = ai_enrich_suggested_parts(ai_normalize_technician_payload($result['data']));
    ai_store_suggestion($ticketId, AI_KIND_TECHNICIAN_REPAIR, $hash, $normalized);

    return [
        'ok' => true,
        'cached' => false,
        'stale' => false,
        'suggestion' => $normalized,
        'generated_at' => date('Y-m-d H:i:s'),
    ];
}

function ai_technician_system_prompt(): string
{
    return <<<'TXT'
You are a senior electronics repair technician assisting shop staff at RAPID Device Care in the Philippines.
Given the live ticket facts, suggest how to diagnose and repair the device.

Rules:
- You are an assistant, not the final diagnosis. The technician must verify everything on the actual unit.
- Never invent prices, part SKUs, serial numbers, or claim you inspected the device in person.
- Do not mention the customer by name. Do not ask for personal data.
- If photos are attached, comment only on what is visible.
- Put safety first: swollen batteries, liquid, smoke, fire, or cracked glass that could cut.
- Prefer practical shop tests (visual, continuity, known-good charger, board-level only if justified).
- Write draft_diagnosis and draft_recommended_action as concise notes the technician can paste into RAPID.
- suggested_parts are optional quotation hints only — list parts likely needed if the top causes are confirmed. Prefer matching the shop catalog when a close item exists (use that catalog_part_id and exact catalog name). Never invent prices.
- Return JSON only, matching this shape:
{
  "summary": "one sentence",
  "safety_notes": ["..."],
  "likely_causes": [{"cause":"...","likelihood":"high|medium|low","why":"..."}],
  "tests": [{"step":1,"action":"...","looking_for":"..."}],
  "parts_to_check": ["..."],
  "suggested_parts": [{"name":"...","reason":"...","quantity":1,"catalog_part_id":null}],
  "draft_diagnosis": "...",
  "draft_recommended_action": "..."
}
TXT;
}

/**
 * @param array<string,mixed> $ticket
 * @param array<string,mixed>|null $diagnosisRow
 * @param array<int,array<string,string>> $similar
 * @param array<int,array<string,mixed>> $catalog
 */
function ai_build_technician_user_prompt(
    array $ticket,
    ?array $diagnosisRow,
    array $similar,
    int $photoCount,
    array $catalog = []
): string {
    $lines = [
        'Current RAPID ticket (no customer identity):',
        'Device type: ' . ai_clip((string) ($ticket['device_type'] ?? ''), 100),
        'Brand: ' . ai_clip((string) ($ticket['brand'] ?? ''), 100),
        'Model: ' . ai_clip((string) ($ticket['model'] ?? ''), 150),
        'Color: ' . ai_clip((string) ($ticket['color'] ?? 'not specified'), 50),
        'Accessories received: ' . ai_clip((string) ($ticket['accessories'] ?? 'none listed'), 300),
        'Physical condition: ' . ai_clip((string) ($ticket['physical_condition'] ?? 'not specified'), 500),
        'Reported problem: ' . ai_clip((string) ($ticket['problem_description'] ?? ''), 2000),
        'Ticket status: ' . ai_clip((string) ($ticket['current_status'] ?? ''), 40),
        'Priority: ' . ai_clip((string) ($ticket['priority'] ?? ''), 20),
        'Photos attached: ' . $photoCount,
    ];

    if ($diagnosisRow && trim((string) ($diagnosisRow['diagnosis'] ?? '')) !== '') {
        $lines[] = 'Technician notes already saved: ' . ai_clip((string) $diagnosisRow['diagnosis'], 1500);
        if (trim((string) ($diagnosisRow['recommended_action'] ?? '')) !== '') {
            $lines[] = 'Recommended action already saved: ' . ai_clip((string) $diagnosisRow['recommended_action'], 800);
        }
    }

    if ($similar) {
        $lines[] = '';
        $lines[] = 'Similar past RAPID jobs (shop history — use as hints, not proof):';
        foreach ($similar as $i => $job) {
            $n = $i + 1;
            $lines[] = $n . '. ' . $job['device'] . ' — problem: ' . $job['problem'] . ' — diagnosis: ' . $job['diagnosis'] . ' — action: ' . $job['action'];
        }
    }

    if ($catalog) {
        $lines[] = '';
        $lines[] = 'Shop parts catalog (prefer these when suggesting quotation parts; use catalog_part_id when it clearly fits):';
        $limit = 40;
        foreach (array_slice($catalog, 0, $limit) as $part) {
            $bit = '- id=' . (int) $part['id'];
            if (!empty($part['sku'])) {
                $bit .= ' sku=' . ai_clip((string) $part['sku'], 40);
            }
            $bit .= ' | ' . ai_clip((string) $part['name'], 120);
            if (!empty($part['category'])) {
                $bit .= ' | ' . ai_clip((string) $part['category'], 40);
            }
            $bit .= ' | list_price=₱' . number_format((float) $part['unit_price'], 2, '.', '');
            $lines[] = $bit;
        }
        if (count($catalog) > $limit) {
            $lines[] = '... and ' . (count($catalog) - $limit) . ' more catalog items not listed.';
        }
    }

    $lines[] = '';
    $lines[] = 'Suggest ordered tests, optional parts for quotation (if causes are confirmed), and a repair approach for this specific unit.';

    return implode("\n", $lines);
}

/**
 * @param array<string,mixed> $ticket
 * @return array<int,array{device:string,problem:string,diagnosis:string,action:string}>
 */
function ai_similar_repairs(array $ticket, int $excludeTicketId, int $limit = 5): array
{
    $brand = trim((string) ($ticket['brand'] ?? ''));
    $model = trim((string) ($ticket['model'] ?? ''));
    $type = trim((string) ($ticket['device_type'] ?? ''));

    if ($brand === '' && $model === '' && $type === '') {
        return [];
    }

    try {
        $stmt = db()->prepare(
            "SELECT d.device_type, d.brand, d.model,
                    LEFT(rt.problem_description, 280) AS problem_description,
                    LEFT(diag.diagnosis, 280) AS diagnosis,
                    LEFT(diag.recommended_action, 220) AS recommended_action
             FROM diagnoses diag
             INNER JOIN repair_tickets rt ON rt.id = diag.ticket_id
             INNER JOIN devices d ON d.id = rt.device_id
             WHERE rt.id <> ?
               AND rt.current_status IN ('repairing','ready_for_pickup','completed')
               AND (
                    (d.brand = ? AND d.model = ?)
                    OR (d.brand = ? AND d.device_type = ?)
                    OR d.model = ?
                    OR d.device_type = ?
               )
             ORDER BY
               CASE
                 WHEN d.brand = ? AND d.model = ? THEN 0
                 WHEN d.brand = ? THEN 1
                 ELSE 2
               END,
               diag.id DESC
             LIMIT " . (int) $limit
        );
        $stmt->execute([
            $excludeTicketId,
            $brand,
            $model,
            $brand,
            $type,
            $model,
            $type,
            $brand,
            $model,
            $brand,
        ]);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[] = [
                'device' => trim($row['brand'] . ' ' . $row['model'] . ' (' . $row['device_type'] . ')'),
                'problem' => ai_clip((string) $row['problem_description'], 280),
                'diagnosis' => ai_clip((string) $row['diagnosis'], 280),
                'action' => ai_clip((string) ($row['recommended_action'] ?? 'n/a'), 220),
            ];
        }
        return $out;
    } catch (Throwable $e) {
        error_log('ai_similar_repairs failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * @param array<int,array<string,mixed>> $mediaRows
 * @return array<int,array{mime:string,data:string}>
 */
function ai_ticket_images_for_prompt(array $mediaRows, int $max = 2): array
{
    $out = [];
    foreach ($mediaRows as $m) {
        if (count($out) >= $max) {
            break;
        }
        $mime = strtolower((string) ($m['file_type'] ?? ''));
        if (strpos($mime, 'image/') !== 0) {
            continue;
        }
        $abs = ai_resolve_media_path((string) ($m['file_path'] ?? ''));
        if ($abs === null) {
            continue;
        }
        $encoded = ai_image_to_jpeg_base64($abs);
        if ($encoded === null) {
            continue;
        }
        $out[] = $encoded;
    }
    return $out;
}

function ai_resolve_media_path(string $relativePath): ?string
{
    $relativePath = str_replace('\\', '/', $relativePath);
    $relativePath = ltrim($relativePath, '/');
    if (!preg_match('#^uploads/(device|repairs)/[A-Za-z0-9._-]+$#', $relativePath)) {
        return null;
    }
    $abs = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $realRoot = realpath(ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads');
    $realFile = realpath($abs);
    if ($realRoot === false || $realFile === false || !is_file($realFile)) {
        return null;
    }
    if (strpos($realFile, $realRoot) !== 0) {
        return null;
    }
    return $realFile;
}

/**
 * @return array{mime:string,data:string}|null
 */
function ai_image_to_jpeg_base64(string $absPath, int $maxEdge = 768): ?array
{
    $info = @getimagesize($absPath);
    if ($info === false) {
        return null;
    }
    $srcMime = strtolower((string) ($info['mime'] ?? ''));
    $w = (int) ($info[0] ?? 0);
    $h = (int) ($info[1] ?? 0);
    if ($w < 1 || $h < 1) {
        return null;
    }

    if (!function_exists('imagecreatetruecolor')) {
        $raw = @file_get_contents($absPath);
        if ($raw === false || strlen($raw) > 1500000) {
            return null;
        }
        $mime = in_array($srcMime, ['image/jpeg', 'image/png', 'image/webp'], true) ? $srcMime : 'image/jpeg';
        return ['mime' => $mime, 'data' => base64_encode($raw)];
    }

    $src = null;
    if ($srcMime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
        $src = @imagecreatefromjpeg($absPath);
    } elseif ($srcMime === 'image/png' && function_exists('imagecreatefrompng')) {
        $src = @imagecreatefrompng($absPath);
    } elseif ($srcMime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $src = @imagecreatefromwebp($absPath);
    }
    if (!$src) {
        return null;
    }

    $scale = min(1, $maxEdge / max($w, $h));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    if ($dst === false) {
        imagedestroy($src);
        return null;
    }
    $white = imagecolorallocate($dst, 255, 255, 255);
    if ($white !== false) {
        imagefilledrectangle($dst, 0, 0, $nw, $nh, $white);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    ob_start();
    imagejpeg($dst, null, 72);
    $bin = ob_get_clean();
    imagedestroy($dst);

    if (!is_string($bin) || $bin === '') {
        return null;
    }

    return ['mime' => 'image/jpeg', 'data' => base64_encode($bin)];
}

/**
 * @return array<int,string>
 */
function ai_model_candidates(string $preferred): array
{
    $preferred = preg_replace('/[^a-zA-Z0-9._-]/', '', $preferred) ?: ai_default_model();
    $fallbacks = [
        'gemini-flash-lite-latest',
        'gemini-3.1-flash-lite',
        'gemini-3.5-flash-lite',
        'gemini-2.5-flash-lite',
        'gemini-flash-latest',
    ];
    $out = [$preferred];
    foreach ($fallbacks as $model) {
        if ($model !== '' && !in_array($model, $out, true)) {
            $out[] = $model;
        }
    }
    return $out;
}

/**
 * Tiny generateContent call used by Admin → Settings → Test connection.
 *
 * @return array{ok:bool, model?:string, error?:string}
 */
function ai_gemini_ping(): array
{
    $parts = [['text' => 'Reply with JSON only: {"ok":true}']];
    $result = ai_gemini_generate($parts, 'You are a connection test. Return {"ok":true} as JSON.');
    if (!$result['ok']) {
        return $result;
    }
    return ['ok' => true, 'model' => (string) get_setting('ai_gemini_model', ai_default_model())];
}

/**
 * @param array<int,array<string,mixed>> $parts
 * @return array{ok:bool, data?:array, error?:string}
 */
function ai_gemini_generate(array $parts, string $systemPrompt): array
{
    $cfg = ai_config();
    if ($cfg['api_key'] === '') {
        return ['ok' => false, 'error' => 'AI is not configured. An admin must add a free Gemini API key under Settings.'];
    }

    $payload = [
        'systemInstruction' => [
            'parts' => [['text' => $systemPrompt]],
        ],
        'contents' => [
            [
                'role' => 'user',
                'parts' => $parts,
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.35,
            'responseMimeType' => 'application/json',
        ],
    ];

    $lastError = 'Could not reach the AI service.';
    $lastStatus = 0;

    foreach (ai_model_candidates($cfg['model']) as $model) {
        $url = rtrim(defined('GEMINI_API_URL') ? GEMINI_API_URL : 'https://generativelanguage.googleapis.com/v1beta/models', '/')
            . '/' . rawurlencode($model) . ':generateContent';

        $http = ai_http_post_json($url, $payload, 55, [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $cfg['api_key'],
        ]);
        if (!$http['ok']) {
            $lastError = $http['error'] ?? $lastError;
            continue;
        }

        $status = (int) ($http['status'] ?? 0);
        $body = $http['json'] ?? null;
        $lastStatus = $status;
        $msg = ai_gemini_error_message($body);

        if ($status === 403 || $status === 401) {
            return ['ok' => false, 'error' => $msg ?: 'Gemini rejected the API key. Create a Gemini API key in Google AI Studio (not Vertex / Agent Platform) and save it in Settings.'];
        }
        if ($status === 404) {
            $lastError = $msg ?: ('Model ' . $model . ' is not available on this free key.');
            continue;
        }
        if (ai_is_capacity_error($status, $msg)) {
            $lastError = 'Gemini is busy on the free tier. RAPID will try another Flash-Lite model.';
            usleep(400000);
            continue;
        }
        if ($status < 200 || $status >= 300) {
            $lastError = $msg ?? ('AI service returned HTTP ' . $status . '.');
            continue;
        }

        $text = ai_extract_gemini_text($body);
        if ($text === '') {
            $block = is_array($body) ? ($body['promptFeedback']['blockReason'] ?? ($body['candidates'][0]['finishReason'] ?? '')) : '';
            if (is_string($block) && $block !== '' && strtoupper($block) !== 'STOP') {
                return ['ok' => false, 'error' => 'The AI could not produce a suggestion for this ticket.'];
            }
            $lastError = 'The AI returned an empty response.';
            continue;
        }

        $data = ai_decode_model_json($text);
        if (!is_array($data)) {
            $lastError = 'The AI response could not be read. Try again.';
            continue;
        }

        if ($model !== $cfg['model']) {
            set_setting('ai_gemini_model', $model);
        }

        return ['ok' => true, 'data' => $data];
    }

    $hint = '';
    if ($lastStatus === 404) {
        $hint = ' Google retired the saved model for new free-tier keys.';
    } elseif (ai_is_capacity_error($lastStatus, $lastError)) {
        $hint = ' Wait 20–30 seconds and click Suggest again.';
    }
    return ['ok' => false, 'error' => trim($lastError . $hint)];
}

function ai_is_capacity_error(int $status, ?string $message): bool
{
    if (in_array($status, [429, 503, 502, 504], true)) {
        return true;
    }
    if (!is_string($message) || $message === '') {
        return false;
    }
    $hay = strtolower($message);
    return strpos($hay, 'high demand') !== false
        || strpos($hay, 'overloaded') !== false
        || strpos($hay, 'unavailable') !== false
        || strpos($hay, 'resource_exhausted') !== false
        || strpos($hay, 'try again later') !== false;
}

function ai_gemini_error_message($body): ?string
{
    if (!is_array($body)) {
        return null;
    }
    $msg = $body['error']['message'] ?? null;
    return is_string($msg) && $msg !== '' ? $msg : null;
}

function ai_extract_gemini_text($body): string
{
    if (!is_array($body)) {
        return '';
    }
    $parts = $body['candidates'][0]['content']['parts'] ?? [];
    if (!is_array($parts)) {
        return '';
    }
    $chunks = [];
    foreach ($parts as $part) {
        if (isset($part['text']) && is_string($part['text'])) {
            $chunks[] = $part['text'];
        }
    }
    return trim(implode("\n", $chunks));
}

function ai_decode_model_json(string $text): ?array
{
    $text = trim($text);
    if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $text, $m)) {
        $text = trim($m[1]);
    }
    $decoded = json_decode($text, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * @return array{
 *   summary:string,
 *   safety_notes:array<int,string>,
 *   likely_causes:array<int,array{cause:string,likelihood:string,why:string}>,
 *   tests:array<int,array{step:int,action:string,looking_for:string}>,
 *   parts_to_check:array<int,string>,
 *   suggested_parts:array<int,array{name:string,reason:string,quantity:float,catalog_part_id:?int}>,
 *   draft_diagnosis:string,
 *   draft_recommended_action:string
 * }
 */
function ai_normalize_technician_payload(array $raw): array
{
    $causes = [];
    foreach (ai_as_list($raw['likely_causes'] ?? []) as $item) {
        if (is_string($item)) {
            $causes[] = [
                'cause' => ai_clip($item, 220),
                'likelihood' => 'medium',
                'why' => '',
            ];
            continue;
        }
        if (!is_array($item)) {
            continue;
        }
        $likelihood = strtolower((string) ($item['likelihood'] ?? $item['confidence'] ?? 'medium'));
        if (!in_array($likelihood, ['high', 'medium', 'low'], true)) {
            $likelihood = 'medium';
        }
        $cause = ai_clip((string) ($item['cause'] ?? $item['title'] ?? ''), 220);
        if ($cause === '') {
            continue;
        }
        $causes[] = [
            'cause' => $cause,
            'likelihood' => $likelihood,
            'why' => ai_clip((string) ($item['why'] ?? $item['reason'] ?? ''), 400),
        ];
    }

    $tests = [];
    $stepNo = 1;
    foreach (ai_as_list($raw['tests'] ?? $raw['steps'] ?? []) as $item) {
        if (is_string($item)) {
            $tests[] = [
                'step' => $stepNo++,
                'action' => ai_clip($item, 400),
                'looking_for' => '',
            ];
            continue;
        }
        if (!is_array($item)) {
            continue;
        }
        $action = ai_clip((string) ($item['action'] ?? $item['test'] ?? $item['step_text'] ?? ''), 400);
        if ($action === '') {
            continue;
        }
        $tests[] = [
            'step' => (int) ($item['step'] ?? $stepNo),
            'action' => $action,
            'looking_for' => ai_clip((string) ($item['looking_for'] ?? $item['expect'] ?? ''), 300),
        ];
        $stepNo++;
    }

    $safety = [];
    foreach (ai_as_list($raw['safety_notes'] ?? []) as $note) {
        if (is_string($note) && trim($note) !== '') {
            $safety[] = ai_clip($note, 400);
        }
    }

    $parts = [];
    foreach (ai_as_list($raw['parts_to_check'] ?? []) as $part) {
        if (is_string($part) && trim($part) !== '') {
            $parts[] = ai_clip($part, 180);
        }
    }

    $suggested = [];
    foreach (ai_as_list($raw['suggested_parts'] ?? []) as $item) {
        if (is_string($item)) {
            $name = ai_clip($item, 180);
            if ($name === '') {
                continue;
            }
            $suggested[] = [
                'name' => $name,
                'reason' => '',
                'quantity' => 1.0,
                'catalog_part_id' => null,
            ];
            continue;
        }
        if (!is_array($item)) {
            continue;
        }
        $name = ai_clip((string) ($item['name'] ?? $item['part'] ?? $item['description'] ?? ''), 180);
        if ($name === '') {
            continue;
        }
        $qty = (float) ($item['quantity'] ?? $item['qty'] ?? 1);
        if ($qty <= 0) {
            $qty = 1.0;
        }
        $catalogId = (int) ($item['catalog_part_id'] ?? $item['part_id'] ?? 0);
        $suggested[] = [
            'name' => $name,
            'reason' => ai_clip((string) ($item['reason'] ?? $item['why'] ?? ''), 300),
            'quantity' => round($qty, 2),
            'catalog_part_id' => $catalogId > 0 ? $catalogId : null,
        ];
    }

    // Older / sparse responses: promote inspection strings into soft suggestions
    if (!$suggested && $parts) {
        foreach (array_slice($parts, 0, 6) as $partName) {
            $suggested[] = [
                'name' => $partName,
                'reason' => 'Listed under parts/areas to check.',
                'quantity' => 1.0,
                'catalog_part_id' => null,
            ];
        }
    }

    return [
        'summary' => ai_clip((string) ($raw['summary'] ?? ''), 400),
        'safety_notes' => array_slice($safety, 0, 8),
        'likely_causes' => array_slice($causes, 0, 6),
        'tests' => array_slice($tests, 0, 10),
        'parts_to_check' => array_slice($parts, 0, 10),
        'suggested_parts' => array_slice($suggested, 0, 8),
        'draft_diagnosis' => ai_clip((string) ($raw['draft_diagnosis'] ?? ''), 2000),
        'draft_recommended_action' => ai_clip((string) ($raw['draft_recommended_action'] ?? ''), 1200),
    ];
}

/**
 * Match AI part hints to the live shop catalog (never invent prices).
 *
 * @param array<string,mixed> $payload
 * @return array<string,mixed>
 */
function ai_enrich_suggested_parts(array $payload): array
{
    if (!function_exists('list_parts_catalog')) {
        return $payload;
    }

    $rawSuggested = ai_as_list($payload['suggested_parts'] ?? []);
    if (!$rawSuggested) {
        $fallback = [];
        foreach (ai_as_list($payload['parts_to_check'] ?? []) as $partName) {
            if (is_string($partName) && trim($partName) !== '') {
                $fallback[] = [
                    'name' => ai_clip($partName, 180),
                    'reason' => 'Listed under parts/areas to check.',
                    'quantity' => 1.0,
                    'catalog_part_id' => null,
                ];
            }
        }
        $rawSuggested = $fallback;
    }

    $catalog = list_parts_catalog(true);
    $byId = [];
    foreach ($catalog as $part) {
        $byId[(int) $part['id']] = $part;
    }

    $enriched = [];
    foreach ($rawSuggested as $item) {
        if (is_string($item)) {
            $item = [
                'name' => $item,
                'reason' => '',
                'quantity' => 1.0,
                'catalog_part_id' => null,
            ];
        }
        if (!is_array($item)) {
            continue;
        }

        $name = ai_clip((string) ($item['name'] ?? ''), 180);
        if ($name === '') {
            continue;
        }
        $qty = (float) ($item['quantity'] ?? 1);
        if ($qty <= 0) {
            $qty = 1.0;
        }
        $reason = ai_clip((string) ($item['reason'] ?? ''), 300);
        $hintId = (int) ($item['catalog_part_id'] ?? $item['part_id'] ?? 0);

        $matched = null;
        if ($hintId > 0 && isset($byId[$hintId])) {
            $matched = $byId[$hintId];
        } else {
            $matched = ai_match_catalog_part($name, $catalog);
        }

        $row = [
            'name' => $matched ? (string) $matched['name'] : $name,
            'reason' => $reason,
            'quantity' => round($qty, 2),
            'part_id' => $matched ? (int) $matched['id'] : null,
            'unit_price' => $matched ? round((float) $matched['unit_price'], 2) : null,
            'sku' => $matched && !empty($matched['sku']) ? (string) $matched['sku'] : null,
            'matched' => $matched !== null,
        ];
        $enriched[] = $row;
    }

    $payload['suggested_parts'] = array_slice($enriched, 0, 8);
    return $payload;
}

/**
 * @param array<int,array<string,mixed>> $catalog
 * @return array<string,mixed>|null
 */
function ai_match_catalog_part(string $needle, array $catalog): ?array
{
    $needleNorm = ai_normalize_part_key($needle);
    if ($needleNorm === '') {
        return null;
    }

    $best = null;
    $bestScore = 0.0;

    foreach ($catalog as $part) {
        $nameNorm = ai_normalize_part_key((string) ($part['name'] ?? ''));
        $skuNorm = ai_normalize_part_key((string) ($part['sku'] ?? ''));
        if ($nameNorm === '') {
            continue;
        }

        if ($needleNorm === $nameNorm || ($skuNorm !== '' && $needleNorm === $skuNorm)) {
            return $part;
        }

        $score = 0.0;
        if ($skuNorm !== '' && (strpos($needleNorm, $skuNorm) !== false || strpos($skuNorm, $needleNorm) !== false)) {
            $score = 0.92;
        } elseif (strpos($nameNorm, $needleNorm) !== false || strpos($needleNorm, $nameNorm) !== false) {
            $score = 0.88;
        } else {
            similar_text($needleNorm, $nameNorm, $percent);
            $score = $percent / 100;
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $part;
        }
    }

    return $bestScore >= 0.72 ? $best : null;
}

function ai_normalize_part_key(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
}

/**
 * @param mixed $value
 * @return array<int,mixed>
 */
function ai_as_list($value): array
{
    if (!is_array($value)) {
        return [];
    }
    return array_values($value);
}

function ai_clip(string $value, int $max): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    if (function_exists('mb_strlen') && mb_strlen($value) > $max) {
        return mb_substr($value, 0, $max);
    }
    if (strlen($value) > $max) {
        return substr($value, 0, $max);
    }
    return $value;
}

/**
 * CA bundle for HTTPS (WAMP Apache often has curl.cainfo empty).
 */
function ai_ca_bundle_path(): ?string
{
    $candidates = [];
    if (defined('ROOT_PATH')) {
        $candidates[] = ROOT_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'cacert.pem';
    }
    $iniCainfo = trim((string) ini_get('curl.cainfo'));
    $iniCafile = trim((string) ini_get('openssl.cafile'));
    if ($iniCainfo !== '') {
        $candidates[] = $iniCainfo;
    }
    if ($iniCafile !== '') {
        $candidates[] = $iniCafile;
    }

    foreach ($candidates as $path) {
        if (is_string($path) && $path !== '' && is_file($path) && is_readable($path)) {
            return $path;
        }
    }
    return null;
}

function ai_friendly_curl_error(string $err): string
{
    $lower = strtolower($err);
    if (strpos($lower, 'ssl') !== false || strpos($lower, 'certificate') !== false || strpos($lower, 'ca file') !== false) {
        return 'HTTPS to Gemini failed (SSL certificate). RAPID needs config/cacert.pem. If this keeps happening, restart Apache after updating WAMP PHP.';
    }
    if (strpos($lower, 'timed out') !== false || strpos($lower, 'timeout') !== false) {
        return 'Gemini timed out. Check your internet connection and try again.';
    }
    if (strpos($lower, 'could not resolve') !== false || strpos($lower, 'not resolve host') !== false) {
        return 'Could not resolve generativelanguage.googleapis.com. Check DNS / internet.';
    }
    return 'Network error contacting Gemini.';
}

/**
 * @param array<int,string> $headers
 * @return array{ok:bool, status?:int, json?:mixed, error?:string}
 */
function ai_http_post_json(string $url, array $payload, int $timeout = 55, array $headers = []): array
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return ['ok' => false, 'error' => 'Could not encode the AI request.'];
    }
    if ($headers === []) {
        $headers = ['Content-Type: application/json'];
    }
    $ca = ai_ca_bundle_path();

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'Could not start the AI request.'];
        }
        $opts = [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 12,
        ];
        if ($ca !== null) {
            $opts[CURLOPT_CAINFO] = $ca;
            $opts[CURLOPT_SSL_VERIFYPEER] = true;
            $opts[CURLOPT_SSL_VERIFYHOST] = 2;
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            if ($err !== '') {
                error_log('Gemini cURL error: ' . $err);
            }
            return ['ok' => false, 'error' => $err !== '' ? ai_friendly_curl_error($err) : 'Could not reach Gemini.'];
        }

        return [
            'ok' => true,
            'status' => $status,
            'json' => json_decode((string) $raw, true),
        ];
    }

    $http = [
        'method' => 'POST',
        'header' => implode("\r\n", $headers) . "\r\n",
        'content' => $json,
        'timeout' => $timeout,
        'ignore_errors' => true,
    ];
    $ssl = [
        'verify_peer' => true,
        'verify_peer_name' => true,
    ];
    if ($ca !== null) {
        $ssl['cafile'] = $ca;
    }
    $ctx = stream_context_create(['http' => $http, 'ssl' => $ssl]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return ['ok' => false, 'error' => 'Could not reach Gemini. Enable cURL or allow_url_fopen in PHP.'];
    }
    $status = 0;
    if (isset($http_response_header) && is_array($http_response_header) && isset($http_response_header[0])) {
        if (preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
    }

    return [
        'ok' => true,
        'status' => $status,
        'json' => json_decode((string) $raw, true),
    ];
}
