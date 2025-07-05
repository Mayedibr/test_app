-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('admin','user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (username, password, full_name, role) VALUES
('admin', '$2y$10$examplehashforadmin', 'Admin User', 'admin'),
('jdoe', '$2y$10$examplehashforuser', 'John Doe', 'user');

-- Employees table
CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_number VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    department VARCHAR(100),
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO employees (employee_number, name, department, email) VALUES
('EMP001', 'Alice Smith', 'HR', 'alice@company.com'),
('EMP002', 'Bob Johnson', 'IT', 'bob@company.com');

-- Entitlement Types table
CREATE TABLE entitlement_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    requirements TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO entitlement_types (name, description, requirements) VALUES
('Annual Leave', 'Paid annual leave', 'Minimum 1 year service'),
('Medical Insurance', 'Health coverage', 'Full-time employees only');

-- Entitlements table
CREATE TABLE entitlements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    entitlement_type_id INT NOT NULL,
    request_number VARCHAR(50) NOT NULL,
    issue_date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (entitlement_type_id) REFERENCES entitlement_types(id) ON DELETE CASCADE
);

INSERT INTO entitlements (employee_id, entitlement_type_id, request_number, issue_date, notes) VALUES
(1, 1, 'REQ-2024-001', '2024-07-01', 'First annual leave'),
(2, 2, 'REQ-2024-002', '2024-07-02', 'Medical insurance issued');

-- Audit Logs table
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Edit History table
CREATE TABLE edit_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    old_data TEXT,
    new_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
); 