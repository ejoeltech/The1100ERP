/**
 * Email Modal JavaScript
 * Handles email sending UI
 */

// Show email modal
function showEmailModal(documentType, documentId, customerEmail = '') {
    const modal = document.getElementById('emailModal');
    const form = document.getElementById('emailForm');

    // Set hidden fields
    document.getElementById('email_document_type').value = documentType;
    document.getElementById('email_document_id').value = documentId;

    // Pre-fill customer email if available
    if (customerEmail) {
        document.getElementById('recipient_email').value = customerEmail;
    }

    // Show modal
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

// Hide email modal
function hideEmailModal() {
    const modal = document.getElementById('emailModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');

    // Reset form
    document.getElementById('emailForm').reset();
}

// Handle email form submission
document.addEventListener('DOMContentLoaded', function () {
    const emailForm = document.getElementById('emailForm');

    if (emailForm) {
        emailForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;

            // Show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<svg class="animate-spin h-5 w-5 inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Sending...';

            // Get form data
            const formData = new FormData(this);

            // Send AJAX request
            fetch(window.AppConfig.basePath + '/api/send-email.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        showNotification('Email sent successfully!', 'success');
                        hideEmailModal();

                        // Reload page to show updated email history
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showNotification('Error: ' + data.message, 'error');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                })
                .catch(error => {
                    showNotification('Failed to send email. Please try again.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                });
        });
    }
});

// Show notification
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 px-6 py-4 rounded-lg shadow-lg z-50 ${type === 'success' ? 'bg-green-500' : 'bg-red-500'
        } text-white font-semibold`;
    notification.textContent = message;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Close modal on outside click
document.addEventListener('click', function (e) {
    const modal = document.getElementById('emailModal');
    if (e.target === modal) {
        hideEmailModal();
    }
});
