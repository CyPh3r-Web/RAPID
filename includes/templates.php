<?php
/**
 * Repair job templates (labor + typical parts for quotations).
 */

declare(strict_types=1);

function ensure_repair_templates_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    try {
        $pdo = db();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `repair_templates` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `name` VARCHAR(150) NOT NULL,
              `device_type` VARCHAR(80) DEFAULT NULL,
              `labor_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
              `other_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
              `notes` TEXT DEFAULT NULL,
              `is_active` TINYINT(1) NOT NULL DEFAULT 1,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_repair_templates_active` (`is_active`),
              KEY `idx_repair_templates_name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `repair_template_items` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `template_id` INT UNSIGNED NOT NULL,
              `part_id` INT UNSIGNED DEFAULT NULL,
              `description` VARCHAR(255) NOT NULL,
              `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
              `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
              `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`),
              KEY `idx_rti_template` (`template_id`),
              KEY `idx_rti_part` (`part_id`),
              CONSTRAINT `fk_rti_template` FOREIGN KEY (`template_id`) REFERENCES `repair_templates` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $done = true;
    } catch (Throwable $e) {
        error_log('ensure_repair_templates_schema failed: ' . $e->getMessage());
    }
}

/**
 * @return list<array<string,mixed>>
 */
function list_repair_templates(bool $activeOnly = true): array
{
    ensure_repair_templates_schema();
    $sql = 'SELECT id, name, device_type, labor_cost, other_cost, notes, is_active
            FROM repair_templates';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY name ASC';
    try {
        return db()->query($sql)->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @return array<string,mixed>|null
 */
function get_repair_template(int $templateId): ?array
{
    if ($templateId <= 0) {
        return null;
    }
    ensure_repair_templates_schema();
    $stmt = db()->prepare('SELECT * FROM repair_templates WHERE id = ? LIMIT 1');
    $stmt->execute([$templateId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $items = db()->prepare(
        'SELECT id, part_id, description, quantity, unit_price, sort_order
         FROM repair_template_items WHERE template_id = ? ORDER BY sort_order ASC, id ASC'
    );
    $items->execute([$templateId]);
    $row['items'] = $items->fetchAll() ?: [];
    return $row;
}

/**
 * @param array<string,mixed> $data
 * @param list<array{part_id?:int|null,description:string,quantity:float|int|string,unit_price:float|int|string}> $items
 * @return array{ok:bool,error?:string,template_id?:int}
 */
function save_repair_template(?int $templateId, array $data, array $items): array
{
    ensure_repair_templates_schema();
    ensure_parts_schema();

    $name = trim((string) ($data['name'] ?? ''));
    $deviceType = trim((string) ($data['device_type'] ?? ''));
    $notes = trim((string) ($data['notes'] ?? ''));
    $labor = (float) ($data['labor_cost'] ?? 0);
    $other = (float) ($data['other_cost'] ?? 0);
    $isActive = !empty($data['is_active']) ? 1 : 0;

    if ($name === '') {
        return ['ok' => false, 'error' => 'Template name is required.'];
    }
    if ($labor < 0 || $other < 0) {
        return ['ok' => false, 'error' => 'Costs cannot be negative.'];
    }

    $normalized = [];
    foreach ($items as $item) {
        $desc = trim((string) ($item['description'] ?? ''));
        $qty = (float) ($item['quantity'] ?? 0);
        $unit = (float) ($item['unit_price'] ?? 0);
        $partId = isset($item['part_id']) ? (int) $item['part_id'] : 0;
        if ($desc === '') {
            continue;
        }
        if ($qty <= 0) {
            return ['ok' => false, 'error' => 'Each template part needs a quantity greater than zero.'];
        }
        if ($unit < 0) {
            return ['ok' => false, 'error' => 'Part prices cannot be negative.'];
        }
        if ($partId > 0) {
            $part = get_part_by_id($partId);
            if (!$part) {
                return ['ok' => false, 'error' => 'A selected catalog part was not found.'];
            }
            if ($desc === '') {
                $desc = (string) $part['name'];
            }
        } else {
            $partId = 0;
        }
        $normalized[] = [
            'part_id' => $partId > 0 ? $partId : null,
            'description' => $desc,
            'quantity' => round($qty, 2),
            'unit_price' => round($unit, 2),
        ];
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        if ($templateId && $templateId > 0) {
            if (!get_repair_template($templateId)) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Template not found.'];
            }
            $pdo->prepare(
                'UPDATE repair_templates
                 SET name = ?, device_type = ?, labor_cost = ?, other_cost = ?, notes = ?, is_active = ?
                 WHERE id = ?'
            )->execute([
                $name,
                $deviceType !== '' ? $deviceType : null,
                number_format($labor, 2, '.', ''),
                number_format($other, 2, '.', ''),
                $notes !== '' ? $notes : null,
                $isActive,
                $templateId,
            ]);
            $pdo->prepare('DELETE FROM repair_template_items WHERE template_id = ?')->execute([$templateId]);
            $id = $templateId;
        } else {
            $pdo->prepare(
                'INSERT INTO repair_templates (name, device_type, labor_cost, other_cost, notes, is_active)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                $name,
                $deviceType !== '' ? $deviceType : null,
                number_format($labor, 2, '.', ''),
                number_format($other, 2, '.', ''),
                $notes !== '' ? $notes : null,
                $isActive,
            ]);
            $id = (int) $pdo->lastInsertId();
        }

        if ($normalized) {
            $ins = $pdo->prepare(
                'INSERT INTO repair_template_items
                    (template_id, part_id, description, quantity, unit_price, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($normalized as $i => $line) {
                $ins->execute([
                    $id,
                    $line['part_id'],
                    $line['description'],
                    number_format($line['quantity'], 2, '.', ''),
                    number_format($line['unit_price'], 2, '.', ''),
                    $i + 1,
                ]);
            }
        }

        $pdo->commit();
        return ['ok' => true, 'template_id' => $id];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => friendly_error($e)];
    }
}

/**
 * @return array{ok:bool,error?:string}
 */
function set_repair_template_active(int $templateId, bool $active): array
{
    ensure_repair_templates_schema();
    if (!get_repair_template($templateId)) {
        return ['ok' => false, 'error' => 'Template not found.'];
    }
    try {
        db()->prepare('UPDATE repair_templates SET is_active = ? WHERE id = ?')
            ->execute([$active ? 1 : 0, $templateId]);
        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => friendly_error($e)];
    }
}

/**
 * JSON-ready payload for technician quote form.
 *
 * @return list<array<string,mixed>>
 */
function repair_templates_for_quote_ui(): array
{
    $out = [];
    foreach (list_repair_templates(true) as $tpl) {
        $full = get_repair_template((int) $tpl['id']);
        if (!$full) {
            continue;
        }
        $parts = [];
        foreach ($full['items'] as $item) {
            $parts[] = [
                'part_id' => $item['part_id'] !== null ? (int) $item['part_id'] : null,
                'name' => (string) $item['description'],
                'quantity' => (float) $item['quantity'],
                'unit_price' => (float) $item['unit_price'],
            ];
        }
        $out[] = [
            'id' => (int) $full['id'],
            'name' => (string) $full['name'],
            'device_type' => (string) ($full['device_type'] ?? ''),
            'labor_cost' => (float) $full['labor_cost'],
            'other_cost' => (float) $full['other_cost'],
            'notes' => (string) ($full['notes'] ?? ''),
            'parts' => $parts,
        ];
    }
    return $out;
}
