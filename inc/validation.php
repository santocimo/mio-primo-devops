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
