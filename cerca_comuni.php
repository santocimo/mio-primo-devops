<?php
// cerca_comuni.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Parametri di connessione (Assicurati che siano identici a index.php)
require_once __DIR__ . '/db.php';
try {
    $pdo = getPDO();

    $term = isset($_GET['term']) ? $_GET['term'] : '';
    
    if (strlen($term) >= 2) {
        // Cerchiamo i comuni che iniziano con le lettere digitate
        // Ordiniamo per nome per comodità dell'utente
        $stmt = $pdo->prepare("SELECT nome, provincia, codice_catastale FROM comuni WHERE nome LIKE ? ORDER BY nome ASC LIMIT 15");
        $stmt->execute([$term . '%']);
        
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = [
                // Quello che l'utente vede nella lista
                'label'  => $row['nome'] . " (" . $row['provincia'] . ")", 
                // Quello che viene scritto nell'input dopo la selezione
                'value'  => $row['nome'], 
                // Il dato segreto (F246) che serve per il Codice Fiscale
                'codice' => $row['codice_catastale'] 
            ];
        }
        echo json_encode($results);
    } else {
        echo json_encode([]);
    }

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}