<?php
function validate_username(string $u): bool {
    // between 3 and 50 chars, letters, numbers, dot, underscore, dash
    return (bool)preg_match('/^[A-Za-z0-9._-]{3,50}$/', $u);
}

function validate_password(string $p): bool {
    // at least 8 chars (you can increase complexity rules)
    return strlen($p) >= 8;
}

function validate_slug(string $s): bool {
    // lowercase letters, numbers, hyphen, 2-100 chars
    return (bool)preg_match('/^[a-z0-9-]{2,100}$/', $s);
}

function validate_gym_name(string $n): bool {
    $len = mb_strlen($n);
    return $len >= 2 && $len <= 150;
}

function validate_tax_code(string $cf): bool {
    $cf = strtoupper(trim($cf));
    if (!preg_match('/^[A-Z0-9]{16}$/', $cf)) {
        return false;
    }

    $oddMap = [
        '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9, '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
        'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9, 'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
        'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11, 'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
        'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23,
    ];
    $evenMap = [
        '0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9,
        'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6, 'H' => 7, 'I' => 8, 'J' => 9,
        'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15, 'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19,
        'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23, 'Y' => 24, 'Z' => 25,
    ];

    $sum = 0;
    for ($index = 0; $index < 15; $index++) {
        $character = $cf[$index];
        $sum += (($index + 1) % 2 !== 0) ? $oddMap[$character] : $evenMap[$character];
    }

    return $cf[15] === chr(65 + ($sum % 26));
}

function validate_birth_date(string $date): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    [$year, $month, $day] = array_map('intval', explode('-', $date));
    return checkdate($month, $day, $year);
}

function validate_gym_category(string $c): bool {
    $allowed = ['gym', 'pilates', 'yoga', 'wellness', 'studio', 'medical', 'real_estate'];
    return in_array($c, $allowed, true);
}

function validate_business_type(string $c): bool {
    $allowed = ['gym', 'pilates', 'yoga', 'wellness', 'studio', 'medical', 'real_estate'];
    return in_array($c, $allowed, true);
}

function validate_service_name(string $n): bool {
    $len = mb_strlen($n);
    return $len >= 2 && $len <= 150;
}

function validate_service_category(string $c): bool {
    $allowed = ['class', 'appointment', 'wellness', 'personal', 'event'];
    return in_array($c, $allowed, true);
}

function validate_duration_minutes($value): bool {
    return is_numeric($value) && (int)$value > 0 && (int)$value <= 1440;
}

function validate_capacity($value): bool {
    return is_numeric($value) && (int)$value >= 1 && (int)$value <= 1000;
}

function validate_price($value): bool {
    return $value === null || $value === '' || preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', (string)$value);
}

function validate_status(string $status): bool {
    $allowed = ['pending', 'confirmed', 'canceled'];
    return in_array($status, $allowed, true);
}

?>
