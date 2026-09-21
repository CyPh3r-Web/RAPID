<?php
/**
 * Customer — book a repair (device + problem + before-repair media)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('customer');

$user = current_user();
$customerId = get_customer_id_for_user((int) $user['id']);

if (!$customerId) {
    flash_set('error', 'Customer profile not found. Please contact support.');
    redirect('auth/logout.php');
}

$errors = [];
$form = [
    'device_type' => '',
    'brand' => '',
    'model' => '',
    'serial_number' => '',
    'imei' => '',
    'color' => '',
    'accessories' => '',
    'physical_condition' => '',
    'problem_description' => '',
    'appointment_date' => '',
    'appointment_time' => '',
    'priority' => 'normal',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    foreach ($form as $key => $_) {
        if (isset($_POST[$key]) && is_string($_POST[$key])) {
            $form[$key] = trim($_POST[$key]);
        }
    }

    if ($form['device_type'] === '') {
        $errors[] = 'Device type is required.';
    }
    if ($form['brand'] === '') {
        $errors[] = 'Brand is required.';
    }
    if ($form['model'] === '') {
        $errors[] = 'Model is required.';
    }
    if ($form['problem_description'] === '' || strlen($form['problem_description']) < 10) {
        $errors[] = 'Please describe the problem (at least 10 characters).';
    }

    $allowedPriority = ['low', 'normal', 'high', 'urgent'];
    if (!in_array($form['priority'], $allowedPriority, true)) {
        $form['priority'] = 'normal';
    }

    $appointmentAt = null;
    if ($form['appointment_date'] !== '') {
        $time = $form['appointment_time'] !== '' ? $form['appointment_time'] : '09:00';
        $combined = $form['appointment_date'] . ' ' . $time . ':00';
        $ts = strtotime($combined);
        if ($ts === false) {
            $errors[] = 'Invalid appointment date/time.';
        } else {
            $appointmentAt = date('Y-m-d H:i:s', $ts);
        }
    }

    $uploadResult = ['ok' => true, 'files' => []];
    if (empty($errors)) {
        $uploadResult = store_uploaded_media($_FILES['media'] ?? [], 'device', 8);
        if (!$uploadResult['ok']) {
            $errors[] = $uploadResult['error'];
        }
    }

    if (empty($errors)) {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            $dev = $pdo->prepare(
                'INSERT INTO devices
                    (customer_id, device_type, brand, model, serial_number, imei, color, accessories, physical_condition)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $dev->execute([
                $customerId,
                $form['device_type'],
                $form['brand'],
                $form['model'],
                $form['serial_number'] !== '' ? $form['serial_number'] : null,
                $form['imei'] !== '' ? $form['imei'] : null,
                $form['color'] !== '' ? $form['color'] : null,
                $form['accessories'] !== '' ? $form['accessories'] : null,
                $form['physical_condition'] !== '' ? $form['physical_condition'] : null,
            ]);
            $deviceId = (int) $pdo->lastInsertId();

            $ticketNumber = generate_ticket_number($pdo);

            $tick = $pdo->prepare(
                'INSERT INTO repair_tickets
                    (ticket_number, customer_id, device_id, problem_description, current_status, priority, appointment_date)
                 VALUES (?, ?, ?, ?, \'booking_submitted\', ?, ?)'
            );
            $tick->execute([
                $ticketNumber,
                $customerId,
                $deviceId,
                $form['problem_description'],
                $form['priority'],
                $appointmentAt,
            ]);
            $ticketId = (int) $pdo->lastInsertId();

            $hist = $pdo->prepare(
                'INSERT INTO repair_status_history (ticket_id, status, remarks, updated_by)
                 VALUES (?, \'booking_submitted\', ?, ?)'
            );
            $hist->execute([
                $ticketId,
                'Online booking submitted by customer.',
                (int) $user['id'],
            ]);

            if (!empty($uploadResult['files'])) {
                $angles = $_POST['media_angles'] ?? [];
                if (!is_array($angles)) {
                    $angles = [];
                }
                $mediaStmt = $pdo->prepare(
                    'INSERT INTO device_media
                        (ticket_id, uploaded_by, file_name, file_path, file_type, media_category)
                     VALUES (?, ?, ?, ?, ?, \'before_repair\')'
                );
                foreach ($uploadResult['files'] as $i => $f) {
                    $angle = media_angle_normalize((string) ($angles[$i] ?? 'other'));
                    $mediaStmt->execute([
                        $ticketId,
                        (int) $user['id'],
                        '[' . $angle . '] ' . $f['file_name'],
                        $f['file_path'],
                        $f['file_type'],
                    ]);
                }
            }

            $pdo->commit();

            create_notification(
                (int) $user['id'],
                'Booking submitted',
                'Your repair request ' . $ticketNumber . ' was submitted successfully.',
                $ticketId
            );
            notify_admins(
                'New repair booking',
                'Customer ' . $user['first_name'] . ' ' . $user['last_name'] . ' submitted ticket ' . $ticketNumber . '.',
                $ticketId
            );
            log_activity((int) $user['id'], 'booking_created', 'Created ticket ' . $ticketNumber);

            flash_set('success', 'Repair booking created. Ticket number: ' . $ticketNumber);
            redirect('customer/ticket.php?id=' . $ticketId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Remove orphaned files
            if (!empty($uploadResult['files'])) {
                foreach ($uploadResult['files'] as $f) {
                    $abs = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $f['file_path']);
                    @unlink($abs);
                }
            }
            $errors[] = friendly_error($e);
        }
    }
}

$pageTitle = 'Book a Repair';
$showSidebar = true;
$navVariant = 'app';
$activeNav = 'book';
$bodyClass = 'app-body';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header flex flex-wrap justify-between items-start gap-2">
            <div>
                <h1>Book a repair</h1>
                <p>Tell us about your device and the issue. A ticket number is generated automatically.</p>
            </div>
            <a class="btn btn-rapid-outline btn-sm" href="<?= e(url('customer/repairs.php')) ?>">My repairs</a>
        </div>

        <?php if ($errors): ?>
            <?php render_error_state(implode(' ', $errors), 'Could not submit booking'); ?>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" data-disable-on-submit data-wizard class="rapid-card">
            <?= csrf_field() ?>

            <ol class="wizard-steps" aria-label="Booking steps">
                <li class="is-current"><span class="wiz-num">1</span> <span class="hidden sm:inline">Device info</span></li>
                <li><span class="wiz-num">2</span> <span class="hidden sm:inline">Condition</span></li>
                <li><span class="wiz-num">3</span> <span class="hidden sm:inline">Confirmation</span></li>
            </ol>

            <div class="wizard-panel" data-step="1">
                <h2 class="text-sm font-semibold text-rapid mb-3">Device information</h2>
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 mb-4">
                    <div class="md:col-span-4">
                        <label class="form-label" for="device_type">Device type <span class="text-red-700">*</span></label>
                        <select class="form-select" id="device_type" name="device_type" required>
                            <option value="">Select type</option>
                            <?php
                            $types = ['Smartphone', 'Tablet', 'Laptop', 'Smartwatch', 'Other'];
                            foreach ($types as $t):
                            ?>
                                <option value="<?= e($t) ?>" <?= $form['device_type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-4">
                        <label class="form-label" for="brand">Brand <span class="text-red-700">*</span></label>
                        <input type="text" class="form-control" id="brand" name="brand" required maxlength="100"
                               value="<?= e($form['brand']) ?>" placeholder="e.g. Samsung">
                    </div>
                    <div class="md:col-span-4">
                        <label class="form-label" for="model">Model <span class="text-red-700">*</span></label>
                        <input type="text" class="form-control" id="model" name="model" required maxlength="150"
                               value="<?= e($form['model']) ?>" placeholder="e.g. Galaxy A54">
                    </div>
                    <div class="md:col-span-4">
                        <label class="form-label" for="color">Color</label>
                        <input type="text" class="form-control" id="color" name="color" maxlength="50" value="<?= e($form['color']) ?>">
                    </div>
                    <div class="md:col-span-4">
                        <label class="form-label" for="serial_number">Serial number</label>
                        <input type="text" class="form-control" id="serial_number" name="serial_number" maxlength="150" value="<?= e($form['serial_number']) ?>">
                    </div>
                    <div class="md:col-span-4">
                        <label class="form-label" for="imei">IMEI</label>
                        <input type="text" class="form-control" id="imei" name="imei" maxlength="50" value="<?= e($form['imei']) ?>">
                    </div>
                    <div class="md:col-span-6">
                        <label class="form-label" for="accessories">Accessories included</label>
                        <input type="text" class="form-control" id="accessories" name="accessories"
                               value="<?= e($form['accessories']) ?>" placeholder="Charger, case, SIM tray…">
                    </div>
                    <div class="md:col-span-6">
                        <label class="form-label" for="physical_condition">Physical condition</label>
                        <input type="text" class="form-control" id="physical_condition" name="physical_condition"
                               value="<?= e($form['physical_condition']) ?>" placeholder="Scratches, cracked glass, water damage…">
                    </div>
                </div>
            </div>

            <div class="wizard-panel" data-step="2" hidden>
                <h2 class="text-sm font-semibold text-rapid mb-3">Condition documentation</h2>
                <div class="mb-4">
                    <label class="form-label" for="problem_description">Problem description <span class="text-red-700">*</span></label>
                    <textarea class="form-control" id="problem_description" name="problem_description" rows="4" required
                              minlength="10" placeholder="Describe what is wrong with the device…"><?= e($form['problem_description']) ?></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 mb-4">
                    <div class="md:col-span-4">
                        <label class="form-label" for="appointment_date">Preferred date</label>
                        <input type="date" class="form-control" id="appointment_date" name="appointment_date"
                               value="<?= e($form['appointment_date']) ?>" min="<?= e(date('Y-m-d')) ?>">
                    </div>
                    <div class="md:col-span-4">
                        <label class="form-label" for="appointment_time">Preferred time</label>
                        <input type="time" class="form-control" id="appointment_time" name="appointment_time"
                               value="<?= e($form['appointment_time'] ?: '10:00') ?>">
                    </div>
                    <div class="md:col-span-4">
                        <label class="form-label" for="priority">Priority</label>
                        <select class="form-select" id="priority" name="priority">
                            <?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $val => $label): ?>
                                <option value="<?= e($val) ?>" <?= $form['priority'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <p class="text-rapid-muted text-sm mb-2">
                    Upload the before-repair record. Tag each file as front, back, or screen.
                    JPG, PNG, WEBP, MP4, MOV · max 10 MB each · up to 8 files.
                </p>
                <div class="dropzone" data-dropzone>
                    <input type="file" class="sr-only" id="media" name="media[]" multiple
                           accept=".jpg,.jpeg,.png,.webp,.mp4,.mov,image/jpeg,image/png,image/webp,video/mp4,video/quicktime">
                    <div data-drop-idle>
                        <i class="bi bi-cloud-arrow-up text-3xl block mb-2 text-rapid" aria-hidden="true"></i>
                        <strong>Drag photos or videos here</strong>
                        <div class="text-sm text-rapid-muted mt-1">or click to browse</div>
                    </div>
                    <div class="dropzone-previews" data-previews></div>
                    <div data-angle-fields></div>
                </div>
            </div>

            <div class="wizard-panel" data-step="3" hidden>
                <h2 class="text-sm font-semibold text-rapid mb-3">Confirm booking</h2>
                <p class="text-rapid-muted text-sm mb-3">Review the details below. Submitting creates your ticket and the before-repair record.</p>
                <dl class="detail-dl mb-0 grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-4">
                    <dt>Device</dt>
                    <dd><span data-review="brand">—</span> <span data-review="model"></span> (<span data-review="device_type"></span>)</dd>
                    <dt>Problem</dt>
                    <dd data-review="problem_description">—</dd>
                    <dt>Preferred time</dt>
                    <dd class="mb-0"><span data-review="appointment_date">—</span> <span data-review="appointment_time"></span></dd>
                </dl>
            </div>

            <div class="flex flex-wrap gap-2 mt-4 pt-3 border-t border-rapid-border">
                <a class="btn btn-outline-secondary" href="<?= e(url('customer/index.php')) ?>">Cancel</a>
                <div class="ml-auto flex flex-wrap gap-2">
                    <button type="button" class="btn btn-rapid-outline" data-wizard-prev hidden>Back</button>
                    <button type="button" class="btn btn-rapid-primary" data-wizard-next>Continue</button>
                    <button type="submit" class="btn btn-rapid-primary" data-wizard-submit hidden>Submit repair request</button>
                </div>
            </div>
        </form>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
