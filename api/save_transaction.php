<?php
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get form data
    $type = $_POST['type'];
    $amount = $_POST['amount'];
    $date = $_POST['date'];
    $description = $_POST['description'];
    $category = $_POST['category'];

    // Parse category value (format: type_id)
    list($categoryType, $categoryId) = explode('_', $category);

    // Set the appropriate column
    $sourceColumn = ($type === 'income') ? 'income_source_id' : 'expense_category_id';

    // Ambil product_id jika income
    $product_id = null;
    if ($type === 'income') {
        $product_id = $_POST['product_id'] ?? null;
        if ($product_id === '') $product_id = null;
    }

    // Ambil quantity jika income
    $quantity = 1;
    if ($type === 'income') {
        $quantity = isset($_POST['quantity']) && intval($_POST['quantity']) > 0 ? intval($_POST['quantity']) : 1;
    }

    // Cek apakah expense category Salary
    $employee_id = null;
    if ($type === 'expense') {
        $stmt = $conn->prepare("SELECT category_name FROM expense_categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        $categoryName = $stmt->fetchColumn();
        if ($categoryName === 'Salary') {
            $employee_id = $_POST['employee_id'] ?? null;
            if (empty($employee_id)) {
                throw new Exception('Employee is required for Salary expense');
            }
        }
    }

    // Ambil shift_id
    $shift_id = isset($_POST['shift_id']) && $_POST['shift_id'] !== '' ? intval($_POST['shift_id']) : null;

    // Start transaction
    $conn->beginTransaction();

    try {
        // Insert/update transaction
        if (isset($_POST['transaction_id'])) {
            $query = "UPDATE transactions 
                     SET type = ?, amount = ?, quantity = ?, date = ?, description = ?,
                         $sourceColumn = ?, employee_id = ?, product_id = ?, shift_id = ?
                     WHERE id = ?";
            $params = [$type, $amount, $quantity, $date, $description, $categoryId, $employee_id, $product_id, $shift_id, $_POST['transaction_id']];
        } else {
            $query = "INSERT INTO transactions 
                     (type, amount, quantity, date, description, $sourceColumn, employee_id, product_id, shift_id, status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')";
            $params = [$type, $amount, $quantity, $date, $description, $categoryId, $employee_id, $product_id, $shift_id];
        }

        $stmt = $conn->prepare($query);
        if (!$stmt->execute($params)) {
            throw new Exception('Failed to save transaction');
        }

        // Kurangi stok produk jika income dan ada product_id
        if ($type === 'income' && $product_id) {
            $updateStock = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
            $updateStock->execute([$quantity, $product_id, $quantity]);
        }

        $transactionId = isset($_POST['transaction_id']) ? 
            $_POST['transaction_id'] : $conn->lastInsertId();

        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => isset($_POST['transaction_id']) ? 
                'Transaction updated successfully' : 
                'Transaction saved successfully'
        ]);

    } catch(Exception $e) {
        $conn->rollBack();
        throw $e;
    }

} catch(Exception $e) {
    error_log('Save Transaction Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
