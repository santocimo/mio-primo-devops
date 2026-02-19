<?php
// cerca_comuni.php
header('Content-Type: application/json');

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