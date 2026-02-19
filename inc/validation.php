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

?>
