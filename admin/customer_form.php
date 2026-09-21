<?php
/**
 * Admin — add / edit customer
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
        'SELECT c.id, c.address, u.first_name, u.last_name, u.email, u.phone, u.status
         FROM customers c INNER JOIN users u ON u.id = c.user_id WHERE c.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        flash_set('error', 'Customer not found.');
        redirect('admin/customers.php');
    }
}

$form = [
    'first_name' => $row['first_name'] ?? '',
    'last_name' => $row['last_name'] ?? '',
    'email' => $row['email'] ?? '',
    'phone' => $row['phone'] ?? '',
    'address' => $row['address'] ?? '',
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
        $result = admin_update_customer($id, $form);
        if ($result['ok']) {
            log_activity((int) $user['id'], 'customer_update', 'Updated customer #' . $id);
            flash_set('success', 'Customer updated.');
            redirect('admin/customer.php?id=' . $id);
        }
        $error = $result['error'] ?? 'Update failed.';
    } else {
        $result = admin_create_customer($form);
        if ($result['ok']) {
            log_activity((int) $user['id'], 'customer_create', 'Created customer #' . $result['customer_id']);
            flash_set('success', 'Customer created.');
            redirect('admin/customer.php?id=' . (int) $result['customer_id']);
        }
        $error = $result['error'] ?? 'Create failed.';
    }
}

$pageTitle = $editing ? 'Edit Customer' : 'Add Customer';
$showSidebar = true;
$navVariant = 'app';
$activeNav = 'customers';
$bodyClass = 'app-body';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <h1><?= $editing ? 'Edit customer' : 'Add customer' ?></h1>
            <p><?= $editing ? 'Update account details and status.' : 'Create a customer account manually.' ?></p>
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
                    <input class="form-control" id="phone" name="phone" required value="<?= e($form['phone']) ?>">
                </div>
                <div class="md:col-span-2">
                    <label class="form-label" for="address">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="2"><?= e($form['address']) ?></textarea>
                </div>
                <div>
                    <label class="form-label" for="status">Status</label>
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
                <button type="submit" class="btn btn-rapid-primary"><?= $editing ? 'Save changes' : 'Create customer' ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url($editing ? 'admin/customer.php?id=' . $id : 'admin/customers.php')) ?>">Cancel</a>
            </div>
        </form>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
