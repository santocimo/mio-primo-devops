-- Migration: create services and appointments for bookings and lessons

CREATE TABLE IF NOT EXISTS services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  gym_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  category VARCHAR(50) NOT NULL DEFAULT 'class',
  description TEXT DEFAULT NULL,
  duration_minutes INT NOT NULL DEFAULT 60,
  capacity INT NOT NULL DEFAULT 10,
  price DECIMAL(10,2) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (gym_id) REFERENCES gyms(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  service_id INT NOT NULL,
  user_id INT DEFAULT NULL,
  customer_name VARCHAR(150) NOT NULL,
  customer_email VARCHAR(150) DEFAULT NULL,
  scheduled_at DATETIME NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  notes TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
