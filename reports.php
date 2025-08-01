<?php
require_once 'config/database.php';

// Get period from URL parameter atau set default ke 'month'
$period = $_GET['period'] ?? 'month';

// Helper function for currency formatting - update to IDR
function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Initialize variables
$db = new Database();
$conn = $db->getConnection();

// Get key metrics with more accurate calculations
$where_clause = "";
switch($period) {
    case 'day':
        $where_clause = "DATE(t.date) = CURRENT_DATE";
        break;
    case 'week':
        $where_clause = "YEARWEEK(t.date, 1) = YEARWEEK(CURRENT_DATE, 1)";
        break;
    case 'month':
        $where_clause = "DATE_FORMAT(t.date, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m')";
        break;
    case 'year':
        $where_clause = "YEAR(t.date) = YEAR(CURRENT_DATE)";
        break;
    default:
        $where_clause = "DATE_FORMAT(t.date, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m')";
}

$metricsQuery = "SELECT 
    COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) as total_income,
    COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) as total_expense,
    (SELECT MAX(created_at) FROM transactions 
     WHERE status = 'completed') as last_transaction
FROM transactions t 
WHERE $where_clause AND t.status = 'completed'";

$stmt = $conn->query($metricsQuery);
$metrics = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate metrics dengan rumus yang benar
$net_cashflow = $metrics['total_income'] - $metrics['total_expense'];
$expense_ratio = $metrics['total_income'] > 0 ? 
    ($metrics['total_expense'] / $metrics['total_income'] * 100) : 0;

// Query total produk terjual (income dengan product_id dan status completed)
$totalProductSold = $conn->query("SELECT COALESCE(SUM(quantity),0) FROM transactions WHERE type='income' AND product_id IS NOT NULL AND status='completed'")->fetchColumn();

ob_start();
?>

<div class="loading-overlay" id="loadingOverlay" style="display:none">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Financial Overview</h5>
            </div>
            <div class="card-body">
                <!-- Hapus row untuk period selector -->

                <!-- Key Metrics -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h6>Net Cash Flow</h6>
                                <h3 id="netCashflow" class="mb-0"><?= formatCurrency($net_cashflow) ?></h3>
                                <small class="text-white-50" id="lastUpdate" data-time="<?= $metrics['last_transaction'] ?>">
                                    <?= $metrics['last_transaction'] ? '' : 'No transactions yet' ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6>Expense Ratio</h6>
                                <h3 id="expenseRatio" class="mb-0"><?= number_format($expense_ratio, 1) ?>%</h3>
                                <small id="expenseStatus" class="text-white">
                                    <?php if ($expense_ratio <= 70): ?>
                                        <i class='bx bx-check'></i> Healthy
                                    <?php else: ?>
                                        <i class='bx bx-x'></i> High
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6>Active Employees</h6>
                                <h3 id="activeEmployees" class="mb-0">
                                    <?php
                                    $activeEmployees = $conn->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn();
                                    echo $activeEmployees;
                                    ?>
                                </h3>
                                <small class="text-white-50">Currently active</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h6>Total Product Sold</h6>
                                <h3 id="totalProductSold" class="mb-0"><?= $totalProductSold ?></h3>
                                <small class="text-white-50">All time</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Comparison Table (hapus chart di atasnya) -->
                <div class="card mt-4">
                    <div class="card-body">
                        <div class="monthly-stats p-3 bg-light rounded">
                            <h6 class="text-primary mb-3">Monthly Comparison</h6>
                            <div class="table-responsive">
                                <table class="table table-sm monthly-stats-table">
                                    <thead>
                                        <tr class="bg-light">
                                            <th>Period</th>
                                            <th class="text-end">Income</th>
                                            <th class="text-end">Expenses</th>
                                            <th class="text-end">Net</th>
                                            <th class="text-end">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="monthlyStatsBody">
                                        <?php
                                        // Ambil 12 bulan (Jan - Dec) tahun berjalan
                                        $months = [];
                                        $year = date('Y');
                                        for ($i = 1; $i <= 12; $i++) {
                                            $months[] = date('M Y', strtotime("$year-$i-01"));
                                        }

                                        // Query untuk 12 bulan (hanya bulan yang ada transaksi)
                                        $monthlyStatsQuery = "SELECT 
                                            DATE_FORMAT(date, '%b %Y') as month,
                                            MONTH(date) as month_num,
                                            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                                            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
                                        FROM transactions 
                                        WHERE YEAR(date) = YEAR(CURRENT_DATE)
                                        AND status = 'completed'
                                        GROUP BY month_num, month
                                        ORDER BY month_num ASC";

                                        $monthlyStatsRaw = $conn->query($monthlyStatsQuery)->fetchAll(PDO::FETCH_ASSOC);
                                        $monthlyStatsMap = [];
                                        foreach ($monthlyStatsRaw as $stat) {
                                            $monthlyStatsMap[$stat['month']] = $stat;
                                        }

                                        $totalIncome = 0;
                                        $totalExpense = 0;
                                        ?>

                                        <?php foreach ($months as $month): 
                                            $stat = $monthlyStatsMap[$month] ?? ['income' => 0, 'expense' => 0];
                                            $netAmount = $stat['income'] - $stat['expense'];
                                            $totalIncome += $stat['income'];
                                            $totalExpense += $stat['expense'];
                                            $isEmpty = ($stat['income'] == 0 && $stat['expense'] == 0);
                                        ?>
                                            <tr>
                                                <td><?= $month ?></td>
                                                <td class="text-end"><?= formatCurrency($stat['income']) ?></td>
                                                <td class="text-end"><?= formatCurrency($stat['expense']) ?></td>
                                                <td class="text-end <?= $netAmount > 0 ? 'text-success' : ($netAmount < 0 ? 'text-danger' : '') ?>">
                                                    <?= formatCurrency($netAmount) ?>
                                                </td>
                                                <td class="text-end">
                                                    <?php if ($isEmpty): ?>
                                                        <span class="badge bg-secondary">No Data</span>
                                                    <?php elseif ($netAmount > 0): ?>
                                                        <span class="badge bg-success">Surplus</span>
                                                    <?php elseif ($netAmount < 0): ?>
                                                        <span class="badge bg-danger">Deficit</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Break Even</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="fw-bold border-top">
                                            <td>Total</td>
                                            <td class="text-end"><?= formatCurrency($totalIncome) ?></td>
                                            <td class="text-end"><?= formatCurrency($totalExpense) ?></td>
                                            <td class="text-end <?= ($totalIncome - $totalExpense) >= 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= formatCurrency($totalIncome - $totalExpense) ?>
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product Sales Chart (hapus period select di sini, gunakan global) -->
                <div class="card mt-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="text-primary mb-0">Product Sales Chart</h6>
                            <div class="d-flex align-items-center gap-2">
                                <!-- Period Selector (global, satu untuk seluruh laporan) -->
                                <div>
                                    <select id="reportPeriod" class="form-select form-select-sm" style="width:auto;display:inline-block;">
                                        <option value="day">Daily</option>
                                        <option value="week">Weekly</option>
                                        <option value="month" selected>Monthly</option>
                                        <option value="year">Yearly</option>
                                    </select>
                                </div>
                                <div class="form-check ms-2">
                                    <input class="form-check-input" type="checkbox" id="productSalesShowLine" checked>
                                    <label class="form-check-label" for="productSalesShowLine" style="font-size:0.95em;">Show Line</label>
                                </div>
                            </div>
                        </div>
                        <div style="height:350px;">
                            <canvas id="productSalesChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Top Income & Expenses -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Top Income Sources</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="topIncomeTable">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Source</th>
                                                <th class="text-end">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Get top 5 income sources untuk bulan ini
                                            $topIncomeQuery = "SELECT 
                                                src.source_name,
                                                COALESCE(SUM(t.amount), 0) as total
                                            FROM income_sources src
                                            LEFT JOIN transactions t ON (
                                                src.id = t.income_source_id 
                                                AND t.type = 'income'
                                                AND t.status = 'completed'
                                                AND MONTH(t.date) = MONTH(CURRENT_DATE)
                                                AND YEAR(t.date) = YEAR(CURRENT_DATE)
                                            )
                                            GROUP BY src.id, src.source_name
                                            ORDER BY total DESC
                                            LIMIT 5";

                                            $stmt = $conn->query($topIncomeQuery);
                                            $topIncome = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                            foreach ($topIncome as $index => $source):
                                                $badgeClass = $index < 3 ? 'bg-success' : 'bg-secondary';
                                            ?>
                                            <tr>
                                                <td><span class="badge <?= $badgeClass ?>"><?= $index + 1 ?></span></td>
                                                <td><?= htmlspecialchars($source['source_name']) ?></td>
                                                <td class="text-end"><?= formatCurrency($source['total']) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Top Expenses</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="topExpensesTable">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Category</th>
                                                <th class="text-end">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Get top 5 expense categories 
                                            $topExpensesQuery = "SELECT 
                                                ec.category_name,
                                                COALESCE(SUM(t.amount), 0) as total
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
                                            ORDER BY total DESC
                                            LIMIT 5";

                                            $stmt = $conn->query($topExpensesQuery);
                                            $topExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                            foreach ($topExpenses as $index => $expense):
                                                $badgeClass = $index < 3 ? 'bg-danger' : 'bg-secondary';
                                            ?>
                                                <tr>
                                                    <td><span class="badge <?= $badgeClass ?>"><?= $index + 1 ?></span></td>
                                                    <td><?= htmlspecialchars($expense['category_name']) ?></td>
                                                    <td class="text-end"><?= formatCurrency($expense['total']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tambahkan tabel ringkasan produk terjual di sini -->
                <div class="card mt-4" id="productSalesSummaryCard" style="display:none;">
                    <div class="card-header">
                        <h6 class="mb-0">Product Sales Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="productSalesSummaryTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Product</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-end">Total Qty</th>
                                        <!-- Tambahkan kolom Shift jika ingin menampilkan transaksi detail -->
                                    </tr>
                                </thead>
                                <tbody id="productSalesSummaryBody">
                                    <!-- Data akan diisi melalui JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<style>
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.8);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    transition: opacity 0.3s ease;
}

/* Progress bar animation */
.progress-bar {
    transition: width 0.5s ease-in-out;
}

/* Style untuk card metrics */
.card {
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.border-primary { border-left: 4px solid #4e73df !important; }
.border-success { border-left: 4px solid #1cc88a !important; }
.border-info { border-left: 4px solid #36b9cc !important; }
.border-warning { border-left: 4px solid #f6c23e !important; }

/* Tambahkan style untuk tabel */
.table th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
}

.table td {
    vertical-align: middle;
}

#monthlyComparisonTable th,
#monthlyComparisonTable td {
    padding: 0.75rem;
}

#monthlyComparisonTable td:not(:first-child) {
    text-align: right;
    font-family: monospace;
    font-size: 0.95rem;
}

/* Tambahkan style untuk monthly comparison table */
#monthlyComparisonTable {
    border-collapse: separate;
    border-spacing: 0;
}

#monthlyComparisonTable th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    padding: 12px 16px;
    font-weight: 600;
    text-align: left;
}

#monthlyComparisonTable th:not(:first-child) {
    text-align: right;
}

#monthlyComparisonTable td {
    padding: 12px 16px;
    border-bottom: 1px solid #dee2e6;
    vertical-align: middle;
}

#monthlyComparisonTable td:not(:first-child) {
    text-align: right;
    font-family: monospace;
    font-size: 0.95rem;
}

#monthlyComparisonTable tr:last-child td {
    border-bottom: none;
}

#monthlyComparisonTable tr:hover {
    background-color: rgba(0,0,0,.02);
}

/* Style untuk nilai positif/negatif */
.text-success { color: #28a745 !important; }
.text-danger { color: #dc3545 !important; }

/* Tambahan style untuk Monthly Comparison */
.comparison-container {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.chart-section {
    height: 500px; /* Diperbesar dari 300px */
    background: #fff;
    border-radius: 8px;
    padding: 1.5rem; /* Diperbesar padding */
    box-shadow: 0 0 10px rgba(0,0,0,0.05);
}

.table-section {
    background: #fff;
    border-radius: 8px;
    padding: 0.5rem;
}

.comparison-table th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    padding: 1rem;
    font-weight: 600;
    color: #495057;
}

.comparison-table td {
    padding: 1rem;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}

.comparison-table td:not(:first-child) {
    font-family: 'Roboto Mono', monospace;
    font-size: 0.95rem;
}

.comparison-table tr:hover {
    background-color: #f8f9fa;
}

/* Warna untuk nilai positif/negatif */
.text-success { 
    color: #28a745 !important;
    font-weight: 500;
}

.text-danger { 
    color: #dc3545 !important;
    font-weight: 500;
}

/* Animasi loading */
.loading {
    opacity: 0.6;
    transition: opacity 0.3s ease;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .comparison-container {
        gap: 1rem;
    }
    
    .chart-section {
        height: 350px; /* Tetap besar di mobile */
        padding: 1rem;
    }
    
    .comparison-table td,
    .comparison-table th {
        padding: 0.75rem;
    }
}

</style>

<?php
$pageContent = ob_get_clean();
ob_start();
?>

<!-- Di bagian bawah file, sebelum include layout.php -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="assets/js/report.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set initial period
    const periodSelect = document.getElementById('reportPeriod');
    const showLineCheckbox = document.getElementById('productSalesShowLine');

    let period = periodSelect ? periodSelect.value : 'month';

    // Load all report data for selected period
    function loadAllReport(period) {
        loadReportData(period);
        if (showLineCheckbox) {
            loadProductSalesChart(period, showLineCheckbox.checked);
        } else {
            loadProductSalesChart(period, true);
        }
        loadProductSalesSummary(period);
    }

    // Initial load
    setTimeout(() => {
        loadAllReport(period);
    }, 100);

    // Period selector event
    if (periodSelect) {
        periodSelect.addEventListener('change', function() {
            period = this.value;
            loadAllReport(period);
        });
    }

    // Show line toggle event
    if (showLineCheckbox) {
        showLineCheckbox.addEventListener('change', function() {
            loadProductSalesChart(period, this.checked);
        });
    }

    // Panggil fungsi untuk memuat ringkasan penjualan produk saat halaman dimuat
    const productSalesPeriod = document.getElementById('productSalesPeriod');
    if (productSalesPeriod) {
        loadProductSalesSummary(productSalesPeriod.value);
        productSalesPeriod.addEventListener('change', function() {
            loadProductSalesSummary(this.value);
        });
    } else {
        loadProductSalesSummary(period);
    }
});

// Ubah semua fungsi agar menerima parameter period
function loadReportData(period = 'month') {
    // ...panggil API dengan period...
    // ...existing code...
}
function loadProductSalesChart(period = 'month', showLine = true) {
    fetch('api/chart-data.php?type=product_sales&period=' + encodeURIComponent(period))
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const ctx = document.getElementById('productSalesChart');
            if (!ctx) return;
            if (window.productSalesChart instanceof Chart) window.productSalesChart.destroy();

            // Urutkan produk berdasarkan total quantity terjual (produk terlaris di depan)
            const sortedDatasets = data.datasets.sort((a, b) => {
                const totalA = a.data.reduce((sum, qty) => sum + qty, 0);
                const totalB = b.data.reduce((sum, qty) => sum + qty, 0);
                return totalB - totalA;
            });

            const labels = data.labels;
            const datasets = sortedDatasets.map(ds => ({
                label: ds.label,
                data: ds.data.map((y, i) => ({ x: labels[i], y })),
                showLine: !!showLine,
                fill: false,
                borderColor: ds.borderColor,
                backgroundColor: ds.backgroundColor,
                pointBackgroundColor: ds.backgroundColor,
                pointBorderColor: ds.borderColor,
                pointRadius: 6,
                pointHoverRadius: 9,
                tension: 0.3 // smooth line
            }));

            window.productSalesChart = new Chart(ctx, {
                type: 'scatter',
                data: { datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: ${context.parsed.y ?? 0} pcs (${context.dataIndex + 1})`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            type: 'category',
                            labels: labels,
                            title: { display: true, text: 
                                period === 'year' ? 'Year' :
                                period === 'month' ? 'Month' :
                                period === 'week' ? 'Week' : 'Day'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Qty Terjual' },
                            ticks: {
                                stepSize: 1,
                                precision: 0,
                                callback: value => value
                            }
                        }
                    }
                }
            });
        });
}

function loadProductSalesSummary(period = 'month') {
    fetch('api/report_data.php?type=product_sales_summary&period=' + encodeURIComponent(period))
        .then(r => {
            if (!r.ok) {
                // Tampilkan error jika endpoint tidak ditemukan
                document.getElementById('productSalesSummaryBody').innerHTML = `
                    <tr>
                        <td colspan="4" class="text-danger text-center">
                            API endpoint not found (404)
                        </td>
                    </tr>
                `;
                document.getElementById('productSalesSummaryCard').style.display = '';
                throw new Error('API endpoint not found');
            }
            return r.json();
        })
        .then(data => {
            if (!data.success) return;
            const tbody = document.getElementById('productSalesSummaryBody');
            tbody.innerHTML = '';

            data.summary.forEach((item, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><span class="badge bg-secondary">${index + 1}</span></td>
                    <td>${item.product_name}</td>
                    <td class="text-end">${item.price !== null ? formatCurrency(item.price) : '-'}</td>
                    <td class="text-end">${item.total_qty !== null ? item.total_qty : '-'}</td>
                `;
                tbody.appendChild(row);
            });

            document.getElementById('productSalesSummaryCard').style.display = data.summary.length > 0 ? '' : 'none';
        })
        .catch(err => {
            // Error sudah ditangani di atas
        });
}

// Update last update text even if empty
function updateTimestamp() {
    const el = document.getElementById('lastUpdate');
    if (el) {
        const time = el.dataset.time;
        if (!time || time === 'null' || time === '') {
            el.textContent = 'No transactions yet';
        } else {
            el.textContent = timeAgo(time);
        }
    }
}
</script>

<?php include 'includes/layout.php'; ?>