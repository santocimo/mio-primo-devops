<?php
require_once __DIR__ . '/inc/security.php';

// Gestione logout (anche dal paywall)
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['admin_logged'])) { header('Location: login.php'); exit; }
require_once __DIR__ . '/inc/subscription.php';

// Già attivo (non in trial) → torna alla dashboard
$status = get_subscription_status();
if ($status === 'active') { header('Location: index.php'); exit; }

$trial_expired = ($status === 'expired' && ($_SESSION['subscription_status'] ?? '') === 'trial');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Abbonamento | SmartRegistry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { background: #f4f7fe; font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 0; }
        .paywall-card { background: white; border-radius: 30px; box-shadow: 0 20px 60px rgba(0,0,0,0.08); width: 100%; max-width: 520px; padding: 2.5rem; text-align: center; }
        .logo-area { background: linear-gradient(135deg, #7c4dff 0%, #64b5f6 100%); width: 70px; height: 70px; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.2rem; color: white; font-size: 2rem; font-weight: 800; }
        .trial-expired-badge { display: inline-block; background: #fee2e2; color: #dc2626; border-radius: 20px; padding: 4px 14px; font-size: 0.78rem; font-weight: 700; margin-bottom: 1rem; }
        .plans { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 1.5rem 0; }
        .plan { border: 2px solid #e5e7eb; border-radius: 20px; padding: 1.5rem 1rem; cursor: pointer; transition: 0.2s; position: relative; text-align: center; }
        .plan:hover { border-color: #7c4dff; transform: translateY(-3px); }
        .plan.best { border-color: #7c4dff; background: #f5f0ff; }
        .best-badge { position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #7c4dff; color: white; border-radius: 20px; padding: 2px 12px; font-size: 0.72rem; font-weight: 700; white-space: nowrap; }
        .plan-price { font-size: 1.8rem; font-weight: 800; color: #1e293b; }
        .plan-price span { font-size: 1rem; font-weight: 500; color: #64748b; }
        .plan-label { font-size: 0.85rem; color: #64748b; margin-top: 4px; }
        .btn-abbona { background: linear-gradient(135deg, #4338ca, #7c3aed); color: white; border: none; border-radius: 14px; padding: 14px; font-weight: 700; width: 100%; font-size: 1rem; box-shadow: 0 8px 24px rgba(124,77,255,0.3); transition: 0.2s; }
        .btn-abbona:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(124,77,255,0.4); }
        .features { text-align: left; margin: 1.2rem 0; }
        .features li { padding: 5px 0; font-size: 0.9rem; color: #374151; list-style: none; }
        .features li::before { content: '✓'; color: #7c4dff; font-weight: 800; margin-right: 8px; }
        .logout-link { font-size: 0.82rem; color: #94a3b8; margin-top: 1rem; display: block; }
    </style>
</head>
<body>
<div class="paywall-card">
    <div class="logo-area">SR</div>

    <?php if ($trial_expired): ?>
        <div class="trial-expired-badge">Prova gratuita scaduta</div>
        <h4 class="fw-800 mb-1">Il tuo periodo di prova è terminato</h4>
        <p class="text-muted small mb-2">Scegli un piano per continuare a usare SmartRegistry</p>
    <?php else: ?>
        <h4 class="fw-800 mb-1">Attiva il tuo abbonamento</h4>
        <p class="text-muted small mb-2">Scegli il piano più adatto alla tua struttura</p>
    <?php endif; ?>

    <ul class="features">
        <li>Clienti/iscritti illimitati</li>
        <li>Appuntamenti e servizi</li>
        <li>Calcolo automatico codice fiscale</li>
        <li>Esportazione PDF ed Excel</li>
        <li>Accesso da browser e app mobile</li>
    </ul>

    <form method="POST" action="subscribe.php">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES); ?>">

        <div class="plans">
            <label class="plan" onclick="document.getElementById('plan_monthly').checked=true; highlightPlan('monthly')">
                <input type="radio" name="plan" id="plan_monthly" value="monthly" style="display:none">
                <div class="plan-price">€4,99 <span>/ mese</span></div>
                <div class="plan-label">Piano mensile</div>
            </label>

            <label class="plan best" id="plan_yearly_card" onclick="document.getElementById('plan_yearly').checked=true; highlightPlan('yearly')">
                <span class="best-badge">Risparmia il 17%</span>
                <input type="radio" name="plan" id="plan_yearly" value="yearly" style="display:none" checked>
                <div class="plan-price">€49,99 <span>/ anno</span></div>
                <div class="plan-label">Piano annuale</div>
            </label>
        </div>

        <button type="submit" class="btn-abbona">Abbonati ora</button>
    </form>

    <a href="?logout=1" class="logout-link" onclick="return confirm('Vuoi uscire?')">Esci dall\'account</a>
</div>

<script>
function highlightPlan(plan) {
    document.querySelectorAll('.plan').forEach(p => p.classList.remove('best'));
    if (plan === 'monthly') {
        document.querySelector('label[onclick*="monthly"]').classList.add('best');
    } else {
        document.getElementById('plan_yearly_card').classList.add('best');
    }
}
</script>
</body>
</html>
