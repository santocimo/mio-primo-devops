<?php
require_once __DIR__ . '/inc/security.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/inc/labels.php';

if (!isset($_SESSION['admin_logged'])) {
    header('Location: login.php');
    exit;
}

$pdo = getPDO();
$use_gym = false;
$current_gym_id = null;
$current_business_type = 'gym';

try {
    $colCheck = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = DATABASE() AND table_name = 'visitatori' AND column_name = 'gym_id'");
    $colCheck->execute();
    $use_gym = (bool)$colCheck->fetchColumn();
} catch (Exception $e) {
    $use_gym = false;
}

if ($use_gym && isset($_SESSION['gym_id'])) {
    $current_gym_id = (int)$_SESSION['gym_id'];
    try {
        $typeStmt = $pdo->prepare("SELECT category FROM gyms WHERE id = ? LIMIT 1");
        $typeStmt->execute([$current_gym_id]);
        $current_business_type = $typeStmt->fetchColumn() ?: 'gym';
    } catch (Exception $e) {
        $current_business_type = 'gym';
    }
} else {
    $current_business_type = getAppSetting($pdo, 'default_business_type', 'gym') ?: 'gym';
}

$person_label = getPersonLabel($current_business_type);
$person_label_plural = getPersonPluralLabel($current_business_type);

$query = "SELECT * FROM visitatori";
$params = [];
if ($use_gym && $current_gym_id) {
    $query .= " WHERE gym_id = ?";
    $params[] = $current_gym_id;
}
$query .= " ORDER BY id DESC";

$contacts = $pdo->prepare($query);
$contacts->execute($params);
$contacts = $contacts->fetchAll();
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($person_label_plural); ?> Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><?php echo htmlspecialchars($person_label_plural, ENT_QUOTES); ?></h2>
            <div class="text-muted">Manage your <?php echo htmlspecialchars(strtolower($person_label_plural), ENT_QUOTES); ?> records.</div>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary">Dashboard</a>
            <button id="btnNew" class="btn btn-primary">New <?php echo htmlspecialchars($person_label, ENT_QUOTES); ?></button>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>CF</th>
                            <th>Birth</th>
                            <th>City</th>
                            <th>Address</th>
                            <th>Phone</th>
                            <th>Gender</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contacts as $contact): ?>
                            <tr data-id="<?php echo (int)$contact['id']; ?>"
                                data-nome="<?php echo htmlspecialchars($contact['nome'], ENT_QUOTES); ?>"
                                data-cognome="<?php echo htmlspecialchars($contact['cognome'], ENT_QUOTES); ?>"
                                data-cf="<?php echo htmlspecialchars($contact['codice_fiscale'], ENT_QUOTES); ?>"
                                data-data="<?php echo htmlspecialchars($contact['data_nascita'], ENT_QUOTES); ?>"
                                data-luogo="<?php echo htmlspecialchars($contact['luogo_nascita'], ENT_QUOTES); ?>"
                                data-indirizzo="<?php echo htmlspecialchars($contact['indirizzo'], ENT_QUOTES); ?>"
                                data-recapito="<?php echo htmlspecialchars($contact['recapito'], ENT_QUOTES); ?>"
                                data-sesso="<?php echo htmlspecialchars($contact['sesso'], ENT_QUOTES); ?>">
                                <td><?php echo htmlspecialchars($contact['nome'] . ' ' . $contact['cognome'], ENT_QUOTES); ?></td>
                                <td><?php echo htmlspecialchars($contact['codice_fiscale'], ENT_QUOTES); ?></td>
                                <td><?php echo htmlspecialchars($contact['data_nascita'], ENT_QUOTES); ?></td>
                                <td><?php echo htmlspecialchars($contact['luogo_nascita'], ENT_QUOTES); ?></td>
                                <td><?php echo htmlspecialchars($contact['indirizzo'], ENT_QUOTES); ?></td>
                                <td><?php echo htmlspecialchars($contact['recapito'], ENT_QUOTES); ?></td>
                                <td><?php echo htmlspecialchars($contact['sesso'], ENT_QUOTES); ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary btn-edit">Edit</button>
                                    <button class="btn btn-sm btn-outline-danger btn-delete">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($contacts)): ?>
                            <tr><td colspan="8" class="text-center py-4">No records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="contactModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo htmlspecialchars($person_label, ENT_QUOTES); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="contactForm">
                    <input type="hidden" name="id" id="contact_id">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES); ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First name</label>
                            <input type="text" class="form-control" name="nome" id="contact_nome" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last name</label>
                            <input type="text" class="form-control" name="cognome" id="contact_cognome" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Codice Fiscale</label>
                            <input type="text" class="form-control" name="codice_fiscale" id="contact_cf" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Birth date</label>
                            <input type="date" class="form-control" name="data_nascita" id="contact_data" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Birth city</label>
                            <input type="text" class="form-control" name="luogo_nascita" id="contact_luogo" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Address</label>
                            <input type="text" class="form-control" name="indirizzo" id="contact_indirizzo" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="recapito" id="contact_recapito">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="sesso" id="contact_sesso" required>
                                <option value="M">M</option>
                                <option value="F">F</option>
                                <option value="O">O</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" id="saveContact" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(function(){
    const modalEl = document.getElementById('contactModal');
    const bsModal = new bootstrap.Modal(modalEl);

    $('#btnNew').on('click', function() {
        $('#contact_id').val('');
        $('#contactForm')[0].reset();
        bsModal.show();
    });

    $(document).on('click', '.btn-edit', function() {
        const row = $(this).closest('tr');
        $('#contact_id').val(row.data('id'));
        $('#contact_nome').val(row.data('nome'));
        $('#contact_cognome').val(row.data('cognome'));
        $('#contact_cf').val(row.data('cf'));
        $('#contact_data').val(row.data('data'));
        $('#contact_luogo').val(row.data('luogo'));
        $('#contact_indirizzo').val(row.data('indirizzo'));
        $('#contact_recapito').val(row.data('recapito'));
        $('#contact_sesso').val(row.data('sesso'));
        bsModal.show();
    });

    $('#saveContact').on('click', function() {
        const data = $('#contactForm').serialize();
        $.post('save_contact.php', data, function(resp) {
            if (resp && resp.ok) {
                location.reload();
            } else {
                Swal.fire('Error', resp.error || 'Unable to save', 'error');
            }
        }, 'json').fail(function() {
            Swal.fire('Error', 'Unable to save contact', 'error');
        });
    });

    $(document).on('click', '.btn-delete', function() {
        const row = $(this).closest('tr');
        const id = row.data('id');
        Swal.fire({
            title: 'Delete record? ',
            text: row.find('td').first().text().trim(),
            icon: 'warning',
            showCancelButton: true
        }).then(function(result) {
            if (result.isConfirmed) {
                $.post('delete_contact.php', { id: id, csrf: '<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES); ?>' }, function(resp) {
                    if (resp && resp.ok) {
                        location.reload();
                    } else {
                        Swal.fire('Error', resp.error || 'Unable to delete', 'error');
                    }
                }, 'json');
            }
        });
    });
});
</script>
</body>
</html>
