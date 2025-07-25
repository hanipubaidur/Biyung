function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
}

document.addEventListener('DOMContentLoaded', function() {
    loadTransactions();
    initializeTypeSwitch();
    
    // Event listener for form sudah ada di sini, tidak perlu double
    document.getElementById('transactionForm').addEventListener('submit', handleFormSubmit);
});

// Handle type switching
function initializeTypeSwitch() {
    const typeSelect = document.getElementById('transactionType');
    const categorySelect = document.getElementById('categorySelect');
    const incomeGroup = document.getElementById('incomeSourceGroup');
    const expenseGroup = document.getElementById('expenseCategoryGroup');
    const employeeSection = document.getElementById('employeeSelectSection');

    function updateFormView() {
        const isExpense = typeSelect.value === 'expense';

        // Show/hide optgroup
        if (incomeGroup) incomeGroup.style.display = isExpense ? 'none' : '';
        if (expenseGroup) expenseGroup.style.display = isExpense ? '' : 'none';

        // Show/hide options
        const incomeOptions = categorySelect.querySelectorAll('.income-option');
        const expenseOptions = categorySelect.querySelectorAll('.expense-option');
        incomeOptions.forEach(opt => {
            opt.style.display = isExpense ? 'none' : '';
            opt.disabled = isExpense;
        });
        expenseOptions.forEach(opt => {
            opt.style.display = isExpense ? '' : 'none';
            opt.disabled = !isExpense;
        });

        // Set default value if current value is hidden/disabled
        const visibleOptions = Array.from(categorySelect.options).filter(opt => !opt.disabled && opt.style.display !== 'none');
        if (visibleOptions.length > 0 && !visibleOptions.includes(categorySelect.selectedOptions[0])) {
            categorySelect.value = visibleOptions[0].value;
        }

        // Show employee dropdown if Salary selected
        let showEmployee = false;
        if (isExpense) {
            const selectedOption = categorySelect.selectedOptions[0];
            if (selectedOption && selectedOption.dataset.categoryName === 'salary') {
                showEmployee = true;
            }
        }
        if (employeeSection) employeeSection.style.display = showEmployee ? 'block' : 'none';
    }

    typeSelect.addEventListener('change', updateFormView);
    categorySelect.addEventListener('change', updateFormView);

    updateFormView();
}

// Hapus loadGoals function karena tidak diperlukan lagi

async function loadTransactions() {
    try {
        const response = await fetch('api/get_transactions.php');
        const transactions = await response.json();
        
        const tbody = document.querySelector('#transactionsTable tbody');
        if (!tbody) return;

        if (!Array.isArray(transactions) || transactions.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-muted">
                        No transactions found
                    </td>
                </tr>`;
            return;
        }

        tbody.innerHTML = transactions.map(t => `
            <tr>
                <td>${new Date(t.date).toLocaleDateString('id-ID')}</td>
                <td>
                    <i class='bx ${t.type === 'income' ? 'bx-plus text-success' : 'bx-minus text-danger'}'></i>
                    ${t.type}
                </td>
                <td>${t.category}</td>
                <td class="text-start ${t.type === 'income' ? 'text-success' : 'text-danger'}">
                    ${t.type === 'income' ? '+' : '-'} 
                    ${new Intl.NumberFormat('id-ID').format(t.amount)}
                </td>
                <td>
                    ${t.shift_name ? `<span class="badge bg-info">${t.shift_name}</span>` : '-'}
                </td>
                <td>${t.description || '-'}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-warning" onclick="editTransaction(${t.id})">
                            <i class='bx bx-edit'></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteTransaction(${t.id})">
                            <i class='bx bx-trash'></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

    } catch (error) {
        console.error('Error loading transactions:', error);
        Swal.fire('Error', 'Failed to load transactions', 'error');
    }
}

// Add event listener to load transactions when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadTransactions(); // Add this line
    initializeTypeSwitch();
    
    // Event listener for form sudah ada di sini, tidak perlu double
    document.getElementById('transactionForm').addEventListener('submit', handleFormSubmit);
});

function editTransaction(id) {
    fetch(`api/get_transaction.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }

            const form = document.getElementById('transactionForm');
            // Set form values
            form.querySelector('[name="type"]').value = data.type;
            form.querySelector('[name="amount"]').value = data.amount;
            form.querySelector('[name="date"]').value = data.date;
            form.querySelector('[name="description"]').value = data.description || '';

            // Trigger type change to show correct category options
            document.getElementById('transactionType').dispatchEvent(new Event('change'));

            // Set correct category option
            const categoryValue = `${data.type}_${data.type === 'income' ? data.income_source_id : data.expense_category_id}`;
            form.querySelector('[name="category"]').value = categoryValue;

            // Set employee dropdown if Salary
            if (data.type === 'expense' && data.category_name === 'Salary' && data.employee_id) {
                const employeeSection = document.getElementById('employeeSelectSection');
                if (employeeSection) {
                    employeeSection.style.display = 'block';
                    const empSelect = form.querySelector('[name="employee_id"]');
                    if (empSelect) empSelect.value = data.employee_id;
                }
            }

            // Add transaction ID for update
            if (!form.querySelector('[name="transaction_id"]')) {
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'transaction_id';
                form.appendChild(idInput);
            }
            form.querySelector('[name="transaction_id"]').value = id;

            // Scroll to form
            form.scrollIntoView({ behavior: 'smooth' });
        })
        .catch(error => {
            Swal.fire('Error', error.message, 'error');
        });
}

function deleteTransaction(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This action cannot be undone",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`api/delete_transaction.php?id=${id}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => { // <-- perbaikan di sini
                if (data.success) {
                    Swal.fire(
                        'Deleted!',
                        'Transaction has been deleted.',
                        'success'
                    ).then(() => {
                        loadTransactions();
                    });
                } else {
                    throw new Error(data.message || 'Failed to delete');
                }
            })
            .catch(error => {
                Swal.fire('Error!', error.message, 'error');
            });
        }
    });
}

async function handleFormSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    // Pastikan product_id ikut dikirim jika income
    const type = form.querySelector('[name="type"]').value;
    let productId = null;
    if (type === 'income') {
        productId = form.querySelector('[name="product_id"]').value;
        formData.set('product_id', productId);
    } else {
        formData.delete('product_id');
    }

    try {
        // Show loading
        Swal.fire({
            title: 'Saving...',
            didOpen: () => Swal.showLoading()
        });

        const response = await fetch('api/save_transaction.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: result.message,
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                // Jangan reload seluruh halaman, cukup reload data transaksi
                loadTransactions();
                // Reset form setelah tambah/edit
                form.reset();
                // Jika ada input hidden transaction_id, hapus agar form kembali ke mode tambah
                const idInput = form.querySelector('[name="transaction_id"]');
                if (idInput) idInput.remove();
            });
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: error.message
        });
    }
}

function updateExpenseChart(data) {
    // Ambil warna dari CHART_COLORS yang sudah didefinisikan di main.js
    const colors = data.map(item => 
        window.CHART_COLORS?.expenseCategories[item.category_name] || '#858796'
    );

    window.chartInstances.expenseChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.map(item => item.category_name),
            datasets: [{
                data: data.map(item => parseFloat(item.total)),
                backgroundColor: colors,
                borderColor: '#fff',
                borderWidth: 1
            }]
        },

    });
    
}