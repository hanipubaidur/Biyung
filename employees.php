<?php
require_once 'config/database.php';
$pageTitle = "Employees";
session_start();

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Handle add/edit employee
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : null;
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $join_date = $_POST['join_date'] ?? null;
        $status = $_POST['status'] ?? 'active';

        if (empty($name)) {
            $_SESSION['employee_flash'] = ['type' => 'error', 'msg' => 'Name is required'];
            header("Location: employees.php");
            exit;
        }

        if ($id) {
            // Update
            $stmt = $conn->prepare("UPDATE employees SET name=?, phone=?, address=?, join_date=?, status=? WHERE id=?");
            $stmt->execute([$name, $phone, $address, $join_date, $status, $id]);
            $_SESSION['employee_flash'] = ['type' => 'success', 'msg' => 'Employee updated successfully'];
        } else {
            // Insert
            $stmt = $conn->prepare("INSERT INTO employees (name, phone, address, join_date, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $phone, $address, $join_date, $status]);
            $_SESSION['employee_flash'] = ['type' => 'success', 'msg' => 'Employee added successfully'];
        }
        header("Location: employees.php");
        exit;
    }

    // Handle delete
    if (isset($_GET['delete'])) {
        $id = intval($_GET['delete']);
        $stmt = $conn->prepare("UPDATE employees SET status='inactive' WHERE id=?");
        $stmt->execute([$id]);
        $_SESSION['employee_flash'] = ['type' => 'success', 'msg' => 'Employee deactivated'];
        header("Location: employees.php");
        exit;
    }

    // Get all employees
    $employees = $conn->query("SELECT * FROM employees ORDER BY status DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // For edit form
    $editEmployee = null;
    if (isset($_GET['edit'])) {
        $id = intval($_GET['edit']);
        $stmt = $conn->prepare("SELECT * FROM employees WHERE id=?");
        $stmt->execute([$id]);
        $editEmployee = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    $_SESSION['employee_flash'] = ['type' => 'error', 'msg' => 'Connection failed: ' . $e->getMessage()];
    header("Location: employees.php");
    exit;
}

ob_start();
?>

<!-- SweetAlert flash message -->
<?php if (isset($_SESSION['employee_flash'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: '<?= $_SESSION['employee_flash']['type'] ?>',
        title: <?= $_SESSION['employee_flash']['type'] === 'success' ? "'Success'" : "'Error'" ?>,
        text: <?= json_encode($_SESSION['employee_flash']['msg']) ?>,
        timer: 1800,
        showConfirmButton: false
    });
});
</script>
<?php unset($_SESSION['employee_flash']); endif; ?>

<div class="row">
    <div class="col-md-5 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?= $editEmployee ? 'Edit Employee' : 'Add Employee' ?></h5>
            </div>
            <div class="card-body">
                <form method="post" action="employees.php" onsubmit="return validateEmployeeForm();">
                    <?php if ($editEmployee): ?>
                        <input type="hidden" name="id" value="<?= $editEmployee['id'] ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editEmployee['name'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($editEmployee['phone'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control"><?= htmlspecialchars($editEmployee['address'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Join Date</label>
                        <input type="date" name="join_date" class="form-control" value="<?= htmlspecialchars($editEmployee['join_date'] ?? (date('Y-m-d'))) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= (isset($editEmployee['status']) && $editEmployee['status'] == 'active') ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= (isset($editEmployee['status']) && $editEmployee['status'] == 'inactive') ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= $editEmployee ? 'Update' : 'Add' ?></button>
                    <?php if ($editEmployee): ?>
                        <a href="employees.php" class="btn btn-secondary ms-2">Cancel</a>
                    <?php endif; ?>
                </form>
                <script>
                function validateEmployeeForm() {
                    var name = document.querySelector('input[name="name"]').value.trim();
                    if (!name) {
                        Swal.fire('Error', 'Name is required', 'error');
                        return false;
                    }
                    return true;
                }
                </script>
            </div>
        </div>
    </div>
    <div class="col-md-7 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Employee List</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Join Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($employees as $emp): ?>
                            <tr>
                                <td><?= htmlspecialchars($emp['name']) ?></td>
                                <td><?= htmlspecialchars($emp['phone']) ?></td>
                                <td><?= htmlspecialchars($emp['address']) ?></td>
                                <td><?= $emp['join_date'] ? date('d-m-Y', strtotime($emp['join_date'])) : '-' ?></td>
                                <td>
                                    <span class="badge <?= $emp['status'] == 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= ucfirst($emp['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="employees.php?edit=<?= $emp['id'] ?>" class="btn btn-sm btn-warning">
                                        <i class='bx bx-edit'></i>
                                    </a>
                                    <?php if ($emp['status'] == 'active'): ?>
                                    <a href="employees.php?delete=<?= $emp['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Deactivate this employee?')">
                                        <i class='bx bx-user-x'></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (empty($employees)): ?>
                        <div class="text-center text-muted">No employees found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
