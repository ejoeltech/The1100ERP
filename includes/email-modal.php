<!-- Email Modal -->
<div id="emailModal"
    class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 items-center justify-center">
    <div class="relative mx-auto p-8 border w-full max-w-md shadow-lg rounded-lg bg-white">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-2xl font-bold text-gray-900">Send Email</h3>
            <button onclick="hideEmailModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>

        <form id="emailForm">
            <input type="hidden" id="email_document_type" name="document_type">
            <input type="hidden" id="email_document_id" name="document_id">

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Recipient Email <span class="text-red-500">*</span>
                    </label>
                    <input type="email" id="recipient_email" name="recipient_email" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                        placeholder="customer@example.com">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Recipient Name (Optional)
                    </label>
                    <input type="text" name="recipient_name"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                        placeholder="John Doe">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Custom Message (Optional)
                    </label>
                    <textarea name="custom_message" rows="3"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                        placeholder="Add a custom message to the email..."></textarea>
                </div>

                <div class="bg-blue-50 border-l-4 border-blue-400 p-3">
                    <p class="text-sm text-blue-800">
                        📎 A PDF copy will be attached to the email automatically.
                    </p>
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="button" onclick="hideEmailModal()"
                    class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold">
                    Cancel
                </button>
                <button type="submit"
                    class="flex-1 px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">
                    Send Email
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Include email modal script -->
<script src="<?php echo isset($base_path) ? $base_path : '.'; ?>/assets/js/email-modal.js"></script>