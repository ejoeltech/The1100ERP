<?php
include '../../includes/session-check.php';
requirePermission('manage_customers');
$pageTitle = 'Manage Customers - Bluedots Technologies';
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "(customer_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status_filter !== '') {
    $where[] = "is_active = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT c.*, 
    (SELECT SUM(balance_due) FROM invoices WHERE customer_id = c.id AND status != 'paid' AND deleted_at IS NULL) as total_due
    FROM customers c 
    WHERE $where_clause 
    AND c.deleted_at IS NULL 
    ORDER BY c.created_at DESC
");
$stmt->execute($params);
$customers = $stmt->fetchAll();

$stats = $pdo->query("SELECT COUNT(*) as total, SUM(is_active=1) as active FROM customers WHERE deleted_at IS NULL")->fetch();

include '../../includes/header.php';
?>

<?php if (isset($_GET['created'])): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
        <p class="text-green-800 font-semibold">✓ Customer created successfully!</p>
    </div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
    <h2 class="text-3xl font-bold text-gray-900">Manage Customers</h2>
    <a href="create-customer.php" class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">
        + Add Customer
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <div class="bg-white p-4 rounded-lg shadow">
        <p class="text-sm text-gray-600">Total Customers</p>
        <p class="text-2xl font-bold text-gray-900">
            <?php echo $stats['total']; ?>
        </p>
    </div>
    <div class="bg-white p-4 rounded-lg shadow">
        <p class="text-sm text-gray-600">Active</p>
        <p class="text-2xl font-bold text-green-600">
            <?php echo $stats['active']; ?>
        </p>
    </div>
</div>

<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <input type="text" name="search" placeholder="Search by name, email, or phone..."
            value="<?php echo htmlspecialchars($search); ?>"
            class="px-4 py-2 border border-gray-300 rounded-lg col-span-2">
        <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">Search</button>
    </form>
</div>

<div class="bg-white rounded-lg shadow-md overflow-hidden table-responsive">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Name</th>
                <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Company</th>
                <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Contact</th>
                <th class="px-4 py-3 text-center text-sm font-bold text-gray-700">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($customers as $customer): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <p class="font-semibold">
                            <?php echo htmlspecialchars($customer['customer_name']); ?>
                        </p>
                        <div class="flex gap-2 mt-1">
                            <?php if (!empty($customer['account_balance']) && $customer['account_balance'] > 0): ?>
                                <span class="text-xs bg-green-100 text-green-800 px-2 py-0.5 rounded-full font-medium">
                                    Credit: <?php echo formatNaira($customer['account_balance']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($customer['total_due']) && $customer['total_due'] > 0): ?>
                                <span class="text-xs bg-red-100 text-red-800 px-2 py-0.5 rounded-full font-medium">
                                    Debit: <?php echo formatNaira($customer['total_due']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?php echo htmlspecialchars($customer['company'] ?? '—'); ?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <p>
                            <?php echo htmlspecialchars($customer['email'] ?? '—'); ?>
                        </p>
                        <p class="text-gray-500">
                            <?php echo htmlspecialchars($customer['phone'] ?? '—'); ?>
                        </p>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <a href="view-customer.php?id=<?php echo $customer['id']; ?>"
                                class="text-primary hover:text-blue-700 font-semibold text-sm">View</a>
                            <span class="text-gray-300">|</span>
                            <a href="edit-customer.php?id=<?php echo $customer['id']; ?>"
                                class="text-primary hover:text-blue-700 font-semibold text-sm">Edit</a>
                            <span class="text-gray-300">|</span>
                            <button onclick="deleteCustomer(<?php echo $customer['id']; ?>)"
                                class="text-red-600 hover:text-red-800 font-semibold text-sm">Delete</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (empty($customers)): ?>
        <p class="text-center py-8 text-gray-500">No customers found</p>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function deleteCustomer(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('../../api/customers/delete-customer.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: id
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire(
                                'Deleted!',
                                'Customer has been deleted.',
                                'success'
                            ).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire(
                                'Error!',
                                data.message || 'Something went wrong.',
                                'error'
                            );
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire(
                            'Error!',
                            'Failed to delete customer.',
                            'error'
                        );
                    });
            }
        })
    }
</script>