<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/inc/security.php';
if (!isset($_SESSION['admin_logged']) || strtoupper($_SESSION['user_role']) !== 'ADMIN') {
    header('HTTP/1.1 403 Forbidden');
    echo "Access denied";
    exit;
}
$pdo = getPDO();
$services = $pdo->query("SELECT s.id, s.name, s.slug, g.name AS gym_name FROM services s JOIN gyms g ON g.id = s.gym_id ORDER BY g.name, s.name")->fetchAll();
$appointments = $pdo->query("SELECT a.*, s.name AS service_name, g.name AS gym_name FROM appointments a JOIN services s ON s.id = a.service_id JOIN gyms g ON g.id = s.gym_id ORDER BY a.scheduled_at DESC")->fetchAll();
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Appointments</title>
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
        <h3>Appointment Management</h3>
        <div>
            <a href="index.php" class="btn btn-secondary">Back</a>
            <button id="btnNew" class="btn btn-viola">New Appointment</button>
        </div>
    </div>

    <table class="table table-hover align-middle main-table">
      <thead>
        <tr class="text-muted small"><th>Service</th><th>Location</th><th>Customer</th><th>Email</th><th>Scheduled</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody id="appointmentList">
      <?php foreach($appointments as $a): ?>
        <tr data-id="<?php echo (int)$a['id']; ?>" data-service-id="<?php echo (int)$a['service_id']; ?>" data-status="<?php echo htmlspecialchars($a['status'], ENT_QUOTES); ?>">
          <td><?php echo htmlspecialchars($a['service_name'], ENT_QUOTES); ?></td>
          <td><?php echo htmlspecialchars($a['gym_name'], ENT_QUOTES); ?></td>
          <td><?php echo htmlspecialchars($a['customer_name'], ENT_QUOTES); ?></td>
          <td><?php echo htmlspecialchars($a['customer_email'], ENT_QUOTES); ?></td>
          <td
            class="scheduled-at"
            data-scheduled-at="<?php echo htmlspecialchars($a['scheduled_at'], ENT_QUOTES); ?>"
            data-scheduled-timezone="<?php echo htmlspecialchars($a['scheduled_timezone'] ?? '', ENT_QUOTES); ?>"
          ><?php echo htmlspecialchars($a['scheduled_at'], ENT_QUOTES); ?></td>
          <td><?php echo htmlspecialchars(ucfirst($a['status']), ENT_QUOTES); ?></td>
          <td>
            <button class="btn btn-sm btn-outline-primary btn-edit">Edit</button>
            <button class="btn btn-sm btn-outline-danger btn-delete">Delete</button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
</div>

<div class="modal fade" id="appointmentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Appointment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="appointmentForm">
          <input type="hidden" name="id" id="appointment_id">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES); ?>">
          <input type="hidden" name="scheduled_at_utc" id="appointment_scheduled_at_utc">
          <input type="hidden" name="scheduled_timezone" id="appointment_scheduled_timezone">
          <div class="row gy-3">
            <div class="col-md-6">
              <label class="form-label">Service</label>
              <select class="form-select" name="service_id" id="appointment_service" required>
                  <?php foreach($services as $s): ?>
                      <option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['gym_name'] . ' - ' . $s['name'], ENT_QUOTES); ?></option>
                  <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Status</label>
              <select class="form-select" name="status" id="appointment_status" required>
                  <option value="pending">Pending</option>
                  <option value="confirmed">Confirmed</option>
                  <option value="canceled">Canceled</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Customer Name</label>
              <input class="form-control" name="customer_name" id="appointment_customer_name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Customer Email</label>
              <input type="email" class="form-control" name="customer_email" id="appointment_customer_email">
            </div>
            <div class="col-md-6">
              <label class="form-label">Scheduled At</label>
              <input type="datetime-local" class="form-control" name="scheduled_at" id="appointment_scheduled_at" required>
            </div>
            <div class="col-12">
              <label class="form-label">Notes</label>
              <textarea class="form-control" name="notes" id="appointment_notes" rows="3"></textarea>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" id="saveAppointment" class="btn btn-viola">Save</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function(){
    const modalEl = document.getElementById('appointmentModal');
    const bsModal = new bootstrap.Modal(modalEl);

    function localDateTimeToUtc(value) {
        const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/.exec(value);
        if (!match) throw new Error('Invalid local date and time');
        const [, yearText, monthText, dayText, hourText, minuteText] = match;
        const year = Number(yearText);
        const month = Number(monthText);
        const day = Number(dayText);
        const hour = Number(hourText);
        const minute = Number(minuteText);
        const localDate = new Date(year, month - 1, day, hour, minute);
        if (
            localDate.getFullYear() !== year ||
            localDate.getMonth() !== month - 1 ||
            localDate.getDate() !== day ||
            localDate.getHours() !== hour ||
            localDate.getMinutes() !== minute
        ) {
            throw new Error('The selected local time does not exist');
        }
        return localDate.toISOString();
    }

    function localDateTimeInput(value, timezone) {
        if (!timezone) return value.replace(' ', 'T').substring(0, 16);
        const instant = new Date(`${value.replace(' ', 'T')}Z`);
        const pad = part => String(part).padStart(2, '0');
        return `${instant.getFullYear()}-${pad(instant.getMonth() + 1)}-${pad(instant.getDate())}T${pad(instant.getHours())}:${pad(instant.getMinutes())}`;
    }

    $('.scheduled-at').each(function() {
        const cell = $(this);
        const timezone = cell.attr('data-scheduled-timezone');
        const stored = cell.attr('data-scheduled-at');
        if (!timezone) {
            cell.text(`Timezone unknown: ${stored}`);
            return;
        }
        const instant = new Date(`${stored.replace(' ', 'T')}Z`);
        const format = timeZone => new Intl.DateTimeFormat(undefined, {
            timeZone,
            dateStyle: 'short',
            timeStyle: 'short'
        }).format(instant);
        cell.text(`Italy: ${format('Europe/Rome')} · Local (${timezone}): ${format(timezone)}`);
    });

    $('#btnNew').on('click', function(){
        $('#appointment_id').val('');
        $('#appointment_service').val($('#appointment_service option:first').val());
        $('#appointment_status').val('pending');
        $('#appointment_customer_name').val('');
        $('#appointment_customer_email').val('');
        $('#appointment_scheduled_at').val('');
        $('#appointment_scheduled_at_utc').val('');
        $('#appointment_scheduled_timezone').val('');
        $('#appointment_notes').val('');
        bsModal.show();
    });

    $(document).on('click', '.btn-edit', function(){
      const tr = $(this).closest('tr');
      const id = tr.data('id');
      $('#appointment_id').val(id);
      $('#appointment_service').val(tr.data('service-id'));
      $('#appointment_customer_name').val(tr.children().eq(2).text().trim());
      $('#appointment_customer_email').val(tr.children().eq(3).text().trim());
      const scheduledCell = tr.children().eq(4);
      $('#appointment_scheduled_at').val(localDateTimeInput(
        scheduledCell.attr('data-scheduled-at'),
        scheduledCell.attr('data-scheduled-timezone')
      ));
      $('#appointment_status').val(tr.data('status') || tr.children().eq(5).text().trim().toLowerCase());
      $('#appointment_notes').val('');
      bsModal.show();
    });

    $('#saveAppointment').on('click', function(){
        try {
            $('#appointment_scheduled_at_utc').val(localDateTimeToUtc($('#appointment_scheduled_at').val()));
            const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if (!timezone) throw new Error('The device timezone is unavailable');
            $('#appointment_scheduled_timezone').val(timezone);
        } catch (error) {
            alert('The selected date/time is invalid for this device timezone.');
            return;
        }
        const data = $('#appointmentForm').serialize();
        $.post('save_appointment.php', data, function(resp){
            if (resp && resp.ok) location.reload(); else alert(resp.error || 'Error');
        }, 'json').fail(function(){ alert('Save failed'); });
    });

    $(document).on('click', '.btn-delete', function(){
      const tr = $(this).closest('tr');
      const id = tr.data('id');
      Swal.fire({title:'Delete appointment?', text:tr.find('td').eq(0).text() + ' - ' + tr.find('td').eq(2).text(), icon:'warning', showCancelButton:true}).then(r=>{ if(r.isConfirmed){ $.post('delete_appointment.php',{id:id, csrf: $('meta[name="csrf-token"]').attr('content')}, function(resp){ if(resp && resp.ok) location.reload(); else Swal.fire('Error', resp.error||'Unable to delete','error'); },'json'); } });
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
