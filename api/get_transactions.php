<?php
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();

    $query = "SELECT 
                t.id,
                t.date,
                t.type,
                t.amount,
                t.description,
                t.status,
                CASE 
                    WHEN t.type = 'income' THEN i.source_name
                    WHEN t.type = 'expense' AND e.category_name = 'Salary' AND emp.name IS NOT NULL
                        THEN CONCAT(e.category_name, ' - ', emp.name)
                    ELSE e.category_name
                END as category,
                t.shift_id,
                s.name as shift_name
              FROM transactions t
              LEFT JOIN income_sources i ON t.income_source_id = i.id
              LEFT JOIN expense_categories e ON t.expense_category_id = e.id
              LEFT JOIN employees emp ON t.employee_id = emp.id
              LEFT JOIN shifts s ON t.shift_id = s.id
              WHERE t.status = 'completed'
              ORDER BY t.date DESC, t.id DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch(PDOException $e) {
    error_log('Get Transactions Error: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to load transactions']);
}
