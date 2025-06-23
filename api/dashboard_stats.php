<?php
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $period = $_GET['period'] ?? 'month';
    
    // Perbaiki where clause
    switch($period) {
        case 'day':
            $where_clause = "DATE(date) = CURRENT_DATE";
            $label = "Daily";
            break;
        case 'week':
            $where_clause = "YEARWEEK(date, 1) = YEARWEEK(CURRENT_DATE, 1)";
            $label = "Weekly";
            break;
        case 'month':
            $where_clause = "DATE_FORMAT(date, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m')";
            $label = "Monthly";
            break;
        case 'year':
            $where_clause = "YEAR(date) = YEAR(CURRENT_DATE)";
            $label = "Yearly";
            break;
        default:
            $where_clause = "DATE_FORMAT(date, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m')";
            $label = "Monthly";
    }

    // Perbaiki query untuk statistik yang lebih akurat
    $query = "SELECT 
        (SELECT total_balance FROM balance_tracking WHERE id = 1) as total_balance,
        
        (SELECT COALESCE(SUM(amount), 0)
         FROM transactions 
         WHERE type = 'income' AND status = 'completed'
         AND $where_clause) as period_income,
         
        (SELECT COALESCE(SUM(amount), 0)
         FROM transactions 
         WHERE type = 'expense' AND status = 'completed'
         AND $where_clause) as period_expenses,
         
        (SELECT MAX(created_at) FROM transactions WHERE $where_clause) as last_update,
        
        (SELECT MAX(created_at) 
         FROM transactions 
         WHERE type = 'income' AND $where_clause) as last_income_update,
         
        (SELECT MAX(created_at)
         FROM transactions 
         WHERE type = 'expense' AND $where_clause) as last_expense_update";

    $stats = $conn->query($query)->fetch(PDO::FETCH_ASSOC);

    // Hitung expense ratio: persentase expense dari income
    $expenseRatio = $stats['period_income'] > 0
        ? round(($stats['period_expenses'] / $stats['period_income']) * 100, 1)
        : 0;

    echo json_encode([
        'success' => true,
        'label' => $label,
        'total_balance' => floatval($stats['total_balance']),
        'period_income' => floatval($stats['period_income']),
        'period_expenses' => floatval($stats['period_expenses']),
        'expense_ratio' => $expenseRatio,
        // 'active_employees' => intval($stats['active_employees']), // Dihapus
        // Kirim null jika tidak ada data
        'last_update' => $stats['last_update'] ?: null,
        'last_income_update' => $stats['last_income_update'] ?: null,
        'last_expense_update' => $stats['last_expense_update'] ?: null
    ]);

} catch(Exception $e) {
    error_log("Error in dashboard_stats.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
