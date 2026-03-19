/**
 * ID Card Designer - Advanced Logic
 * Handles absolute positioning, independent front/back states, and live preview.
 */

class IDCardAdvancedDesigner {
    constructor(config) {
        this.activeSide = 'front';
        
        const defaultSideConfig = {
            bg_color: '#ffffff',
            primary_color: '#0072bc',
            secondary_color: '#39b54a',
            wave_height: 150,
            wave_opacity: 0.9,
            logo_x: 100, logo_y: 20, logo_width: 80, logo_height: 50,
            brand_x: 80, brand_y: 80, brand_size: 24, brand_color: '#1a1a1a',
            photo_x: 100, photo_y: 130, photo_w: 150, photo_h: 150, photo_radius: 50, photo_border_w: 4, photo_border_color: '#0072bc',
            name_x: 75, name_y: 300, name_size: 22, name_color: '#1a1a1a',
            role_x: 110, role_y: 335, role_size: 16, role_color: '#0072bc',
            code_x: 100, code_y: 380,
            qr_x: 260, qr_y: 460, show_qr: 'block'
        };

        const defaultBackConfig = {
            bg_color_back: '#f9f9f9',
            wave_height_back: 120,
            wave_opacity_back: 0.7,
            btitle_x: 75, btitle_y: 50, btitle_size: 18, btitle_color: '#333',
            btext_x: 25, btext_y: 90,
            em_x: 25, em_y: 220,
            sig_x: 100, sig_y: 350
        };

        this.config = {
            front: { ...defaultSideConfig, ...config?.front },
            back: { ...defaultBackConfig, ...config?.back }
        };

        this.mockData = {
            company_name: window.AppMock?.company_name || 'BLUEDOTS TECH',
            company_logo: window.AppMock?.company_logo || '',
            full_name: 'JOHNSON O. DOE',
            designation: 'CHIEF TECHNOLOGY OFFICER',
            employee_code: 'EMP/2026/0042',
            phone: '+234 802 999 0000',
            email: 'john.doe@bluedots.com',
            photo_url: 'https://ui-avatars.com/api/?name=John+Doe&size=200&background=ccc&color=fff',
            qr_code: '<div style="width:70px; height:70px; background:#ddd; display:flex; align-items:center; justify-content:center; font-size:10px;">QR DATA</div>',
            signature: '<div style="font-family:cursive; font-size:24px;">J.Doe</div>',
            emergency_label: 'EMERGENCY CONTACT',
            emergency_contact: '+234 803 111 2222',
            disclaimer: 'This card is the property of Bluedots Technologies. If found, please return to any of our offices or contact HR at info@bluedots.com.',
            company_website: 'www.bluedotstech.com',
            ...window.AppMock
        };

        this.init();
    }

    init() {
        this.bindEvents();
        this.render();
        this.syncSidebar();
    }

    bindEvents() {
        // Control changes
        document.addEventListener('input', (e) => {
            if (e.target.classList.contains('design-control')) {
                const side = e.target.closest('[data-side]')?.dataset.side || this.activeSide;
                const key = e.target.name;
                const val = e.target.type === 'checkbox' ? (e.target.checked ? 'block' : 'none') : e.target.value;
                this.updateConfig(side, key, val);
            }
        });

        // Sidebar side toggle
        document.querySelectorAll('.side-toggle').forEach(btn => {
            btn.addEventListener('click', () => {
                this.activeSide = btn.dataset.side;
                this.syncSidebar();
                document.querySelectorAll('.side-toggle').forEach(b => b.classList.remove('bg-indigo-600', 'text-white'));
                btn.classList.add('bg-indigo-600', 'text-white');
            });
        });
    }

    syncSidebar() {
        // Toggle control groups visibility
        document.querySelectorAll('.control-group').forEach(group => {
            group.style.display = group.dataset.side === this.activeSide ? 'block' : 'none';
        });

        // Set current values in inputs
        const currentConfig = this.config[this.activeSide];
        for (const [key, val] of Object.entries(currentConfig)) {
            const input = document.querySelector(`[name="${key}"]`);
            if (input) {
                if (input.type === 'checkbox') input.checked = val === 'block';
                else input.value = val;
            }
        }
    }

    updateConfig(side, key, val) {
        this.config[side][key] = val;
        this.render();
    }

    render() {
        // Render Front
        const frontCanvas = document.getElementById('designerPreview');
        if (frontCanvas) {
            let html = document.getElementById('idFrontTemplate').innerHTML;
            const data = { ...this.mockData, ...this.config.front };
            frontCanvas.innerHTML = this.applyPlaceholders(html, data);
        }

        // Render Back
        const backCanvas = document.getElementById('designerBackPreview');
        if (backCanvas) {
            let html = document.getElementById('idBackTemplate').innerHTML;
            const data = { ...this.mockData, ...this.config.back, ...this.config.front }; // Back inherits colors
            backCanvas.innerHTML = this.applyPlaceholders(html, data);
        }

        // Global CSS Rules (colors)
        document.documentElement.style.setProperty('--primary-color', this.config.front.primary_color);
        document.documentElement.style.setProperty('--secondary-color', this.config.front.secondary_color);

        // Update hidden inputs
        document.getElementById('hiddenConfig').value = JSON.stringify(this.config);
    }

    applyPlaceholders(str, data) {
        return str.replace(/{{(\w+)}}/g, (match, key) => {
            return typeof data[key] !== 'undefined' ? data[key] : match;
        });
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    window.designer = new IDCardAdvancedDesigner(window.SavedConfig || {});
});
