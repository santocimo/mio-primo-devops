<?php
/**
 * Create a test user in the database.
 * Usage: DB_PASSWORD=... php scripts/create_test_user.php
 */
require_once __DIR__ . '/../db.php';
try {
    $pdo = getPDO();
    $username = 'devtest';
    $password = 'devtest123';
    $role = 'ADMIN';
    $gym_id = 1;

    // check exists
    $chk = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $chk->execute([$username]);
    if ($chk->fetch()) {
        echo "User $username already exists\n";
        exit(0);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Determine which columns exist in users table and insert accordingly
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $available = array_flip($cols);

    $fields = [];
    $placeholders = [];
    $values = [];

    if (isset($available['username'])) { $fields[] = 'username'; $placeholders[]='?'; $values[]=$username; }
    if (isset($available['password_hash'])) { $fields[] = 'password_hash'; $placeholders[]='?'; $values[]=$hash; }
    if (isset($available['role'])) { $fields[] = 'role'; $placeholders[]='?'; $values[]=$role; }
    if (isset($available['gym_id'])) { $fields[] = 'gym_id'; $placeholders[]='?'; $values[]=$gym_id; }
    if (empty($fields)) { throw new Exception('No suitable columns found in users table'); }

    $sql = 'INSERT INTO users (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
    $ins = $pdo->prepare($sql);
    $ins->execute($values);
    echo "Created user: $username / $password (role=$role)\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
