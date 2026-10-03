<?php
/**
 * Parts catalog for quotations / inventory-ready pricing.
 */

declare(strict_types=1);

/**
 * Ensure parts + quotation_items.part_id exist (safe for existing installs).
 */
function ensure_parts_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    try {
        $pdo = db();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `parts` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `sku` VARCHAR(60) DEFAULT NULL,
              `name` VARCHAR(255) NOT NULL,
              `category` VARCHAR(100) DEFAULT NULL,
              `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
              `notes` TEXT DEFAULT NULL,
              `is_active` TINYINT(1) NOT NULL DEFAULT 1,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_parts_sku` (`sku`),
              KEY `idx_parts_active` (`is_active`),
              KEY `idx_parts_name` (`name`),
              KEY `idx_parts_category` (`category`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        ensure_quotation_items_schema();

        if (!$pdo->query("SHOW COLUMNS FROM `parts` LIKE 'stock_qty'")->fetch()) {
            $pdo->exec(
                'ALTER TABLE `parts`
                 ADD COLUMN `stock_qty` INT NOT NULL DEFAULT 0 AFTER `unit_price`,
                 ADD COLUMN `reorder_level` INT UNSIGNED NOT NULL DEFAULT 2 AFTER `stock_qty`'
            );
        }

        $col = $pdo->query("SHOW COLUMNS FROM `quotation_items` LIKE 'part_id'")->fetch();
        if (!$col) {
            $pdo->exec(
                'ALTER TABLE `quotation_items`
                 ADD COLUMN `part_id` INT UNSIGNED DEFAULT NULL AFTER `quotation_id`,
                 ADD KEY `idx_quotation_items_part` (`part_id`)'
            );
            try {
                $pdo->exec(
                    'ALTER TABLE `quotation_items`
                     ADD CONSTRAINT `fk_quotation_items_part`
                     FOREIGN KEY (`part_id`) REFERENCES `parts` (`id`) ON DELETE SET NULL'
                );
            } catch (Throwable $e) {
                // Constraint may already exist on partial upgrades
                error_log('parts FK attach skipped: ' . $e->getMessage());
            }
        }

        $done = true;
    } catch (Throwable $e) {
        error_log('ensure_parts_schema failed: ' . $e->getMessage());
    }
}

/**
 * @return list<array<string, mixed>>
 */
function list_parts_catalog(bool $activeOnly = true): array
{
    ensure_parts_schema();
    $sql = 'SELECT id, sku, name, category, unit_price, stock_qty, reorder_level, is_active
            FROM parts';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY category IS NULL, category ASC, name ASC';
    try {
        return db()->query($sql)->fetchAll() ?: [];
    } catch (Throwable $e) {
        error_log('list_parts_catalog failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * @return array<string, mixed>|null
 */
function get_part_by_id(int $partId): ?array
{
    if ($partId <= 0) {
        return null;
    }
    ensure_parts_schema();
    $stmt = db()->prepare(
        'SELECT id, sku, name, category, unit_price, stock_qty, reorder_level, notes, is_active, created_at, updated_at
         FROM parts WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$partId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * @param array{sku?:string,name?:string,category?:string,unit_price?:float|string,stock_qty?:int|string,reorder_level?:int|string,notes?:string,is_active?:int|bool|string} $data
 * @return array{ok:bool, error?:string, part_id?:int}
 */
function save_part(?int $partId, array $data): array
{
    ensure_parts_schema();

    $sku = trim((string) ($data['sku'] ?? ''));
    $name = trim((string) ($data['name'] ?? ''));
    $category = trim((string) ($data['category'] ?? ''));
    $notes = trim((string) ($data['notes'] ?? ''));
    $unitPrice = (float) ($data['unit_price'] ?? 0);
    $stockQty = (int) ($data['stock_qty'] ?? 0);
    $reorderLevel = (int) ($data['reorder_level'] ?? 2);
    $isActive = !empty($data['is_active']) ? 1 : 0;

    if ($name === '') {
        return ['ok' => false, 'error' => 'Part name is required.'];
    }
    if ((function_exists('mb_strlen') ? mb_strlen($name) : strlen($name)) > 255) {
        return ['ok' => false, 'error' => 'Part name must be 255 characters or fewer.'];
    }
    if ($sku !== '' && (function_exists('mb_strlen') ? mb_strlen($sku) : strlen($sku)) > 60) {
        return ['ok' => false, 'error' => 'SKU must be 60 characters or fewer.'];
    }
    if ($category !== '' && (function_exists('mb_strlen') ? mb_strlen($category) : strlen($category)) > 100) {
        return ['ok' => false, 'error' => 'Category must be 100 characters or fewer.'];
    }
    if ($unitPrice < 0) {
        return ['ok' => false, 'error' => 'Unit price cannot be negative.'];
    }
    // Negative stock is allowed: it records parts owed to approved jobs (backorder).
    if ($reorderLevel < 0) {
        return ['ok' => false, 'error' => 'Reorder level cannot be negative.'];
    }

    $skuValue = $sku !== '' ? $sku : null;

    try {
        if ($skuValue !== null) {
            $dup = db()->prepare(
                'SELECT id FROM parts WHERE sku = ? AND id <> ? LIMIT 1'
            );
            $dup->execute([$skuValue, $partId ?? 0]);
            if ($dup->fetch()) {
                return ['ok' => false, 'error' => 'That SKU is already in use.'];
            }
        }

        if ($partId && $partId > 0) {
            $existing = get_part_by_id($partId);
            if (!$existing) {
                return ['ok' => false, 'error' => 'Part not found.'];
            }
            db()->prepare(
                'UPDATE parts
                 SET sku = ?, name = ?, category = ?, unit_price = ?, stock_qty = ?, reorder_level = ?, notes = ?, is_active = ?
                 WHERE id = ?'
            )->execute([
                $skuValue,
                $name,
                $category !== '' ? $category : null,
                number_format($unitPrice, 2, '.', ''),
                $stockQty,
                $reorderLevel,
                $notes !== '' ? $notes : null,
                $isActive,
                $partId,
            ]);
            return ['ok' => true, 'part_id' => $partId];
        }

        db()->prepare(
            'INSERT INTO parts (sku, name, category, unit_price, stock_qty, reorder_level, notes, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $skuValue,
            $name,
            $category !== '' ? $category : null,
            number_format($unitPrice, 2, '.', ''),
            $stockQty,
            $reorderLevel,
            $notes !== '' ? $notes : null,
            $isActive,
        ]);

        return ['ok' => true, 'part_id' => (int) db()->lastInsertId()];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => friendly_error($e)];
    }
}

/**
 * Soft-deactivate a catalog part (preferred over hard delete).
 *
 * @return array{ok:bool, error?:string}
 */
function set_part_active(int $partId, bool $active): array
{
    ensure_parts_schema();
    if (!get_part_by_id($partId)) {
        return ['ok' => false, 'error' => 'Part not found.'];
    }
    try {
        db()->prepare('UPDATE parts SET is_active = ? WHERE id = ?')
            ->execute([$active ? 1 : 0, $partId]);
        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => friendly_error($e)];
    }
}

/**
 * Move catalog stock for every catalog line on a quotation.
 * $direction -1 consumes (quote approved), +1 restores (approved job cancelled).
 * Stock may go negative: that means parts are on backorder for an approved job.
 * Call inside the caller's transaction, after ensure_parts_schema() (DDL would auto-commit it).
 */
function adjust_stock_for_quotation(PDO $pdo, int $quotationId, int $direction): void
{
    $pdo->prepare(
        'UPDATE parts p
         INNER JOIN (
             SELECT part_id, SUM(CEIL(quantity)) AS qty
             FROM quotation_items
             WHERE quotation_id = ? AND part_id IS NOT NULL
             GROUP BY part_id
         ) x ON x.part_id = p.id
         SET p.stock_qty = p.stock_qty + (? * x.qty)'
    )->execute([$quotationId, $direction]);
}

/**
 * Active parts at or below their reorder level, lowest stock first.
 * Pass $quotationId to limit to parts used on that quotation.
 *
 * @return list<array<string, mixed>>
 */
function low_stock_parts(int $limit = 10, ?int $quotationId = null): array
{
    ensure_parts_schema();
    $sql = 'SELECT id, sku, name, stock_qty, reorder_level FROM parts
            WHERE is_active = 1 AND stock_qty <= reorder_level';
    $params = [];
    if ($quotationId !== null) {
        $sql .= ' AND id IN (SELECT part_id FROM quotation_items WHERE quotation_id = ?)';
        $params[] = $quotationId;
    }
    $sql .= ' ORDER BY stock_qty ASC, name ASC LIMIT ' . max(1, $limit);
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        error_log('low_stock_parts failed: ' . $e->getMessage());
        return [];
    }
}

function stock_badge_class(int $qty, int $reorderLevel): string
{
    if ($qty <= 0) {
        return 'badge-status-danger';
    }
    return $qty <= $reorderLevel ? 'badge-status-warning' : 'badge-status-success';
}
