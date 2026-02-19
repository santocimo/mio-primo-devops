<?php
require_once __DIR__ . '/inc/security.php';
// Handle logout request: clear session and redirect to login
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    header('Location: login.php');
    exit;
}
if(!isset($_SESSION['admin_logged'])) { header("Location: login.php"); exit; }
$ruolo_reale = isset($_SESSION['user_role']) ? strtoupper($_SESSION['user_role']) : 'USER';
$supervisore = "CIMÒ";
$versione_software = "V3.5.6 Search-Fixed";
require_once __DIR__ . '/db.php';

try {
    $pdo = getPDO();

        // --- Tenant resolution ---
        // Detect whether the visitatori table has a gym_id column; if not, stay backwards-compatible
        $use_gym = false;
        try {
            $colCheck = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = DATABASE() AND table_name = 'visitatori' AND column_name = 'gym_id'");
            $colCheck->execute();
            $use_gym = (bool)$colCheck->fetchColumn();
        } catch (Exception $e) { $use_gym = false; }
        if ($use_gym) {
            $current_gym_id = isset($_SESSION['gym_id']) ? (int)$_SESSION['gym_id'] : 1;
        } else {
            $current_gym_id = null;
        }

        if (isset($_GET['check_cf'])) {
        $cf_da_controllare = strtoupper($_GET['check_cf']);
        $escludi_id = $_GET['exclude_id'] ?? 0;
        if ($use_gym) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM visitatori WHERE codice_fiscale = ? AND id != ? AND gym_id = ?");
            $stmt->execute([$cf_da_controllare, $escludi_id, $current_gym_id]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM visitatori WHERE codice_fiscale = ? AND id != ?");
            $stmt->execute([$cf_da_controllare, $escludi_id]);
        }
        echo json_encode(['exists' => $stmt->fetchColumn() > 0]);
        exit;
    }

    function forzaMaiuscolo($str) { return mb_convert_case($str, MB_CASE_UPPER, "UTF-8"); }
    
    if (isset($_GET['export_excel']) && $ruolo_reale == 'ADMIN') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=registro_'.date('d-m-Y').'.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'NOME', 'COGNOME', 'CF', 'DATA NASCITA', 'COMUNE', 'INDIRIZZO', 'RECAPITO', 'SESSO']);
        if ($use_gym) {
            $rs = $pdo->prepare("SELECT * FROM visitatori WHERE gym_id = ? ORDER BY id DESC");
            $rs->execute([$current_gym_id]);
        } else {
            $rs = $pdo->query("SELECT * FROM visitatori ORDER BY id DESC");
        }
        while ($r = $rs->fetch()) fputcsv($out, $r);
        exit;
    }

    if (isset($_GET['delete'])) {
        if ($use_gym) {
            $pdo->prepare("DELETE FROM visitatori WHERE id = ? AND gym_id = ?")->execute([$_GET['delete'], $current_gym_id]);
        } else {
            $pdo->prepare("DELETE FROM visitatori WHERE id = ?")->execute([$_GET['delete']]);
        }
        header("Location: index.php"); exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuovo_cf'])) {
        $baseParams = [':n' => forzaMaiuscolo($_POST['nuovo_nome']), ':c' => forzaMaiuscolo($_POST['nuovo_cognome']), ':cf' => strtoupper($_POST['nuovo_cf']), ':d' => $_POST['data_nascita_db'], ':l' => forzaMaiuscolo($_POST['luogo_nascita']), ':i' => forzaMaiuscolo($_POST['indirizzo']), ':r' => $_POST['recapito'], ':s' => $_POST['sesso']];
        if (!empty($_POST['id_record'])) {
            $baseParams[':id'] = $_POST['id_record'];
            if ($use_gym) {
                $baseParams[':g'] = $current_gym_id;
                $sql = "UPDATE visitatori SET nome=:n, cognome=:c, codice_fiscale=:cf, data_nascita=:d, luogo_nascita=:l, indirizzo=:i, recapito=:r, sesso=:s WHERE id=:id AND gym_id = :g";
            } else {
                $sql = "UPDATE visitatori SET nome=:n, cognome=:c, codice_fiscale=:cf, data_nascita=:d, luogo_nascita=:l, indirizzo=:i, recapito=:r, sesso=:s WHERE id=:id";
            }
        } else {
            if ($use_gym) {
                $baseParams[':g'] = $current_gym_id;
                $sql = "INSERT INTO visitatori (nome, cognome, codice_fiscale, data_nascita, luogo_nascita, indirizzo, recapito, sesso, gym_id) VALUES (:n,:c,:cf,:d,:l,:i,:r,:s,:g)";
            } else {
                $sql = "INSERT INTO visitatori (nome, cognome, codice_fiscale, data_nascita, luogo_nascita, indirizzo, recapito, sesso) VALUES (:n,:c,:cf,:d,:l,:i,:r,:s)";
            }
        }
        $pdo->prepare($sql)->execute($baseParams);
        header("Location: index.php"); exit;
    }
    if ($use_gym) {
        $stTot = $pdo->prepare("SELECT COUNT(*) FROM visitatori WHERE gym_id = ?"); $stTot->execute([$current_gym_id]);
        $totale = $stTot->fetchColumn() ?: 0;
        $stU = $pdo->prepare("SELECT COUNT(*) FROM visitatori WHERE sesso='M' AND gym_id = ?"); $stU->execute([$current_gym_id]);
        $uomini = $stU->fetchColumn() ?: 0;
        $stD = $pdo->prepare("SELECT COUNT(*) FROM visitatori WHERE sesso='F' AND gym_id = ?"); $stD->execute([$current_gym_id]);
        $donne = $stD->fetchColumn() ?: 0;
    } else {
        $totale = $pdo->query("SELECT COUNT(*) FROM visitatori")->fetchColumn() ?: 0;
        $uomini = $pdo->query("SELECT COUNT(*) FROM visitatori WHERE sesso='M'")->fetchColumn() ?: 0;
        $donne = $pdo->query("SELECT COUNT(*) FROM visitatori WHERE sesso='F'")->fetchColumn() ?: 0;
    }
    // Server-side search endpoint used by AJAX live search
    if (isset($_GET['search'])) {
        $q = trim($_GET['search']);
        if ($q === '') {
            if ($use_gym) {
                $stmt = $pdo->prepare("SELECT * FROM visitatori WHERE gym_id = ? ORDER BY id DESC");
                $stmt->execute([$current_gym_id]);
            } else {
                $stmt = $pdo->query("SELECT * FROM visitatori ORDER BY id DESC");
            }
            $rs = $stmt;
        } else {
            $like = "%" . $q . "%";
            if ($use_gym) {
                $stmt = $pdo->prepare("SELECT * FROM visitatori WHERE gym_id = ? AND CONCAT_WS(' ', nome, cognome, codice_fiscale, luogo_nascita, indirizzo, recapito) LIKE ? ORDER BY id DESC");
                $stmt->execute([$current_gym_id, $like]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM visitatori WHERE CONCAT_WS(' ', nome, cognome, codice_fiscale, luogo_nascita, indirizzo, recapito) LIKE ? ORDER BY id DESC");
                $stmt->execute([$like]);
            }
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
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        :root { --viola-deep: #4338ca; --viola-bright: #7c3aed; --bg: linear-gradient(180deg,#f1f5f9,#f8fafc); }
        body { background: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; color: #1e293b; }
        .sidebar { background: linear-gradient(180deg,#0b1220 0%, #111827 100%); min-height: 100vh; padding: 2.5rem 1.5rem; color: #fff; position: sticky; top: 0; box-shadow: 6px 0 30px rgba(2,6,23,0.12); }
        .sidebar-brand { font-weight: 900; font-size: 1.8rem; background: linear-gradient(45deg, #a78bfa, #f472b6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 2.6rem; display: block; letter-spacing: -0.6px; text-shadow: 0 6px 18px rgba(124,58,237,0.06); }
        .glass-header { background: linear-gradient(135deg, rgba(76,29,149,0.98) 0%, rgba(124,58,237,0.98) 100%); border-radius: 24px; padding: 2.6rem; color: white; box-shadow: 0 26px 60px -12px rgba(76,29,149,0.35); margin-bottom: 2rem; backdrop-filter: blur(6px); border: 1px solid rgba(255,255,255,0.04); }
        .stat-card-white { background: linear-gradient(180deg,#ffffff 0%, #fbfbff 100%); border: none; border-radius: 20px; padding: 1.2rem; box-shadow: 0 12px 30px -10px rgba(16,24,40,0.06); border-bottom: 4px solid rgba(226,232,240,0.6); height: 100%; }
        .stat-val { font-size: 1.4rem; font-weight: 800; display: block; }
        .main-card { background: #fff; border: none; border-radius: 28px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.05); padding: 2rem; }
        .input-custom { border: 1px solid rgba(15,23,42,0.06); border-radius: 12px; padding: 0.9rem 1rem; background: linear-gradient(180deg,#fff 0%, #fbfdff 100%); font-size: 0.95rem; box-shadow: inset 0 1px 0 rgba(255,255,255,0.6), 0 6px 18px rgba(16,24,40,0.03); transition: box-shadow 180ms ease, transform 120ms ease, border-color 140ms ease; }
        .input-custom::placeholder { color: #94a3b8; }
        .input-custom:focus { border-color: rgba(124,58,237,0.9); background: #fff; outline: none; box-shadow: 0 8px 30px rgba(124,58,237,0.08); transform: translateY(-1px); }
        .btn-viola { background: linear-gradient(135deg, var(--viola-deep) 0%, var(--viola-bright) 100%); border: none; border-radius: 16px; font-weight: 900; padding: 0.95rem 1.1rem; color: white; box-shadow: 0 10px 36px rgba(124,58,237,0.20); transition: transform 0.12s ease, box-shadow 0.12s ease; font-size: 0.98rem; }
        .btn-viola:hover { transform: translateY(-3px); box-shadow: 0 20px 48px rgba(124,58,237,0.26); }
        .grid-row { background: #fff; border-left: 5px solid transparent; padding: 1.2rem; margin-bottom: 0.8rem; border-radius: 16px; transition: 0.18s ease; box-shadow: 0 6px 20px -12px rgba(16,24,40,0.06); display: flex !important; }
        .grid-row:hover { border-left-color: var(--viola-bright); transform: translateY(-3px); box-shadow: 0 18px 40px -16px rgba(16,24,40,0.08); }
        .cf-tag { background: #faf5ff; color: #7e22ce; font-family: 'Monaco', monospace; padding: 5px 12px; border-radius: 10px; font-size: 0.8rem; font-weight: 700; border: 1px solid #f3e8ff; }
        .cf-display-box { background: linear-gradient(180deg,#0f172a 0%, #111827 100%); border-radius: 16px; padding: 18px; text-align: center; color: #e9d5ff; margin: 1.5rem 0; border: 1px solid rgba(167,139,250,0.12); box-shadow: 0 8px 30px rgba(15,23,42,0.25) inset; }
            /* Make search input match the app's custom inputs */
            .search-box .form-control { border: 1px solid rgba(15,23,42,0.06); border-radius: 999px; padding: 0.75rem 1rem; background: linear-gradient(180deg,#fff 0%, #fbfdff 100%); box-shadow: 0 6px 18px rgba(16,24,40,0.03); outline: none; }
            .search-box .form-control:focus { border-color: rgba(124,58,237,0.9); box-shadow: 0 10px 30px rgba(124,58,237,0.06); }
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
            <a href="index.php" class="nav-link text-white p-0 small fw-bold"><i class="bi bi-grid-1x2 me-2"></i> <span data-i18n="nav.dashboard">Dashboard</span></a>
            <?php
            // Show management links only to global admins (not bound to a gym)
            $is_super_admin = (isset($_SESSION['admin_logged']) && isset($_SESSION['user_role']) && strtoupper($_SESSION['user_role']) === 'ADMIN' && !isset($_SESSION['gym_id']));
            if ($use_gym && $is_super_admin) {
                try {
                    $gyms = $pdo->query("SELECT id,name,slug FROM gyms ORDER BY name")->fetchAll();
                } catch (Exception $e) { $gyms = []; }
            ?>
            <div class="mt-3">
                <label class="small text-white opacity-75">Gym</label>
                <select id="gymSelect" class="form-select form-select-sm mt-1">
                    <?php foreach($gyms as $g): ?>
                        <option value="<?php echo (int)$g['id']; ?>" <?php if(isset($current_gym_id) && $current_gym_id == $g['id']) echo 'selected'; ?>><?php echo htmlspecialchars($g['name'], ENT_QUOTES); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <a href="gyms.php" class="nav-link text-white p-0 small fw-bold mt-2">Manage Gyms</a>
            <a href="users.php" class="nav-link text-white p-0 small fw-bold mt-1">Manage Users</a>
            <?php } elseif ($use_gym && isset($_SESSION['gym_id'])) {
                // user is bound to a specific gym: show the gym name (no selector)
                try {
                    $gstmt = $pdo->prepare("SELECT name FROM gyms WHERE id = ? LIMIT 1");
                    $gstmt->execute([$_SESSION['gym_id']]);
                    $ginfo = $gstmt->fetch();
                    $current_gym_name = $ginfo ? $ginfo['name'] : '';
                } catch (Exception $e) { $current_gym_name = ''; }
            ?>
            <div class="mt-3">
                <div class="small text-white opacity-75">Gym</div>
                <div class="fw-bold text-white"><?php echo htmlspecialchars($current_gym_name, ENT_QUOTES); ?></div>
            </div>
            <?php } ?>
            <a href="?logout=1" class="nav-link text-danger p-0 small fw-bold mt-4"><i class="bi bi-power me-2"></i> <span data-i18n="nav.exit">Esci</span></a>
        </div>
        <div class="position-absolute bottom-0 mb-4 opacity-25 small">Architect: <strong><?php echo $supervisore; ?></strong></div>
    </div>

    <div class="flex-grow-1 p-3 p-md-4">
        <div class="glass-header d-flex justify-content-between align-items-center">
            <div>
                    <h2 class="fw-800 m-0" data-i18n="title.app">Registro Digitale</h2>
                    <p class="m-0 opacity-75 small" data-i18n="subtitle.app">Gestione professionale anagrafiche</p>
            </div>
                <div class="d-flex gap-2 align-items-center">
                    <select id="languageSelect" class="form-select form-select-sm" style="width:90px;">
                        <option value="it">IT</option>
                        <option value="en">EN</option>
                    </select>
                    <button onclick="window.print()" class="btn btn-light btn-sm rounded-pill px-3 fw-bold" data-i18n="btn.pdf">PDF</button>
                    <?php if($ruolo_reale == 'ADMIN'): ?><a href="?export_excel=1" class="btn btn-outline-light btn-sm rounded-pill px-3 fw-bold" data-i18n="btn.excel">EXCEL</a><?php endif; ?>
                </div>
        </div>

        <div class="row g-3 mb-4 stat-row">
              <div class="col-4"><div class="stat-card-white"><span class="text-muted small fw-bold d-block mb-1" data-i18n="stat.total">TOTALE</span><span class="stat-val text-primary"><?php echo $totale; ?></span></div></div>
              <div class="col-4"><div class="stat-card-white"><span class="text-muted small fw-bold d-block mb-1" data-i18n="stat.male">UOMINI</span><span class="stat-val text-info"><?php echo $uomini; ?></span></div></div>
              <div class="col-4"><div class="stat-card-white"><span class="text-muted small fw-bold d-block mb-1" data-i18n="stat.female">DONNE</span><span class="stat-val text-danger"><?php echo $donne; ?></span></div></div>
        </div>

        <div class="row g-4">
            <div id="form-col" class="col-lg-4">
                <div class="card main-card">
                    <h5 id="formTitle" class="fw-800 mb-4" data-i18n="form.title">Anagrafica</h5>
                    <form method="POST" id="mainForm">
                        <input type="hidden" name="id_record" id="id_record">
                        <div class="mb-3">
                            <input type="text" name="nuovo_nome" id="nome" class="form-control input-custom mb-2" placeholder="Nome" required data-i18n-placeholder="ph.name">
                            <input type="text" name="nuovo_cognome" id="cognome" class="form-control input-custom" placeholder="Cognome" required data-i18n-placeholder="ph.surname">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-7"><input type="text" id="datepicker" class="form-control input-custom" placeholder="Data Nascita" readonly required data-i18n-placeholder="ph.dob"><input type="hidden" name="data_nascita_db" id="data_db"></div>
                            <div class="col-5"><select name="sesso" id="sesso" class="form-select input-custom"><option value="M" data-i18n="sex.male">Maschio</option><option value="F" data-i18n="sex.female">Femmina</option></select></div>
                        </div>
                        <div class="mb-3">
                            <input type="text" name="luogo_nascita" id="comune_input" class="form-control input-custom mb-2" placeholder="Comune di nascita" data-i18n-placeholder="ph.birthplace">
                            <input type="text" name="indirizzo" id="indirizzo" class="form-control input-custom mb-2" placeholder="Indirizzo" data-i18n-placeholder="ph.address">
                            <input type="text" name="recapito" id="recapito" class="form-control input-custom" placeholder="Telefono" data-i18n-placeholder="ph.phone">
                        </div>
                        <div class="cf-display-box" id="cf_box_ui">
                            <small class="d-block opacity-50 mb-1 fw-bold" id="cf_label" data-i18n="cf.label">CODICE FISCALE</small>
                            <input type="text" name="nuovo_cf" id="cf_output" class="w-100 border-0 bg-transparent text-center fw-bold" readonly style="color: inherit; font-size: 1.2rem; letter-spacing: 2px;">
                        </div>
                        <button type="submit" id="submitBtn" class="btn btn-viola w-100" data-i18n="btn.save">SALVA</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-3 search-box">
                    <h6 class="fw-800 m-0" data-i18n="heading.registered">ISCRITTI</h6>
                    <input type="text" id="liveSearch" class="form-control border-0 shadow-sm rounded-pill w-50 ps-3" placeholder="🔍 Cerca..." data-i18n-placeholder="ph.search">
                </div>
                <div id="grid-body">
                    <?php
                    if ($use_gym) { $st = $pdo->prepare("SELECT * FROM visitatori WHERE gym_id = ? ORDER BY id DESC"); $st->execute([$current_gym_id]); }
                    else { $st = $pdo->query("SELECT * FROM visitatori ORDER BY id DESC"); }
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

    // --- i18n definitions and helper ---
    const i18n = {
        it: {
            'title.app':'Registro Digitale', 'subtitle.app':'Gestione professionale anagrafiche',
            'btn.pdf':'PDF', 'btn.excel':'EXCEL',
            'stat.total':'TOTALE', 'stat.male':'UOMINI', 'stat.female':'DONNE',
            'form.title':'Anagrafica', 'form.edit':'Modifica',
            'ph.name':'Nome', 'ph.surname':'Cognome', 'ph.dob':'Data Nascita', 'ph.birthplace':'Comune di nascita', 'ph.address':'Indirizzo', 'ph.phone':'Telefono', 'ph.search':'🔍 Cerca...',
            'cf.label':'CODICE FISCALE', 'cf.exists':'GIÀ IN ARCHIVIO',
            'btn.save':'SALVA', 'heading.registered':'ISCRITTI',
            'sex.male':'Maschio', 'sex.female':'Femmina',
            'confirm.delete.title':'Elimina?', 'confirm.delete.confirm':'Elimina', 'confirm.cancel':'Annulla', 'nav.exit':'Esci'
        },
        en: {
            'title.app':'Digital Registry', 'subtitle.app':'Professional registry management',
            'btn.pdf':'PDF', 'btn.excel':'EXCEL',
            'stat.total':'TOTAL', 'stat.male':'MALE', 'stat.female':'FEMALE',
            'form.title':'Record', 'form.edit':'Edit',
            'ph.name':'First name', 'ph.surname':'Last name', 'ph.dob':'Date of birth', 'ph.birthplace':'Place of birth', 'ph.address':'Address', 'ph.phone':'Phone', 'ph.search':'🔍 Search...',
            'cf.label':'TAX CODE', 'cf.exists':'ALREADY IN ARCHIVE',
            'btn.save':'SAVE', 'heading.registered':'REGISTERED',
            'sex.male':'Male', 'sex.female':'Female',
            'confirm.delete.title':'Delete?', 'confirm.delete.confirm':'Delete', 'confirm.cancel':'Cancel', 'nav.exit':'Exit'
        }
    };
    let currentLang = localStorage.getItem('lang') || (navigator.language && navigator.language.startsWith('en') ? 'en' : 'it');

    function t(key) { return (i18n[currentLang] && i18n[currentLang][key]) ? i18n[currentLang][key] : key; }

    function applyLang(lang) {
        currentLang = lang;
        localStorage.setItem('lang', lang);
        document.querySelectorAll('[data-i18n]').forEach(el => { const k = el.getAttribute('data-i18n'); if(i18n[lang] && i18n[lang][k]) el.textContent = i18n[lang][k]; });
        document.querySelectorAll('[data-i18n-placeholder]').forEach(el => { const k = el.getAttribute('data-i18n-placeholder'); if(i18n[lang] && i18n[lang][k]) el.setAttribute('placeholder', i18n[lang][k]); });
        // update option labels in selects
        document.querySelectorAll('option[data-i18n]').forEach(opt => { const k = opt.getAttribute('data-i18n'); if(i18n[lang] && i18n[lang][k]) opt.textContent = i18n[lang][k]; });
        // update sweetalert button defaults where used dynamically
    }

    // initialize language selector
    const langSel = document.getElementById('languageSelect'); if(langSel){ langSel.value = currentLang; langSel.addEventListener('change', function(){ applyLang(this.value); }); }
    applyLang(currentLang);

    // gym selector change (set session gym and reload)
    $(document).on('change', '#gymSelect', function() {
        const gid = $(this).val();
        $.post('set_gym.php', { gym_id: gid }, function(resp) {
            if (resp && resp.ok) location.reload(); else alert('Unable to set gym');
        }, 'json').fail(function(){ alert('Unable to set gym'); });
    });

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
                    $("#cf_label").text(t('cf.exists'));
                    $("#submitBtn").prop("disabled", true);
                } else {
                    $("#cf_box_ui").removeClass("cf-error-state");
                    $("#cf_label").text(t('cf.label'));
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
        Swal.fire({ title: t('confirm.delete.title'), text: n, icon: 'warning', showCancelButton: true, confirmButtonColor: '#7c3aed', confirmButtonText: t('confirm.delete.confirm'), cancelButtonText: t('confirm.cancel') }).then((r) => { if (r.isConfirmed) window.location.href="?delete="+id; }); 
    }

    window.modificaRecord = function(d) {
        $("#formTitle").text(t('form.edit')); $("#id_record").val(d.id);
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