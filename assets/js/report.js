document.addEventListener('DOMContentLoaded', function() {
    console.log('Loading report charts...'); // Debug log
    loadReportData();
});

async function loadReportData() {
    try {
        console.log('Loading monthly comparison data...');
        const response = await fetch('api/chart-data.php?type=monthly_comparison');
        const data = await response.json();
        
        console.log('Data received:', data);

        if (data.success && data.monthly_comparison) {
            const chartData = data.monthly_comparison;
            const ctx = document.getElementById('monthlyComparisonChart');
            
            if (!ctx) {
                console.error('Canvas element not found');
                return;
            }

            // Create chart
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.months, // Now shows Jan-Dec
                    datasets: [{
                        label: 'Income',
                        data: chartData.income,
                        backgroundColor: REPORT_COLORS.income.background,
                        borderColor: REPORT_COLORS.income.border,
                        borderWidth: 1
                    }, {
                        label: 'Expenses',
                        data: chartData.expense,
                        backgroundColor: REPORT_COLORS.expense.background,
                        borderColor: REPORT_COLORS.expense.border,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: value => formatCurrency(value)
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: ${formatCurrency(context.raw)}`;
                                }
                            }
                        }
                    }
                }
            });

        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Helper function untuk format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(amount);
}

function updateMetrics(metrics) {
    if (!metrics) return;

    const elements = {
        netCashflow: document.getElementById('netCashflow'),
        lastNetUpdate: document.getElementById('lastNetUpdate'),
        expenseRatio: document.getElementById('expenseRatio'),
        activeEmployees: document.getElementById('activeEmployees')
    };

    // Update values with formatting
    if (elements.netCashflow) {
        elements.netCashflow.textContent = formatCurrency(metrics.net_cashflow);
        // Set timestamp data
        if (elements.lastNetUpdate) {
            elements.lastNetUpdate.dataset.time = metrics.last_update;
            updateTimestamp();  // Reuse existing function from main.js
        }
    }
    if (elements.expenseRatio) elements.expenseRatio.textContent = `${metrics.expense_ratio.toFixed(1)}%`;
    if (elements.activeEmployees) elements.activeEmployees.textContent = metrics.active_employees ?? 0;

    // Update status indicators
    updateStatusIndicators(metrics);
}

function updateStatusIndicators(metrics) {
    const expenseStatus = document.getElementById('expenseStatus');
    if (expenseStatus) {
        expenseStatus.innerHTML = metrics.expense_ratio <= 70 ? 
            '<i class="bx bx-check"></i> Healthy' : 
            '<i class="bx bx-x"></i> High';
    }
}

function showLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        document.body.style.cursor = 'wait';
        overlay.style.display = 'flex';
    }
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        document.body.style.cursor = 'default';
        overlay.style.display = 'none';
    }
}

function updateExportPeriod(period) {
    const exportDate = document.getElementById('exportDate');
    const datePickerContainer = document.getElementById('datePickerContainer');
    
    if (!exportDate || !datePickerContainer) return;

    const today = new Date();
    
    switch(period) {
        case 'day':
            datePickerContainer.style.display = 'block';
            exportDate.value = today.toISOString().split('T')[0];
            break;
        case 'week':
            const monday = new Date(today);
            monday.setDate(today.getDate() - today.getDay() + 1);
            exportDate.value = monday.toISOString().split('T')[0];
            datePickerContainer.style.display = 'none';
            break;
        case 'month':
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            exportDate.value = firstDay.toISOString().split('T')[0];
            datePickerContainer.style.display = 'none';
            break;
        case 'year':
            const firstDayOfYear = new Date(today.getFullYear(), 0, 1);
            exportDate.value = firstDayOfYear.toISOString().split('T')[0];
            datePickerContainer.style.display = 'none';
            break;
    }
}

// Panggil loadProductSalesChart jika ada elemen chart-nya
if (document.getElementById('productSalesChart')) {
    loadProductSalesChart();
}

function loadProductSalesChart(period = 'month', showLine = true) {
    fetch('api/chart-data.php?type=product_sales&period=' + encodeURIComponent(period))
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const ctx = document.getElementById('productSalesChart');
            if (!ctx) return;
            if (window.productSalesChart instanceof Chart) window.productSalesChart.destroy();

            // Urutkan produk terlaris
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
                tension: 0.3
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