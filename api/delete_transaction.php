<?php
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $conn->beginTransaction();

    $id = $_GET['id'] ?? null;
    if (!$id) {
        throw new Exception('Transaction ID is required');
    }

    // Get transaction details first for rollback purposes
    $stmt = $conn->prepare("
        SELECT t.*, ec.category_name 
        FROM transactions t
        LEFT JOIN expense_categories ec ON t.expense_category_id = ec.id 
        WHERE t.id = ?
    ");
    $stmt->execute([$id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new Exception('Transaction not found');
    }

    // Hard delete transaction
    $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ?");
    $result = $stmt->execute([$id]);

    if ($result) {
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Transaction deleted successfully'
        ]);
    } else {
        throw new Exception('Failed to delete transaction');
    }

} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
