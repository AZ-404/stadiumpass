
USE stadium_db;
INSERT INTO users (name, email, password_hash, role) VALUES
 ('Test Staff', 'staff@test.com', 'temp', 'staff'),
 ('Test Fan',   'fan@test.com',   'temp', 'fan');
INSERT INTO bookings (user_id, match_id, total, status) VALUES (2, 1, 700.00, 'confirmed');
INSERT INTO tickets (booking_id, match_id, seat_id, qr_code, status) VALUES
 (1, 1, 1, 'TEST-VALID-001', 'valid'),      
 (1, 1, 2, 'TEST-USED-002',  'used'),       
 (1, 1, 3, 'TEST-CANCEL-003','cancelled');  
