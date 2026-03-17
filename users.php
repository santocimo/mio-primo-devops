<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/inc/security.php';
$role = isset($_SESSION['user_role']) ? trim(strtoupper($_SESSION['user_role'])) : '';
$is_global_admin = isset($_SESSION['admin_logged']) && $role !== '' && (strpos($role, 'ADMIN') !== false || strpos($role, 'SUPER') !== false);
if (!$is_global_admin) {
    header('HTTP/1.1 403 Forbidden');
    echo "Access denied";
    exit;
}
$pdo = getPDO();
$users = $pdo->query("SELECT id,username,role,gym_id,created_at FROM users ORDER BY username")->fetchAll();
$gyms = $pdo->query("SELECT id,name FROM gyms ORDER BY name")->fetchAll();
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Users</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables (Bootstrap 5) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
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
      .main-table thead th { background: linear-gradient(90deg, var(--viola-deep), var(--viola-bright)); color: #fff; border: 0; }
      .main-table thead tr { border-top-left-radius:12px; border-top-right-radius:12px; }
      .main-table tbody tr:hover { transform: translateX(4px); transition: transform .12s ease; }
      .modal-content { border-radius:12px; overflow:hidden; }
      .form-control { border-radius:10px; }
    </style>
  <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
</head>
<body class="p-4">
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>User Management</h3>
        <div>
            <a href="index.php" class="btn btn-secondary">Back</a>
        <button id="btnNewUser" class="btn btn-viola">New User</button>
        </div>
    </div>

    <table class="table table-hover align-middle main-table">
      <thead>
        <tr class="text-muted small"><th>Username</th><th>Role</th><th>Gym</th><th>Actions</th></tr>
      </thead>
      <tbody id="userList">
      <?php foreach($users as $u): ?>
        <tr data-id="<?php echo (int)$u['id']; ?>">
          <td><?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?></td>
          <td>
            <?php $r = htmlspecialchars($u['role'], ENT_QUOTES);
                $badge = 'bg-secondary';
                if ($r === 'SUPER') $badge = 'bg-dark';
                elseif ($r === 'OPERATORE') $badge = 'bg-info text-dark';
            ?>
            <span class="badge <?php echo $badge; ?>"><?php echo $r; ?></span>
          </td>
          <td><?php echo $u['gym_id'] ? htmlspecialchars($u['gym_id'], ENT_QUOTES) : '-'; ?></td>
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
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="userForm">
          <input type="hidden" name="id" id="user_id">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES); ?>">
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input class="form-control" name="username" id="user_username" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input class="form-control" name="password" id="user_password" placeholder="Leave blank to keep current">
          </div>
          <div class="mb-3">
            <label class="form-label">Role</label>
            <select class="form-select" name="role" id="user_role">
                <option value="SUPER">SUPER</option>
                <option value="ADMIN">ADMIN</option>
                <option value="OPERATORE">OPERATORE</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Gym (optional)</label>
            <select class="form-select" name="gym_id" id="user_gym">
                <option value="">-- none --</option>
                <?php foreach($gyms as $g): ?>
                    <option value="<?php echo (int)$g['id']; ?>"><?php echo htmlspecialchars($g['name'], ENT_QUOTES); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Assigning a gym binds the user to that gym.</div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" id="saveUser" class="btn btn-viola">Save</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function(){
    const modalEl = document.getElementById('userModal');
    const bsModal = new bootstrap.Modal(modalEl);
    $('#btnNewUser').on('click', function(){ $('#user_id').val(''); $('#user_username').val(''); $('#user_password').val(''); $('#user_role').val('OPERATORE'); $('#user_gym').val(''); bsModal.show(); });
    $(document).on('click', '.btn-edit', function(){
      const tr = $(this).closest('tr');
      const id = tr.data('id');
      $('#user_id').val(id);
      $('#user_username').val(tr.children().eq(0).text().trim());
      $('#user_role').val(tr.children().eq(1).text().trim());
      $('#user_gym').val(tr.children().eq(2).text().trim() || '');
      $('#user_password').val('');
      bsModal.show();
    });
    $('#saveUser').on('click', function(){
        const data = $('#userForm').serialize();
        $.post('save_user.php', data, function(resp){ if (resp && resp.ok) location.reload(); else alert(resp.error||'Error'); }, 'json').fail(function(){ alert('Save failed'); });
    });
    $(document).on('click', '.btn-delete', function(){
      const tr = $(this).closest('tr'); const id = tr.data('id');
      Swal.fire({title:'Delete user?', text:tr.find('td').eq(0).text(), icon:'warning', showCancelButton:true}).then(r=>{ if(r.isConfirmed){ $.post('delete_user.php',{id:id, csrf: $('meta[name="csrf-token"]').attr('content')}, function(resp){ if(resp && resp.ok) location.reload(); else Swal.fire('Error', resp.error||'Unable to delete','error'); },'json'); } });
    });
});
</script>
  <script>
  // initialize DataTables for improved UX
  $(document).ready(function(){
    $('.main-table').DataTable({
      responsive: true,
      pageLength: 10,
      lengthChange: false,
      columnDefs: [{ orderable: false, targets: -1 }]
    });
  });
  </script>
</body>
</html>
