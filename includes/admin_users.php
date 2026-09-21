<?php
/**
 * Admin helpers for managing customers & technicians
 */

declare(strict_types=1);

/**
 * @return array{ok:bool, error?:string, user_id?:int, customer_id?:int}
 */
function admin_create_customer(array $data): array
{
    $first = trim($data['first_name'] ?? '');
    $last = trim($data['last_name'] ?? '');
    $email = strtolower(trim($data['email'] ?? ''));
    $phone = trim($data['phone'] ?? '');
    $address = trim($data['address'] ?? '');
    $password = $data['password'] ?? '';
    $status = ($data['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    if ($first === '' || $last === '') {
        return ['ok' => false, 'error' => 'First and last name are required.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'A valid email is required.'];
    }
    if ($phone === '') {
        return ['ok' => false, 'error' => 'Phone is required.'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
    }

    $pdo = db();
    try {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $check->execute([$email]);
        if ($check->fetch()) {
            return ['ok' => false, 'error' => 'Email is already registered.'];
        }

        $pdo->beginTransaction();
        $pdo->prepare(
            "INSERT INTO users (role, first_name, last_name, email, phone, password, status)
             VALUES ('customer', ?, ?, ?, ?, ?, ?)"
        )->execute([$first, $last, $email, $phone, password_hash($password, PASSWORD_DEFAULT), $status]);
        $userId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO customers (user_id, address) VALUES (?, ?)')
            ->execute([$userId, $address !== '' ? $address : null]);
        $customerId = (int) $pdo->lastInsertId();
        $pdo->commit();

        return ['ok' => true, 'user_id' => $userId, 'customer_id' => $customerId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => friendly_error($e)];
    }
}

/**
 * @return array{ok:bool, error?:string}
 */
function admin_update_customer(int $customerId, array $data): array
{
    $first = trim($data['first_name'] ?? '');
    $last = trim($data['last_name'] ?? '');
    $email = strtolower(trim($data['email'] ?? ''));
    $phone = trim($data['phone'] ?? '');
    $address = trim($data['address'] ?? '');
    $status = ($data['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
    $password = $data['password'] ?? '';

    if ($first === '' || $last === '') {
        return ['ok' => false, 'error' => 'First and last name are required.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'A valid email is required.'];
    }

    $pdo = db();
    try {
        $row = $pdo->prepare('SELECT c.id, c.user_id FROM customers c WHERE c.id = ? LIMIT 1');
        $row->execute([$customerId]);
        $cust = $row->fetch();
        if (!$cust) {
            return ['ok' => false, 'error' => 'Customer not found.'];
        }

        $dup = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $dup->execute([$email, (int) $cust['user_id']]);
        if ($dup->fetch()) {
            return ['ok' => false, 'error' => 'Email is already used by another account.'];
        }

        $pdo->beginTransaction();
        if ($password !== '') {
            if (strlen($password) < 8) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'New password must be at least 8 characters.'];
            }
            $pdo->prepare(
                'UPDATE users SET first_name=?, last_name=?, email=?, phone=?, status=?, password=? WHERE id=?'
            )->execute([$first, $last, $email, $phone, $status, password_hash($password, PASSWORD_DEFAULT), (int) $cust['user_id']]);
        } else {
            $pdo->prepare(
                'UPDATE users SET first_name=?, last_name=?, email=?, phone=?, status=? WHERE id=?'
            )->execute([$first, $last, $email, $phone, $status, (int) $cust['user_id']]);
        }
        $pdo->prepare('UPDATE customers SET address=? WHERE id=?')
            ->execute([$address !== '' ? $address : null, $customerId]);
        $pdo->commit();
        return ['ok' => true];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => friendly_error($e)];
    }
}

/**
 * @return array{ok:bool, error?:string, user_id?:int, technician_id?:int}
 */
function admin_create_technician(array $data): array
{
    $first = trim($data['first_name'] ?? '');
    $last = trim($data['last_name'] ?? '');
    $email = strtolower(trim($data['email'] ?? ''));
    $phone = trim($data['phone'] ?? '');
    $spec = trim($data['specialization'] ?? '');
    $availability = $data['availability_status'] ?? 'available';
    $password = $data['password'] ?? '';
    $status = ($data['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    if (!in_array($availability, ['available', 'busy', 'unavailable'], true)) {
        $availability = 'available';
    }
    if ($first === '' || $last === '') {
        return ['ok' => false, 'error' => 'First and last name are required.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'A valid email is required.'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
    }

    $pdo = db();
    try {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $check->execute([$email]);
        if ($check->fetch()) {
            return ['ok' => false, 'error' => 'Email is already registered.'];
        }

        $pdo->beginTransaction();
        $pdo->prepare(
            "INSERT INTO users (role, first_name, last_name, email, phone, password, status)
             VALUES ('technician', ?, ?, ?, ?, ?, ?)"
        )->execute([$first, $last, $email, $phone !== '' ? $phone : null, password_hash($password, PASSWORD_DEFAULT), $status]);
        $userId = (int) $pdo->lastInsertId();

        $pdo->prepare(
            'INSERT INTO technicians (user_id, specialization, availability_status) VALUES (?, ?, ?)'
        )->execute([$userId, $spec !== '' ? $spec : null, $availability]);
        $techId = (int) $pdo->lastInsertId();
        $pdo->commit();

        return ['ok' => true, 'user_id' => $userId, 'technician_id' => $techId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => friendly_error($e)];
    }
}

/**
 * @return array{ok:bool, error?:string}
 */
function admin_update_technician(int $technicianId, array $data): array
{
    $first = trim($data['first_name'] ?? '');
    $last = trim($data['last_name'] ?? '');
    $email = strtolower(trim($data['email'] ?? ''));
    $phone = trim($data['phone'] ?? '');
    $spec = trim($data['specialization'] ?? '');
    $availability = $data['availability_status'] ?? 'available';
    $status = ($data['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
    $password = $data['password'] ?? '';

    if (!in_array($availability, ['available', 'busy', 'unavailable'], true)) {
        $availability = 'available';
    }
    if ($first === '' || $last === '') {
        return ['ok' => false, 'error' => 'First and last name are required.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'A valid email is required.'];
    }

    $pdo = db();
    try {
        $row = $pdo->prepare('SELECT id, user_id FROM technicians WHERE id = ? LIMIT 1');
        $row->execute([$technicianId]);
        $tech = $row->fetch();
        if (!$tech) {
            return ['ok' => false, 'error' => 'Technician not found.'];
        }

        $dup = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $dup->execute([$email, (int) $tech['user_id']]);
        if ($dup->fetch()) {
            return ['ok' => false, 'error' => 'Email is already used by another account.'];
        }

        $pdo->beginTransaction();
        if ($password !== '') {
            if (strlen($password) < 8) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'New password must be at least 8 characters.'];
            }
            $pdo->prepare(
                'UPDATE users SET first_name=?, last_name=?, email=?, phone=?, status=?, password=? WHERE id=?'
            )->execute([$first, $last, $email, $phone !== '' ? $phone : null, $status, password_hash($password, PASSWORD_DEFAULT), (int) $tech['user_id']]);
        } else {
            $pdo->prepare(
                'UPDATE users SET first_name=?, last_name=?, email=?, phone=?, status=? WHERE id=?'
            )->execute([$first, $last, $email, $phone !== '' ? $phone : null, $status, (int) $tech['user_id']]);
        }
        $pdo->prepare(
            'UPDATE technicians SET specialization=?, availability_status=? WHERE id=?'
        )->execute([$spec !== '' ? $spec : null, $availability, $technicianId]);
        $pdo->commit();
        return ['ok' => true];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => friendly_error($e)];
    }
}

function get_notifications_for_user(int $userId, int $limit = 15): array
{
    $stmt = db()->prepare(
        'SELECT id, ticket_id, title, message, is_read, created_at
         FROM notifications WHERE user_id = ?
         ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limit
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function count_unread_notifications(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function mark_notification_read(int $userId, int $notificationId): bool
{
    $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
    $stmt->execute([$notificationId, $userId]);
    return $stmt->rowCount() > 0;
}

function mark_all_notifications_read(int $userId): void
{
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')
        ->execute([$userId]);
}
