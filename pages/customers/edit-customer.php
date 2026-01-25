<?php
include '../../includes/session-check.php';
requirePermission('manage_customers');

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: manage-customers.php?error=Invalid customer');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    header('Location: manage-customers.php?error=Customer not found');
    exit;
}

$pageTitle = 'Edit Customer - ERP System';
include '../../includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <h2 class="text-3xl font-bold text-gray-900 mb-6">Edit Customer</h2>

    <div class="bg-white rounded-lg shadow-md p-8">
        <form method="POST" action="../../api/customers/update-customer.php">
            <input type="hidden" name="id" value="<?php echo $customer['id']; ?>">

            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Customer Name <span
                            class="text-red-500">*</span></label>
                    <input type="text" name="customer_name" required
                        value="<?php echo htmlspecialchars($customer['customer_name']); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Company</label>
                    <input type="text" name="company"
                        value="<?php echo htmlspecialchars($customer['company'] ?? ''); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email <span
                                class="text-red-500">*</span></label>
                        <input type="email" name="email" required
                            value="<?php echo htmlspecialchars($customer['email']); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Phone</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Address</label>
                    <textarea name="address" rows="3"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"><?php echo htmlspecialchars($customer['address'] ?? ''); ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">City</label>
                    <input type="text" name="city" value="<?php echo htmlspecialchars($customer['city'] ?? ''); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                    <textarea name="notes" rows="3"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"><?php echo htmlspecialchars($customer['notes'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="flex flex-col md:flex-row gap-4 justify-end mt-8">
                <a href="manage-customers.php"
                    class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold text-center">Cancel</a>
                <button type="submit"
                    class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold text-center">Update
                    Customer</button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>