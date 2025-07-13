<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Handle delete (soft/hard)
if (isset($_POST['delete'])) {
    try {
        $id = $_POST['id'];
        $type = $_POST['type'];
        
        if ($type === 'income') {
            $checkQuery = "SELECT COUNT(*) FROM transactions WHERE income_source_id = ? AND status = 'completed'";
            $table = "income_sources";
        } else {
            $checkQuery = "SELECT COUNT(*) FROM transactions WHERE expense_category_id = ? AND status = 'completed'";
            $table = "expense_categories";
        }

        $stmt = $conn->prepare($checkQuery);
        $stmt->execute([$id]);
        $usageCount = $stmt->fetchColumn();

        if ($usageCount > 0) {
            // Soft delete
            $query = "UPDATE $table SET is_active = FALSE WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$id]);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Successfully deactivated'
            ]);
        } else {
            // Hard delete
            $query = "DELETE FROM $table WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$id]);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Successfully deleted'
            ]);
        }
        exit;
    } catch(Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

// Handle add
if (isset($_POST['add'])) {
    try {
        $name = trim($_POST['name']);
        $type = $_POST['type'];
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            throw new Exception('Name is required');
        }

        if ($type === 'income') {
            $checkQuery = "SELECT COUNT(*) FROM income_sources WHERE source_name = ? AND is_active = TRUE";
            $insertQuery = "INSERT INTO income_sources (source_name, description) VALUES (?, ?)";
        } else {
            $checkQuery = "SELECT COUNT(*) FROM expense_categories WHERE category_name = ? AND is_active = TRUE";
            $insertQuery = "INSERT INTO expense_categories (category_name, description) VALUES (?, ?)";
        }

        $stmt = $conn->prepare($checkQuery);
        $stmt->execute([$name]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Name already exists');
        }

        $stmt = $conn->prepare($insertQuery);
        $success = $stmt->execute([$name, $description]);

        header('Content-Type: application/json');
        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => 'Added successfully'
            ]);
        } else {
            throw new Exception('Failed to add');
        }
    } catch(Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Handle add/edit/delete produk
if (isset($_POST['add_product'])) {
    try {
        $name = trim($_POST['product_name']);
        $stock = intval($_POST['product_stock'] ?? 0);
        $price = intval($_POST['product_price'] ?? 0);
        if (empty($name)) throw new Exception('Product name is required');

        // Cek apakah sudah ada produk dengan nama & harga sama (case-insensitive)
        $stmt = $conn->prepare("SELECT id, stock FROM products WHERE LOWER(name) = LOWER(?) AND price = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$name, $price]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Jika ada, update stok saja
            $newStock = $existing['stock'] + $stock;
            $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
            $stmt->execute([$newStock, $existing['id']]);
            $msg = 'Stock updated for existing product';
        } else {
            // Jika tidak ada, insert produk baru
            $stmt = $conn->prepare("INSERT INTO products (name, stock, price) VALUES (?, ?, ?)");
            $stmt->execute([$name, $stock, $price]);
            $msg = 'Product added';
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $msg]);
        exit;
    } catch(Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}
if (isset($_POST['delete_product'])) {
    try {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("UPDATE products SET is_active = FALSE WHERE id = ?");
        $stmt->execute([$id]);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Product deactivated']);
        exit;
    } catch(Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Handle edit stock (tambah/kurang stok produk)
if (isset($_POST['edit_stock'])) {
    try {
        $id = intval($_POST['id']);
        $delta = intval($_POST['stock_delta']);
        // Ambil stok lama
        $stmt = $conn->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $oldStock = $stmt->fetchColumn();
        if ($oldStock === false) throw new Exception('Product not found');
        $newStock = max(0, $oldStock + $delta);
        $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $stmt->execute([$newStock, $id]);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Stock updated', 'stock' => $newStock]);
        exit;
    } catch(Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Get active income sources
$incomeSources = $conn->query("SELECT * FROM income_sources WHERE is_active = TRUE ORDER BY source_name")->fetchAll();

// Get active expense categories
$expenseCategories = $conn->query("SELECT * FROM expense_categories WHERE is_active = TRUE ORDER BY category_name")->fetchAll();

// Get active products
$products = $conn->query("SELECT * FROM products WHERE is_active = TRUE ORDER BY name")->fetchAll();

ob_start();
?>

<div class="row">
    <!-- Income Sources -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Income Sources</h5>
                <button type="button" class="btn btn-sm btn-success" onclick="addItem('income')">
                    <i class='bx bx-plus'></i> Add Source
                </button>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($incomeSources as $source): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0"><?= htmlspecialchars($source['source_name']) ?></h6>
                            <?php if($source['description']): ?>
                                <small class="text-muted"><?= htmlspecialchars($source['description']) ?></small>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                onclick="confirmDelete(<?= $source['id'] ?>, 'income', '<?= htmlspecialchars($source['source_name']) ?>')">
                            <i class='bx bx-trash'></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Expense Categories -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Expense Categories</h5>
                <button type="button" class="btn btn-sm btn-success" onclick="addItem('expense')">
                    <i class='bx bx-plus'></i> Add Category
                </button>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($expenseCategories as $category): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0"><?= htmlspecialchars($category['category_name']) ?></h6>
                            <?php if($category['description']): ?>
                                <small class="text-muted"><?= htmlspecialchars($category['description']) ?></small>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                onclick="confirmDelete(<?= $category['id'] ?>, 'expense', '<?= htmlspecialchars($category['category_name']) ?>')">
                            <i class='bx bx-trash'></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Products & Stock -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Products & Stock</h5>
                <button type="button" class="btn btn-sm btn-success" onclick="addProduct()">
                    <i class='bx bx-plus'></i> Add Product
                </button>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($products as $product): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0"><?= htmlspecialchars($product['name']) ?></h6>
                            <small class="text-muted">Stock: <span id="stock-<?= $product['id'] ?>"><?= $product['stock'] ?></span></small>
                        </div>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="editStock(<?= $product['id'] ?>, '<?= htmlspecialchars(addslashes($product['name'])) ?>', <?= $product['stock'] ?>)">
                                <i class='bx bx-edit'></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    onclick="deleteProduct(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>')">
                                <i class='bx bx-trash'></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.delete-form {
    margin-left: 10px;
}
.list-group-item:hover {
    background-color: #f8f9fa;
}
.delete-form button {
    opacity: 0;
    transition: opacity 0.2s;
}
.list-group-item:hover .delete-form button {
    opacity: 1;
}
</style>

<script>
function confirmDelete(id, type, name) {
    Swal.fire({
        title: 'Are you sure?',
        html: `Do you want to delete <strong>${name}</strong>?<br>This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('delete', '1');
            formData.append('id', id);
            formData.append('type', type);

            fetch('categories.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: `${name} has been deleted.`,
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    throw new Error(data.error || 'Failed to delete');
                }
            })
            .catch(error => {
                Swal.fire(
                    'Error!',
                    error.message,
                    'error'
                );
            });
        }
    });
}

function addItem(type) {
    Swal.fire({
        title: `Add New ${type === 'income' ? 'Income Source' : 'Expense Category'}`,
        html: `
            <input type="text" id="swal-name" 
                   class="swal2-input" placeholder="Name" required>
            <textarea id="swal-description" 
                     class="swal2-textarea" placeholder="Description (optional)"></textarea>
        `,
        showCancelButton: true,
        confirmButtonText: 'Add',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            const name = document.getElementById('swal-name').value;
            const description = document.getElementById('swal-description').value;
            
            if (!name.trim()) {
                Swal.showValidationMessage('Name is required');
                return false;
            }

            const formData = new FormData();
            formData.append('add', '1');
            formData.append('type', type);
            formData.append('name', name);
            formData.append('description', description);

            return fetch('categories.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.error || 'Failed to add');
                }
                return data;
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Added Successfully',
                text: result.value.message,
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        }
    }).catch(error => {
        Swal.fire('Error', error.message, 'error');
    });
}

function addProduct() {
    Swal.fire({
        title: 'Add New Product',
        html: `
            <div class="mb-2 text-start" style="font-size:0.98em;">
                <label for="swal-product-name" class="form-label mb-1">Product Name <span class="text-danger">*</span></label>
                <input type="text" id="swal-product-name" class="swal2-input" placeholder="Nama produk, misal: Es Ubi Ungu" required>
            </div>
            <div class="mb-2 text-start" style="font-size:0.98em;">
                <label for="swal-product-stock" class="form-label mb-1">Stock Awal</label>
                <input type="number" id="swal-product-stock" class="swal2-input" placeholder="Stok awal (misal: 10)" value="0" min="0">
                <div class="form-text text-muted">Jumlah stok produk yang tersedia saat pertama kali ditambah.</div>
            </div>
            <div class="mb-2 text-start" style="font-size:0.98em;">
                <label for="swal-product-price" class="form-label mb-1">Price (Rp)</label>
                <input type="number" id="swal-product-price" class="swal2-input" placeholder="Harga jual per item (misal: 5000)" value="0" min="0">
                <div class="form-text text-muted">Harga jual produk per item (dalam rupiah).</div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Add',
        preConfirm: () => {
            const name = document.getElementById('swal-product-name').value;
            const stock = document.getElementById('swal-product-stock').value;
            const price = document.getElementById('swal-product-price').value;
            if (!name.trim()) {
                Swal.showValidationMessage('Product name is required');
                return false;
            }
            if (price === '' || isNaN(price) || Number(price) < 0) {
                Swal.showValidationMessage('Price must be a positive number');
                return false;
            }
            const formData = new FormData();
            formData.append('add_product', '1');
            formData.append('product_name', name);
            formData.append('product_stock', stock);
            formData.append('product_price', price);
            return fetch('categories.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) throw new Error(data.error || 'Failed to add');
                    return data;
                })
                .catch(error => {
                    Swal.showValidationMessage(error.message);
                    return false;
                });
        }
    }).then(result => {
        if (result.isConfirmed && result.value && result.value.success) {
            Swal.fire({
                icon: 'success',
                title: 'Product Added',
                text: result.value.message,
                timer: 1200,
                showConfirmButton: false
            }).then(() => location.reload());
        }
    });
}

function deleteProduct(id, name) {
    Swal.fire({
        title: 'Are you sure?',
        html: `Delete <strong>${name}</strong>?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('delete_product', '1');
            formData.append('id', id);
            fetch('categories.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) location.reload();
                    else throw new Error(data.error || 'Failed to delete');
                })
                .catch(error => Swal.fire('Error!', error.message, 'error'));
        }
    });
}

function editStock(id, name, currentStock) {
    Swal.fire({
        title: 'Edit Stock',
        html: `
            <div class="mb-2 text-start">
                <b>${name}</b><br>
                Current Stock: <span class="badge bg-info">${currentStock}</span>
            </div>
            <input type="number" id="stockDelta" class="swal2-input" placeholder="e.g. -2 untuk kurangi, 5 untuk tambah" value="-1">
            <div class="form-text text-muted">Masukkan angka negatif untuk mengurangi stok, positif untuk menambah.</div>
        `,
        inputAttributes: { min: -currentStock },
        showCancelButton: true,
        confirmButtonText: 'Update',
        preConfirm: () => {
            const delta = parseInt(document.getElementById('stockDelta').value, 10);
            if (isNaN(delta) || delta === 0) {
                Swal.showValidationMessage('Isi perubahan stok (tidak boleh 0)');
                return false;
            }
            return delta;
        }
    }).then(result => {
        if (result.isConfirmed && result.value) {
            const formData = new FormData();
            formData.append('edit_stock', '1');
            formData.append('id', id);
            formData.append('stock_delta', result.value);
            fetch('categories.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Success', 'Stock updated', 'success');
                        // Update tampilan stok tanpa reload
                        document.getElementById('stock-' + id).textContent = data.stock;
                    } else {
                        throw new Error(data.error || 'Failed to update stock');
                    }
                })
                .catch(error => Swal.fire('Error', error.message, 'error'));
        }
    });
}
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>