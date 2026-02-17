<?php
session_start();
if(!isset($_SESSION['admin_logged'])) { header("Location: login.php"); exit; }
$ruolo_reale = isset($_SESSION['user_role']) ? strtoupper($_SESSION['user_role']) : 'USER';
$supervisore = "CIMÒ";
$versione_software = "V3.5.0 Premium";
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
    <title>SmartReg Premium | <?php echo $supervisore; ?></title>
    <link rel="manifest" href="manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap');
        :root { --accent: #6366f1; --accent-dark: #4f46e5; --sidebar: #0f172a; --bg: #f8fafc; }
        body { background-color: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; color: #1e293b; }
        
        /* Sidebar elegante */
        .sidebar { background: var(--sidebar); min-height: 100vh; padding: 2rem; color: #fff; position: sticky; top: 0; box-shadow: 10px 0 30px rgba(0,0,0,0.05); }
        .sidebar-brand { font-weight: 800; letter-spacing: -1px; font-size: 1.5rem; margin-bottom: 3rem; display: block; background: linear-gradient(to right, #fff, #94a3b8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        
        /* Card Statistiche Ammalianti */
        .stat-card { border: none; border-radius: 20px; padding: 1.2rem; transition: transform 0.3s; position: relative; overflow: hidden; }
        .stat-card:hover { transform: translateY(-5px); }
        .card-total { background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); color: white; }
        .card-male { background: #fff; border: 1px solid #e2e8f0; }
        .card-female { background: #fff; border: 1px solid #e2e8f0; }
        .stat-val { font-size: 1.5rem; font-weight: 800; display: block; }
        .stat-label { font-size: 0.65rem; text-transform: uppercase; font-weight: 700; opacity: 0.8; letter-spacing: 1px; }

        /* Pulsanti e Input */
        .btn-premium { background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%); border: none; border-radius: 12px; font-weight: 700; padding: 0.8rem; box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3); transition: 0.3s; }
        .btn-premium:hover { transform: scale(1.02); opacity: 0.9; }
        .btn-pdf { background: #fff; border: 1px solid #e2e8f0; color: #1e293b; border-radius: 12px; font-weight: 700; transition: 0.3s; }
        .btn-pdf:hover { background: #f1f5f9; }
        
        .main-card { background: #fff; border: none; border-radius: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -2px rgba(0,0,0,0.05); padding: 2rem; }
        .input-custom { border: 1px solid #e2e8f0; border-radius: 12px; padding: 0.75rem; background: #f8fafc; font-size: 0.9rem; transition: 0.2s; }
        .input-custom:focus { border-color: var(--accent); box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1); background: #fff; }

        /* Lista Iscritti */
        .grid-header { font-weight: 700; color: #64748b; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 1px; padding: 1rem 0; }
        .grid-row { padding: 1.2rem 0; border-bottom: 1px solid #f1f5f9; transition: 0.2s; border-radius: 12px; margin-bottom: 5px; }
        .grid-row:hover { background: #f1f5f9; }
        
        .cf-badge { background: #eef2ff; color: #4338ca; font-family: 'Monaco', monospace; padding: 4px 8px; border-radius: 8px; font-size: 0.75rem; font-weight: 600; }
        
        @media (max-width: 991px) { .sidebar { display: none; } .main-card { padding: 1.2rem; } }
        @media print { .sidebar, .btn-actions, #form-col, .search-box, .azioni-col { display: none !important; } .col-lg-8 { width: 100% !important; flex: 0 0 100% !important; } }
    </style>
</head>
<body>
<div class="container-fluid p-0 d-flex">
    <div class="sidebar" style="width: 260px;">
        <span class="sidebar-brand">SmartReg.</span>
        <div class="mb-5">
            <small class="text-muted d-block text-uppercase mb-1" style="font-size: 0.6rem; letter-spacing: 1px;">Operatore Corrente</small>
            <div class="d-flex align-items-center">
                <div class="bg-primary rounded-circle me-2" style="width:10px; height:10px;"></div>
                <span class="fw-bold small text-white"><?php echo $ruolo_reale; ?></span>
            </div>
        </div>
        <nav class="nav flex-column gap-2">
            <a href="index.php" class="nav-link text-white opacity-75 small p-0"><i class="bi bi-grid-1x2 me-2"></i> Dashboard</a>
            <a href="?logout=1" class="nav-link text-danger small p-0 mt-4 fw-bold"><i class="bi bi-box-arrow-left me-2"></i> ESCI</a>
        </nav>
        <div class="footer-sig" style="bottom: 30px; opacity: 0.3;">
            <small>Arch: <strong><?php echo $supervisore; ?></strong></small><br>
            <small><?php echo $versione_software; ?></small>
        </div>
    </div>

    <div class="flex-grow-1 p-3 p-md-5">
        <div class="d-flex justify-content-between align-items-end mb-5 btn-actions">
            <div>
                <h1 class="fw-800 h3 m-0">Registro <span class="text-primary">CIMÒ</span></h1>
                <p class="text-muted small m-0">Gestione iscritti e anagrafiche digitali</p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-pdf btn-sm px-3"><i class="bi bi-file-earmark-pdf me-2"></i>Stampa</button>
                <?php if($ruolo_reale == 'ADMIN'): ?>
                <a href="?export_excel=1" class="btn btn-pdf btn-sm px-3"><i class="bi bi-download me-2"></i>Excel</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-3 mb-5 btn-actions">
            <div class="col-4">
                <div class="stat-card card-total">
                    <span class="stat-label">Totale Iscritti</span>
                    <span class="stat-val"><?php echo $totale; ?></span>
                </div>
            </div>
            <div class="col-4">
                <div class="stat-card card-male">
                    <span class="stat-label text-primary">Uomini</span>
                    <span class="stat-val text-primary"><i class="bi bi-gender-male me-1"></i><?php echo $uomini; ?></span>
                </div>
            </div>
            <div class="col-4">
                <div class="stat-card card-female">
                    <span class="stat-label text-danger">Donne</span>
                    <span class="stat-val text-danger"><i class="bi bi-gender-female me-1"></i><?php echo $donne; ?></span>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div id="form-col" class="col-lg-4">
                <div class="card main-card">
                    <h5 id="formTitle" class="fw-bold mb-4">Nuova Anagrafica</h5>
                    <form method="POST">
                        <input type="hidden" name="id_record" id="id_record">
                        <div class="mb-3">
                            <label class="label-custom">Nome e Cognome</label>
                            <div class="row g-2">
                                <div class="col-6"><input type="text" name="nuovo_nome" id="nome" class="form-control input-custom" placeholder="Nome" required></div>
                                <div class="col-6"><input type="text" name="nuovo_cognome" id="cognome" class="form-control input-custom" placeholder="Cognome" required></div>
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-7"><label class="label-custom">Data di Nascita</label><input type="text" id="datepicker" class="form-control input-custom" placeholder="GG/MM/AAAA" readonly required><input type="hidden" name="data_nascita_db" id="data_db"></div>
                            <div class="col-5"><label class="label-custom">Genere</label><select name="sesso" id="sesso" class="form-select input-custom"><option value="M">Uomo</option><option value="F">Donna</option></select></div>
                        </div>
                        <div class="mb-3"><label class="label-custom">Comune e Indirizzo</label>
                            <input type="text" name="luogo_nascita" id="comune_input" class="form-control input-custom mb-2" placeholder="Cerca Comune...">
                            <input type="text" name="indirizzo" id="indirizzo" class="form-control input-custom" placeholder="Via/Piazza, Civico">
                        </div>
                        <div class="mb-4"><label class="label-custom">Contatto Telefonico</label><input type="text" name="recapito" id="recapito" class="form-control input-custom" placeholder="+39 3XX..."></div>
                        <div class="cf-box mb-4">
                            <small class="stat-label d-block mb-2" style="color: #94a3b8">Codice Fiscale Calcolato</small>
                            <input type="text" name="nuovo_cf" id="cf_output" class="cf-text" style="color: #6366f1; font-size: 1.1rem;" readonly>
                        </div>
                        <button type="submit" class="btn btn-premium w-100 text-white shadow-lg">SALVA REGISTRO</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card main-card">
                    <div class="d-flex justify-content-between align-items-center mb-4 search-box">
                        <h5 class="fw-bold m-0" style="font-size: 0.9rem;">ELENCO ISCRITTI</h5>
                        <input type="text" id="liveSearch" class="form-control form-control-sm border-0 bg-light rounded-pill px-3 w-50" placeholder="🔍 Cerca per nome o CF...">
                    </div>
                    <div class="container-fluid px-0">
                        <div class="row grid-header mx-0 d-none d-md-flex">
                            <div class="col-5">Dati Anagrafici</div>
                            <div class="col-4">Recapito & Indirizzo</div>
                            <div class="col-3 text-end">Operazioni</div>
                        </div>
                        <div id="grid-body">
                            <?php $st = $pdo->query("SELECT * FROM visitatori ORDER BY id DESC");
                            while($v = $st->fetch()) {
                                $v_json = json_encode($v, JSON_HEX_APOS | JSON_HEX_QUOT);
                                $h = (isset($_GET['updated']) && $_GET['updated'] == $v['id']) ? 'highlight-new' : '';
                                echo "<div class='row grid-row mx-0 align-items-center $h'>
                                    <div class='col-7 col-md-5'>
                                        <div class='fw-bold' style='font-size: 0.9rem;'>{$v['nome']} {$v['cognome']}</div>
                                        <span class='cf-badge'>{$v['codice_fiscale']}</span>
                                    </div>
                                    <div class='col-md-4 d-none d-md-block'>
                                        <div class='small fw-600'><i class='bi bi-phone me-1 text-muted'></i>{$v['recapito']}</div>
                                        <div class='text-muted' style='font-size: 0.7rem;'>{$v['indirizzo']} - {$v['luogo_nascita']}</div>
                                    </div>
                                    <div class='col-5 col-md-3 text-end azioni-col'>
                                        <button onclick='modificaRecord($v_json)' class='btn btn-sm p-2 text-primary bg-light border-0 rounded-circle me-1'><i class='bi bi-pencil-fill'></i></button>
                                        <button onclick='confermaElimina({$v['id']}, \"{$v['nome']} {$v['cognome']}\")' class='btn btn-sm p-2 text-danger bg-light border-0 rounded-circle'><i class='bi bi-trash3-fill'></i></button>
                                    </div>
                                </div>";
                            } ?>
                        </div>
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
    $("#comune_input").autocomplete({ 
        source: "cerca_comuni.php", 
        select: function(e, ui) { $(this).val(ui.item.value); belfiore = ui.item.codice; generaCF(); return false; } 
    });

    function getLetters(str, isName) {
        let s = str.toUpperCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[^A-Z]/g, '');
        let c = s.replace(/[AEIOU]/g, ''); let v = s.replace(/[^AEIOU]/g, '');
        if (isName && c.length >= 4) return c[0] + c[2] + c[3];
        return (c + v + "XXX").substring(0, 3);
    }

    window.confermaElimina = function(id, n) { 
        Swal.fire({ title: 'Rimuovere iscritto?', text: n, icon: 'warning', showCancelButton: true, confirmButtonColor: '#4f46e5', cancelButtonText: 'Annulla', confirmButtonText: 'Elimina Ora' }).then((res) => { if (res.isConfirmed) window.location.href = "?delete=" + id; }); 
    }

    window.modificaRecord = function(d) {
        $("#formTitle").text("Modifica Record"); $("#id_record").val(d.id);
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