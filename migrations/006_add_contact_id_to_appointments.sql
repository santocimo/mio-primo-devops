ALTER TABLE appointments
  ADD COLUMN contact_id INT DEFAULT NULL,
  ADD INDEX idx_appointments_contact_id (contact_id);