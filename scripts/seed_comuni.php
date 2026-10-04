<?php
// Seed comuni from provided `comuni.csv` into `comuni` table
require_once __DIR__ . '/../db.php';

try {
    $pdo = getPDO();

    // Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS comuni (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(200) NOT NULL,
        provincia VARCHAR(10) DEFAULT NULL,
        codice_catastale VARCHAR(10) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY ux_comuni_nome (nome, provincia)
    ) ENGINE=InnoDB CHARSET=utf8mb4");

    $csv = __DIR__ . '/../comuni.csv';
    if (!file_exists($csv)) {
        echo "comuni.csv not found, skipping seed.\n";
        exit(0);
    }

    $fh = fopen($csv, 'r');
    if (!$fh) { throw new Exception('Cannot open comuni.csv'); }

    $pdo->beginTransaction();
    $insert = $pdo->prepare('INSERT INTO comuni (nome, provincia, codice_catastale) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE codice_catastale=VALUES(codice_catastale)');
    $row = 0;
    while (($data = fgetcsv($fh, 0, ',')) !== false) {
        $row++;
        if ($row === 1) continue; // skip header if present
        $nome = trim($data[0] ?? '');
        $prov = trim($data[1] ?? '');
        $cod  = trim($data[2] ?? '');
        if ($nome === '') continue;
        $insert->execute([$nome, $prov, $cod]);
    }
    $pdo->commit();
    fclose($fh);
    echo "Seeded comuni table.\n";
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo "Error seeding comuni: " . $e->getMessage() . "\n";
    exit(1);
}
