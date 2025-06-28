<?php
require_once '../config/database.php';
header('Content-Type: application/json');

define('EXPENSE_CATEGORIES', [
    'Housing' => '#4e73df',
    'Food' => '#1cc88a',
    'Transportation' => '#36b9cc',
    'Healthcare' => '#f6c23e',
    'Entertainment' => '#e74a3b',
    'Shopping' => '#858796',
    'Education' => '#5a5c69',
    'Debt/Loan' => '#2c9faf',
    'Other' => '#4A5568'
]);

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Add debug logging
    error_log('Received request - type: ' . ($_GET['type'] ?? 'not set') . ', period: ' . ($_GET['period'] ?? 'not set'));
    
    $type = $_GET['type'] ?? '';
    $period = $_GET['period'] ?? 'month';

    if ($type === 'flow') {
        switch($period) {
            case 'day':
                // Show all days in current month, label = 1,2,3,...
                $query = "SELECT 
                    DAY(date) as label,
                    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
                    FROM transactions 
                    WHERE DATE_FORMAT(date, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m')
                    AND status != 'deleted'
                    GROUP BY DAY(date)
                    ORDER BY DAY(date) ASC";
                break;

            case 'week':
                // Show all weeks in current month, label = Week 1, Week 2, ...
                $query = "SELECT 
                    FLOOR((DAYOFMONTH(date)-1)/7) + 1 as week_num,
                    CONCAT('Week ', FLOOR((DAYOFMONTH(date)-1)/7) + 1) as label,
                    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
                    FROM transactions 
                    WHERE DATE_FORMAT(date, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m')
                    AND status != 'deleted'
                    GROUP BY week_num
                    ORDER BY week_num ASC";
                break;

            case 'month':
                // Show all months in current year, label = Jan, Feb, ... (index 1-12)
                $query = "SELECT 
                    MONTH(date) as month_num,
                    DATE_FORMAT(date, '%b') as label,
                    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
                    FROM transactions 
                    WHERE YEAR(date) = YEAR(CURRENT_DATE)
                    AND status != 'deleted'
                    GROUP BY month_num, label
                    ORDER BY month_num ASC";
                break;

            case 'year':
                // Show 6 years (rolling window: current year - 1 to current year + 4)
                $currentYear = date('Y');
                $startYear = $currentYear - 1;
                $endYear = $currentYear + 4;
                $query = "SELECT 
                    YEAR(date) as label,
                    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
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

        // Untuk daily, weekly, monthly, yearly: label harus urut dan mulai dari 1
        if ($period === 'day') {
            $labels = [];
            $income = [];
            $expenses = [];
            $daysInMonth = intval(date('t'));
            for ($i = 1; $i <= $daysInMonth; $i++) {
                $labels[] = (string)$i;
                $found = false;
                foreach ($filledData as $row) {
                    if ((int)$row['label'] === $i) {
                        $income[] = floatval($row['income']);
                        $expenses[] = floatval($row['expense']);
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $income[] = 0;
                    $expenses[] = 0;
                }
            }
        } elseif ($period === 'week') {
            $numWeeks = ceil(date('t') / 7);
            $labels = [];
            $income = [];
            $expenses = [];
            for ($w = 1; $w <= $numWeeks; $w++) {
            $labels[] = "Week $w";
            $found = false;
            foreach ($filledData as $row) {
                if ((int)$row['week_num'] === $w || $row['label'] == "Week $w") {
                    $income[] = floatval($row['income']);
                    $expenses[] = floatval($row['expense']);
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $income[] = 0;
                $expenses[] = 0;
            }
        }
        } elseif ($period === 'month') {
            $months = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'];
            $labels = [];
            $income = [];
            $expenses = [];
            foreach ($months as $num => $mon) {
                $labels[] = $mon;
                $found = false;
                foreach ($filledData as $row) {
                    if ((int)($row['month_num'] ?? 0) === $num || $row['label'] === $mon) {
                        $income[] = floatval($row['income']);
                        $expenses[] = floatval($row['expense']);
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $income[] = 0;
                    $expenses[] = 0;
                }
            }
        } elseif ($period === 'year') {
            $currentYear = date('Y');
            $startYear = $currentYear - 1;
            $endYear = $currentYear + 4;
            $labels = [];
            $income = [];
            $expenses = [];
            for ($y = $startYear; $y <= $endYear; $y++) {
                $labels[] = (string)$y;
                $found = false;
                foreach ($filledData as $row) {
                    if ((int)$row['label'] === $y) {
                        $income[] = floatval($row['income']);
                        $expenses[] = floatval($row['expense']);
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $income[] = 0;
                    $expenses[] = 0;
                }
            }
        }

        echo json_encode([
            'success' => true,
            'dates' => $labels,
            'income' => $income,
            'expenses' => $expenses
        ]);
        exit;
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

        // Tambahkan warna tetap ke setiap kategori
        $result = array_map(function($item) {
            return [
                'category_name' => $item['category_name'],
                'total' => floatval($item['total']),
                'color' => EXPENSE_CATEGORIES[$item['category_name']] ?? '#858796'
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
        // Get monthly comparison data for current year (Jan-Dec)
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
                        'month' => $month,
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
                    'month' => $month,
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
        if ($period === 'year') {
            // Rolling window: 6 tahun, dari tahun berjalan-1 sampai tahun berjalan+4
            $currentYear = date('Y');
            $startYear = $currentYear - 1;
            $endYear = $currentYear + 4;
            $query = "SELECT 
                p.id as product_id,
                p.name as product_name,
                p.price as product_price,
                YEAR(t.date) as period_label,
                COUNT(t.id) as qty
            FROM transactions t
            INNER JOIN products p ON t.product_id = p.id
            WHERE t.type = 'income'
                AND t.status = 'completed'
                AND YEAR(t.date) >= $startYear
                AND YEAR(t.date) <= $endYear
                AND p.is_active = 1
            GROUP BY p.id, p.name, p.price, period_label
            ORDER BY p.name, p.price, period_label";
            $labels = [];
            for ($y = $startYear; $y <= $endYear; $y++) $labels[] = (string)$y;
        } elseif ($period === 'month') {
            $year = date('Y');
            $query = "SELECT 
                p.id as product_id,
                p.name as product_name,
                p.price as product_price,
                MONTH(t.date) as period_num,
                DATE_FORMAT(t.date, '%b') as period_label,
                COUNT(t.id) as qty
            FROM transactions t
            INNER JOIN products p ON t.product_id = p.id
            WHERE t.type = 'income'
                AND t.status = 'completed'
                AND YEAR(t.date) = $year
                AND p.is_active = 1
            GROUP BY p.id, p.name, p.price, period_num, period_label
            ORDER BY p.name, p.price, period_num";
            $labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        } elseif ($period === 'week') {
            $year = date('Y');
            $query = "SELECT 
                p.id as product_id,
                p.name as product_name,
                p.price as product_price,
                FLOOR((DAYOFMONTH(t.date)-1)/7) + 1 as period_num,
                CONCAT('Week ', FLOOR((DAYOFMONTH(t.date)-1)/7) + 1) as period_label,
                COUNT(t.id) as qty
            FROM transactions t
            INNER JOIN products p ON t.product_id = p.id
            WHERE t.type = 'income'
                AND t.status = 'completed'
                AND YEAR(t.date) = $year
                AND p.is_active = 1
            GROUP BY p.id, p.name, p.price, period_num, period_label
            ORDER BY p.name, p.price, period_num";
            $numWeeks = ceil(date('t') / 7);
            $labels = [];
            for ($w = 1; $w <= $numWeeks; $w++) $labels[] = "Week $w";
        } else {
            $year = date('Y');
            $month = date('m');
            $query = "SELECT 
                p.id as product_id,
                p.name as product_name,
                p.price as product_price,
                DAY(t.date) as period_num,
                DAY(t.date) as period_label,
                COUNT(t.id) as qty
            FROM transactions t
            INNER JOIN products p ON t.product_id = p.id
            WHERE t.type = 'income'
                AND t.status = 'completed'
                AND YEAR(t.date) = $year
                AND MONTH(t.date) = $month
                AND p.is_active = 1
            GROUP BY p.id, p.name, p.price, period_num, period_label
            ORDER BY p.name, p.price, period_num";
            $days = (int)date('t');
            $labels = [];
            for ($d = 1; $d <= $days; $d++) $labels[] = (string)$d;
        }

        $stmt = $conn->query($query);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Susun data: labels = period_label, datasets = per produk+harga (qty integer, tidak koma)
        $products = [];
        $productInfo = []; // Untuk info detail produk
        foreach ($rows as $row) {
            $key = $row['product_name'] . ' (Rp' . number_format($row['product_price'], 0, ',', '.') . ')';
            if (!isset($products[$key])) $products[$key] = [];
            $products[$key][$row['period_label']] = (int)$row['qty'];
            // Simpan info produk (nama, harga, id)
            $productInfo[$key] = [
                'product_id' => $row['product_id'],
                'product_name' => $row['product_name'],
                'product_price' => (int)$row['product_price']
            ];
        }
        // Build datasets
        $datasets = [];
        $colorList = [
            '#4e73df','#1cc88a','#36b9cc','#f6c23e','#e74a3b','#858796','#5a5c69','#2c9faf','#7E57C2','#4A5568'
        ];
        $colorIdx = 0;
        foreach ($products as $productLabel => $sales) {
            $data = [];
            foreach ($labels as $label) {
                $data[] = isset($sales[$label]) ? (int)$sales[$label] : 0;
            }
            $datasets[] = [
                'label' => $productLabel,
                'data' => $data,
                'backgroundColor' => $colorList[$colorIdx % count($colorList)],
                'borderColor' => $colorList[$colorIdx % count($colorList)],
                'fill' => false,
                // Tambahan info produk
                'product_id' => $productInfo[$productLabel]['product_id'],
                'product_name' => $productInfo[$productLabel]['product_name'],
                'product_price' => $productInfo[$productLabel]['product_price']
            ];
            $colorIdx++;
        }

        // Tambahkan summary total terjual per produk
        $summary = [];
        foreach ($datasets as $ds) {
            $totalQty = array_sum($ds['data']);
            $summary[] = [
                'product_id' => $ds['product_id'],
                'product_name' => $ds['product_name'],
                'product_price' => $ds['product_price'],
                'total_qty' => $totalQty
            ];
        }

        echo json_encode([
            'success' => true,
            'labels' => $labels,
            'datasets' => $datasets,
            'summary' => $summary // Untuk tabel/analisis tambahan di frontend
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
            // Fill all days in current month, label = 1,2,3,...
            $daysInMonth = intval($today->format('t'));
            for($i = 1; $i <= $daysInMonth; $i++) {
                $found = false;
                foreach($data as $row) {
                    if((int)$row['label'] === $i) {
                        $filled[] = $row;
                        $found = true;
                        break;
                    }
                }
                if(!$found) {
                    $filled[] = ['label' => $i, 'income' => 0, 'expense' => 0];
                }
            }
            break;

        case 'week':
            // Fill all weeks in current month (usually 4-5 weeks), label = Week 1, Week 2, ...
            $numWeeks = ceil($today->format('t') / 7);
            for($w = 1; $w <= $numWeeks; $w++) {
                $found = false;
                foreach($data as $row) {
                    if((isset($row['week_num']) && (int)$row['week_num'] === $w) || $row['label'] == "Week $w") {
                        $filled[] = $row;
                        $found = true;
                        break;
                    }
                }
                if(!$found) {
                    $filled[] = ['week_num' => $w, 'label' => "Week $w", 'income' => 0, 'expense' => 0];
                }
            }
            break;

        case 'month':
            // Fill all months in year, label = Jan, Feb, ... (index 1-12)
            $months = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'];
            foreach($months as $num => $mon) {
                $found = false;
                foreach($data as $row) {
                    if ((isset($row['month_num']) && (int)$row['month_num'] === $num) || $row['label'] === $mon) {
                        $filled[] = $row;
                        $found = true;
                        break;
                    }
                }
                if(!$found) {
                    $filled[] = ['month_num' => $num, 'label' => $mon, 'income' => 0, 'expense' => 0];
                }
            }
            break;

        case 'year':
            // Fill 6 years (current year - 1 until current year + 4)
            $currentYear = intval($today->format('Y'));
            $startYear = $currentYear - 1;
            $endYear = $currentYear + 4;
            for($y = $startYear; $y <= $endYear; $y++) {
                $found = false;
                foreach($data as $row) {
                    if((int)$row['label'] === $y) {
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