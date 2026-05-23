-- Hotel Reservation System Database
-- Database Management System Final Project

-- Create database
CREATE DATABASE IF NOT EXISTS hotel_reservation;
USE hotel_reservation;

-- Users table for authentication
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Room types table
CREATE TABLE room_types (
    type_id INT PRIMARY KEY AUTO_INCREMENT,
    type_name VARCHAR(50) NOT NULL,
    description TEXT,
    base_price DECIMAL(10,2) NOT NULL,
    max_occupancy INT NOT NULL
);

-- Rooms table
CREATE TABLE rooms (
    room_id INT PRIMARY KEY AUTO_INCREMENT,
    room_number VARCHAR(10) NOT NULL UNIQUE,
    type_id INT NOT NULL,
    floor_number INT NOT NULL,
    status ENUM('available', 'occupied', 'maintenance', 'reserved') DEFAULT 'available',
    FOREIGN KEY (type_id) REFERENCES room_types(type_id) ON DELETE CASCADE
);

-- Guests table
CREATE TABLE guests (
    guest_id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address TEXT,
    id_number VARCHAR(50) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Reservations table
CREATE TABLE reservations (
    reservation_id INT PRIMARY KEY AUTO_INCREMENT,
    guest_id INT NOT NULL,
    room_id INT NOT NULL,
    check_in_date DATE NOT NULL,
    check_in_time TIME NOT NULL DEFAULT CURRENT_TIME,
    check_out_date DATE NOT NULL,
    check_out_time TIME NOT NULL DEFAULT '12:00:00',
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('confirmed', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'confirmed',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (guest_id) REFERENCES guests(guest_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- Payments table
CREATE TABLE payments (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    reservation_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'credit_card', 'bank_transfer', 'online', 'paypal', 'gcash') NOT NULL,
    payment_type ENUM('full', 'half', 'remaining') DEFAULT 'full',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id) ON DELETE CASCADE
);

-- Services table
CREATE TABLE services (
    service_id INT PRIMARY KEY AUTO_INCREMENT,
    service_name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL
);

-- Guest services table (junction table)
CREATE TABLE guest_services (
    guest_service_id INT PRIMARY KEY AUTO_INCREMENT,
    reservation_id INT NOT NULL,
    service_id INT NOT NULL,
    quantity INT DEFAULT 1,
    total_price DECIMAL(10,2) NOT NULL,
    service_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(service_id) ON DELETE CASCADE
);

-- Insert sample data

-- Insert users
INSERT INTO users (username, password, full_name, email, phone, role) VALUES
('admin', '12345678', 'System Administrator', 'admin@hotel.com', '1234567890', 'admin'),
('john_doe', 'password123', 'John Doe', 'john@example.com', '9876543210', 'user'),
('jane_smith', 'password123', 'Jane Smith', 'jane@example.com', '5551234567', 'user');

-- Insert room types
INSERT INTO room_types (type_name, description, base_price, max_occupancy) VALUES
('Standard Single', 'Comfortable single room with basic amenities', 1200.00, 1),
('Standard Double', 'Spacious double room with queen bed', 2200.00, 2),
('Deluxe Room', 'Premium room with city view and mini bar', 3500.00, 2),
('Suite', 'Luxury suite with separate living area', 6500.00, 4),
('Family Room', 'Large room suitable for families', 4000.00, 6),
('Executive Suite', 'Executive suite with office space and premium amenities', 8500.00, 2),
('Penthouse', 'Top floor penthouse with panoramic views', 12000.00, 4),
('Budget Room', 'Basic room for budget-conscious travelers', 750.00, 1),
('Twin Room', 'Room with two single beds', 1800.00, 2),
('Connecting Rooms', 'Two connecting rooms ideal for families', 5000.00, 4);

-- Insert rooms
INSERT INTO rooms (room_number, type_id, floor_number, status) VALUES
('101', 1, 1, 'available'),
('102', 1, 1, 'available'),
('103', 2, 1, 'occupied'),
('104', 2, 1, 'available'),
('105', 8, 1, 'occupied'),
('201', 2, 2, 'available'),
('202', 3, 2, 'available'),
('203', 3, 2, 'maintenance'),
('204', 9, 2, 'available'),
('205', 3, 2, 'occupied'),
('301', 4, 3, 'available'),
('302', 5, 3, 'reserved'),
('303', 4, 3, 'available'),
('304', 5, 3, 'occupied'),
('401', 6, 4, 'available'),
('402', 7, 4, 'available'),
('403', 10, 4, 'occupied');

-- Insert guests
INSERT INTO guests (first_name, last_name, email, phone, address, id_number) VALUES
('Michael', 'Johnson', 'michael@email.com', '1112223333', '123 Main St, City', '123456'),
('Sarah', 'Williams', 'sarah@email.com', '4445556666', '456 Oak Ave, Town', '789012'),
('David', 'Brown', 'david@email.com', '7778889999', '789 Pine Rd, Village', '345678');

-- Insert services
INSERT INTO services (service_name, description, price) VALUES
('Room Service', '24/7 room service delivery', 15.00),
('Laundry Service', 'Professional laundry and dry cleaning', 25.00),
('Airport Transfer', 'Airport pickup and drop-off', 50.00),
('Spa Access', 'Full day spa access', 75.00),
('Breakfast', 'Continental breakfast buffet', 20.00);

-- Insert sample reservations
INSERT INTO reservations (guest_id, room_id, check_in_date, check_out_date, total_amount, status, created_by) VALUES
(1, 3, '2024-01-15', '2024-01-18', 6600.00, 'checked_in', 1),
(2, 8, '2024-01-20', '2024-01-25', 17500.00, 'confirmed', 1),
(3, 4, '2024-02-01', '2024-02-03', 4400.00, 'confirmed', 2);

-- Insert sample payments
INSERT INTO payments (reservation_id, amount, payment_method, status) VALUES
(1, 6600.00, 'credit_card', 'completed'),
(2, 17500.00, 'bank_transfer', 'pending'),
(3, 4400.00, 'cash', 'completed');

-- Insert sample guest services
INSERT INTO guest_services (reservation_id, service_id, quantity, total_price) VALUES
(1, 1, 2, 30.00),
(1, 5, 3, 60.00),
(2, 3, 1, 50.00);

-- Create indexes for better performance
CREATE INDEX idx_reservations_dates ON reservations(check_in_date, check_out_date);
CREATE INDEX idx_reservations_status ON reservations(status);
CREATE INDEX idx_rooms_status ON rooms(status);
CREATE INDEX idx_guests_email ON guests(email);

-- Create views for reporting
CREATE VIEW reservation_details AS
SELECT 
    r.reservation_id,
    CONCAT(g.first_name, ' ', g.last_name) AS guest_name,
    g.email AS guest_email,
    g.phone AS guest_phone,
    room.room_number,
    rt.type_name,
    r.check_in_date,
    r.check_out_date,
    r.total_amount,
    r.status,
    DATEDIFF(r.check_out_date, r.check_in_date) AS nights_stayed,
    u.full_name AS created_by_user
FROM reservations r
JOIN guests g ON r.guest_id = g.guest_id
JOIN rooms room ON r.room_id = room.room_id
JOIN room_types rt ON room.type_id = rt.type_id
JOIN users u ON r.created_by = u.user_id;

CREATE VIEW room_occupancy AS
SELECT 
    rt.type_name,
    COUNT(r.room_id) AS total_rooms,
    SUM(CASE WHEN r.status = 'available' THEN 1 ELSE 0 END) AS available_rooms,
    SUM(CASE WHEN r.status = 'occupied' THEN 1 ELSE 0 END) AS occupied_rooms,
    SUM(CASE WHEN r.status = 'maintenance' THEN 1 ELSE 0 END) AS maintenance_rooms,
    SUM(CASE WHEN r.status = 'reserved' THEN 1 ELSE 0 END) AS reserved_rooms
FROM room_types rt
LEFT JOIN rooms r ON rt.type_id = r.type_id
GROUP BY rt.type_id, rt.type_name;

CREATE VIEW revenue_report AS
SELECT 
    DATE_FORMAT(p.payment_date, '%Y-%m') AS month,
    COUNT(p.payment_id) AS payment_count,
    SUM(p.amount) AS total_revenue,
    AVG(p.amount) AS average_payment
FROM payments p
WHERE p.status = 'completed'
GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m')
ORDER BY month DESC;
