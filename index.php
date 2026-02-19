<?php
session_start();
if(!isset($_SESSION['admin_logged'])) { header("Location: login.php"); exit; }
$ruolo_reale = isset($_SESSION['user_role']) ? strtoupper($_SESSION['user_role']) : 'USER';
$supervisore = "CIMÒ";
$versione_software = "V3.5.6 Search-Fixed";
$host = 'database-santo'; $db = 'mio_database'; $user = 'root'; $pass = 'password_segreta';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    if (isset($_GET['check_cf'])) {
        $cf_da_controllare = strtoupper($_GET['check_cf']);
        $escludi_id = $_GET['exclude_id'] ?? 0;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM visitatori WHERE codice_fiscale = ? AND id != ?");
        $stmt->execute([$cf_da_controllare, $escludi_id]);
        echo json_encode(['exists' => $stmt->fetchColumn() > 0]);
        exit;
    }

    function forzaMaiuscolo($str) { return mb_convert_case($str, MB_CASE_UPPER, "UTF-8"); }
    
    if (isset($_GET['export_excel']) && $_SESSION['user_role'] == 'admin') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=registro_'.date('d-m-Y').'.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'NOME', 'COGNOME', 'CF', 'DATA NASCITA', 'COMUNE', 'INDIRIZZO', 'RECAPITO', 'SESSO']);
        $rs = $pdo->query("SELECT * FROM visitatori ORDER BY id DESC");
        while ($r = $rs->fetch()) fputcsv($out, $r);
        exit;
    }

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
    $uomini = $pdo->query("SELECT COUNT(*) FROM visitatori WHERE sesso='M'")->fetchColumn() ?: 0;
    $donne = $pdo->query("SELECT COUNT(*) FROM visitatori WHERE sesso='F'")->fetchColumn() ?: 0;
    // Server-side search endpoint used by AJAX live search
    if (isset($_GET['search'])) {
        $q = trim($_GET['search']);
        if ($q === '') {
            $rs = $pdo->query("SELECT * FROM visitatori ORDER BY id DESC");
        } else {
            $like = "%" . $q . "%";
            $stmt = $pdo->prepare("SELECT * FROM visitatori WHERE CONCAT_WS(' ', nome, cognome, codice_fiscale, luogo_nascita, indirizzo, recapito) LIKE ? ORDER BY id DESC");
            $stmt->execute([$like]);
            $rs = $stmt;
        }
        $out = '';
        while ($v = $rs->fetch()) {
            $v_json = json_encode($v, JSON_HEX_APOS | JSON_HEX_QUOT);
            $name = htmlspecialchars($v['nome'] . ' ' . $v['cognome'], ENT_QUOTES);
            $cf = htmlspecialchars($v['codice_fiscale'], ENT_QUOTES);
            $luogo = htmlspecialchars($v['luogo_nascita'], ENT_QUOTES);
            $indirizzo = htmlspecialchars($v['indirizzo'], ENT_QUOTES);
            $recap = htmlspecialchars($v['recapito'], ENT_QUOTES);
            $id = (int)$v['id'];
            $out .= "<div class='grid-row justify-content-between align-items-center'>";
            $out .= "<div style='flex:1'>";
            $out .= "<div class='fw-800'>{$name}</div>";
            $out .= "<div class='mt-1'><span class='cf-tag'>{$cf}</span></div>";
            $out .= "<div class='text-muted small mt-2'>{$luogo} | {$indirizzo} | {$recap}</div>";
            $out .= "</div>";
            $out .= "<div class='azioni-col'>";
            $out .= "<button onclick='modificaRecord($v_json)' class='btn btn-sm btn-light border-0 rounded-circle me-1'><i class='bi bi-pencil-fill text-primary'></i></button>";
            $out .= "<button onclick='confermaElimina({$id}, \"" . addslashes($name) . "\")' class='btn btn-sm btn-light border-0 rounded-circle'><i class='bi bi-trash3-fill text-danger'></i></button>";
            $out .= "</div></div>";
        }
        echo $out;
        exit;
    }
} catch (PDOException $e) { $db_error = "Errore Database"; }
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard | Registro Digitale</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        :root { --viola-deep: #4338ca; --viola-bright: #7c3aed; --bg: #f8fafc; }
        body { background-color: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; color: #1e293b; }
        .sidebar { background: #0f172a; min-height: 100vh; padding: 2.5rem 1.5rem; color: #fff; position: sticky; top: 0; }
        .sidebar-brand { font-weight: 800; font-size: 1.6rem; background: linear-gradient(45deg, #a78bfa, #f472b6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 3rem; display: block; }
        .glass-header { background: linear-gradient(135deg, #4c1d95 0%, #7c3aed 100%); border-radius: 24px; padding: 2rem; color: white; box-shadow: 0 20px 25px -5px rgba(124, 58, 237, 0.3); margin-bottom: 2rem; }
        .stat-card-white { background: #fff; border: none; border-radius: 20px; padding: 1.2rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.04); border-bottom: 4px solid #e2e8f0; height: 100%; }
        .stat-val { font-size: 1.4rem; font-weight: 800; display: block; }
        .main-card { background: #fff; border: none; border-radius: 28px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.05); padding: 2rem; }
        .input-custom { border: 2px solid #f1f5f9; border-radius: 14px; padding: 0.8rem; background: #f8fafc; font-size: 0.95rem; }
        .input-custom:focus { border-color: var(--viola-bright); background: #fff; outline: none; }
        .btn-viola { background: linear-gradient(135deg, var(--viola-deep) 0%, var(--viola-bright) 100%); border: none; border-radius: 16px; font-weight: 800; padding: 1rem; color: white; }
        .grid-row { background: #fff; border-left: 5px solid transparent; padding: 1.2rem; margin-bottom: 0.8rem; border-radius: 16px; transition: 0.2s; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex !important; }
        .grid-row:hover { border-left-color: var(--viola-bright); transform: scale(1.01); }
        .cf-tag { background: #faf5ff; color: #7e22ce; font-family: 'Monaco', monospace; padding: 5px 12px; border-radius: 10px; font-size: 0.8rem; font-weight: 700; border: 1px solid #f3e8ff; }
        .cf-display-box { background: #1e293b; border-radius: 16px; padding: 20px; text-align: center; color: #a78bfa; margin: 1.5rem 0; border: 2px solid transparent; }
        .cf-error-state { background: #fee2e2 !important; color: #ef4444 !important; border-color: #ef4444 !important; }
        @media print {
            .sidebar, .glass-header, #form-col, .search-box, .azioni-col, .btn-sm, .stat-row { display: none !important; }
            body { background: white !important; }
            .col-lg-8 { width: 100% !important; max-width: 100% !important; flex: 0 0 100% !important; }
            .grid-row { border: 1px solid #eee !important; border-radius: 0 !important; margin: 0 !important; break-inside: avoid; border-left: none !important; }
        }
    </style>
</head>
<body>
<div class="container-fluid p-0 d-flex">
    <div class="sidebar" style="width: 260px;">
        <span class="sidebar-brand">SmartReg.</span>
        <div class="nav flex-column gap-3">
            <a href="index.php" class="nav-link text-white p-0 small fw-bold"><i class="bi bi-grid-1x2 me-2"></i> Dashboard</a>
            <a href="?logout=1" class="nav-link text-danger p-0 small fw-bold mt-4"><i class="bi bi-power me-2"></i> Esci</a>
        </div>
        <div class="position-absolute bottom-0 mb-4 opacity-25 small">Architect: <strong><?php echo $supervisore; ?></strong></div>
    </div>

    <div class="flex-grow-1 p-3 p-md-4">
        <div class="glass-header d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-800 m-0">Registro Digitale</h2>
                <p class="m-0 opacity-75 small">Gestione professionale anagrafiche</p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-light btn-sm rounded-pill px-3 fw-bold">PDF</button>
                <?php if($ruolo_reale == 'ADMIN'): ?><a href="?export_excel=1" class="btn btn-outline-light btn-sm rounded-pill px-3 fw-bold">EXCEL</a><?php endif; ?>
            </div>
        </div>

        <div class="row g-3 mb-4 stat-row">
            <div class="col-4"><div class="stat-card-white"><span class="text-muted small fw-bold d-block mb-1">TOTALE</span><span class="stat-val text-primary"><?php echo $totale; ?></span></div></div>
            <div class="col-4"><div class="stat-card-white"><span class="text-muted small fw-bold d-block mb-1">UOMINI</span><span class="stat-val text-info"><?php echo $uomini; ?></span></div></div>
            <div class="col-4"><div class="stat-card-white"><span class="text-muted small fw-bold d-block mb-1">DONNE</span><span class="stat-val text-danger"><?php echo $donne; ?></span></div></div>
        </div>

        <div class="row g-4">
            <div id="form-col" class="col-lg-4">
                <div class="card main-card">
                    <h5 id="formTitle" class="fw-800 mb-4">Anagrafica</h5>
                    <form method="POST" id="mainForm">
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
                            <input type="text" name="luogo_nascita" id="comune_input" class="form-control input-custom mb-2" placeholder="Comune di nascita">
                            <input type="text" name="indirizzo" id="indirizzo" class="form-control input-custom mb-2" placeholder="Indirizzo">
                            <input type="text" name="recapito" id="recapito" class="form-control input-custom" placeholder="Telefono">
                        </div>
                        <div class="cf-display-box" id="cf_box_ui">
                            <small class="d-block opacity-50 mb-1 fw-bold" id="cf_label">CODICE FISCALE</small>
                            <input type="text" name="nuovo_cf" id="cf_output" class="w-100 border-0 bg-transparent text-center fw-bold" readonly style="color: inherit; font-size: 1.2rem; letter-spacing: 2px;">
                        </div>
                        <button type="submit" id="submitBtn" class="btn btn-viola w-100">SALVA</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-3 search-box">
                    <h6 class="fw-800 m-0">ISCRITTI</h6>
                    <input type="text" id="liveSearch" class="form-control border-0 shadow-sm rounded-pill w-50 ps-3" placeholder="🔍 Cerca...">
                </div>
                <div id="grid-body">
                    <?php
                    $st = $pdo->query("SELECT * FROM visitatori ORDER BY id DESC");
                    while($v = $st->fetch()) {
                        $v_json = json_encode($v, JSON_HEX_APOS | JSON_HEX_QUOT);
                        $name = htmlspecialchars($v['nome'] . ' ' . $v['cognome'], ENT_QUOTES);
                        $cf = htmlspecialchars($v['codice_fiscale'], ENT_QUOTES);
                        $luogo = htmlspecialchars($v['luogo_nascita'], ENT_QUOTES);
                        $indirizzo = htmlspecialchars($v['indirizzo'], ENT_QUOTES);
                        $recap = htmlspecialchars($v['recapito'], ENT_QUOTES);
                        $id = (int)$v['id'];
                        echo "<div class='grid-row justify-content-between align-items-center'>";
                        echo "<div style='flex:1'>";
                        echo "<div class='fw-800'>{$name}</div>";
                        echo "<div class='mt-1'><span class='cf-tag'>{$cf}</span></div>";
                        echo "<div class='text-muted small mt-2'>{$luogo} | {$indirizzo} | {$recap}</div>";
                        echo "</div>";
                        echo "<div class='azioni-col'>";
                        echo "<button onclick='modificaRecord($v_json)' class='btn btn-sm btn-light border-0 rounded-circle me-1'><i class='bi bi-pencil-fill text-primary'></i></button>";
                        echo "<button onclick='confermaElimina({$id}, \"" . addslashes($name) . "\")' class='btn btn-sm btn-light border-0 rounded-circle'><i class='bi bi-trash3-fill text-danger'></i></button>";
                        echo "</div></div>";
                    }
                    ?>
                </div>
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

    // --- NUOVA LIVE SEARCH (server-backed, debounced) ---
    let searchTimer;
    $("#liveSearch").on("input", function() {
        clearTimeout(searchTimer);
        const q = $(this).val().trim();
        searchTimer = setTimeout(function() {
            $.get("?search=" + encodeURIComponent(q), function(html) {
                $("#grid-body").html(html);
            });
        }, 250);
    });

    function calcolaControllo(cf15) {
        const d = {'0':1,'1':0,'2':5,'3':7,'4':9,'5':13,'6':15,'7':17,'8':19,'9':21,'A':1,'B':0,'C':5,'D':7,'E':9,'F':13,'G':15,'H':17,'I':19,'J':21,'K':2,'L':4,'M':18,'N':20,'O':11,'P':3,'Q':6,'R':8,'S':12,'T':14,'U':16,'V':10,'W':22,'X':25,'Y':24,'Z':23};
        const p = {'0':0,'1':1,'2':2,'3':3,'4':4,'5':5,'6':6,'7':7,'8':8,'9':9,'A':0,'B':1,'C':2,'D':3,'E':4,'F':5,'G':6,'H':7,'I':8,'J':9,'K':10,'L':11,'M':12,'N':13,'O':14,'P':15,'Q':16,'R':17,'S':18,'T':19,'U':20,'V':21,'W':22,'X':23,'Y':24,'Z':25};
        let s=0; for(let i=0; i<15; i++) s += ((i+1)%2 !== 0) ? d[cf15[i]] : p[cf15[i]];
        return String.fromCharCode(65 + (s%26));
    }

    function checkUniqueCF(cf) {
        if(cf.length === 16) {
            $.getJSON("?check_cf=" + cf + "&exclude_id=" + ($("#id_record").val() || 0), function(data) {
                if(data.exists) {
                    $("#cf_box_ui").addClass("cf-error-state");
                    $("#cf_label").text("GIÀ IN ARCHIVIO");
                    $("#submitBtn").prop("disabled", true);
                } else {
                    $("#cf_box_ui").removeClass("cf-error-state");
                    $("#cf_label").text("CODICE FISCALE");
                    $("#submitBtn").prop("disabled", false);
                }
            });
        }
    }

    function generaCF() {
        const n=$("#nome").val(), c=$("#cognome").val(), dIn=$("#datepicker").val(), s=$("#sesso").val();
        if (n && c && dIn.length === 10 && belfiore) {
            let p = dIn.split('/'); $("#data_db").val(p[2] + "-" + p[1] + "-" + p[0]);
            let cf = getLetters(c, false) + getLetters(n, true) + p[2].slice(-2) + ['A','B','C','D','E','H','L','M','P','R','S','T'][parseInt(p[1])-1];
            let gg = parseInt(p[0]); if (s==='F') gg += 40;
            cf += gg.toString().padStart(2, '0') + belfiore;
            let finale = cf.toUpperCase() + calcolaControllo(cf.toUpperCase());
            $("#cf_output").val(finale);
            checkUniqueCF(finale);
        }
    }

    function getLetters(str, isName) {
        let s = str.toUpperCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[^A-Z]/g, '');
        let c = s.replace(/[AEIOU]/g, ''); let v = s.replace(/[^AEIOU]/g, '');
        if (isName && c.length >= 4) return c[0] + c[2] + c[3];
        return (c + v + "XXX").substring(0, 3);
    }

    $("#datepicker").datepicker({ dateFormat: "dd/mm/yy", changeMonth:true, changeYear:true, yearRange:"1920:2026", onSelect: generaCF });
    $("#comune_input").autocomplete({ source: "cerca_comuni.php", select: function(e, ui) { $(this).val(ui.item.value); belfiore = ui.item.codice; generaCF(); return false; } });
    
    window.confermaElimina = function(id, n) { 
        Swal.fire({ title: 'Elimina?', text: n, icon: 'warning', showCancelButton: true, confirmButtonColor: '#7c3aed', confirmButtonText: 'Elimina' }).then((r) => { if (r.isConfirmed) window.location.href="?delete="+id; }); 
    }

    window.modificaRecord = function(d) {
        $("#formTitle").text("Modifica"); $("#id_record").val(d.id);
        $("#nome").val(d.nome); $("#cognome").val(d.cognome); $("#sesso").val(d.sesso);
        $("#comune_input").val(d.luogo_nascita); $("#indirizzo").val(d.indirizzo); $("#recapito").val(d.recapito);
        let dt = d.data_nascita.split('-'); $("#datepicker").val(dt[2]+'/'+dt[1]+'/'+dt[0]);
        $("#cf_output").val(d.codice_fiscale); $("#data_db").val(d.data_nascita);
        $("#cf_box_ui").removeClass("cf-error-state"); $("#submitBtn").prop("disabled", false);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    $("input, select").on("change keyup", generaCF);
});
</script>
</body>
</html>