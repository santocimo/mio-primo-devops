<?php
// importa_completo.php
set_time_limit(0); // Impedisce al server di bloccarsi per il caricamento lungo
$host = 'database-santo';
$db   = 'mio_database';
$user = 'root';
$pass = 'password_segreta';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Pulizia/Creazione Tabella
    $pdo->exec("DROP TABLE IF EXISTS comuni");
    $pdo->exec("CREATE TABLE comuni (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100),
        provincia CHAR(2),
        codice_catastale CHAR(4),
        INDEX (nome)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 2. Scaricamento dati da sorgente affidabile (JSON)
    // Usiamo una risorsa GitHub affidabile che mantiene i codici Belfiore aggiornati
    $url = "https://raw.githubusercontent.com/matteocontrini/comuni-json/master/comuni.json";
    $json = file_get_contents($url);
    $data = json_decode($json, true);

    $stmt = $pdo->prepare("INSERT INTO comuni (nome, provincia, codice_catastale) VALUES (?, ?, ?)");

    $pdo->beginTransaction();
    foreach ($data as $comune) {
        $stmt->execute([
            strtoupper($comune['nome']),
            $comune['sigla'],
            $comune['codiceCatastale'] // Es: F246
        ]);
    }
    $pdo->commit();

    echo "✅ Successo! Importati " . count($data) . " comuni con codici catastali corretti.";

} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    die("❌ Errore durante l'importazione: " . $e->getMessage());
}