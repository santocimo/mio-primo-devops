<?php
session_start();
if(!isset($_SESSION['admin_logged'])) { header("Location: login.php"); exit; }
$ruolo_reale = isset($_SESSION['user_role']) ? strtoupper($_SESSION['user_role']) : 'USER';
$supervisore = "CIMÒ";
$versione_software = "V3.5.2 Pro";
$host = 'database-santo'; $db = 'mio_database'; $user = 'root'; $pass = 'password_segreta';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    function forzaMaiuscolo($str) { return mb_convert_case($str, MB_CASE_UPPER, "UTF-8"); }
    if (isset($_GET['delete'])) {
        $pdo->prepare("DELETE FROM visitatori WHERE id = ?")->execute([$_GET['delete']]);
        header("Location: index.php"); exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuovo_cf'])) {
        $p = [':n' => forzaMaiuscolo($_POST['nuovo_nome']), ':c' => forzaMaiuscolo($_POST['nuovo_cognome']), ':cf' => strtoupper($_POST['nuovo_cf']), ':d' => $_POST['data_nascita_db'], ':l' => forzaMaiuscolo($_POST['luogo_nascita']), ':i' => forzaMaiuscolo($_POST['indirizzo']), ':r' => $_POST['recapito'], ':s' => $_POST['sesso']];
        if (!empty($_POST['id_record'])) {
            $p[':id'] = $_POST['id_record'];
            $sql = "UPDATE visitatori SET nome=:n, cognome=:c, codice_fiscale=:cf, data_nascita=:d, luogo_nascita=:l, indirizzo=:i, recapito=:r, sesso=:s WHERE id=:id";
        } else {
            $sql = "INSERT INTO visitatori (nome, cognome, codice_fiscale, data_nascita, luogo_nascita, indirizzo, recapito, sesso) VALUES (:n,:c,:cf,:d,:l,:i,:r,:s)";
        }
        $pdo->prepare($sql)->execute($p);
        header("Location: index.php"); exit;
    }
    $totale = $pdo->query("SELECT COUNT(*) FROM visitatori")->fetchColumn() ?: 0;
} catch (PDOException $e) { $db_error = "Errore Database"; }
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SmartReg Ultra | <?php echo $supervisore; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        :root { --viola-deep: #4338ca; --viola-bright: #7c3aed; --bg: #fdfdff; }
        body { background-color: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; }
        
        .sidebar { background: #0f172a; min-height: 100vh; padding: 2.5rem 1.5rem; color: #fff; position: sticky; top: 0; }
        .sidebar-brand { font-weight: 800; font-size: 1.6rem; background: linear-gradient(45deg, #a78bfa, #f472b6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 3rem; display: block; }
        .glass-header { background: linear-gradient(135deg, #4c1d95 0%, #7c3aed 100%); border-radius: 24px; padding: 2rem; color: white; box-shadow: 0 20px 25px -5px rgba(124, 58, 237, 0.2); margin-bottom: 2rem; }
        .main-card { background: #fff; border: none; border-radius: 28px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.05); padding: 2rem; }
        .input-custom { border: 2px solid #f1f5f9; border-radius: 14px; padding: 0.8rem; background: #f8fafc; font-size: 0.95rem; transition: all 0.2s ease; }
        .btn-viola { background: linear-gradient(135deg, var(--viola-deep) 0%, var(--viola-bright) 100%); border: none; border-radius: 16px; font-weight: 800; padding: 1rem; color: white; box-shadow: 0 10px 15px -3px rgba(124, 58, 237, 0.4); }
        .grid-row { background: #fff; border-left: 5px solid transparent; padding: 1.2rem; margin-bottom: 0.8rem; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .cf-tag { background: #faf5ff; color: #7e22ce; font-family: 'Monaco', monospace; padding: 5px 12px; border-radius: 10px; font-size: 0.8rem; font-weight: 700; border: 1px solid #f3e8ff; }
        .cf-display-box { background: #1e293b; border-radius: 16px; padding: 20px; text-align: center; color: #a78bfa; margin: 1.5rem 0; }

        /* REGOLE DI STAMPA PDF PER CIMÒ */
        @media print {
            .sidebar, .glass-header, #form-col, .search-box, .azioni-col, .btn-sm { display: none !important; }
            body { background: white !important; padding: 0 !important; }
            .flex-grow-1 { padding: 0 !important; }
            .col-lg-8 { width: 100% !important; max-width: 100% !important; flex: 0 0 100% !important; }
            .main-card { box-shadow: none !important; padding: 0 !important; }
            .grid-row { 
                border: 1px solid #eee !important; 
                margin-bottom: 0 !important; 
                border-radius: 0 !important; 
                break-inside: avoid; 
                display: flex !important;
                border-left: none !important;
            }
            .cf-tag { border: none !important; background: none !important; padding: 0 !important; font-size: 0.9rem !important; color: black !important; }
            h6 { font-size: 1.5rem !important; margin-bottom: 20px !important; text-align: center; }
        }
        @media (max-width: 991px) { .sidebar { display: none; } }
    </style>
</head>
<body>
<div class="container-fluid p-0 d-flex">
    <div class="sidebar" style="width: 260px;">
        <span class="sidebar-brand">SmartReg.</span>
        <div class="nav flex-column gap-3">
            <a href="index.php" class="nav-link text-white p-0 small fw-bold"><i class="bi bi-house-door me-2"></i> Dashboard</a>
            <a href="?logout=1" class="nav-link text-danger p-0 small fw-bold mt-4"><i class="bi bi-power me-2"></i> Logout</a>
        </div>
        <div class="position-absolute bottom-0 mb-4 opacity-25 small">Architect: <strong><?php echo $supervisore; ?></strong></div>
    </div>

    <div class="flex-grow-1 p-3 p-md-4">
        <div class="glass-header d-flex justify-content-between align-items-center">
            <div><h2 class="fw-800 m-0">Registro <span style="color: #a78bfa;"><?php echo $supervisore; ?></span></h2></div>
            <button onclick="window.print()" class="btn btn-light btn-sm rounded-pill px-4 fw-bold">ESPORTA PDF</button>
        </div>

        <div class="row g-4">
            <div id="form-col" class="col-lg-4">
                <div class="card main-card">
                    <h5 id="formTitle" class="fw-800 mb-4">Anagrafica</h5>
                    <form method="POST">
                        <input type="hidden" name="id_record" id="id_record">
                        <div class="mb-3">
                            <input type="text" name="nuovo_nome" id="nome" class="form-control input-custom mb-2" placeholder="Nome" required>
                            <input type="text" name="nuovo_cognome" id="cognome" class="form-control input-custom" placeholder="Cognome" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-7"><input type="text" id="datepicker" class="form-control input-custom" placeholder="Data Nascita" readonly required><input type="hidden" name="data_nascita_db" id="data_db"></div>
                            <div class="col-5"><select name="sesso" id="sesso" class="form-select input-custom"><option value="M">M</option><option value="F">F</option></select></div>
                        </div>
                        <div class="mb-3">
                            <input type="text" name="luogo_nascita" id="comune_input" class="form-control input-custom mb-2" placeholder="Comune">
                            <input type="text" name="indirizzo" id="indirizzo" class="form-control input-custom mb-2" placeholder="Indirizzo">
                            <input type="text" name="recapito" id="recapito" class="form-control input-custom" placeholder="Telefono">
                        </div>
                        <div class="cf-display-box">
                            <input type="text" name="nuovo_cf" id="cf_output" class="w-100 border-0 bg-transparent text-center fw-bold" readonly style="color: inherit; font-size: 1.1rem; letter-spacing: 2px;">
                        </div>
                        <button type="submit" class="btn btn-viola w-100">SALVA</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-3 search-box">
                    <h6 class="fw-800 m-0">ELENCO ISCRITTI (<?php echo $totale; ?>)</h6>
                    <input type="text" id="liveSearch" class="form-control border-0 shadow-sm rounded-pill w-50 ps-3" placeholder="Cerca...">
                </div>
                <div id="grid-body">
                    <?php $st = $pdo->query("SELECT * FROM visitatori ORDER BY id DESC");
                    while($v = $st->fetch()) {
                        $v_json = json_encode($v, JSON_HEX_APOS | JSON_HEX_QUOT);
                        echo "<div class='grid-row d-flex justify-content-between align-items-center'>
                            <div style='flex:1'>
                                <div class='fw-800' style='font-size: 0.95rem;'>{$v['nome']} {$v['cognome']}</div>
                                <div class='mt-1'><span class='cf-tag'>{$v['codice_fiscale']}</span></div>
                                <div class='text-muted small mt-1'>{$v['indirizzo']} - {$v['luogo_nascita']}</div>
                            </div>
                            <div class='text-end' style='min-width: 150px;'>
                                <div class='small fw-bold'>{$v['recapito']}</div>
                                <div class='azioni-col mt-2'>
                                    <button onclick='modificaRecord($v_json)' class='btn btn-sm btn-light border-0 rounded-circle'><i class='bi bi-pencil text-primary'></i></button>
                                    <button onclick='confermaElimina({$v['id']}, \"{$v['nome']}\")' class='btn btn-sm btn-light border-0 rounded-circle'><i class='bi bi-trash text-danger'></i></button>
                                </div>
                            </div>
                        </div>";
                    } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
$(function() {
    let belfiore = "";
    $("#liveSearch").on("keyup", function() {
        let v = $(this).val().toLowerCase();
        $(".grid-row").filter(function() { $(this).toggle($(this).text().toLowerCase().indexOf(v) > -1) });
    });
    // Funzioni CF...
    $("#datepicker").datepicker({ dateFormat: "dd/mm/yy", changeMonth:true, changeYear:true, yearRange:"1920:2026", onSelect: function() { generaCF(); } });
    $("#comune_input").autocomplete({ source: "cerca_comuni.php", select: function(e, ui) { $(this).val(ui.item.value); belfiore = ui.item.codice; generaCF(); return false; } });
    
    window.confermaElimina = function(id, n) { 
        Swal.fire({ title: 'Elimina?', text: n, icon: 'warning', showCancelButton: true, confirmButtonColor: '#7c3aed', confirmButtonText: 'Sì' }).then((r) => { if (r.isConfirmed) window.location.href="?delete="+id; }); 
    }
    window.modificaRecord = function(d) {
        $("#formTitle").text("Modifica"); $("#id_record").val(d.id); $("#nome").val(d.nome); $("#cognome").val(d.cognome);
        $("#sesso").val(d.sesso); $("#comune_input").val(d.luogo_nascita); $("#indirizzo").val(d.indirizzo); $("#recapito").val(d.recapito);
        let dt = d.data_nascita.split('-'); $("#datepicker").val(dt[2]+'/'+dt[1]+'/'+dt[0]);
        $("#cf_output").val(d.codice_fiscale); $("#data_db").val(d.data_nascita);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
});
</script>
</body>
</html>