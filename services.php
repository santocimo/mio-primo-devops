<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/inc/security.php';
if (!isset($_SESSION['admin_logged']) || strtoupper($_SESSION['user_role']) !== 'ADMIN') {
    header('HTTP/1.1 403 Forbidden');
    echo "Access denied";
    exit;
}
$pdo = getPDO();
$gyms = $pdo->query("SELECT id,name FROM gyms ORDER BY name")->fetchAll();
$services = $pdo->query("SELECT s.*, g.name AS gym_name FROM services s JOIN gyms g ON g.id = s.gym_id ORDER BY g.name, s.name")->fetchAll();
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
      .main-table tbody tr:hover { transform: translateX(4px); transition: transform .12s ease; }
      .modal-content { border-radius:12px; overflow:hidden; }
      .form-control { border-radius:10px; }
    </style>
  <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
</head>
<body class="p-4">
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Service Management</h3>
        <div>
            <a href="index.php" class="btn btn-secondary">Back</a>
            <button id="btnNew" class="btn btn-viola">New Service</button>
        </div>
    </div>

    <table class="table table-hover align-middle main-table">
      <thead>
        <tr class="text-muted small"><th>Name</th><th>Location</th><th>Category</th><th>Duration</th><th>Capacity</th><th>Price</th><th>Actions</th></tr>
      </thead>
      <tbody id="serviceList">
      <?php foreach($services as $s): ?>
        <tr data-id="<?php echo (int)$s['id']; ?>" data-gym-id="<?php echo (int)$s['gym_id']; ?>" data-slug="<?php echo htmlspecialchars($s['slug'], ENT_QUOTES); ?>" data-category="<?php echo htmlspecialchars($s['category'], ENT_QUOTES); ?>" data-duration="<?php echo (int)$s['duration_minutes']; ?>" data-capacity="<?php echo (int)$s['capacity']; ?>" data-price="<?php echo $s['price'] !== null ? number_format($s['price'], 2, '.', '') : ''; ?>" data-description="<?php echo htmlspecialchars($s['description'] ?? '', ENT_QUOTES); ?>">
          <td><?php echo htmlspecialchars($s['name'], ENT_QUOTES); ?></td>
          <td><?php echo htmlspecialchars($s['gym_name'], ENT_QUOTES); ?></td>
          <td><?php echo htmlspecialchars(ucfirst($s['category']), ENT_QUOTES); ?></td>
          <td><?php echo (int)$s['duration_minutes']; ?> min</td>
          <td><?php echo (int)$s['capacity']; ?></td>
          <td><?php echo $s['price'] !== null ? number_format($s['price'], 2, ',', '.') : '-'; ?></td>
          <td>
            <button class="btn btn-sm btn-outline-primary btn-edit">Edit</button>
            <button class="btn btn-sm btn-outline-danger btn-delete">Delete</button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
</div>

<div class="modal fade" id="serviceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Service</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="serviceForm">
          <input type="hidden" name="id" id="service_id">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES); ?>">
          <div class="row gy-3">
            <div class="col-md-6">
              <label class="form-label">Location</label>
              <select class="form-select" name="gym_id" id="service_gym" required>
                  <?php foreach($gyms as $g): ?>
                      <option value="<?php echo (int)$g['id']; ?>"><?php echo htmlspecialchars($g['name'], ENT_QUOTES); ?></option>
                  <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Category</label>
              <select class="form-select" name="category" id="service_category" required>
                  <option value="class">Class</option>
                  <option value="appointment">Appointment</option>
                  <option value="wellness">Wellness</option>
                  <option value="personal">Personal</option>
                  <option value="event">Event</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Name</label>
              <input class="form-control" name="name" id="service_name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Slug</label>
              <input class="form-control" name="slug" id="service_slug" required>
              <div class="form-text">Short identifier used in URLs.</div>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea class="form-control" name="description" id="service_description" rows="3"></textarea>
            </div>
            <div class="col-md-4">
              <label class="form-label">Duration (minutes)</label>
              <input type="number" min="1" max="1440" class="form-control" name="duration_minutes" id="service_duration" value="60" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Capacity</label>
              <input type="number" min="1" max="1000" class="form-control" name="capacity" id="service_capacity" value="10" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Price</label>
              <input type="text" class="form-control" name="price" id="service_price" placeholder="0.00">
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" id="saveService" class="btn btn-viola">Save</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function(){
    const modalEl = document.getElementById('serviceModal');
    const bsModal = new bootstrap.Modal(modalEl);

    $('#btnNew').on('click', function(){
        $('#service_id').val('');
        $('#service_gym').val($('#service_gym option:first').val());
        $('#service_category').val('class');
        $('#service_name').val('');
        $('#service_slug').val('');
        $('#service_description').val('');
        $('#service_duration').val('60');
        $('#service_capacity').val('10');
        $('#service_price').val('');
        bsModal.show();
    });

    $(document).on('click', '.btn-edit', function(){
      const tr = $(this).closest('tr');
      const id = tr.data('id');
      $('#service_id').val(id);
      $('#service_name').val(tr.children().eq(0).text().trim());
      $('#service_gym').val(tr.data('gym-id'));
      $('#service_category').val(tr.data('category') || 'class');
      $('#service_duration').val(tr.data('duration') || '60');
      $('#service_capacity').val(tr.data('capacity') || '10');
      $('#service_price').val(tr.data('price') || '');
      $('#service_slug').val(tr.data('slug') || '');
      $('#service_description').val(tr.data('description') || '');
      bsModal.show();
    });

    $('#saveService').on('click', function(){
        const data = $('#serviceForm').serialize();
        $.post('save_service.php', data, function(resp){
            if (resp && resp.ok) location.reload(); else alert(resp.error || 'Error');
        }, 'json').fail(function(){ alert('Save failed'); });
    });

    $(document).on('click', '.btn-delete', function(){
      const tr = $(this).closest('tr');
      const id = tr.data('id');
      Swal.fire({title:'Delete service?', text:tr.find('td').eq(0).text(), icon:'warning', showCancelButton:true}).then(r=>{ if(r.isConfirmed){ $.post('delete_service.php',{id:id, csrf: $('meta[name="csrf-token"]').attr('content')}, function(resp){ if(resp && resp.ok) location.reload(); else Swal.fire('Error', resp.error||'Unable to delete','error'); },'json'); } });
    });
});
</script>
<script>
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
