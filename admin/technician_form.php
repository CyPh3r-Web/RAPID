<?php
/**
 * Admin — add / edit technician
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$user = current_user();
$id = (int) ($_GET['id'] ?? 0);
$editing = $id > 0;
$row = null;

if ($editing) {
    $stmt = db()->prepare(
        'SELECT t.id, t.specialization, t.availability_status,
                u.first_name, u.last_name, u.email, u.phone, u.status
         FROM technicians t INNER JOIN users u ON u.id = t.user_id WHERE t.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        flash_set('error', 'Technician not found.');
        redirect('admin/technicians.php');
    }
}

$form = [
    'first_name' => $row['first_name'] ?? '',
    'last_name' => $row['last_name'] ?? '',
    'email' => $row['email'] ?? '',
    'phone' => $row['phone'] ?? '',
    'specialization' => $row['specialization'] ?? '',
    'availability_status' => $row['availability_status'] ?? 'available',
    'status' => $row['status'] ?? 'active',
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    foreach ($form as $k => $_) {
        if (isset($_POST[$k]) && is_string($_POST[$k])) {
            $form[$k] = trim($_POST[$k]);
        }
    }
    $form['password'] = (string) ($_POST['password'] ?? '');

    if ($editing) {
        $result = admin_update_technician($id, $form);
        if ($result['ok']) {
            log_activity((int) $user['id'], 'technician_update', 'Updated technician #' . $id);
            flash_set('success', 'Technician updated.');
            redirect('admin/technicians.php');
        }
        $error = $result['error'] ?? 'Update failed.';
    } else {
        $result = admin_create_technician($form);
        if ($result['ok']) {
            log_activity((int) $user['id'], 'technician_create', 'Created technician #' . $result['technician_id']);
            flash_set('success', 'Technician created.');
            redirect('admin/technicians.php');
        }
        $error = $result['error'] ?? 'Create failed.';
    }
}

$pageTitle = $editing ? 'Edit Technician' : 'Add Technician';
$showSidebar = true;
$navVariant = 'app';
$activeNav = 'technicians';
$bodyClass = 'app-body';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <h1><?= $editing ? 'Edit technician' : 'Add technician' ?></h1>
            <p>Account access, specialization, and availability.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="rapid-card max-w-[720px]" data-disable-on-submit>
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="form-label" for="first_name">First name</label>
                    <input class="form-control" id="first_name" name="first_name" required value="<?= e($form['first_name']) ?>">
                </div>
                <div>
                    <label class="form-label" for="last_name">Last name</label>
                    <input class="form-control" id="last_name" name="last_name" required value="<?= e($form['last_name']) ?>">
                </div>
                <div>
                    <label class="form-label" for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required value="<?= e($form['email']) ?>">
                </div>
                <div>
                    <label class="form-label" for="phone">Phone</label>
                    <input class="form-control" id="phone" name="phone" value="<?= e($form['phone']) ?>">
                </div>
                <div>
                    <label class="form-label" for="specialization">Specialization</label>
                    <input class="form-control" id="specialization" name="specialization" value="<?= e($form['specialization']) ?>" placeholder="e.g. Smartphones & Tablets">
                </div>
                <div>
                    <label class="form-label" for="availability_status">Availability</label>
                    <select class="form-select" id="availability_status" name="availability_status">
                        <?php foreach (['available' => 'Available', 'busy' => 'Busy', 'unavailable' => 'Unavailable'] as $val => $label): ?>
                            <option value="<?= e($val) ?>" <?= $form['availability_status'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="status">Account status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="active" <?= $form['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $form['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="password">Password <?= $editing ? '<span class="text-rapid-muted font-normal">(leave blank to keep)</span>' : '' ?></label>
                    <input type="password" class="form-control" id="password" name="password" <?= $editing ? '' : 'required minlength="8"' ?> autocomplete="new-password">
                </div>
            </div>
            <div class="flex gap-2 mt-3">
                <button type="submit" class="btn btn-rapid-primary"><?= $editing ? 'Save changes' : 'Create technician' ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/technicians.php')) ?>">Cancel</a>
            </div>
        </form>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
