<?php
session_start();
if(!isset($_SESSION['admin_logged'])) { header("Location: login.php"); exit; }
$ruolo_reale = isset($_SESSION['user_role']) ? strtoupper($_SESSION['user_role']) : 'USER';
$supervisore = "CIMÒ";
$versione_software = "V3.5.1 Ultra";
$host = 'database-santo'; $db = 'mio_database'; $user = 'root'; $pass = 'password_segreta';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    function forzaMaiuscolo($str) { return mb_convert_case($str, MB_CASE_UPPER, "UTF-8"); }
    function calcolaEta($data) {
        $n = new DateTime($data); $o = new DateTime();
        return $n->diff($o)->y;
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
} catch (PDOException $e) { $db_error = "Errore Database"; }
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SmartReg Ultra | <?php echo $supervisore; ?></title>
    <link rel="manifest" href="manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap');
        :root { --viola-deep: #4338ca; --viola-bright: #7c3aed; --bg: #fdfdff; }
        body { background-color: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; color: #1e293b; }
        
        .sidebar { background: #0f172a; min-height: 100vh; padding: 2.5rem 1.5rem; color: #fff; position: sticky; top: 0; }
        .sidebar-brand { font-weight: 800; font-size: 1.6rem; background: linear-gradient(45deg, #a78bfa, #f472b6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 3rem; display: block; }
        
        /* Dashboard Header */
        .glass-header { background: linear-gradient(135deg, #4c1d95 0%, #7c3aed 100%); border-radius: 24px; padding: 2rem; color: white; box-shadow: 0 20px 25px -5px rgba(124, 58, 237, 0.2); margin-bottom: 2rem; }
        
        /* Stat Cards con icone grandi */
        .stat-card-white { background: #fff; border: none; border-radius: 20px; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.04); border-bottom: 4px solid #e2e8f0; transition: 0.3s; }
        .stat-card-white:hover { transform: translateY(-5px); border-bottom-color: var(--viola-bright); }
        .icon-box { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 1rem; }
        
        /* Form & Inputs */
        .main-card { background: #fff; border: none; border-radius: 28px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.05); padding: 2rem; }
        .input-custom { border: 2px solid #f1f5f9; border-radius: 14px; padding: 0.8rem; background: #f8fafc; font-size: 0.95rem; transition: all 0.2s ease; }
        .input-custom:focus { border-color: var(--viola-bright); background: #fff; box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.1); outline: none; }
        
        /* Bottoni */
        .btn-viola { background: linear-gradient(135deg, var(--viola-deep) 0%, var(--viola-bright) 100%); border: none; border-radius: 16px; font-weight: 800; padding: 1rem; color: white; transition: 0.3s; box-shadow: 0 10px 15px -3px rgba(124, 58, 237, 0.4); }
        .btn-viola:hover { transform: translateY(-2px); box-shadow: 0 20px 25px -5px rgba(124, 58, 237, 0.5); color: white; }
        
        /* Lista Row Style */
        .grid-row { background: #fff; border-left: 5px solid transparent; padding: 1.2rem; margin-bottom: 0.8rem; border-radius: 16px; transition: 0.2s; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .grid-row:hover { border-left-color: var(--viola-bright); background: #fdfdff; transform: scale(1.01); }
        .cf-tag { background: #faf5ff; color: #7e22ce; font-family: 'Monaco', monospace; padding: 5px 12px; border-radius: 10px; font-size: 0.8rem; font-weight: 700; border: 1px solid #f3e8ff; }
        
        .cf-display-box { background: #1e293b; border-radius: 16px; padding: 20px; text-align: center; color: #a78bfa; margin: 1.5rem 0; border: 1px solid rgba(167, 139, 250, 0.2); }

        @media (max-width: 991px) { .sidebar { display: none; } .glass-header { padding: 1.5rem; } }
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
        <div class="position-absolute bottom-0 mb-4 opacity-25 small">
            Architect: <strong><?php echo $supervisore; ?></strong><br><?php echo $versione_software; ?>
        </div>
    </div>

    <div class="flex-grow-1 p-3 p-md-4">
        <div class="glass-header d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-800 m-0">Registro <span style="color: #a78bfa;"><?php echo $supervisore; ?></span></h2>
                <p class="m-0 opacity-75 small">Sistema di gestione anagrafica centralizzato</p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-light btn-sm rounded-pill px-3 fw-bold"><i class="bi bi-printer me-1"></i> PDF</button>
                <?php if($ruolo_reale == 'ADMIN'): ?>
                <a href="?export_excel=1" class="btn btn-outline-light btn-sm rounded-pill px-3 fw-bold">EXCEL</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-3 mb-4 btn-actions">
            <div class="col-4">
                <div class="stat-card-white">
                    <div class="icon-box bg-primary bg-opacity-10 text-primary"><i class="bi bi-people-fill"></i></div>
                    <span class="stat-val"><?php echo $totale; ?></span>
                    <span class="text-muted small fw-bold">TOTALE ISCRITTI</span>
                </div>
            </div>
            <div class="col-4">
                <div class="stat-card-white">
                    <div class="icon-box bg-info bg-opacity-10 text-info"><i class="bi bi-gender-male"></i></div>
                    <span class="stat-val"><?php echo $uomini; ?></span>
                    <span class="text-muted small fw-bold">UOMINI</span>
                </div>
            </div>
            <div class="col-4">
                <div class="stat-card-white">
                    <div class="icon-box bg-danger bg-opacity-10 text-danger"><i class="bi bi-gender-female"></i></div>
                    <span class="stat-val"><?php echo $donne; ?></span>
                    <span class="text-muted small fw-bold">DONNE</span>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div id="form-col" class="col-lg-4">
                <div class="card main-card">
                    <h5 id="formTitle" class="fw-800 mb-4 text-indigo-900">Anagrafica</h5>
                    <form method="POST">
                        <input type="hidden" name="id_record" id="id_record">
                        <div class="mb-3">
                            <label class="small fw-800 text-muted mb-2 d-block">NOMINATIVO</label>
                            <input type="text" name="nuovo_nome" id="nome" class="form-control input-custom mb-2" placeholder="Nome" required>
                            <input type="text" name="nuovo_cognome" id="cognome" class="form-control input-custom" placeholder="Cognome" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-7"><label class="small fw-800 text-muted mb-2 d-block">NASCITA</label><input type="text" id="datepicker" class="form-control input-custom" placeholder="GG/MM/AAAA" readonly required><input type="hidden" name="data_nascita_db" id="data_db"></div>
                            <div class="col-5"><label class="small fw-800 text-muted mb-2 d-block">GENERE</label><select name="sesso" id="sesso" class="form-select input-custom"><option value="M">M</option><option value="F">F</option></select></div>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-800 text-muted mb-2 d-block">RECAPITI</label>
                            <input type="text" name="luogo_nascita" id="comune_input" class="form-control input-custom mb-2" placeholder="Comune di nascita">
                            <input type="text" name="indirizzo" id="indirizzo" class="form-control input-custom mb-2" placeholder="Indirizzo">
                            <input type="text" name="recapito" id="recapito" class="form-control input-custom" placeholder="Telefono">
                        </div>
                        <div class="cf-display-box">
                            <small class="d-block opacity-50 mb-1 fw-bold">CODICE FISCALE</small>
                            <input type="text" name="nuovo_cf" id="cf_output" class="cf-text w-100 border-0 bg-transparent text-center fw-bold" readonly style="color: inherit; font-size: 1.2rem; letter-spacing: 2px;">
                        </div>
                        <button type="submit" class="btn btn-viola w-100">SALVA DATI</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-800 m-0">ELENCO DATABASE</h6>
                    <div class="position-relative w-50">
                        <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                        <input type="text" id="liveSearch" class="form-control border-0 shadow-sm rounded-pill ps-5 py-2 small" placeholder="Cerca...">
                    </div>
                </div>
                <div id="grid-body">
                    <?php $st = $pdo->query("SELECT * FROM visitatori ORDER BY id DESC");
                    while($v = $st->fetch()) {
                        $v_json = json_encode($v, JSON_HEX_APOS | JSON_HEX_QUOT);
                        $h = (isset($_GET['updated']) && $_GET['updated'] == $v['id']) ? 'style="border-left-color: #10b981;"' : '';
                        echo "<div class='grid-row d-flex justify-content-between align-items-center' $h>
                            <div>
                                <div class='fw-800' style='font-size: 1rem;'>{$v['nome']} {$v['cognome']}</div>
                                <div class='mt-1'><span class='cf-tag'>{$v['codice_fiscale']}</span></div>
                                <div class='text-muted small mt-2'><i class='bi bi-geo-alt me-1'></i>{$v['indirizzo']} - {$v['luogo_nascita']}</div>
                            </div>
                            <div class='text-end'>
                                <div class='fw-bold small mb-2'><i class='bi bi-phone me-1'></i>{$v['recapito']}</div>
                                <div class='azioni-col'>
                                    <button onclick='modificaRecord($v_json)' class='btn btn-sm btn-light rounded-pill px-3'><i class='bi bi-pencil-fill text-primary'></i></button>
                                    <button onclick='confermaElimina({$v['id']}, \"{$v['nome']} {$v['cognome']}\")' class='btn btn-sm btn-light rounded-pill px-3'><i class='bi bi-trash3-fill text-danger'></i></button>
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
    
    function calcolaControllo(cf15) {
        const d = {'0':1,'1':0,'2':5,'3':7,'4':9,'5':13,'6':15,'7':17,'8':19,'9':21,'A':1,'B':0,'C':5,'D':7,'E':9,'F':13,'G':15,'H':17,'I':19,'J':21,'K':2,'L':4,'M':18,'N':20,'O':11,'P':3,'Q':6,'R':8,'S':12,'T':14,'U':16,'V':10,'W':22,'X':25,'Y':24,'Z':23};
        const p = {'0':0,'1':1,'2':2,'3':3,'4':4,'5':5,'6':6,'7':7,'8':8,'9':9,'A':0,'B':1,'C':2,'D':3,'E':4,'F':5,'G':6,'H':7,'I':8,'J':9,'K':10,'L':11,'M':12,'N':13,'O':14,'P':15,'Q':16,'R':17,'S':18,'T':19,'U':20,'V':21,'W':22,'X':23,'Y':24,'Z':25};
        let s=0; for(let i=0; i<15; i++) s += ((i+1)%2 !== 0) ? d[cf15[i]] : p[cf15[i]];
        return String.fromCharCode(65 + (s%26));
    }

    function generaCF() {
        const n=$("#nome").val(), c=$("#cognome").val(), dIn=$("#datepicker").val(), s=$("#sesso").val();
        if (n && c && dIn.length === 10 && belfiore) {
            let p = dIn.split('/'); $("#data_db").val(p[2] + "-" + p[1] + "-" + p[0]);
            let cf = getLetters(c, false) + getLetters(n, true) + p[2].slice(-2) + ['A','B','C','D','E','H','L','M','P','R','S','T'][parseInt(p[1])-1];
            let gg = parseInt(p[0]); if (s==='F') gg += 40;
            cf += gg.toString().padStart(2, '0') + belfiore;
            $("#cf_output").val(cf.toUpperCase() + calcolaControllo(cf.toUpperCase()));
        }
    }

    $("#datepicker").datepicker({ dateFormat: "dd/mm/yy", changeMonth:true, changeYear:true, yearRange:"1920:2026", onSelect: generaCF });
    $("#comune_input").autocomplete({ source: "cerca_comuni.php", select: function(e, ui) { $(this).val(ui.item.value); belfiore = ui.item.codice; generaCF(); return false; } });
    
    function getLetters(str, isName) {
        let s = str.toUpperCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[^A-Z]/g, '');
        let c = s.replace(/[AEIOU]/g, ''); let v = s.replace(/[^AEIOU]/g, '');
        if (isName && c.length >= 4) return c[0] + c[2] + c[3];
        return (c + v + "XXX").substring(0, 3);
    }

    window.confermaElimina = function(id, n) { 
        Swal.fire({ title: 'Sicuro di eliminare?', text: n, icon: 'warning', showCancelButton: true, confirmButtonColor: '#7c3aed', confirmButtonText: 'Sì, elimina', cancelButtonText: 'No' }).then((res) => { if (res.isConfirmed) window.location.href = "?delete=" + id; }); 
    }

    window.modificaRecord = function(d) {
        $("#formTitle").text("Modifica"); $("#id_record").val(d.id);
        $("#nome").val(d.nome); $("#cognome").val(d.cognome); $("#sesso").val(d.sesso);
        $("#comune_input").val(d.luogo_nascita); $("#indirizzo").val(d.indirizzo); $("#recapito").val(d.recapito);
        let dt = d.data_nascita.split('-'); $("#datepicker").val(dt[2]+'/'+dt[1]+'/'+dt[0]);
        $("#cf_output").val(d.codice_fiscale); $("#data_db").val(d.data_nascita);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    $("input, select").on("change keyup", generaCF);
});
</script>
</body>
</html>