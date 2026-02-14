<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); // Forza la visualizzazione degli errori per capire cosa succede
session_start();

if(isset($_SESSION['admin_logged'])) { 
    header("Location: index.php"); 
    exit; 
}

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = $_POST['username'] ?? '';
    $p = $_POST['password'] ?? '';

    if ($u === 'admin' && $p === 'admin123') {
        $_SESSION['user_role'] = 'admin';
        $_SESSION['admin_logged'] = true;
        header("Location: index.php"); 
        exit;
    } elseif ($u === 'op' && $p === 'op123') {
        $_SESSION['user_role'] = 'operatore';
        $_SESSION['admin_logged'] = true;
        header("Location: index.php"); 
        exit;
    } else {
        $error = "Credenziali errate!";
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Login | SmartRegistry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #f4f7fe; font-family: 'Inter', sans-serif; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { background: white; padding: 2.5rem; border-radius: 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.05); width: 100%; max-width: 400px; text-align: center; }
        .logo-area { background: linear-gradient(135deg, #7c4dff 0%, #64b5f6 100%); width: 70px; height: 70px; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; color: white; font-size: 2rem; font-weight: bold; }
        .input-custom { background: #f8f9fc; border: 2px solid #f1f3f9; border-radius: 14px; padding: 0.8rem 1.1rem; width: 100%; transition: 0.3s; margin-bottom: 1rem; }
        .btn-login { background: #7c4dff; color: white; border: none; border-radius: 14px; padding: 12px; font-weight: 700; width: 100%; box-shadow: 0 4px 15px rgba(124, 77, 255, 0.3); }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo-area">SR</div>
        <h3 class="fw-bold mb-4">Accesso Riservato</h3>
        <?php if($error): ?> <div class="alert alert-danger p-2 small"><?php echo $error; ?></div> <?php endif; ?>
        <form method="POST">
            <input type="text" name="username" class="input-custom" placeholder="Username" required>
            <input type="password" name="password" class="input-custom" placeholder="Password" required>
            <button type="submit" class="btn btn-login">ACCEDI</button>
        </form>
    </div>
</body>
</html>