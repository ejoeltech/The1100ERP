<?php include '../includes/session-check.php'; ?>
<?php
$pageTitle = 'My Profile';
include '../includes/header.php';
?>

<div class="max-w-4xl mx-auto p-6">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">My Profile</h1>
        <p class="text-gray-600">Manage your account settings and digital signature.</p>
    </div>

    <!-- User Info Card -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">Account Details</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-semibold text-gray-600">Full Name</label>
                <div class="mt-1 text-lg font-medium text-gray-900">
                    <?php echo htmlspecialchars($current_user['full_name']); ?>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-600">Email Address</label>
                <div class="mt-1 text-lg font-medium text-gray-900">
                    <?php echo htmlspecialchars($current_user['email']); ?>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-600">Username</label>
                <div class="mt-1 text-lg font-medium text-gray-900">
                    <?php echo htmlspecialchars($current_user['username']); ?>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-600">Role</label>
                <div
                    class="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    <?php echo ucfirst($current_user['role']); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Signature Card -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">Digital Signature</h2>
        <p class="text-gray-600 mb-6 text-sm">Create your digital signature below. This can be appended to quotes and
            invoices you generate.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

            <!-- Current Signature -->
            <div class="border rounded-lg p-4 bg-gray-50 flex flex-col items-center justify-center min-h-[200px]">
                <h3 class="text-sm font-bold text-gray-500 mb-4 uppercase tracking-wider">Current Signature</h3>
                <?php if (!empty($current_user['signature_file']) && file_exists('../uploads/signatures/' . $current_user['signature_file'])): ?>
                    <img src="../uploads/signatures/<?php echo htmlspecialchars($current_user['signature_file']); ?>?v=<?php echo time(); ?>"
                        alt="Your Signature" class="max-w-full h-auto max-h-[150px]">
                <?php else: ?>
                    <div class="text-gray-400 italic text-center">
                        <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z">
                            </path>
                        </svg>
                        No signature saved yet.
                    </div>
                <?php endif; ?>
            </div>

            <!-- New Signature Pad -->
            <div>
                <h3 class="text-sm font-bold text-gray-500 mb-2 uppercase tracking-wider">Create New Signature</h3>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p- bg-white">
                    <canvas id="signature-pad" class="w-full h-[200px] cursor-crosshair touch-none"></canvas>
                </div>
                <div class="flex justify-between mt-4">
                    <button type="button" id="clear-btn"
                        class="px-4 py-2 text-sm text-red-600 hover:text-red-800 font-medium">
                        Clear & Start Over
                    </button>
                    <button type="button" id="save-btn"
                        class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold shadow-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                            </path>
                        </svg>
                        Save Signature
                    </button>
                </div>
                <p class="text-xs text-gray-500 mt-2 text-right">* Drawn with realistic blue biro effect</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
<script>
    // Initialize Signature Pad
    const canvas = document.getElementById('signature-pad');

    // Handle High DPI Screens
    function resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
    }
    window.onresize = resizeCanvas;
    resizeCanvas();

    const signaturePad = new SignaturePad(canvas, {
        penColor: 'rgb(0, 0, 139)', // "Blue Biro" Color (DarkBlue)
        minWidth: 0.5,
        maxWidth: 2.5,
        throttle: 16, // Smoothness
        velocityFilterWeight: 0.7
    });

    // Clear Button
    document.getElementById('clear-btn').addEventListener('click', function () {
        signaturePad.clear();
    });

    // Save Button
    document.getElementById('save-btn').addEventListener('click', function () {
        if (signaturePad.isEmpty()) {
            alert("Please draw a signature first.");
            return;
        }

        const dataUrl = signaturePad.toDataURL('image/png');

        // Show loading state
        const btn = this;
        const originalText = btn.innerHTML;
        btn.innerHTML = 'Saving...';
        btn.disabled = true;

        // Send to backend
        fetch('../api/save-signature.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ image: dataUrl })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Signature saved successfully!');
                    window.location.reload();
                } else {
                    alert('Error saving signature: ' + (data.message || 'Unknown error'));
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            })
            .catch((error) => {
                console.error('Error:', error);
                alert('An error occurred while saving.');
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
    });
</script>

<?php include '../includes/footer.php'; ?>