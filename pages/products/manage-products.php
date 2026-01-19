<?php
include '../../includes/session-check.php';
requirePermission('manage_products');

$pageTitle = 'Manage Products - Bluedots Technologies';

// Search and filter
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query
$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "(product_code LIKE ? OR product_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($category_filter) {
    $where[] = "category = ?";
    $params[] = $category_filter;
}
if ($status_filter !== '') {
    $where[] = "is_active = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where);

// Get products
$stmt = $pdo->prepare("SELECT * FROM products WHERE $where_clause AND deleted_at IS NULL ORDER BY created_at DESC");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get stats
$stmt = $pdo->query("SELECT COUNT(*) as total, SUM(is_active=1) as active FROM products WHERE deleted_at IS NULL");
$stats = $stmt->fetch();

// Get categories
$categories = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

include '../../includes/header.php';
?>

<?php if (isset($_GET['created'])): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
        <p class="text-green-800 font-semibold">✓ Product created successfully!</p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
        <p class="text-green-800 font-semibold">✓ Product updated successfully!</p>
    </div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
    <h2 class="text-3xl font-bold text-gray-900">Manage Products</h2>
    <a href="create-product.php" class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">
        + Add Product
    </a>
</div>

<!-- Stats -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white p-4 rounded-lg shadow">
        <p class="text-sm text-gray-600">Total Products</p>
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
    <div class="bg-white p-4 rounded-lg shadow">
        <p class="text-sm text-gray-600">Categories</p>
        <p class="text-2xl font-bold text-blue-600">
            <?php echo count($categories); ?>
        </p>
    </div>
</div>

<!-- Search and Filter -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <input type="text" name="search" placeholder="Search by name or code..."
            value="<?php echo htmlspecialchars($search); ?>" class="px-4 py-2 border border-gray-300 rounded-lg">
        <select name="category" class="px-4 py-2 border border-gray-300 rounded-lg">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat; ?>" <?php echo $category_filter === $cat ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg">
            <option value="">All Status</option>
            <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
            <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
        </select>
        <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">Search</button>
    </form>
</div>

<!-- Products Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden table-responsive">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Code</th>
                <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Product Name</th>
                <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Category</th>
                <th class="px-4 py-3 text-right text-sm font-bold text-gray-700">Price</th>
                <th class="px-4 py-3 text-center text-sm font-bold text-gray-700">Status</th>
                <th class="px-4 py-3 text-center text-sm font-bold text-gray-700">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-sm">
                        <?php echo htmlspecialchars($product['product_code']); ?>
                    </td>
                    <td class="px-4 py-3">
                        <p class="font-semibold">
                            <?php echo htmlspecialchars($product['product_name']); ?>
                        </p>
                        <?php if ($product['description']): ?>
                            <p class="text-xs text-gray-500">
                                <?php echo substr(htmlspecialchars($product['description']), 0, 60); ?>...
                            </p>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?php echo htmlspecialchars($product['category']); ?>
                    </td>
                    <td class="px-4 py-3 text-right font-semibold">
                        <?php echo formatNaira($product['unit_price']); ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($product['is_active']): ?>
                            <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">Active</span>
                        <?php else: ?>
                            <span class="px-3 py-1 bg-gray-100 text-gray-800 text-xs font-semibold rounded-full">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <a href="edit-product.php?id=<?php echo $product['id']; ?>"
                                class="text-primary hover:text-blue-700 font-semibold text-sm">Edit</a>
                            <span class="text-gray-300">|</span>
                            <a href="../../api/products/toggle-product-status.php?id=<?php echo $product['id']; ?>"
                                class="text-yellow-600 hover:text-yellow-700 font-semibold text-sm">
                                <?php echo $product['is_active'] ? 'Disable' : 'Enable'; ?>
                            </a>
                            <span class="text-gray-300">|</span>
                            <button onclick="deleteProduct(<?php echo $product['id']; ?>)"
                                class="text-red-600 hover:text-red-800 font-semibold text-sm">Delete</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (empty($products)): ?>
        <p class="text-center py-8 text-gray-500">No products found</p>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function deleteProduct(id) {
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
                fetch('../../api/products/delete-product.php', {
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
                            'Product has been deleted.',
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
                        'Failed to delete product.',
                        'error'
                    );
                });
            }
        })
    }
</script>