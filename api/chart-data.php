<?php
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Add debug logging
    error_log('Received request - type: ' . ($_GET['type'] ?? 'not set') . ', period: ' . ($_GET['period'] ?? 'not set'));
    
    $type = $_GET['type'] ?? '';
    $period = $_GET['period'] ?? 'month';

    if ($type === 'flow') {
        $date = $_GET['date'] ?? null;
        switch($period) {
            case 'date':
                $dateVal = $date ?: date('Y-m-d');
                $query = "SELECT 
                    DATE_FORMAT(date, '%d %b') as label,
                    SUM(CASE 
                        WHEN type = 'income' 
                            AND income_source_id IS NOT NULL 
                            AND (expense_category_id IS NULL OR expense_category_id = 0)
                        THEN amount ELSE 0 END) as income,
                    SUM(CASE 
                        WHEN type = 'expense' 
                            AND expense_category_id IS NOT NULL 
                            AND (income_source_id IS NULL OR income_source_id = 0)
                        THEN amount ELSE 0 END) as expense
                    FROM transactions 
                    WHERE DATE(date) = " . $conn->quote($dateVal) . "
                    AND status != 'deleted'
                    GROUP BY DATE(date)
                    ORDER BY date ASC";
                break;

            case 'day':
                // Show all days in current month
                $query = "SELECT 
                    DATE_FORMAT(date, '%d %b') as label,
                    SUM(CASE 
                        WHEN type = 'income' 
                            AND income_source_id IS NOT NULL 
                            AND (expense_category_id IS NULL OR expense_category_id = 0)
                        THEN amount ELSE 0 END) as income,
                    SUM(CASE 
                        WHEN type = 'expense' 
                            AND expense_category_id IS NOT NULL 
                            AND (income_source_id IS NULL OR income_source_id = 0)
                        THEN amount ELSE 0 END) as expense
                    FROM transactions 
                    WHERE DATE_FORMAT(date, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m')
                    AND status != 'deleted'
                    GROUP BY DATE(date)
                    ORDER BY date ASC";
                break;

            case 'week':
                // Show all weeks in current month
                $query = "SELECT 
                    CONCAT('Week ', 
                        FLOOR((DAYOFMONTH(date)-1)/7) + 1) as label,
                    SUM(CASE 
                        WHEN type = 'income' 
                            AND income_source_id IS NOT NULL 
                            AND (expense_category_id IS NULL OR expense_category_id = 0)
                        THEN amount ELSE 0 END) as income,
                    SUM(CASE 
                        WHEN type = 'expense' 
                            AND expense_category_id IS NOT NULL 
                            AND (income_source_id IS NULL OR income_source_id = 0)
                        THEN amount ELSE 0 END) as expense
                    FROM transactions 
                    WHERE DATE_FORMAT(date, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m')
                    AND status != 'deleted'
                    AND type IN ('income', 'expense')
                    GROUP BY FLOOR((DAYOFMONTH(date)-1)/7)
                    ORDER BY MIN(date) ASC";
                break;

            case 'month':
                // Show all months in current year
                $query = "SELECT 
                    DATE_FORMAT(date, '%M') as label,
                    SUM(CASE 
                        WHEN type = 'income' 
                            AND income_source_id IS NOT NULL 
                            AND (expense_category_id IS NULL OR expense_category_id = 0)
                        THEN amount ELSE 0 END) as income,
                    SUM(CASE 
                        WHEN type = 'expense' 
                            AND expense_category_id IS NOT NULL 
                            AND (income_source_id IS NULL OR income_source_id = 0)
                        THEN amount ELSE 0 END) as expense
                    FROM transactions 
                    WHERE YEAR(date) = YEAR(CURRENT_DATE)
                    AND status != 'deleted'
                    GROUP BY MONTH(date)
                    ORDER BY MONTH(date) ASC";
                break;

            case 'year':
                // Show 6 years (current year - 2 until current year + 3)
                $currentYear = date('Y');
                $startYear = $currentYear - 2;
                $endYear = $currentYear + 3;
                $query = "SELECT 
                    YEAR(date) as label,
                    SUM(CASE 
                        WHEN type = 'income' 
                            AND income_source_id IS NOT NULL 
                            AND (expense_category_id IS NULL OR expense_category_id = 0)
                        THEN amount ELSE 0 END) as income,
                    SUM(CASE 
                        WHEN type = 'expense' 
                            AND expense_category_id IS NOT NULL 
                            AND (income_source_id IS NULL OR income_source_id = 0)
                        THEN amount ELSE 0 END) as expense
                    FROM transactions 
                    WHERE YEAR(date) BETWEEN $startYear AND $endYear
                    AND status != 'deleted'
                    GROUP BY YEAR(date)
                    ORDER BY YEAR(date) ASC";
                break;
        }

        $stmt = $conn->query($query);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fill missing periods in chronological order
        $filledData = fillMissingPeriods($data, $period);

        echo json_encode([
            'success' => true,
            'dates' => array_column($filledData, 'label'),
            'income' => array_map('floatval', array_column($filledData, 'income')),
            'expenses' => array_map('floatval', array_column($filledData, 'expense'))
        ]);
    } 
    elseif ($type === 'category') {
        // Perbaiki where clause untuk 'week'
        $where_clause = match($period) {
            'day' => "DATE(t.date) = CURRENT_DATE",
            'week' => "YEARWEEK(t.date) = YEARWEEK(CURRENT_DATE)",
            'month' => "DATE_FORMAT(t.date, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m')",
            'year' => "YEAR(t.date) = YEAR(CURRENT_DATE)",
            default => "DATE_FORMAT(t.date, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m')"
        };

        // Perbaiki query expense distribution
        $query = "SELECT 
            ec.category_name,
            COALESCE(SUM(t.amount), 0) as total,
            COUNT(t.id) as transaction_count
            FROM expense_categories ec
            LEFT JOIN transactions t ON (
                ec.id = t.expense_category_id 
                AND t.type = 'expense'
                AND t.status != 'deleted'
                AND $where_clause
            )
            WHERE ec.is_active = TRUE 
            GROUP BY ec.id, ec.category_name
            HAVING total > 0
            ORDER BY total DESC";

        $stmt = $conn->query($query);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Hapus penambahan warna kategori
        $result = array_map(function($item) {
            return [
                'category_name' => $item['category_name'],
                'total' => floatval($item['total'])
            ];
        }, $expenses);

        echo json_encode($result);
        exit;
    }
    elseif ($type === 'top_income') {
        // Get real-time top income sources for current month
        $query = "SELECT 
            is.source_name,
            COALESCE(SUM(t.amount), 0) as total
            FROM income_sources is
            LEFT JOIN transactions t ON (
                is.id = t.income_source_id 
                AND t.type = 'income'
                AND MONTH(t.date) = MONTH(CURRENT_DATE)
                AND YEAR(t.date) = YEAR(CURRENT_DATE)
            )
            GROUP BY is.id, is.source_name
            HAVING total > 0
            ORDER BY total DESC
            LIMIT 5";

        $stmt = $conn->query($query);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($result);
    }
    elseif ($type === 'top_expenses') {
        // Get real-time top expenses with budget comparison
        $query = "SELECT 
            ec.category_name,
            COALESCE(SUM(t.amount), 0) as total,
            ec.budget_limit
            FROM expense_categories ec
            LEFT JOIN transactions t ON (
                ec.id = t.expense_category_id 
                AND t.type = 'expense'
                AND MONTH(t.date) = MONTH(CURRENT_DATE)
                AND YEAR(t.date) = YEAR(CURRENT_DATE)
            )
            GROUP BY ec.id, ec.category_name, ec.budget_limit
            HAVING total > 0
            ORDER BY total DESC
            LIMIT 5";

        $stmt = $conn->query($query);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($result);
    }
    elseif ($type === 'reports') {
        // Get monthly cashflow
        $cashflowQuery = "SELECT 
            DATE_FORMAT(date, '%M %Y') as month,
            SUM(CASE WHEN type = 'income' AND status = 'completed' THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN type = 'expense' AND status = 'completed' THEN amount ELSE 0 END) as expense
        FROM transactions 
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        AND status != 'deleted'
        GROUP BY YEAR(date), MONTH(date), DATE_FORMAT(date, '%M %Y')
        ORDER BY YEAR(date) ASC, MONTH(date) ASC";

        $stmt = $conn->query($cashflowQuery);
        $cashflowData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get expense distribution for current month
        $expenseDistQuery = "SELECT 
            ec.category_name,
            COALESCE(SUM(t.amount), 0) as total,
            COALESCE(COUNT(t.id), 0) as transaction_count
        FROM expense_categories ec
        LEFT JOIN transactions t ON (
            ec.id = t.expense_category_id 
            AND t.type = 'expense'
            AND t.status = 'completed'
            AND MONTH(t.date) = MONTH(CURRENT_DATE)
            AND YEAR(t.date) = YEAR(CURRENT_DATE)
        )
        GROUP BY ec.id, ec.category_name
        HAVING total > 0
        ORDER BY total DESC";

        $stmt = $conn->query($expenseDistQuery);
        $expenseDistData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate percentages
        $totalExpense = array_sum(array_column($expenseDistData, 'total'));
        foreach ($expenseDistData as &$category) {
            $category['percentage'] = $totalExpense > 0 ? 
                round(($category['total'] / $totalExpense) * 100, 1) : 0;
        }

        echo json_encode([
            'success' => true,
            'cashflow' => [
                'dates' => array_column($cashflowData, 'month'),
                'income' => array_map('floatval', array_column($cashflowData, 'income')),
                'expenses' => array_map('floatval', array_column($cashflowData, 'expense'))
            ],
            'expense_distribution' => $expenseDistData
        ]);
        exit;
    }
    elseif ($type === 'monthly_comparison') {
        // Query untuk 12 bulan tahun berjalan
        $query = "SELECT 
            DATE_FORMAT(date, '%b') as month,
            MONTH(date) as month_num,
            SUM(CASE 
                WHEN type = 'income' AND status = 'completed' 
                THEN amount ELSE 0 
            END) as income,
            SUM(CASE 
                WHEN type = 'expense' AND status = 'completed' 
                THEN amount ELSE 0 
            END) as expense
        FROM transactions 
        WHERE YEAR(date) = YEAR(CURRENT_DATE)
        AND status != 'deleted'
        GROUP BY month_num, month
        ORDER BY month_num ASC";

        $stmt = $conn->query($query);
        $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate complete 12 months data
        $months = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
        ];
        $completeData = [];
        foreach ($months as $num => $month) {
            $found = false;
            foreach ($monthlyData as $data) {
                if ($data['month_num'] == $num) {
                    $completeData[] = [
                        'month' => $month . ' ' . date('Y'),
                        'income' => floatval($data['income']),
                        'expense' => floatval($data['expense']),
                        'net' => floatval($data['income']) - floatval($data['expense'])
                    ];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $completeData[] = [
                    'month' => $month . ' ' . date('Y'),
                    'income' => 0,
                    'expense' => 0,
                    'net' => 0
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'monthly_comparison' => [
                'months' => array_column($completeData, 'month'),
                'income' => array_column($completeData, 'income'),
                'expense' => array_column($completeData, 'expense'),
                'net' => array_column($completeData, 'net')
            ]
        ]);
        exit;
    }
    elseif ($type === 'product_sales') {
        $period = $_GET['period'] ?? 'month';

        // Ambil semua produk
        $products = $conn->query("SELECT id, name, price FROM products ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

        // Palet warna
        $palette = [
            '#4e73df','#1cc88a','#36b9cc','#f6c23e','#e74a3b','#858796','#5a5c69','#2c9faf','#7E57C2','#4A5568'
        ];

        $labels = [];
        $labelMap = [];
        $datasets = [];

        if ($period === 'day') {
            // Label: tanggal 1 - akhir bulan
            $daysInMonth = (int)date('t');
            for ($i = 1; $i <= $daysInMonth; $i++) {
                $labels[] = $i;
                $labelMap[$i] = $i;
            }
            // Query per tanggal
            foreach ($products as $i => $product) {
                $data = [];
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $where = "DATE_FORMAT(t.date, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m') AND DAY(t.date) = $d";
                    $qty = $conn->query("SELECT COALESCE(SUM(quantity),0) FROM transactions t WHERE $where AND t.product_id = {$product['id']} AND t.type='income' AND t.status='completed'")->fetchColumn();
                    $data[] = (int)$qty;
                }
                $color = $palette[$i % count($palette)];
                $datasets[] = [
                    'label' => $product['name'] . ' (Rp' . number_format($product['price'],0,',','.') . ')',
                    'data' => $data,
                    'borderColor' => $color,
                    'backgroundColor' => $color . '80'
                ];
            }
        } elseif ($period === 'week') {
            // Label: Week 1-5 (max 5 minggu per bulan)
            $daysInMonth = (int)date('t');
            $numWeeks = ceil($daysInMonth / 7);
            $labels = [];
            for ($w = 1; $w <= $numWeeks; $w++) {
                $labels[] = "Week $w";
            }
            foreach ($products as $i => $product) {
                $data = [];
                for ($w = 1; $w <= $numWeeks; $w++) {
                    $startDay = ($w - 1) * 7 + 1;
                    $endDay = min($w * 7, $daysInMonth);
                    $where = "DATE_FORMAT(t.date, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m') AND DAY(t.date) BETWEEN $startDay AND $endDay";
                    $qty = $conn->query("SELECT COALESCE(SUM(quantity),0) FROM transactions t WHERE $where AND t.product_id = {$product['id']} AND t.type='income' AND t.status='completed'")->fetchColumn();
                    $data[] = (int)$qty;
                }
                $color = $palette[$i % count($palette)];
                $datasets[] = [
                    'label' => $product['name'] . ' (Rp' . number_format($product['price'],0,',','.') . ')',
                    'data' => $data,
                    'borderColor' => $color,
                    'backgroundColor' => $color . '80'
                ];
            }
        } elseif ($period === 'month') {
            // Label: Jan - Dec
            $labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            foreach ($products as $i => $product) {
                $data = [];
                for ($m = 1; $m <= 12; $m++) {
                    $where = "YEAR(t.date) = YEAR(CURRENT_DATE) AND MONTH(t.date) = $m";
                    $qty = $conn->query("SELECT COALESCE(SUM(quantity),0) FROM transactions t WHERE $where AND t.product_id = {$product['id']} AND t.type='income' AND t.status='completed'")->fetchColumn();
                    $data[] = (int)$qty;
                }
                $color = $palette[$i % count($palette)];
                $datasets[] = [
                    'label' => $product['name'] . ' (Rp' . number_format($product['price'],0,',','.') . ')',
                    'data' => $data,
                    'borderColor' => $color,
                    'backgroundColor' => $color . '80'
                ];
            }
        } elseif ($period === 'year') {
            // Label: 5 tahun, tahun sekarang di tengah
            $currentYear = (int)date('Y');
            $years = [];
            for ($i = -2; $i <= 2; $i++) {
                $years[] = $currentYear + $i;
            }
            $labels = $years;
            foreach ($products as $i => $product) {
                $data = [];
                foreach ($years as $y) {
                    $where = "YEAR(t.date) = $y";
                    $qty = $conn->query("SELECT COALESCE(SUM(quantity),0) FROM transactions t WHERE $where AND t.product_id = {$product['id']} AND t.type='income' AND t.status='completed'")->fetchColumn();
                    $data[] = (int)$qty;
                }
                $color = $palette[$i % count($palette)];
                $datasets[] = [
                    'label' => $product['name'] . ' (Rp' . number_format($product['price'],0,',','.') . ')',
                    'data' => $data,
                    'borderColor' => $color,
                    'backgroundColor' => $color . '80'
                ];
            }
        } else {
            // Default: monthly
            $labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            foreach ($products as $i => $product) {
                $data = [];
                for ($m = 1; $m <= 12; $m++) {
                    $where = "YEAR(t.date) = YEAR(CURRENT_DATE) AND MONTH(t.date) = $m";
                    $qty = $conn->query("SELECT COALESCE(SUM(quantity),0) FROM transactions t WHERE $where AND t.product_id = {$product['id']} AND t.type='income' AND t.status='completed'")->fetchColumn();
                    $data[] = (int)$qty;
                }
                $color = $palette[$i % count($palette)];
                $datasets[] = [
                    'label' => $product['name'] . ' (Rp' . number_format($product['price'],0,',','.') . ')',
                    'data' => $data,
                    'borderColor' => $color,
                    'backgroundColor' => $color . '80'
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'labels' => $labels,
            'datasets' => $datasets
        ]);
        exit;
    }

} catch(Exception $e) {
    error_log('Chart Data Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'message' => 'Failed to load chart data'
    ]);
}

function fillMissingPeriods($data, $period) {
    $filled = [];
    $today = new DateTime();
    
    switch($period) {
        case 'day':
            // Fill all days in current month
            $daysInMonth = intval($today->format('t'));
            for($i = 1; $i <= $daysInMonth; $i++) {
                $date = (new DateTime())->setDate($today->format('Y'), $today->format('m'), $i);
                $dateLabel = $date->format('d M');
                $found = false;
                foreach($data as $row) {
                    if($row['label'] == $dateLabel) {
                        $filled[] = $row;
                        $found = true;
                        break;
                    }
                }
                if(!$found) {
                    $filled[] = ['label' => $dateLabel, 'income' => 0, 'expense' => 0];
                }
            }
            break;

        case 'week':
            // Always fill 5 weeks for months with 29-31 days
            $daysInMonth = intval($today->format('t'));
            $numWeeks = ceil($daysInMonth / 7);
            // Ensure at least 4, at most 5 weeks
            $numWeeks = max(4, min(5, $numWeeks));
            for($w = 1; $w <= $numWeeks; $w++) {
                $weekLabel = "Week " . $w;
                $found = false;
                foreach($data as $row) {
                    if($row['label'] == $weekLabel) {
                        $filled[] = $row;
                        $found = true;
                        break;
                    }
                }
                if(!$found) {
                    $filled[] = ['label' => $weekLabel, 'income' => 0, 'expense' => 0];
                }
            }
            break;

        case 'month':
            // Fill all months in year
            $months = ['January', 'February', 'March', 'April', 'May', 'June',
                      'July', 'August', 'September', 'October', 'November', 'December'];
            foreach($months as $month) {
                $found = false;
                foreach($data as $row) {
                    if($row['label'] == $month) {
                        $filled[] = $row;
                        $found = true;
                        break;
                    }
                }
                if(!$found) {
                    $filled[] = ['label' => $month, 'income' => 0, 'expense' => 0];
                }
            }
            break;

        case 'year':
            // Fill 6 years (current year - 2 until current year + 3)
            $currentYear = intval($today->format('Y'));
            $startYear = $currentYear - 2;
            $endYear = $currentYear + 3;
            for($y = $startYear; $y <= $endYear; $y++) {
                $found = false;
                foreach($data as $row) {
                    if($row['label'] == $y) {
                        $filled[] = $row;
                        $found = true;
                        break;
                    }
                }
                if(!$found) {
                    $filled[] = ['label' => $y, 'income' => 0, 'expense' => 0];
                }
            }
            break;
    }

    return $filled;
}