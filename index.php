<?php
session_start();

if(!isset($_SESSION['admin_logged'])) { header("Location: login.php"); exit; }

$ruolo_reale = isset($_SESSION['user_role']) ? strtoupper($_SESSION['user_role']) : 'USER';
$supervisore = "CIMÒ";
$versione_software = "V3.2 Final";

// Database
$host = 'database-santo'; $db = 'mio_database'; $user = 'root'; $pass = 'password_segreta';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    function forzaMaiuscolo($str) { return mb_convert_case($str, MB_CASE_UPPER, "UTF-8"); }

    // EXPORT EXCEL
    if (isset($_GET['export_excel']) && $_SESSION['user_role'] == 'admin') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=registro_'.date('d-m-Y').'.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'NOME', 'COGNOME', 'CF', 'DATA NASCITA', 'COMUNE', 'INDIRIZZO', 'RECAPITO', 'SESSO']);
        $rows = $pdo->query("SELECT * FROM visitatori ORDER BY id DESC");
        while ($row = $rows->fetch()) fputcsv($output, $row);
        exit;
    }

    // ELIMINAZIONE
    if (isset($_GET['delete'])) {
        $pdo->prepare("DELETE FROM visitatori WHERE id = ?")->execute([$_GET['delete']]);
        header("Location: index.php"); exit;
    }

    // SALVATAGGIO
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuovo_cf'])) {
        $params = [
            ':n' => forzaMaiuscolo($_POST['nuovo_nome']), ':c' => forzaMaiuscolo($_POST['nuovo_cognome']), 
            ':cf' => strtoupper($_POST['nuovo_cf']), ':d' => $_POST['data_nascita_db'], 
            ':l' => forzaMaiuscolo($_POST['luogo_nascita']), ':i' => forzaMaiuscolo($_POST['indirizzo']), 
            ':r' => $_POST['recapito'], ':s' => $_POST['sesso']
        ];
        if (!empty($_POST['id_record'])) {
            $params[':id'] = $_POST['id_record'];
            $sql = "UPDATE visitatori SET nome=:n, cognome=:c, codice_fiscale=:cf, data_nascita=:d, luogo_nascita=:l, indirizzo=:i, recapito=:r, sesso=:s WHERE id=:id";
        } else {
            $sql = "INSERT INTO visitatori (nome, cognome, codice_fiscale, data_nascita, luogo_nascita, indirizzo, recapito, sesso) VALUES (:n,:c,:cf,:d,:l,:i,:r,:s)";
        }
        $pdo->prepare($sql)->execute($params);
        header("Location: index.php"); exit;
    }

    // STATISTICHE PER GRAFICI
    $totale = $pdo->query("SELECT COUNT(*) FROM visitatori")->fetchColumn() ?: 0;
    $uomini = $pdo->query("SELECT COUNT(*) FROM visitatori WHERE sesso='M'")->fetchColumn() ?: 0;
    $donne = $pdo->query("SELECT COUNT(*) FROM visitatori WHERE sesso='F'")->fetchColumn() ?: 0;

} catch (PDOException $e) { $db_error = "Errore Database"; }
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>SmartReg Pro | Dashboard <?php echo $supervisore; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --accent: #7c4dff; --sidebar: #1a1a2e; }
        body { background-color: #f4f7fe; font-family: 'Inter', sans-serif; overflow-x: hidden; }
        .sidebar { background: var(--sidebar); min-height: 100vh; padding: 2rem; color: #fff; position: sticky; top: 0; z-index: 1000; }
        .stat-card { border: none; border-radius: 20px; transition: 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .main-card { background: #fff; border: none; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 1.5rem; height: 100%; }
        .cf-box { background: linear-gradient(135deg, #7c4dff 0%, #64b5f6 100%); border-radius: 12px; padding: 15px; color: white; text-align: center; }
        .cf-text { font-family: monospace; font-size: 1.2rem; font-weight: bold; background: transparent; border: none; color: white; width: 100%; text-align: center; }
        .footer-sig { position: absolute; bottom: 20px; font-size: 0.7rem; opacity: 0.5; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 10px; width: 80%; }
        
        @media print {
            .sidebar, .btn-actions, #form-col, .azioni-col, .chart-container { display: none !important; }
            .col-lg-8 { width: 100% !important; flex: 0 0 100% !important; max-width: 100% !important; }
            body { background: white !important; }
            .main-card { box-shadow: none !important; }
        }
    </style>
</head>
<body>
<div class="container-fluid p-0 d-flex">
    <div class="sidebar d-none d-lg-block" style="width: 280px;">
        <h4 class="fw-bold mb-5"><i class="bi bi-cpu-fill text-primary"></i> SmartReg Pro</h4>
        <div class="mb-4">
            <small class="text-muted d-block text-uppercase small fw-bold">User Status</small>
            <span class="badge bg-primary"><?php echo $ruolo_reale; ?></span>
        </div>
        <a href="?logout=1" class="btn btn-outline-danger btn-sm w-100 mt-5"><i class="bi bi-power"></i> Logout</a>
        <div class="footer-sig">System Architect: <strong><?php echo $supervisore; ?></strong><br>Rel: <?php echo $versione_software; ?></div>
    </div>

    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4 btn-actions">
            <h2 class="fw-bold m-0">Dashboard Analitica</h2>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-danger rounded-pill px-4"><i class="bi bi-file-pdf"></i> PDF</button>
                <?php if($ruolo_reale == 'ADMIN'): ?><a href="?export_excel=1" class="btn btn-success rounded-pill px-4"><i class="bi bi-file-excel"></i> EXCEL</a><?php endif; ?>
            </div>
        </div>

        <div class="row g-3 mb-4 btn-actions">
            <div class="col-md-4">
                <div class="card stat-card bg-primary text-white p-3">
                    <div class="d-flex justify-content-between"><div><small>Totale Iscritti</small><h3 class="fw-bold m-0"><?php echo $totale; ?></h3></div><i class="bi bi-people fs-1 opacity-50"></i></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card bg-info text-white p-3">
                    <div class="d-flex justify-content-between"><div><small>Uomini (M)</small><h3 class="fw-bold m-0"><?php echo $uomini; ?></h3></div><i class="bi bi-gender-male fs-1 opacity-50"></i></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card bg-warning text-white p-3">
                    <div class="d-flex justify-content-between"><div><small>Donne (F)</small><h3 class="fw-bold m-0"><?php echo $donne; ?></h3></div><i class="bi bi-gender-female fs-1 opacity-50"></i></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4" id="form-col">
                <div class="card main-card">
                    <h5 class="fw-bold mb-4" id="formTitle">Anagrafica</h5>
                    <form method="POST">
                        <input type="hidden" name="id_record" id="id_record">
                        <div class="mb-2"><label class="small fw-bold">NOME</label><input type="text" name="nuovo_nome" id="nome" class="form-control" required></div>
                        <div class="mb-2"><label class="small fw-bold">COGNOME</label><input type="text" name="nuovo_cognome" id="cognome" class="form-control" required></div>
                        <div class="row g-2 mb-2">
                            <div class="col-8"><label class="small fw-bold">DATA NASCITA</label><input type="text" id="datepicker" class="form-control" required><input type="hidden" name="data_nascita_db" id="data_db"></div>
                            <div class="col-4"><label class="small fw-bold">SESSO</label><select name="sesso" id="sesso" class="form-select"><option value="M">M</option><option value="F">F</option></select></div>
                        </div>
                        <div class="mb-2"><label class="small fw-bold">COMUNE</label><input type="text" name="luogo_nascita" id="comune_input" class="form-control"></div>
                        <div class="mb-2"><label class="small fw-bold">INDIRIZZO</label><input type="text" name="indirizzo" id="indirizzo" class="form-control"></div>
                        <div class="mb-3"><label class="small fw-bold">TELEFONO</label><input type="text" name="recapito" id="recapito" class="form-control"></div>
                        <div class="cf-box mb-3"><small class="d-block opacity-75 small">CODICE FISCALE</small><input type="text" name="nuovo_cf" id="cf_output" class="cf-text" readonly></div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold">SALVA REGISTRO</button>
                    </form>
                    <div class="chart-container mt-4" style="height:200px">
                        <canvas id="genderChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card main-card">
                    <h5 class="fw-bold mb-4">Registro Cronologico</h5>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light small">
                                <tr>
                                    <th>UTENTE</th>
                                    <th>CONTATTI / RESIDENZA</th>
                                    <th class="text-end azioni-col">AZIONI</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $st = $pdo->query("SELECT * FROM visitatori ORDER BY id DESC");
                                while($v = $st->fetch()) {
                                    $v_json = json_encode($v, JSON_HEX_APOS | JSON_HEX_QUOT);
                                    echo "<tr>
                                        <td><strong>{$v['nome']} {$v['cognome']}</strong><br><small class='text-primary font-monospace'>{$v['codice_fiscale']}</small></td>
                                        <td><small><i class='bi bi-telephone text-muted'></i> {$v['recapito']}<br><span class='text-muted'>{$v['indirizzo']} ({$v['luogo_nascita']})</span></small></td>
                                        <td class='text-end azioni-col'>
                                            <button onclick='modificaRecord($v_json)' class='btn btn-sm btn-light border'><i class='bi bi-pencil'></i></button>
                                            <button onclick='confermaElimina({$v['id']}, \"{$v['nome']} {$v['cognome']}\")' class='btn btn-sm btn-light border text-danger'><i class='bi bi-trash'></i></button>
                                        </td>
                                    </tr>";
                                }
                                ?>
                            </tbody>
                        </table>
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
    // Grafico
    new Chart(document.getElementById('genderChart'), {
        type: 'doughnut',
        data: {
            labels: ['Uomini', 'Donne'],
            datasets: [{ data: [<?php echo "$uomini, $donne"; ?>], backgroundColor: ['#0dcaf0', '#ffc107'] }]
        },
        options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });

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
    $("#datepicker").datepicker({ dateFormat: "dd/mm/yy", onSelect: generaCF });
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
    function calcolaControllo(cf15) {
        const d={'0':1,'1':0,'2':5,'3':7,'4':9,'5':13,'6':15,'7':17,'8':19,'9':21,'A':1,'B':0,'C':5,'D':7,'E':9,'F':13,'G':15,'H':17,'I':19,'J':21,'K':2,'L':4,'M':18,'N':20,'O':11,'P':3,'Q':6,'R':8,'S':12,'T':14,'U':16,'V':10,'W':22,'X':25,'Y':24,'Z':23};
        const p={'0':0,'1':1,'2':2,'3':3,'4':4,'5':5,'6':6,'7':7,'8':8,'9':9,'A':0,'B':1,'C':2,'D':3,'E':4,'F':5,'G':6,'H':7,'I':8,'J':9,'K':10,'L':11,'M':12,'N':13,'O':14,'P':15,'Q':16,'R':17,'S':18,'T':19,'U':20,'V':21,'W':22,'X':23,'Y':24,'Z':25};
        let s=0; for(let i=0; i<15; i++) s += ((i+1)%2 !== 0) ? d[cf15[i]] : p[cf15[i]];
        return String.fromCharCode(65 + (s%26));
    }
    window.confermaElimina = function(id, nome) {
        Swal.fire({ title: 'Eliminare?', text: nome, icon: 'warning', showCancelButton: true }).then((r) => { if (r.isConfirmed) window.location.href = "?delete=" + id; });
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