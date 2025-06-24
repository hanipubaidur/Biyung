DROP DATABASE IF EXISTS biyung;
CREATE DATABASE biyung;
USE biyung;

-- Tambah tabel employees
CREATE TABLE employees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    join_date DATE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Balance tracking tetap
CREATE TABLE balance_tracking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    total_balance DECIMAL(15,2) DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO balance_tracking (total_balance) VALUES (0);

-- Income sources (khusus Biyung)
CREATE TABLE income_sources (
    id INT PRIMARY KEY AUTO_INCREMENT,
    source_name VARCHAR(50) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Expense categories (khusus Biyung)
CREATE TABLE expense_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(50) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    stock INT DEFAULT 0,
    price INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Transactions table
CREATE TABLE transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type ENUM('income', 'expense') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    date DATE NOT NULL,
    description TEXT,
    income_source_id INT NULL,
    expense_category_id INT NULL,
    product_id INT NULL,
    payment_method ENUM('cash', 'bank_transfer', 'e-wallet', 'qris') DEFAULT 'cash',
    employee_id INT NULL,
    status ENUM('completed', 'pending', 'cancelled', 'deleted') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (income_source_id) REFERENCES income_sources(id) ON DELETE SET NULL,
    FOREIGN KEY (expense_category_id) REFERENCES expense_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
);

-- Monthly category summaries
CREATE TABLE category_monthly_summaries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    year INT NOT NULL,
    month INT NOT NULL,
    total_amount DECIMAL(15,2) DEFAULT 0,
    transaction_count INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES expense_categories(id),
    UNIQUE KEY year_month_category (year, month, category_id)
);

-- View income summary
CREATE VIEW v_income_summary AS
SELECT 
    i.id,
    i.source_name,
    COUNT(t.id) as transaction_count,
    COALESCE(SUM(t.amount), 0) as total_amount,
    MAX(t.date) as last_transaction
FROM income_sources i
LEFT JOIN transactions t ON i.id = t.income_source_id 
    AND t.type = 'income' 
    AND t.status = 'completed'
GROUP BY i.id, i.source_name;

-- View expense summary
CREATE VIEW v_expense_summary AS
SELECT 
    e.id,
    e.category_name,
    COUNT(t.id) as transaction_count,
    COALESCE(SUM(t.amount), 0) as total_amount
FROM expense_categories e
LEFT JOIN transactions t ON e.id = t.expense_category_id 
    AND t.type = 'expense' 
    AND t.status = 'completed'
GROUP BY e.id, e.category_name;

-- Insert default income sources (Biyung)
INSERT INTO income_sources (source_name, description) VALUES
('Cash', 'Tunai langsung dari pembeli'),
('Bank Transfer/E-Wallet', 'Transfer bank atau e-wallet'),
('QRIS', 'Pembayaran via QRIS');

-- Insert default expense categories (Biyung)
INSERT INTO expense_categories (category_name, description) VALUES
('Shopping', 'Modal bahan baku'),
('Salary', 'Gaji karyawan'),
('Other', 'Pengeluaran lain-lain');

-- Triggers (update balance)
DELIMITER //

CREATE TRIGGER after_transaction_insert
AFTER INSERT ON transactions
FOR EACH ROW
BEGIN
    -- Update monthly summaries
    IF NEW.type = 'expense' AND NEW.status = 'completed' THEN
        INSERT INTO category_monthly_summaries (
            category_id, year, month, total_amount, transaction_count
        ) 
        SELECT 
            NEW.expense_category_id,
            YEAR(NEW.date),
            MONTH(NEW.date),
            NEW.amount,
            1
        FROM expense_categories ec
        WHERE ec.id = NEW.expense_category_id
        ON DUPLICATE KEY UPDATE
            total_amount = total_amount + NEW.amount,
            transaction_count = transaction_count + 1;
    END IF;

    -- Update balance tracking
    IF NEW.status = 'completed' THEN
        UPDATE balance_tracking SET
            total_balance = (
                SELECT COALESCE(SUM(
                    CASE 
                        WHEN type = 'income' AND status = 'completed' THEN amount 
                        WHEN type = 'expense' AND status = 'completed' THEN -amount
                        ELSE 0
                    END
                ), 0)
                FROM transactions
            )
        WHERE id = 1;
    END IF;
END//

DELIMITER ;