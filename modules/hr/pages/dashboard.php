<?php
// HR Dashboard
// require_once '../../../config.php'; // Removed to prevent double inclusion
require_once '../../../includes/session-check.php';

requireLogin();

$pageTitle = 'HR Dashboard | ' . COMPANY_NAME;
$currentPage = 'hr_dashboard';

include_once '../../../includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">HR Dashboard</h1>
        <p class="text-gray-600">Overview of Human Resources</p>
    </div>
    <div class="flex gap-2">
        <a href="employees.php" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
            + New Employee
        </a>
    </div>
</div>

<!-- Stats Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-gray-500">Total Employees</p>
                <h3 class="text-2xl font-bold text-gray-900 mt-2">0</h3>
            </div>
            <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                    </path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-gray-500">On Leave Today</p>
                <h3 class="text-2xl font-bold text-gray-900 mt-2">0</h3>
            </div>
            <div class="p-2 bg-yellow-50 rounded-lg text-yellow-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                    </path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-gray-500">Open Vacancies</p>
                <h3 class="text-2xl font-bold text-gray-900 mt-2">0</h3>
            </div>
            <div class="p-2 bg-green-50 rounded-lg text-green-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                    </path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-gray-500">Attendance Rate</p>
                <h3 class="text-2xl font-bold text-gray-900 mt-2">0%</h3>
            </div>
            <div class="p-2 bg-purple-50 rounded-lg text-purple-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Quick Actions -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-6 border-b border-gray-100">
            <h3 class="text-lg font-bold text-gray-900">Quick Actions</h3>
        </div>
        <div class="p-6 grid grid-cols-2 gap-4">
            <a href="attendance.php"
                class="p-4 border rounded-lg hover:border-primary hover:text-primary transition-colors text-center group">
                <div
                    class="w-10 h-10 bg-gray-50 group-hover:bg-blue-50 rounded-full mx-auto flex items-center justify-center mb-2 transition-colors">
                    <svg class="w-5 h-5 text-gray-500 group-hover:text-primary" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <span class="font-medium">Mark Attendance</span>
            </a>
            <a href="leave.php"
                class="p-4 border rounded-lg hover:border-primary hover:text-primary transition-colors text-center group">
                <div
                    class="w-10 h-10 bg-gray-50 group-hover:bg-blue-50 rounded-full mx-auto flex items-center justify-center mb-2 transition-colors">
                    <svg class="w-5 h-5 text-gray-500 group-hover:text-primary" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                        </path>
                    </svg>
                </div>
                <span class="font-medium">Leave Request</span>
            </a>
            <a href="documents.php"
                class="p-4 border rounded-lg hover:border-primary hover:text-primary transition-colors text-center group">
                <div
                    class="w-10 h-10 bg-gray-50 group-hover:bg-blue-50 rounded-full mx-auto flex items-center justify-center mb-2 transition-colors">
                    <svg class="w-5 h-5 text-gray-500 group-hover:text-primary" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                        </path>
                    </svg>
                </div>
                <span class="font-medium">Generate Letter</span>
            </a>
            <a href="#"
                class="p-4 border rounded-lg hover:border-primary hover:text-primary transition-colors text-center group">
                <div
                    class="w-10 h-10 bg-gray-50 group-hover:bg-blue-50 rounded-full mx-auto flex items-center justify-center mb-2 transition-colors">
                    <svg class="w-5 h-5 text-gray-500 group-hover:text-primary" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                        </path>
                    </svg>
                </div>
                <span class="font-medium">Recruitment</span>
            </a>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-6 border-b border-gray-100">
            <h3 class="text-lg font-bold text-gray-900">Recent Activity</h3>
        </div>
        <div class="p-6">
            <div class="text-center text-gray-400 py-8">
                No recent activity
            </div>
        </div>
    </div>
</div>

<?php
// Adjust path for footer too
include_once '../../../includes/footer.php';
?>