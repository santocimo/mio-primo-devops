<?php
require_once __DIR__ . '/db.php';
session_start();
if (!isset($_SESSION['admin_logged']) || strtoupper($_SESSION['user_role']) !== 'ADMIN') {
    header('HTTP/1.1 403 Forbidden');
    echo "Access denied";
    exit;
}
$pdo = getPDO();
$gyms = $pdo->query("SELECT id,name,slug,created_at FROM gyms ORDER BY name")->fetchAll();
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Gyms</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="p-4">
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Gym Management</h3>
        <div>
            <a href="index.php" class="btn btn-secondary">Back</a>
            <button id="btnNew" class="btn btn-primary">New Gym</button>
        </div>
    </div>

    <table class="table table-striped">
        <thead><tr><th>#</th><th>Name</th><th>Slug</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody id="gymList">
        <?php foreach($gyms as $g): ?>
            <tr data-id="<?php echo (int)$g['id']; ?>">
                <td><?php echo (int)$g['id']; ?></td>
                <td><?php echo htmlspecialchars($g['name'], ENT_QUOTES); ?></td>
                <td><?php echo htmlspecialchars($g['slug'], ENT_QUOTES); ?></td>
                <td><?php echo htmlspecialchars($g['created_at'], ENT_QUOTES); ?></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary btn-edit">Edit</button>
                    <button class="btn btn-sm btn-outline-danger btn-delete">Delete</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div class="modal fade" id="gymModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Gym</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="gymForm">
          <input type="hidden" name="id" id="gym_id">
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input class="form-control" name="name" id="gym_name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Slug</label>
            <input class="form-control" name="slug" id="gym_slug" required>
            <div class="form-text">Short identifier used in URLs.</div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" id="saveGym" class="btn btn-primary">Save</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(function(){
    const modalEl = document.getElementById('gymModal');
    const bsModal = new bootstrap.Modal(modalEl);

    $('#btnNew').on('click', function(){ $('#gym_id').val(''); $('#gym_name').val(''); $('#gym_slug').val(''); bsModal.show(); });

    $(document).on('click', '.btn-edit', function(){
        const tr = $(this).closest('tr');
        const id = tr.data('id');
        $('#gym_id').val(id);
        $('#gym_name').val(tr.children().eq(1).text().trim());
        $('#gym_slug').val(tr.children().eq(2).text().trim());
        bsModal.show();
    });

    $('#saveGym').on('click', function(){
        const data = $('#gymForm').serialize();
        $.post('save_gym.php', data, function(resp){
            if (resp && resp.ok) location.reload();
            else alert(resp.error || 'Error');
        }, 'json').fail(function(){ alert('Save failed'); });
    });

    $(document).on('click', '.btn-delete', function(){
        const tr = $(this).closest('tr');
        const id = tr.data('id');
        Swal.fire({title:'Delete gym?', text:tr.find('td').eq(1).text(), icon:'warning', showCancelButton:true}).then(r=>{ if(r.isConfirmed){ $.post('delete_gym.php',{id:id}, function(resp){ if(resp && resp.ok) location.reload(); else Swal.fire('Error', resp.error||'Unable to delete','error'); },'json'); } });
    });
});
</script>
</body>
</html>
