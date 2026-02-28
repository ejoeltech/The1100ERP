<?php
// HR Recruitment
// require_once '../../../config.php'; // Removed to prevent double inclusion
require_once '../../../includes/session-check.php';
require_once '../../../includes/groq-config.php';

requireLogin();

$pageTitle = 'Recruitment | ' . COMPANY_NAME;
$currentPage = 'hr_recruitment';

$message = '';
$error = '';
$generated_ad = '';

// Handle Candidate Addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_candidate'])) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO hr_recruitment_candidates (first_name, last_name, email, phone, applied_for_role, status)
            VALUES (?, ?, ?, ?, ?, 'new')
        ");
        $stmt->execute([
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['role']
        ]);
        $message = "Candidate added successfully.";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle AI Job Ad Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_ad'])) {
    try {
        $role = $_POST['job_role'];
        $requirements = $_POST['requirements'];

        $systemPrompt = "You are an Expert Recruiter. Write a compelling Job Advertisement for " . COMPANY_NAME . ".
        Tone: Professional, Exciting, Inclusive.
        Format: HTML.
        Include: Role Overview, Key Responsibilities, Requirements, and Benefits.";

        $userPrompt = "Generate a Job Ad for the role of '$role'.
        Specific Requirements/Context: $requirements";

        $generated_ad = callGroqAPI($userPrompt, $systemPrompt);
        $message = "Job Ad generated successfully.";
    } catch (Exception $e) {
        $error = "AI Error: " . $e->getMessage();
    }
}

// Fetch Candidates
$candidates = $pdo->query("SELECT * FROM hr_recruitment_candidates ORDER BY created_at DESC")->fetchAll();

include_once '../../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Recruitment</h1>
    <p class="text-gray-600">Manage candidates and create job publications</p>
</div>

<?php if ($message): ?>
    <div class="bg-green-50 text-green-700 p-4 rounded-lg mb-6 border border-green-200">
        <?php echo $message; ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="bg-red-50 text-red-700 p-4 rounded-lg mb-6 border border-red-200">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

    <!-- Left Column: Candidates -->
    <div class="space-y-6">
        <!-- Add Candidate -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Add New Candidate</h3>
            <form method="POST" class="grid grid-cols-1 gap-4">
                <input type="hidden" name="add_candidate" value="1">
                <div class="grid grid-cols-2 gap-4">
                    <input type="text" name="first_name" placeholder="First Name" required
                        class="rounded-lg border-gray-300">
                    <input type="text" name="last_name" placeholder="Last Name" required
                        class="rounded-lg border-gray-300">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <input type="email" name="email" placeholder="Email" required class="rounded-lg border-gray-300">
                    <input type="text" name="phone" placeholder="Phone" class="rounded-lg border-gray-300">
                </div>
                <input type="text" name="role" placeholder="Applied For Role" required
                    class="rounded-lg border-gray-300">
                <button type="submit" class="bg-gray-800 text-white py-2 rounded-lg font-bold hover:bg-gray-900">+ Add
                    Candidate</button>
            </form>
        </div>

        <!-- Candidates List -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                <h3 class="font-bold text-gray-900">Recent Candidates</h3>
                <span class="bg-gray-200 text-gray-700 text-xs px-2 py-1 rounded-full">
                    <?php echo count($candidates); ?>
                </span>
            </div>
            <ul class="divide-y divide-gray-200 max-h-[500px] overflow-y-auto">
                <?php foreach ($candidates as $c): ?>
                    <li class="p-4 hover:bg-gray-50">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="font-bold text-gray-900">
                                    <?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?>
                                </h4>
                                <p class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars($c['applied_for_role']); ?>
                                </p>
                                <div class="text-xs text-gray-400 mt-1">
                                    <?php echo htmlspecialchars($c['email']); ?> •
                                    <?php echo htmlspecialchars($c['phone']); ?>
                                </div>
                            </div>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 capitalize">
                                <?php echo $c['status']; ?>
                            </span>
                        </div>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($candidates)): ?>
                    <li class="p-6 text-center text-gray-500">No candidates yet.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- Right Column: AI Job Ad Generator -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex flex-col h-full">
        <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
            <span>✨</span> AI Job Ad Generator
        </h3>

        <form method="POST" class="mb-4">
            <input type="hidden" name="generate_ad" value="1">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Job Role Title</label>
                    <input type="text" name="job_role" required placeholder="e.g. Senior Sales Manager"
                        class="w-full rounded-lg border-gray-300 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Key Requirements / Context</label>
                    <textarea name="requirements" rows="3" required
                        placeholder="e.g. 5 years experience, Lagos based, driving license required..."
                        class="w-full rounded-lg border-gray-300 focus:ring-primary"></textarea>
                </div>
                <button type="submit"
                    class="w-full bg-purple-600 text-white py-3 rounded-lg font-bold hover:bg-purple-700 flex justify-center items-center gap-2">
                    Generate with AI
                </button>
            </div>
        </form>

        <?php if ($generated_ad): ?>
            <div class="flex-grow border rounded-lg p-4 bg-gray-50 overflow-y-auto">
                <div class="prose prose-sm max-w-none">
                    <?php echo $generated_ad; ?>
                </div>
            </div>
            <button
                onclick="navigator.clipboard.writeText(document.querySelector('.prose').innerText).then(()=>alert('Copied!'))"
                class="mt-4 w-full border border-gray-300 py-2 rounded-lg text-gray-600 hover:bg-gray-100">
                Copy Content
            </button>
        <?php else: ?>
            <div
                class="flex-grow border-2 border-dashed border-gray-200 rounded-lg flex items-center justify-center text-gray-400 p-8 text-center">
                Generated content will appear here...
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../../../includes/footer.php'; ?>