CREATE DATABASE IF NOT EXISTS stadium_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE stadium_db;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('fan','admin','staff') NOT NULL DEFAULT 'fan',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE teams (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE matches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  home_team_id INT NOT NULL,
  away_team_id INT NOT NULL,
  match_date DATE NOT NULL,
  match_time TIME NOT NULL,
  status ENUM('upcoming','finished','cancelled') NOT NULL DEFAULT 'upcoming',
  FOREIGN KEY (home_team_id) REFERENCES teams(id),
  FOREIGN KEY (away_team_id) REFERENCES teams(id)
) ENGINE=InnoDB;

CREATE TABLE sections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  capacity INT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE seats (
  id INT AUTO_INCREMENT PRIMARY KEY,
  section_id INT NOT NULL,
  row_label VARCHAR(5) NOT NULL,
  seat_number INT NOT NULL,
  FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
  UNIQUE (section_id, row_label, seat_number)
) ENGINE=InnoDB;

CREATE TABLE prices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  match_id INT NOT NULL,
  section_id INT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
  UNIQUE (match_id, section_id)
) ENGINE=InnoDB;

CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  match_id INT NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  status ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (match_id) REFERENCES matches(id)
) ENGINE=InnoDB;

CREATE TABLE tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  match_id INT NOT NULL,
  seat_id INT NOT NULL,
  qr_code VARCHAR(64) NOT NULL UNIQUE,
  status ENUM('valid','used','cancelled') NOT NULL DEFAULT 'valid',
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  FOREIGN KEY (match_id) REFERENCES matches(id),
  FOREIGN KEY (seat_id) REFERENCES seats(id),
  UNIQUE KEY uq_match_seat (match_id, seat_id)
) ENGINE=InnoDB;

CREATE TABLE entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NOT NULL,
  section_id INT NOT NULL,
  staff_id INT NOT NULL,
  entered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  FOREIGN KEY (section_id) REFERENCES sections(id),
  FOREIGN KEY (staff_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE complaints (
  id INT AUTO_INCREMENT PRIMARY KEY,
  staff_id INT NOT NULL,
  description TEXT NOT NULL,
  status ENUM('open','resolved') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (staff_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ===== بيانات تجريبية =====
INSERT INTO teams (name) VALUES ('Al Ahly'), ('Zamalek'), ('Pyramids'), ('Al Masry');

INSERT INTO matches (home_team_id, away_team_id, match_date, match_time) VALUES
 (1, 2, '2026-11-20', '20:00:00'),
 (3, 4, '2026-11-27', '18:00:00'),
 (2, 3, '2026-12-04', '21:00:00');

INSERT INTO sections (name, capacity) VALUES
 ('VIP Stand', 20), ('North Stand', 20), ('South Stand', 20);

-- 20 مقعد لكل قسم (صفين A و B × 10 مقاعد)
INSERT INTO seats (section_id, row_label, seat_number)
SELECT s.id, r.row_label, n.num
FROM sections s
CROSS JOIN (SELECT 'A' AS row_label UNION SELECT 'B') r
CROSS JOIN (SELECT 1 AS num UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
            UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10) n;

-- أسعار كل قسم لكل مباراة
INSERT INTO prices (match_id, section_id, price)
SELECT m.id, s.id,
  CASE s.name WHEN 'VIP Stand' THEN 500 WHEN 'North Stand' THEN 200 ELSE 100 END
FROM matches m CROSS JOIN sections s;
