<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
?>
</main>

<!-- Footer -->
<footer class="bg-white border-t border-gray-200 mt-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="text-center text-sm text-gray-600">
            <p class="font-semibold">
                <?php echo COMPANY_NAME; ?>
            </p>
            <p class="mt-1">
                <?php echo COMPANY_ADDRESS; ?>
            </p>
            <p class="mt-1">
                Phone:
                <?php echo COMPANY_PHONE; ?> |
                Email:
                <?php echo COMPANY_EMAIL; ?> |
                <?php echo COMPANY_WEBSITE; ?>
            </p>
            <p class="mt-4 text-xs text-gray-500">
                ©
                <?php echo date('Y'); ?> <?php echo COMPANY_NAME; ?>. All rights reserved.
            </p>
        </div>
    </div>
</footer>

<script>
    // Mobile menu toggle functionality
    function toggleMobileMenu() {
        const menu = document.getElementById('mobileMenu');
        const overlay = document.getElementById('mobileMenuOverlay');
        const hamburger = document.querySelector('.hamburger');

        menu.classList.toggle('active');
        overlay.classList.toggle('active');
        if (hamburger) {
            hamburger.classList.toggle('active');
        }

        // Prevent body scroll when menu is open
        if (menu.classList.contains('active')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }

    // Close mobile menu when clicking a link
    document.addEventListener('DOMContentLoaded', function () {
        const mobileMenu = document.getElementById('mobileMenu');
        if (mobileMenu) {
            const links = mobileMenu.querySelectorAll('a');
            links.forEach(link => {
                link.addEventListener('click', function () {
                    toggleMobileMenu();
                });
            });
        }
    });

    // Close menu on escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const menu = document.getElementById('mobileMenu');
            if (menu && menu.classList.contains('active')) {
                toggleMobileMenu();
            }
        }
    });
</script>

</body>


</html>