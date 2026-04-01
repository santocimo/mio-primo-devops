<?php
require_once __DIR__ . '/inc/security.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/inc/labels.php';
require_once __DIR__ . '/inc/validation.php';

if (!isset($_SESSION['admin_logged']) || strtoupper($_SESSION['user_role']) !== 'ADMIN') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Access denied';
    exit;
}

$pdo = getPDO();
$current_default = getAppSetting($pdo, 'default_business_type', 'gym');
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = trim($_POST['default_business_type'] ?? '');
    $csrf = $_POST['csrf'] ?? '';
    if (empty($selected) || !validate_business_type($selected) || !verify_csrf($csrf)) {
        $error = 'Invalid selection or CSRF token.';
    } else {
        if (setAppSetting($pdo, 'default_business_type', $selected)) {
            $message = 'Default business type updated successfully.';
            $current_default = $selected;
        } else {
            $error = 'Unable to save settings.';
        }
    }
}

$businessTypes = getSupportedBusinessTypes();
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>App Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card shadow-sm">
        <div class="card-body">
            <h4 class="card-title mb-3">Application Settings</h4>
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
            <?php elseif ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES); ?>">
                <div class="mb-3">
                    <label class="form-label">Default Business Type</label>
                    <select class="form-select" name="default_business_type" required>
                        <?php foreach ($businessTypes as $key => $label): ?>
                            <option value="<?php echo htmlspecialchars($key, ENT_QUOTES); ?>" <?php echo $current_default === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">This value is used when no specific location is selected, or in legacy mode.</div>
                </div>
                <button type="submit" class="btn btn-primary">Save settings</button>
                <a href="index.php" class="btn btn-secondary ms-2">Back</a>
            </form>
        </div>
    </div>
</div>
</body>
</html>
