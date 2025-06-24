const CHART_COLORS = {
    income: {
        background: 'rgba(40, 167, 69, 0.5)',
        border: 'rgb(40, 167, 69)'
    },
    expense: {
        background: 'rgba(220, 53, 69, 0.5)', 
        border: 'rgb(220, 53, 69)'
    },
    expenseCategories: {
        'Housing': '#4e73df',
        'Food': '#1cc88a',
        'Transportation': '#36b9cc',
        'Healthcare': '#f6c23e',
        'Entertainment': '#e74a3b',
        'Shopping': '#858796',
        'Education': '#5a5c69',
        'Other': '#4A5568'
    }
};

document.addEventListener('DOMContentLoaded', function() {
    window.chartInstances = {
        cashFlow: null,
        expenseChart: null
    };

    // Inisialisasi timestamp pertama kali
    updateTimestamp();
    
    // Update timestamp setiap detik
    setInterval(updateTimestamp, 1000);

    // Load initial data
    const defaultPeriod = 'month';
    document.querySelector(`[data-period="${defaultPeriod}"]`).classList.add('active');
    loadChartData(defaultPeriod);
    loadDashboardStats(defaultPeriod);  // Ini akan mengupdate semua stats

    // Setup period selector
    document.querySelector('.period-selector').addEventListener('click', function(e) {
        const button = e.target.closest('[data-period]');
        if (!button) return;

        const period = button.dataset.period;
        
        // Update active state
        document.querySelectorAll('[data-period]').forEach(btn => 
            btn.classList.remove('active'));
        button.classList.add('active');
        
        // Load new data
        loadChartData(period);
        loadDashboardStats(period);  // Ini akan mengupdate semua stats sesuai period
    });

    // Load other components
    loadSavingsData();
    loadRecentTransactions();

    // Add monthly comparison chart load
    loadMonthlyComparison();
});

// Fungsi untuk load semua data sekaligus
async function loadAllData(period) {
    try {
        await Promise.all([
            loadChartData(period),
            loadDashboardStats(period)
        ]);
    } catch (error) {
        console.error('Error loading data:', error);
    }
}

async function loadChartData(period) {
    try {
        console.log('Loading data for period:', period);
        
        // Show loading states
        const chartLoading = document.getElementById('chartLoading');
        const donutLoading = document.getElementById('donutLoading');
        const cashFlowChart = document.getElementById('cashFlowChart');
        const expenseDonutChart = document.getElementById('expenseDonutChart');
        
        if (chartLoading) chartLoading.style.display = 'block';
        if (donutLoading) donutLoading.style.display = 'block';
        if (cashFlowChart) cashFlowChart.style.display = 'none';
        if (expenseDonutChart) expenseDonutChart.style.display = 'none';

        const [flowResponse, categoryResponse] = await Promise.all([
            fetch(`api/chart-data.php?type=flow&period=${period}`),
            fetch(`api/chart-data.php?type=category&period=${period}`)
        ]);

        const flowData = await flowResponse.json();
        const categoryData = await categoryResponse.json();

        console.log('Flow data:', flowData);
        console.log('Category data:', categoryData);

        // Hide loading, show charts
        if (chartLoading) chartLoading.style.display = 'none';
        if (donutLoading) donutLoading.style.display = 'none';
        if (cashFlowChart) cashFlowChart.style.display = 'block';
        if (expenseDonutChart) expenseDonutChart.style.display = 'block';

        if (flowData) updateCashFlowChart(flowData);
        if (categoryData) updateExpenseChart(categoryData);

    } catch (error) {
        console.error('Error loading chart data:', error);
        if (chartLoading) chartLoading.innerHTML = 'Error loading data';
        if (donutLoading) donutLoading.innerHTML = 'Error loading data';
    }
}

// Perbaiki update untuk cash flow chart
function updateCashFlowChart(data) {
    const ctx = document.getElementById('cashFlowChart');
    if (!ctx) {
        console.error('Cash flow chart canvas not found');
        return;
    }

    // Destroy existing chart if any
    if (window.chartInstances.cashFlow instanceof Chart) {
        window.chartInstances.cashFlow.destroy();
    }

    // Create new chart instance with only income and expense lines
    window.chartInstances.cashFlow = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.dates,
            datasets: [{
                label: 'Income',
                data: data.income,
                borderColor: CHART_COLORS.income.border,
                backgroundColor: CHART_COLORS.income.background,
                fill: true,
                tension: 0.3,
                pointRadius: 4,
                pointHoverRadius: 6
            }, {
                label: 'Expenses',
                data: data.expenses,
                borderColor: CHART_COLORS.expense.border,
                backgroundColor: CHART_COLORS.expense.background,
                fill: true,
                tension: 0.3,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: value => formatCurrency(value)
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + 
                                   formatCurrency(context.raw);
                        }
                    }
                }
            }
        }
    });
}

function updateExpenseChart(data) {
    const ctx = document.getElementById('expenseDonutChart');
    if (!ctx) return;

    if (window.chartInstances.expenseChart instanceof Chart) {
        window.chartInstances.expenseChart.destroy();
    }

    // Check for empty data
    if (!data || !Array.isArray(data) || data.length === 0) {
        window.chartInstances.expenseChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['No Data Available'],
                datasets: [{
                    data: [1],
                    backgroundColor: ['#f0f0f0'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'right'
                    },
                    title: {
                        display: true,
                        text: 'No expense data for this period',
                        position: 'center',
                        font: {
                            size: 14
                        }
                    }
                }
            }
        });

        // Update table juga untuk empty state
        const tbody = document.getElementById('expenseBreakdownTable');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="3" class="text-center text-muted">
                        <i class='bx bx-info-circle me-1'></i>
                        No expense data available
                    </td>
                </tr>
            `;
        }
        return;
    }

    // Calculate total for percentages
    const total = data.reduce((sum, item) => sum + parseFloat(item.total), 0);

    // Create chart with actual data
    window.chartInstances.expenseChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.map(item => item.category_name),
            datasets: [{
                data: data.map(item => parseFloat(item.total)),
                backgroundColor: data.map(item => 
                    CHART_COLORS.expenseCategories[item.category_name] || '#858796'
                ),
                borderWidth: 1,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        padding: 10, // Kurangi padding
                        usePointStyle: true,
                        font: {
                            size: 11 // Kurangi ukuran font
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.raw;
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${context.label}: ${formatCurrency(value)} (${percentage}%)`;
                        }
                    }
                }
            },
            layout: {
                padding: {
                    left: 10,
                    right: 10,
                    top: 0,
                    bottom: 0
                }
            }
        }
    });

    // Update table with percentages
    updateExpenseTable(data, total);
}

function updateExpenseTable(data, total) {
    const tbody = document.getElementById('expenseBreakdownTable');
    if (!tbody) return;

    tbody.innerHTML = data
        .sort((a, b) => parseFloat(b.total) - parseFloat(a.total))
        .map(item => {
            const percentage = ((parseFloat(item.total) / total) * 100).toFixed(1);
            const color = CHART_COLORS.expenseCategories[item.category_name] || '#858796';
            return `
                <tr>
                    <td>
                        <i class="bx bxs-circle" style="color: ${color}"></i>
                        ${item.category_name}
                    </td>
                    <td>${formatCurrency(item.total)}</td>
                    <td>${percentage}%</td>
                </tr>
            `;
        }).join('');
}

// Helper untuk loading state
function showLoading() {
    document.body.style.cursor = 'wait';
}

function hideLoading() {
    document.body.style.cursor = 'default';
}

async function loadSavingsData() {
    try {
        const response = await fetch('api/get_savings_summary.php');
        const data = await response.json();
        
        const container = document.getElementById('activeSavings');
        if (!container) return;

        if (!data.success) {
            container.innerHTML = `
                <div class="col-12 text-center text-muted">
                    <p>No savings data available</p>
                </div>`;
            return;
        }

        // Update total savings display
        const totalSavings = data.total_savings || 0;
        container.innerHTML = `
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h4>Total Savings</h4>
                    <h3>${formatCurrency(totalSavings)}</h3>
                </div>
                <div class="progress mt-2" style="height: 10px;">
                    <div class="progress-bar bg-success" style="width: 100%"></div>
                </div>
            </div>`;
    } catch (error) {
        console.error('Error loading savings:', error);
    }
}

async function loadRecentTransactions() {
    try {
        const response = await fetch('api/recent_transactions.php');
        const transactions = await response.json();
        const container = document.getElementById('recentTransactions');
        const loading = document.getElementById('transactionLoading');

        if (loading) loading.style.display = 'none';
        
        if (!Array.isArray(transactions) || transactions.length === 0) {
            container.innerHTML = `
                <div class="text-center p-4 text-muted">
                    <i class='bx bx-info-circle me-2'></i>
                    No transactions found
                </div>`;
            return;
        }

        container.innerHTML = transactions.map(t => `
            <div class="list-group-item">
                <div class="d-flex w-100 justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">
                            <i class='bx ${t.type === 'income' ? 'bx-plus text-success' : 'bx-minus text-danger'}'></i>
                            ${t.category}
                        </h6>
                        <small class="text-muted">${t.date}</small>
                    </div>
                    <div class="text-end">
                        <h6 class="mb-1 ${t.type === 'income' ? 'text-success' : 'text-danger'}">
                            ${t.type === 'income' ? '+' : '-'} ${formatCurrency(t.amount)}
                        </h6>
                        <small class="text-muted">${t.description || '-'}</small>
                    </div>
                </div>
            </div>
        `).join('');

    } catch (error) {
        console.error('Error loading transactions:', error);
        document.getElementById('recentTransactions').innerHTML = `
            <div class="text-center p-4 text-danger">
                <i class='bx bx-error-circle me-2'></i>
                Failed to load transactions
            </div>`;
    }
}

function updateTimestamp() {
    const elements = [
        { id: 'lastUpdate', time: 'last_update' },
        { id: 'lastIncomeUpdate', time: 'last_income_update' },
        { id: 'lastExpenseUpdate', time: 'last_expense_update' }
    ];

    elements.forEach(el => {
        const element = document.getElementById(el.id);
        if (element && element.dataset.time) {
            element.textContent = timeAgo(element.dataset.time);
        }
    });
}

function timeAgo(date) {
    if (!date) return 'No transactions yet';
    
    try {
        const now = new Date();
        const past = new Date(date);
        
        if (isNaN(past.getTime())) {
            return 'Invalid date';
        }
        
        const seconds = Math.floor((now - past) / 1000);
        
        if (seconds < 10) return 'just now';
        if (seconds < 60) return `${seconds} seconds ago`;
        
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return `${minutes} minute${minutes !== 1 ? 's' : ''} ago`;
        
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours} hour${hours !== 1 ? 's' : ''} ago`;
        
        const days = Math.floor(hours / 24);
        if (days < 30) return `${days} day${days !== 1 ? 's' : ''} ago`;
        
        return past.toLocaleDateString('id-ID');
    } catch (error) {
        console.error('Error parsing date:', error);
        return 'Invalid date format';
    }
}

function updateTimestamps(timestamps) {
    Object.entries(timestamps).forEach(([id, time]) => {
        const el = document.getElementById(id);
        if (el) {
            el.dataset.time = time || '';
            el.textContent = timeAgo(time);
            // Tambah class untuk styling
            el.className = time ? 'text-white-50' : 'text-warning';
        }
    });
}

async function loadDashboardStats(period) {
    try {
        showLoading();
        const response = await fetch(`api/dashboard_stats.php?period=${period}`);
        const data = await response.json();
        
        if (data.success) {
            // Update period labels
            document.querySelectorAll('.periodLabel').forEach(el => {
                el.textContent = data.label;
            });

            // Update values with animation
            animateValue('totalBalance', data.total_balance);
            animateValue('periodIncome', data.period_income);
            animateValue('periodExpenses', data.period_expenses);

            // Animate Expense Ratio and update label
            // Agar animasi muncul setiap select period, reset dulu textContent ke 0 sebelum animasi
            const expenseRatioEl = document.getElementById('expenseRatio');
            if (expenseRatioEl) expenseRatioEl.textContent = '0%';
            updateExpenseRatio(data.expense_ratio, data.label);
            animateValue('expenseRatio', data.expense_ratio, true);

            // Update expense status badge
            const status = document.getElementById('expenseStatus');
            if (status) {
                status.innerHTML = data.expense_ratio <= 70
                    ? "<i class='bx bx-check'></i> Healthy"
                    : "<i class='bx bx-x'></i> High";
            }

            // Update timestamps dengan nilai default jika kosong
            updateTimestamps({
                'lastUpdate': data.last_update || null,
                'lastIncomeUpdate': data.last_income_update || null,
                'lastExpenseUpdate': data.last_expense_update || null
            });
        }
    } catch (error) {
        console.error('Error loading dashboard stats:', error);
        // Update timestamps dengan null untuk menampilkan pesan default
        updateTimestamps({
            'lastUpdate': null,
            'lastIncomeUpdate': null,
            'lastExpenseUpdate': null
        });
    } finally {
        hideLoading();
    }
}

// Fungsi baru: animasi untuk persen (jika isPercent true, tambahkan %)
function animateValue(elementId, value, isPercent = false) {
    const el = document.getElementById(elementId);
    if (!el) return;

    let start = parseFloat(el.textContent.replace(/[^\d.-]/g, '')) || 0;
    const duration = 1000;
    const increment = (value - start) / (duration / 16);

    let current = start;
    const animate = () => {
        current += increment;
        const finished = increment > 0 ? current >= value : current <= value;

        if (finished) {
            el.textContent = isPercent ? value.toFixed(1) + '%' : formatCurrency(value);
        } else {
            el.textContent = isPercent ? current.toFixed(1) + '%' : formatCurrency(current);
            requestAnimationFrame(animate);
        }
    };

    animate();
}

// Update Expense Ratio label and status
function updateExpenseRatio(value, label) {
    const el = document.getElementById('expenseRatio');
    const status = document.getElementById('expenseStatus');
    const labelEl = document.getElementById('expenseRatioLabel');
    // Jangan set angka di sini, biar animateValue yang handle
    if (labelEl && label) labelEl.textContent = label + ' Expense Ratio';
    if (status) {
        status.innerHTML = value <= 70
            ? "<i class='bx bx-check'></i> Healthy"
            : "<i class='bx bx-x'></i> High";
    }
}

// Update UI saat periode berubah
document.querySelector('.period-selector')?.addEventListener('click', function(e) {
    const button = e.target.closest('[data-period]');
    if (!button) return;

    // Update active state
    this.querySelectorAll('[data-period]').forEach(btn => 
        btn.classList.remove('active'));
    button.classList.add('active');
    
    // Load data untuk periode yang dipilih
    loadDashboardStats(button.dataset.period);
    loadChartData(button.dataset.period);
});