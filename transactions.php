<?php
require_once 'config/database.php';
$pageTitle = "Transactions";

try {
    $db = new Database();
    $conn = $db->getConnection();

    $income_query = "SELECT * FROM income_sources WHERE is_active = TRUE ORDER BY source_name";
    $expense_query = "SELECT * FROM expense_categories WHERE is_active = TRUE ORDER BY category_name";

    $income_sources = $conn->query($income_query)->fetchAll(PDO::FETCH_ASSOC);
    $expense_categories = $conn->query($expense_query)->fetchAll(PDO::FETCH_ASSOC);

    // Ambil data employee aktif untuk dropdown Salary
    $employees = $conn->query("SELECT id, name FROM employees WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

    // Ambil produk aktif untuk dropdown income
    $products = $conn->query("SELECT id, name, stock, price FROM products WHERE is_active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

    // Ambil data shift
    $shifts = $conn->query("SELECT * FROM shifts ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

ob_start();
?>

<!-- Transaction Form -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Add Transaction</h5>
    </div>
    <div class="card-body">
        <form id="transactionForm" autocomplete="off">
            <div class="row">
                <div class="col-md-6">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Type</label>
                                <select class="form-select" name="type" id="transactionType" required>
                                    <option value="income">Income</option>
                                    <option value="expense">Expense</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" id="categoryLabel">Source/Category</label>
                                <select class="form-select" name="category" id="categorySelect" required>
                                    <!-- Income Sources -->
                                    <optgroup label="Income Sources" id="incomeSourceGroup">
                                    <?php foreach($income_sources as $source): ?>
                                        <option value="income_<?php echo $source['id']; ?>" class="income-option">
                                            <?php echo htmlspecialchars($source['source_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    </optgroup>
                                    <!-- Expense Categories -->
                                    <optgroup label="Expense Categories" id="expenseCategoryGroup" style="display:none;">
                                    <?php foreach($expense_categories as $category): ?>
                                        <option value="expense_<?php echo $category['id']; ?>" class="expense-option" style="display:none;" data-category-name="<?= strtolower($category['category_name']) ?>">
                                            <?php echo htmlspecialchars($category['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    </optgroup>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3" id="productSelectSection" style="display:none;">
                        <label class="form-label">Product</label>
                        <select class="form-select" name="product_id" id="productSelectDropdown">
                            <option value="">-- Select Product --</option>
                            <?php foreach($products as $prod): ?>
                                <?php if ($prod['stock'] > 0): ?>
                                    <option value="<?= $prod['id'] ?>" data-price="<?= $prod['price'] ?>" data-max-stock="<?= $prod['stock'] ?>">
                                        <?= htmlspecialchars($prod['name']) ?> (Stok: <?= $prod['stock'] ?>, Harga: Rp<?= number_format($prod['price'],0,',','.') ?>)
                                    </option>
                                <?php else: ?>
                                    <option value="<?= $prod['id'] ?>" disabled class="text-muted">
                                        <?= htmlspecialchars($prod['name']) ?> <span class="text-danger">(Stok habis)</span>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-muted" id="productStockInfo">
                            Pilih barang yang dibeli.
                            <span class="text-danger" id="productStockError"></span>
                        </div>
                        <div id="productStockInfo" class="form-text text-muted mt-1"></div>
                    </div>           
                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control" name="amount" id="amountInput" required>
                        </div>
                        <div class="form-text text-muted" id="amountInfo">
                            Amount = harga produk per item x quantity.
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" name="date" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <!-- Employee dropdown for Salary expense -->
                    <div class="mb-3" id="employeeSelectSection" style="display:none;">
                        <label class="form-label">Employee</label>
                        <select class="form-select" name="employee_id">
                            <option value="">-- Select Employee --</option>
                            <?php foreach($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Quantity input for income transactions -->
                    <div class="mb-3" id="quantitySection" style="display:none;">
                        <label class="form-label">Quantity</label>
                        <input type="number" class="form-control" name="quantity" id="quantityInput" min="1" value="1" oninput="validateQuantity(this)">
                        <div class="form-text text-muted" id="quantityInfo">
                            Jumlah barang yang dibeli.
                            <span class="text-danger" id="quantityError"></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Shift</label>
                        <select class="form-select" name="shift_id" id="shiftSelect" required>
                            <option value="">-- Select Shift --</option>
                            <?php foreach($shifts as $shift): ?>
                                <option value="<?= $shift['id'] ?>">
                                    <?= htmlspecialchars($shift['name']) ?> (<?= substr($shift['start_time'],0,5) ?> - <?= substr($shift['end_time'],0,5) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-muted">Pilih shift transaksi ini dicatat.</div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Transaction</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Transaction List -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Transaction History</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="transactionsTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Shift</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Will be populated via JavaScript -->
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show product dropdown if income
    function updateProductDropdown() {
        const typeSelect = document.getElementById('transactionType');
        const productSection = document.getElementById('productSelectSection');
        const quantitySection = document.getElementById('quantitySection');
        if (typeSelect.value === 'income') {
            productSection.style.display = '';
            quantitySection.style.display = '';
        } else {
            productSection.style.display = 'none';
            quantitySection.style.display = 'none';
            productSection.querySelector('select').value = '';
            document.getElementById('productStockInfo').textContent = '';
        }
    }
    document.getElementById('transactionType').addEventListener('change', updateProductDropdown);
    updateProductDropdown();

    // Tampilkan sisa stok dan harga saat produk dipilih, serta auto-isi amount
    const productSelect = document.getElementById('productSelectDropdown');
    const stockInfo = document.getElementById('productStockInfo');
    const amountInput = document.getElementById('amountInput');
    const quantityInput = document.getElementById('quantityInput');

    function updateProductStockInfo() {
        const selected = productSelect.options[productSelect.selectedIndex];
        if (!selected || !selected.value) {
            stockInfo.textContent = '';
            if (amountInput) amountInput.value = '';
            return;
        }
        // Ambil stok dari label option
        const stokMatch = selected.text.match(/\(Stok: (\d+)[^)]*\)/);
        if (stokMatch) {
            const stok = stokMatch[1];
            stockInfo.textContent = 'Sisa stok: ' + stok;
            if (quantityInput) quantityInput.max = stok;
        } else if (selected.text.includes('Stok habis')) {
            stockInfo.textContent = 'Stok habis';
            if (quantityInput) quantityInput.max = 1;
        } else {
            stockInfo.textContent = '';
            if (quantityInput) quantityInput.removeAttribute('max');
        }
        // Auto-isi harga ke amount jika ada data-price dan quantity
        if (selected.dataset.price && amountInput && quantityInput) {
            const price = parseInt(selected.dataset.price, 10) || 0;
            const qty = parseInt(quantityInput.value, 10) || 1;
            amountInput.value = price * qty;
        }
    }

    // Update amount jika quantity berubah (khusus income + produk)
    if (quantityInput) {
        quantityInput.addEventListener('input', function() {
            const selected = productSelect.options[productSelect.selectedIndex];
            if (selected && selected.dataset.price) {
                const price = parseInt(selected.dataset.price, 10) || 0;
                const qty = parseInt(quantityInput.value, 10) || 1;
                amountInput.value = price * qty;
            }
        });
    }

    if (productSelect) {
        productSelect.addEventListener('change', function() {
            updateProductStockInfo();
        });
    }

    // Fungsi update info stok & harga (bisa dipanggil ulang)
    function updateProductStockInfo() {
        const selected = productSelect.options[productSelect.selectedIndex];
        if (!selected || !selected.value) {
            stockInfo.textContent = '';
            if (amountInput) amountInput.value = '';
            return;
        }
        // Ambil stok dari label option
        const stokMatch = selected.text.match(/\(Stok: (\d+)[^)]*\)/);
        if (stokMatch) {
            const stok = stokMatch[1];
            stockInfo.textContent = 'Sisa stok: ' + stok;
        } else if (selected.text.includes('Stok habis')) {
            stockInfo.textContent = 'Stok habis';
        } else {
            stockInfo.textContent = '';
        }
        // Auto-isi harga ke amount jika ada data-price
        if (selected.dataset.price && amountInput) {
            amountInput.value = selected.dataset.price;
        }
    }

    // Update stok produk di dropdown setelah transaksi income berhasil
    window.updateProductStockAfterTransaction = function(productId) {
        // Tidak perlu lagi, karena akan reload halaman
    };
});

function validateQuantity(input) {
    const selected = document.getElementById('productSelectDropdown').options[
        document.getElementById('productSelectDropdown').selectedIndex
    ];
    
    const maxStock = parseInt(selected.getAttribute('data-max-stock')) || 0;
    const price = parseInt(selected.dataset.price) || 0;
    let quantity = parseInt(input.value) || 0;
    const quantityError = document.getElementById('quantityError');
    
    // Reset error message
    quantityError.textContent = '';
    
    // Jika quantity > stok, set ke nilai maksimum stok
    if (quantity > maxStock) {
        quantity = maxStock;
        input.value = maxStock;
        quantityError.textContent = `Maksimal pembelian ${maxStock} item (sesuai stok)`;
    }
    
    // Update amount otomatis (price x quantity)
    const amountInput = document.getElementById('amountInput');
    if (amountInput && price) {
        amountInput.value = price * quantity;
    }
}
</script>
<?php
$pageContent = ob_get_clean();
$pageScript = '<script src="assets/js/transactions.js"></script>';
include 'includes/layout.php';
?>