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
    <style>
      @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
      :root { --viola-deep: #4338ca; --viola-bright: #7c3aed; }
      body { font-family: 'Plus Jakarta Sans', sans-serif; background: linear-gradient(180deg,#f1f5f9,#f8fafc); color:#0f172a; }
      .container { max-width: 980px; }
      .btn-viola { background: linear-gradient(135deg, var(--viola-deep), var(--viola-bright)); color: #fff; border: none; box-shadow: 0 10px 30px rgba(124,58,237,0.12); border-radius: 12px; font-weight:700; }
      .btn-viola:hover { transform: translateY(-2px); }
      .main-table { background:#fff; border-radius:12px; box-shadow: 0 12px 30px rgba(2,6,23,0.04); }
      .modal-content { border-radius:12px; overflow:hidden; }
      .form-control { border-radius:10px; }
    </style>
</head>
<body class="p-4">
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Gym Management</h3>
        <div>
            <a href="index.php" class="btn btn-secondary">Back</a>
        <button id="btnNew" class="btn btn-viola">New Gym</button>
        </div>
    </div>

    <table class="table table-striped main-table">
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
        <button type="button" id="saveGym" class="btn btn-viola">Save</button>
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
