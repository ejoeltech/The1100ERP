<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helpers.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die('Access Denied: Admin role required. Your role: ' . ($_SESSION['role'] ?? 'not set'));
}

require_once '../includes/ai-rate-limiter.php';

$pageTitle = 'AI Settings & Analytics - ERP System';
include '../includes/header.php';


// Get current settings
$settings = [
    'ip_hourly' => getSetting('ai_ip_hourly_limit', 5),
    'ip_daily' => getSetting('ai_ip_daily_limit', 20),
    'user_hourly' => getSetting('ai_user_hourly_limit', 10),
    'user_daily' => getSetting('ai_user_daily_limit', 50),
    'monthly_budget' => getSetting('ai_monthly_budget_usd', 100),
    'enable_caching' => getSetting('ai_enable_caching', '1') === '1',
    'cache_ttl' => getSetting('ai_cache_ttl_hours', 24),
    'enable_public' => getSetting('ai_enable_public_access', '1') === '1',
    'log_retention' => getSetting('ai_log_retention_days', 90),
    'emergency_disable' => getSetting('ai_emergency_disable', '0') === '1',
];

// Get statistics
$stats = AiRateLimiter::getStatistics(30);

// Calculate quick stats
$todayCost = 0;
$monthCost = 0;
$todayRequests = 0;

foreach ($stats['daily_costs'] as $day) {
    $monthCost += $day['cost'];
    if ($day['date'] === date('Y-m-d')) {
        $todayCost = $day['cost'];
        $todayRequests = $day['requests'];
    }
}

$cacheHitRate = $stats['cache_stats']['total_cached'] > 0 
    ? round(($stats['cache_stats']['total_hits'] / $stats['cache_stats']['total_cached']) * 100, 1)
    : 0;
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">AI Settings & Analytics</h1>
        <p class="text-gray-600 mt-2">Manage rate limits, monitor usage, and control AI features</p>
    </div>

    <!-- Emergency Disable Alert -->
    <?php if ($settings['emergency_disable']): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-r-lg">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <svg class="w-6 h-6 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <div>
                        <h3 class="text-red-800 font-semibold">AI Features Disabled</h3>
                        <p class="text-red-700 text-sm">All AI features are currently disabled (emergency mode or budget exceeded)</p>
                    </div>
                </div>
                <button onclick="toggleEmergencyMode(false)" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium">
                    Re-enable AI
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-gray-600">Today's Cost</h3>
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <p class="text-2xl font-bold text-gray-900">$<?= number_format($todayCost, 2) ?></p>
            <p class="text-xs text-gray-500 mt-1"><?= $todayRequests ?> requests</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-gray-600">Monthly Cost</h3>
                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            </div>
            <p class="text-2xl font-bold text-gray-900">$<?= number_format($monthCost, 2) ?></p>
            <p class="text-xs text-gray-500 mt-1">Budget: $<?= $settings['monthly_budget'] ?></p>
            <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                <div class="bg-green-500 h-2 rounded-full" style="width: <?= min(100, ($monthCost / $settings['monthly_budget']) * 100) ?>%"></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-gray-600">Cache Hit Rate</h3>
                <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            </div>
            <p class="text-2xl font-bold text-gray-900"><?= $cacheHitRate ?>%</p>
            <p class="text-xs text-gray-500 mt-1"><?= number_format($stats['cache_stats']['total_hits']) ?> hits</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-gray-600">Cached Responses</h3>
                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path></svg>
            </div>
            <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['cache_stats']['total_cached']) ?></p>
            <p class="text-xs text-gray-500 mt-1">Avg hits: <?= round($stats['cache_stats']['avg_hits'], 1) ?></p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Settings Panel -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 mb-8">
                <h2 class="text-xl font-bold text-gray-900 mb-6">Rate Limit Configuration</h2>
                
                <form id="settingsForm" onsubmit="saveSettings(event)">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                IP Hourly Limit
                                <span class="text-gray-500">(Public users)</span>
                            </label>
                            <input type="number" name="ip_hourly" value="<?= $settings['ip_hourly'] ?>" min="1" max="100" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                IP Daily Limit
                                <span class="text-gray-500">(Public users)</span>
                            </label>
                            <input type="number" name="ip_daily" value="<?= $settings['ip_daily'] ?>" min="1" max="500" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                User Hourly Limit
                                <span class="text-gray-500">(Logged in)</span>
                            </label>
                            <input type="number" name="user_hourly" value="<?= $settings['user_hourly'] ?>" min="1" max="200" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                User Daily Limit
                                <span class="text-gray-500">(Logged in)</span>
                            </label>
                            <input type="number" name="user_daily" value="<?= $settings['user_daily'] ?>" min="1" max="1000" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Monthly Budget (USD)
                            </label>
                            <input type="number" name="monthly_budget" value="<?= $settings['monthly_budget'] ?>" min="1" step="0.01" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Cache TTL (hours)
                            </label>
                            <input type="number" name="cache_ttl" value="<?= $settings['cache_ttl'] ?>" min="1" max="168" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Log Retention (days)
                            </label>
                            <input type="number" name="log_retention" value="<?= $settings['log_retention'] ?>" min="7" max="365" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>

                    <div class="space-y-4 mb-6">
                        <label class="flex items-center">
                            <input type="checkbox" name="enable_caching" <?= $settings['enable_caching'] ? 'checked' : '' ?> 
                                   class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span class="ml-2 text-sm font-medium text-gray-700">Enable Response Caching</span>
                            <span class="ml-2 text-xs text-gray-500">(Saves ~30% API costs)</span>
                        </label>

                        <label class="flex items-center">
                            <input type="checkbox" name="enable_public" <?= $settings['enable_public'] ? 'checked' : '' ?> 
                                   class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span class="ml-2 text-sm font-medium text-gray-700">Allow Public Access</span>
                            <span class="ml-2 text-xs text-gray-500">(System Designer accessible without login)</span>
                        </label>
                    </div>

                    <button type="submit" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-blue-700 transition">
                        Save Settings
                    </button>
                </form>
            </div>

            <!-- Daily Cost Chart -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Daily API Costs (Last 30 Days)</h2>
                <canvas id="costChart" height="80"></canvas>
            </div>
        </div>

        <!-- Side Panel -->
        <div class="space-y-8">
            <!-- Emergency Controls -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Emergency Controls</h3>
                
                <button onclick="clearCache()" class="w-full mb-3 bg-yellow-500 text-white py-2 px-4 rounded-lg font-medium hover:bg-yellow-600 transition">
                    Clear All Cache
                </button>

                <button onclick="toggleEmergencyMode(<?= $settings['emergency_disable'] ? 'false' : 'true' ?>)" 
                        class="w-full mb-3 <?= $settings['emergency_disable'] ? 'bg-green-500 hover:bg-green-600' : 'bg-red-500 hover:bg-red-600' ?> text-white py-2 px-4 rounded-lg font-medium transition">
                    <?= $settings['emergency_disable'] ? 'Enable AI Features' : 'Disable All AI' ?>
                </button>

                <button onclick="exportUsage()" class="w-full bg-gray-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-gray-700 transition">
                    Export Usage Report
                </button>
            </div>

            <!-- Top Tools -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Usage by Tool</h3>
                <div class="space-y-3">
                    <?php foreach ($stats['tool_stats'] as $tool): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700"><?= ucwords(str_replace('_', ' ', $tool['tool_name'])) ?></span>
                            <span class="text-sm text-gray-600"><?= $tool['requests'] ?> ($<?= number_format($tool['cost'], 2) ?>)</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Top Users -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Top Users (30 days)</h3>
                <div class="space-y-3">
                    <?php foreach (array_slice($stats['top_users'], 0, 5) as $user): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($user['username']) ?></span>
                            <span class="text-sm text-gray-600"><?= $user['requests'] ?> req</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Top IPs -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Top Public IPs (30 days)</h3>
                <div class="space-y-3">
                    <?php foreach (array_slice($stats['top_ips'], 0, 5) as $ip): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-mono text-gray-700"><?= htmlspecialchars($ip['ip_address']) ?></span>
                            <span class="text-sm text-gray-600"><?= $ip['requests'] ?> req</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Cost Chart
const ctx = document.getElementById('costChart').getContext('2d');
const dailyCosts = <?= json_encode(array_reverse($stats['daily_costs'])) ?>;

new Chart(ctx, {
    type: 'line',
    data: {
        labels: dailyCosts.map(d => new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
        datasets: [{
            label: 'Daily Cost ($)',
            data: dailyCosts.map(d => parseFloat(d.cost)),
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: value => '$' + value.toFixed(2)
                }
            }
        }
    }
});

async function saveSettings(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    
    try {
        const response = await fetch('../api/ai/update-settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Settings saved successfully!');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) {
        alert('Failed to save settings');
    }
}

async function clearCache() {
    if (!confirm('Clear all cached responses? This will increase API usage temporarily.')) return;
    
    try {
        const response = await fetch('../api/ai/clear-cache.php', { method: 'POST' });
        const result = await response.json();
        
        if (result.success) {
            alert('Cache cleared successfully!');
            location.reload();
        }
    } catch (e) {
        alert('Failed to clear cache');
    }
}

async function toggleEmergencyMode(enable) {
    const action = enable ? 'disable' : 'enable';
    if (!confirm(`Are you sure you want to ${action} all AI features?`)) return;
    
    try {
        const response = await fetch('../api/ai/emergency-toggle.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ enable: !enable })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('AI features ' + (enable ? 'disabled' : 'enabled'));
            location.reload();
        }
    } catch (e) {
        alert('Failed to toggle emergency mode');
    }
}

function exportUsage() {
    window.location.href = '../api/ai/export-usage.php';
}
</script>

<?php include '../includes/footer.php'; ?>
