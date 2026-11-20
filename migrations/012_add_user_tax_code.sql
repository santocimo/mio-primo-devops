ALTER TABLE users ADD COLUMN tax_code VARCHAR(16) DEFAULT NULL;

UPDATE users u
JOIN gyms g ON g.id = u.gym_id
  AND u.username = JSON_UNQUOTE(JSON_EXTRACT(g.settings, '$.manager_username'))
SET u.tax_code = UPPER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(g.settings, '$.manager_cf'))))
WHERE u.tax_code IS NULL
  AND UPPER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(g.settings, '$.manager_cf')))) REGEXP '^[A-Z0-9]{16}$';
