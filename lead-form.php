<?php
// Public lead capture page (no auth). Embeddable lead-intake form.
// Mirrors the repo's public pages (signup.php): require config, use $pdo directly.
require_once 'config.php';

$success = isset($_GET['success']);
$error   = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';

// CSRF-less public POST is fine; we rate-limit by IP in save-lead.php.
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact <?php echo htmlspecialchars(COMPANY_NAME); ?> — Get a Quote</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body class="bg-gray-50 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-lg">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Get a Free Quote</h1>
            <p class="text-gray-500">Tell us what you need — we'll get back to you fast.</p>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-50 text-green-700 p-4 rounded-lg text-sm mb-4 border border-green-200">
                ✅ Thanks! Your request was received. We'll contact you shortly.
            </div>
        <?php elseif ($error): ?>
            <div class="bg-red-50 text-red-700 p-4 rounded-lg text-sm mb-4 border border-red-200">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="api/leads/save-lead.php" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                <input type="text" name="name" required
                    class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone *</label>
                <input type="tel" name="phone" required placeholder="080..."
                    class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" placeholder="you@example.com"
                    class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">What are you interested in?</label>
                <input type="text" name="interest" placeholder="e.g. 5kVA inverter + 2 batteries"
                    class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                <textarea name="message" rows="3"
                    class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            <button type="submit"
                class="w-full bg-blue-600 text-white py-3 rounded-lg font-bold hover:bg-blue-700 transition shadow-lg">
                Send My Request
            </button>
            <p class="text-xs text-gray-400 text-center">By submitting you agree to be contacted about your enquiry.</p>
        </form>
    </div>
</body>

</html>
