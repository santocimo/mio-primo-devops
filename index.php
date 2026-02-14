<?php
session_start();
// Protezione accesso
if(!isset($_SESSION['admin_logged'])) { header("Location: login.php"); exit; }
$ruolo = $_SESSION['user_role'];

// Logout
if(isset($_GET['logout'])) { session_destroy(); header("Location: login.php"); exit; }

// Configurazione Database
$host = 'database-santo'; $db = 'mio_database'; $user = 'root'; $pass = 'password_segreta';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // EXPORT EXCEL (Solo Admin)
    if (isset($_GET['export_excel']) && $ruolo == 'admin') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=registro_'.date('d-m-Y').'.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'NOME', 'COGNOME', 'CF', 'DATA NASCITA', 'COMUNE DI NASCITA', 'INDIRIZZO', 'RECAPITO', 'SESSO']);
        $rows = $pdo->query("SELECT * FROM visitatori ORDER BY id DESC");
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) fputcsv($output, $row);
        exit;
    }

    // ELIMINAZIONE (Admin e Operatore Responsabile)
    if (isset($_GET['delete'])) {
        $pdo->prepare("DELETE FROM visitatori WHERE id = ?")->execute([$_GET['delete']]);
        header("Location: index.php"); exit;
    }

    // SALVATAGGIO / UPDATE
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuovo_cf'])) {
        function forzaMaiuscolo($str) { return mb_convert_case($str, MB_CASE_UPPER, "UTF-8"); }
        
        $nome = forzaMaiuscolo($_POST['nuovo_nome']);
        $cognome = forzaMaiuscolo($_POST['nuovo_cognome']); // Gestisce correttamente CIMÒ
        $cf = strtoupper($_POST['nuovo_cf']);
        $id_rec = !empty($_POST['id_record']) ? (int)$_POST['id_record'] : null;

        $params = [
            ':n' => $nome, ':c' => $cognome, ':cf' => $cf, 
            ':d' => $_POST['data_nascita_db'], 
            ':l' => forzaMaiuscolo($_POST['luogo_nascita']), 
            ':i' => forzaMaiuscolo($_POST['indirizzo']), 
            ':r' => $_POST['recapito'], ':s' => $_POST['sesso']
        ];

        if ($id_rec) {
            $params[':id'] = $id_rec;
            $sql = "UPDATE visitatori SET nome=:n, cognome=:c, codice_fiscale=:cf, data_nascita=:d, luogo_nascita=:l, indirizzo=:i, recapito=:r, sesso=:s WHERE id=:id";
        } else {
            $sql = "INSERT INTO visitatori (nome, cognome, codice_fiscale, data_nascita, luogo_nascita, indirizzo, recapito, sesso) VALUES (:n,:c,:cf,:d,:l,:i,:r,:s)";
        }
        $pdo->prepare($sql)->execute($params);
        header("Location: index.php"); exit;
    }
    $totale = $pdo->query("SELECT COUNT(*) FROM visitatori")->fetchColumn() ?: 0;
} catch (PDOException $e) { die("Errore Database: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>SmartRegistry Pro | Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { --accent: #7c4dff; --sidebar: #1a1a2e; --text-light: #6c757d; }
        body { background-color: #f4f7fe; font-family: 'Inter', sans-serif; color: #2d3436; overflow-x: hidden; }
        
        /* Sidebar */
        .sidebar { background: var(--sidebar); min-height: 100vh; padding: 2rem 1.5rem; color: #fff; position: sticky; top: 0; transition: 0.3s; }
        .nav-link { border-radius: 14px; padding: 12px 15px; margin-bottom: 5px; transition: 0.3s; color: rgba(255,255,255,0.7); }
        .nav-link:hover, .nav-link.active { background: rgba(124, 77, 255, 0.15); color: #fff; }
        
        /* Card & UI */
        .main-card { background: #fff; border: none; border-radius: 24px; box-shadow: 0 10px 40px rgba(0,0,0,0.04); padding: 1.8rem; }
        .label-custom { font-size: 0.72rem; font-weight: 700; color: var(--text-light); text-transform: uppercase; margin-bottom: 0.5rem; display: block; letter-spacing: 0.5px; }
        .input-custom { background: #f8f9fc; border: 2px solid #f1f3f9; border-radius: 12px; padding: 0.75rem 1rem; width: 100%; transition: 0.3s; }
        .input-custom:focus { border-color: var(--accent); background: #fff; outline: none; box-shadow: 0 0 0 4px rgba(124, 77, 255, 0.1); }
        
        /* CF Badge */
        .cf-box { background: linear-gradient(135deg, #7c4dff 0%, #64b5f6 100%); border-radius: 16px; padding: 15px; margin-top: 10px; box-shadow: 0 8px 20px rgba(124, 77, 255, 0.2); }
        .cf-text { font-family: 'Monaco', 'Consolas', monospace; font-size: 1.4rem; font-weight: 700; border: none; background: transparent; color: white; width: 100%; text-align: center; letter-spacing: 2px; }
        
        .btn-save { background: var(--accent); color: white; border: none; border-radius: 12px; padding: 12px; font-weight: 700; width: 100%; transition: 0.3s; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(124, 77, 255, 0.4); color: #fff; }

        /* Print Settings */
        @media print {
            .sidebar, .header-main, #visitForm, .azioni-col { display: none !important; }
            .main-card { box-shadow: none !important; border: 1px solid #eee; }
            .container-fluid { display: block !important; }
            body { background: #fff; }
        }
    </style>
</head>
<body>

<div class="container-fluid p-0 d-flex">
    <div class="sidebar d-none d-lg-block" style="width: 270px;">
        <div class="d-flex align-items-center mb-5 px-2">
            <div class="bg-primary rounded-3 p-2 me-3"><i class="bi bi-lightning-charge-fill text-white"></i></div>
            <h4 class="fw-bold m-0">SmartReg</h4>
        </div>
        <nav class="nav flex-column mb-auto">
            <a href="index.php" class="nav-link active"><i class="bi bi-grid-fill me-3"></i>Dashboard</a>
            <div class="mt-4 px-3 mb-2 small text-muted text-uppercase fw-bold" style="font-size: 0.65rem;">Profilo Attivo</div>
            <div class="px-3 text-white small mb-4"><i class="bi bi-person-badge me-2"></i><?php echo strtoupper($ruolo); ?></div>
        </nav>
        <a href="?logout=1" class="nav-link text-danger mt-5"><i class="bi bi-box-arrow-left me-3"></i>Esci dal sistema</a>
    </div>

    <div class="flex-grow-1 p-4 p-lg-5">
        <div class="d-flex justify-content-between align-items-center mb-4 header-main">
            <div>
                <h3 class="fw-bold m-0">Registro Visitatori</h3>
                <p class="text-muted small">Gestione anagrafica e monitoraggio accessi</p>
            </div>
            <?php if($ruolo == 'admin'): ?>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-white shadow-sm rounded-3 text-danger fw-bold"><i class="bi bi-file-earmark-pdf me-2"></i>PDF</button>
                <a href="?export_excel=1" class="btn btn-white shadow-sm rounded-3 text-success fw-bold"><i class="bi bi-file-earmark-excel me-2"></i>Excel</a>
            </div>
            <?php endif; ?>
        </div>

        <div class="row g-4">
            <div class="col-xl-4" id="visitForm">
                <div class="card main-card">
                    <h5 class="fw-bold mb-4" id="formTitle">Nuovo Inserimento</h5>
                    <form method="POST">
                        <input type="hidden" name="id_record" id="id_record">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="label-custom">Nome</label>
                                <input type="text" name="nuovo_nome" id="nome" class="input-custom" required>
                            </div>
                            <div class="col-6">
                                <label class="label-custom">Cognome</label>
                                <input type="text" name="nuovo_cognome" id="cognome" class="input-custom" required>
                            </div>
                            <div class="col-12">
                                <label class="label-custom">Data di Nascita</label>
                                <input type="text" id="datepicker" class="input-custom" placeholder="GG/MM/AAAA" required>
                                <input type="hidden" name="data_nascita_db" id="data_db">
                            </div>
                            <div class="col-6">
                                <label class="label-custom">Sesso</label>
                                <select name="sesso" id="sesso" class="input-custom">
                                    <option value="F">Femmina (F)</option>
                                    <option value="M">Maschio (M)</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="label-custom">Comune di Nascita</label>
                                <input type="text" id="comune_input" name="luogo_nascita" class="input-custom">
                            </div>
                            <div class="col-12">
                                <label class="label-custom">Indirizzo Residenza</label>
                                <input type="text" name="indirizzo" id="indirizzo" class="input-custom">
                            </div>
                            <div class="col-12">
                                <label class="label-custom">Recapito Telefonico</label>
                                <input type="text" name="recapito" id="recapito" class="input-custom">
                            </div>
                            
                            <div class="cf-box">
                                <label class="label-custom text-white opacity-75">Codice Fiscale Calcolato</label>
                                <input type="text" name="nuovo_cf" id="cf_output" class="cf-text" readonly placeholder="----------">
                            </div>
                            
                            <button type="submit" class="btn btn-save mt-3">SALVA NEL DATABASE</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="card main-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold m-0">Lista Iscritti <span class="badge bg-light text-primary ms-2"><?php echo $totale; ?></span></h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="border-0">Anagrafica</th>
                                    <th class="border-0">Contatti & Luogo</th>
                                    <th class="border-0 azioni-col">Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $st = $pdo->query("SELECT * FROM visitatori ORDER BY id DESC");
                                while($v = $st->fetch()) {
                                    $json = json_encode($v, JSON_HEX_APOS | JSON_HEX_QUOT);
                                    echo "<tr>
                                            <td>
                                                <div class='fw-bold text-dark'>{$v['nome']} {$v['cognome']}</div>
                                                <div class='text-primary small fw-bold' style='letter-spacing:1px'>{$v['codice_fiscale']}</div>
                                            </td>
                                            <td>
                                                <div class='small'><i class='bi bi-telephone me-2 text-muted'></i>{$v['recapito']}</div>
                                                <div class='small text-muted'><i class='bi bi-geo-alt me-2'></i>{$v['luogo_nascita']}</div>
                                            </td>
                                            <td class='azioni-col'>
                                                <div class='d-flex gap-2'>
                                                    <button onclick='modificaRecord($json)' class='btn btn-sm btn-light p-2 rounded-3'><i class='bi bi-pencil-square text-primary'></i></button>
                                                    <button onclick='confermaElimina({$v['id']}, \"{$v['nome']} {$v['cognome']}\")' class='btn btn-sm btn-light p-2 rounded-3'><i class='bi bi-trash3 text-danger'></i></button>
                                                </div>
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
    let belfiore_global = "";

    // Logica Codice Fiscale
    function generaCF() {
        const n=$("#nome").val(), c=$("#cognome").val(), dIn=$("#datepicker").val(), s=$("#sesso").val();
        if (n && c && dIn.length === 10) {
            let p = dIn.split('/'); $("#data_db").val(p[2] + "-" + p[1].padStart(2,'0') + "-" + p[0].padStart(2,'0'));
            if (belfiore_global) {
                let cf = getLetters(c, false) + getLetters(n, true) + p[2].slice(-2) + ['A','B','C','D','E','H','L','M','P','R','S','T'][parseInt(p[1])-1];
                let gg = parseInt(p[0]); if (s==='F') gg += 40;
                cf += gg.toString().padStart(2, '0') + belfiore_global;
                if (cf.length === 15) $("#cf_output").val(cf.toUpperCase() + calcolaControllo(cf.toUpperCase()));
            }
        }
    }

    $("#datepicker").datepicker({ dateFormat: "dd/mm/yy", onSelect: generaCF });
    $("#comune_input").autocomplete({ 
        source: "cerca_comuni.php", 
        select: function(e, ui) { $(this).val(ui.item.value); belfiore_global = ui.item.codice; generaCF(); return false; } 
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

    // Popup Cancellazione con SweetAlert2
    window.confermaElimina = function(id, nome) {
        Swal.fire({
            title: 'Sei sicuro?',
            text: "Vuoi eliminare definitivamente " + nome + "?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7c4dff',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sì, elimina',
            cancelButtonText: 'Annulla',
            background: '#fff',
            borderRadius: '20px'
        }).then((result) => {
            if (result.isConfirmed) window.location.href = "?delete=" + id;
        });
    }

    // Funzione Modifica
    window.modificaRecord = function(d) {
        $("#formTitle").text("Modifica Anagrafica"); $("#id_record").val(d.id);
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