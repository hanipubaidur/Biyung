<?php
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    if (!isset($_GET['id'])) {
        throw new Exception('ID is required');
    }

    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT t.*, e.category_name FROM transactions t LEFT JOIN expense_categories e ON t.expense_category_id = e.id WHERE t.id = ?");
    $stmt->execute([$_GET['id']]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        throw new Exception('Transaction not found');
    }
    
    // Sertakan employee_id jika Salary
    $result = $transaction;
    if ($transaction['category_name'] === 'Salary') {
        $result['employee_id'] = $transaction['employee_id'];
    }

    echo json_encode($result);
} catch(Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
