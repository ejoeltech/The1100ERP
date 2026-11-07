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
                <?php echo htmlspecialchars(COMPANY_NAME, ENT_QUOTES, 'UTF-8'); ?>
            </p>
            <p class="mt-1">
                <?php echo htmlspecialchars(COMPANY_ADDRESS, ENT_QUOTES, 'UTF-8'); ?>
            </p>
            <p class="mt-1">
                Phone:
                <?php echo htmlspecialchars(COMPANY_PHONE, ENT_QUOTES, 'UTF-8'); ?> |
                Email:
                <?php echo htmlspecialchars(COMPANY_EMAIL, ENT_QUOTES, 'UTF-8'); ?> |
                <?php echo htmlspecialchars(COMPANY_WEBSITE, ENT_QUOTES, 'UTF-8'); ?>
            </p>
            <p class="mt-4 text-xs text-gray-500">
                ©
                <?php echo date('Y'); ?> <?php echo htmlspecialchars(COMPANY_NAME, ENT_QUOTES, 'UTF-8'); ?>. All rights reserved.
            </p>
        </div>
    </div>
</footer>

<script nonce="<?php echo function_exists('cspNonce') ? cspNonce() : ''; ?>">
    // Mobile menu toggle functionality
    function toggleMobileMenu() {
        const menu = document.getElementById('mobileMenu');
        const overlay = document.getElementById('mobileMenuOverlay');
        const hamburger = document.querySelector('.hamburger');
        if (!menu || !overlay) return;

        const isOpen = !menu.classList.contains('active');
        menu.classList.toggle('active', isOpen);
        overlay.classList.toggle('active', isOpen);
        menu.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        overlay.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        if ('inert' in menu) menu.inert = !isOpen;
        if (!isOpen) menu.setAttribute('inert', '');
        else menu.removeAttribute('inert');
        if (hamburger) {
            hamburger.classList.toggle('active', isOpen);
            hamburger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }

        // Prevent body scroll when menu is open
        if (isOpen) {
            document.body.style.overflow = 'hidden';
            const first = menu.querySelector('a, button');
            if (first) first.focus();
        } else {
            document.body.style.overflow = '';
            if (hamburger) hamburger.focus();
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
            mobileMenu.addEventListener('keydown', function (e) {
                if (e.key !== 'Tab') return;
                const focusables = Array.from(mobileMenu.querySelectorAll('a[href], button:not([disabled])'));
                if (!focusables.length) return;
                const first = focusables[0];
                const last = focusables[focusables.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
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