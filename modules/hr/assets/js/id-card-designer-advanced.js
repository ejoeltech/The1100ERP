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
            brand_x: 80, brand_y: 80, brand_size: 24, brand_color: '#1a1a1a', show_brand: 'block',
            photo_x: 100, photo_y: 130, photo_w: 150, photo_h: 150, photo_radius: 50, photo_border_w: 4, photo_border_color: '#0072bc',
            name_x: 75, name_y: 300, name_size: 22, name_color: '#1a1a1a', name_w: 200,
            role_x: 110, role_y: 335, role_size: 16, role_color: '#0072bc', show_role: 'block',
            code_x: 100, code_y: 380, show_code: 'block',
            qr_x: 260, qr_y: 460, show_qr: 'block',
            qr_template: '{{employee_code}} | {{full_name}}'
        };

        const defaultBackConfig = {
            bg_color_back: '#f9f9f9',
            wave_height_back: 120,
            wave_opacity_back: 0.7,
            back_title: 'THIS IS TO CERTIFY THAT',
            back_content: 'The bearer of this identification card is a duly registered member/staff of {{company_name}}. \n\nThis card is issued for identification purposes only and must be presented upon request. If found, please return to the address below. \n\nUnauthorized use, duplication, or possession of this card is strictly prohibited and may result in disciplinary or legal action.',
            btitle_x: 0, btitle_y: 35, btitle_w: 350, btitle_size: 11, btitle_color: '#333',
            btext_x: 0, btext_y: 70, btext_w: 350,
            sig_x: 35, sig_y: 260, sig_w: 280,
            business_address: '123 Business Street, Lagos, Nigeria\n+234 800 000 0000 | info@bluedots.com',
            addr_x: 35, addr_y: 430, addr_w: 200,
            show_qr_back: 'block',
            qr_x_back: 260, qr_y_back: 450, qr_size_back: 65
        };

        this.config = {
            front: { ...defaultSideConfig, ...config?.front },
            back: { ...defaultBackConfig, ...config?.back }
        };

        // Migration: If old default title is present, update to new one
        if (this.config.back.back_title === 'TERMS & CONDITIONS') {
            this.config.back.back_title = defaultBackConfig.back_title;
            this.config.back.back_content = defaultBackConfig.back_content;
            this.config.back.btitle_y = defaultBackConfig.btitle_y;
            this.config.back.btext_y = defaultBackConfig.btext_y;
            this.config.back.sig_y = defaultBackConfig.sig_y;
            this.config.back.addr_y = defaultBackConfig.addr_y;
        }

        this.mockData = {
            company_name: window.AppMock?.company_name || 'BLUEDOTS TECH',
            company_logo: window.AppMock?.company_logo || '',
            full_name: 'JOHNSON O. DOE',
            designation: 'CHIEF TECHNOLOGY OFFICER',
            employee_code: 'ID: EMP/2026/0042',
            phone: '+234 800 123 4567',
            email: 'johnson.doe@bluedots.com',
            photo_url: 'https://ui-avatars.com/api/?name=John+Doe&size=200&background=ccc&color=fff',
            qr_code: '<div style="width:70px; height:70px; background:#ddd; display:flex; align-items:center; justify-content:center; font-size:10px;">QR DATA</div>',
            signature: 'Authorized Sig.',
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
                const tab = e.target.closest('[data-tab]')?.dataset.tab;
                const side = (tab === 'front' || tab === 'back') ? tab : this.activeSide;
                const key = e.target.name;
                const val = e.target.type === 'checkbox' ? (e.target.checked ? 'block' : 'none') : e.target.value;
                this.updateConfig(side, key, val);

                // If primary color changed, also update related colors if not custom
                if (key === 'primary_color') {
                    this.updateConfig('front', 'photo_border_color', val);
                    this.updateConfig('front', 'role_color', val);
                }
            }

            // Live CSS update
            if (e.target.name === 'id_card_custom_css') {
                const styleTag = document.getElementById('customStyles');
                if (styleTag) styleTag.innerHTML = e.target.value;
            }
        });

        // Reset button click
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('center-btn')) {
                const key = e.target.dataset.center;
                this.centerElement(key);
            }
            if (e.target.id === 'resetBackBtn') {
                if (confirm('Reset the back layout to professional defaults?')) {
                    this.resetBack();
                }
            }
        });

        // Sidebar tab toggle
        document.querySelectorAll('.tab-toggle').forEach(btn => {
            btn.addEventListener('click', () => {
                const tab = btn.dataset.tab;
                if (tab === 'front' || tab === 'back') this.activeSide = tab;
                
                // UI Toggle
                document.querySelectorAll('.tab-toggle').forEach(b => b.classList.remove('bg-indigo-600', 'text-white'));
                btn.classList.add('bg-indigo-600', 'text-white');
                
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.style.display = content.dataset.tab === tab ? 'block' : 'none';
                });

                this.syncSidebar();
            });
        });
    }

    centerElement(key) {
        // Map control key to element selector
        const selectorMap = {
            logo_x: '.comp-logo',
            photo_x: '.comp-photo-frame',
            name_x: '.comp-name',
            brand_x: '.brand-name',
            role_x: '.comp-role',
            code_x: '.comp-contact-row',
            addr_x: '.business-address',
            btitle_x: '.brand-name', // Back title shares class
            btext_x: '.back-content',
            qr_x_back: '.comp-qr-back'
        };

        const selector = selectorMap[key];
        if (!selector) return;

        const previewId = this.activeSide === 'front' ? 'designerPreview' : 'designerBackPreview';
        const el = document.querySelector(`#${previewId} ${selector}`);
        if (!el) return;

        const width = el.offsetWidth;
        const centerX = Math.round((350 - width) / 2);
        
        this.updateConfig(this.activeSide, key, centerX);
        this.syncSidebar();
    }

    syncSidebar() {
        // Set current values in inputs for front/back
        const currentConfig = this.config[this.activeSide];
        for (const [key, val] of Object.entries(currentConfig)) {
            const input = document.querySelector(`[name="${key}"]`);
            if (input) {
                if (input.type === 'checkbox') input.checked = val === 'block';
                else input.value = val;
            }
        }
    }

    resetBack() {
        const defaultBackConfig = {
            bg_color_back: '#f9f9f9',
            wave_height_back: 120,
            wave_opacity_back: 0.7,
            back_title: 'THIS IS TO CERTIFY THAT',
            back_content: 'The bearer of this identification card is a duly registered member/staff of {{company_name}}. \n\nThis card is issued for identification purposes only and must be presented upon request. If found, please return to the address below. \n\nUnauthorized use, duplication, or possession of this card is strictly prohibited and may result in disciplinary or legal action.',
            btitle_x: 0, btitle_y: 35, btitle_w: 350, btitle_size: 11, btitle_color: '#333',
            btext_x: 0, btext_y: 70, btext_w: 350,
            sig_x: 35, sig_y: 260, sig_w: 280,
            business_address: '123 Business Street, Lagos, Nigeria\n+234 800 000 0000 | info@bluedots.com',
            addr_x: 35, addr_y: 430, addr_w: 200,
            show_qr_back: 'block',
            qr_x_back: 260, qr_y_back: 450, qr_size_back: 65
        };
        this.config.back = { ...defaultBackConfig };
        this.syncSidebar();
        this.render();
    }

    updateConfig(side, key, val) {
        if (!this.config[side]) return;
        this.config[side][key] = val;
        this.render();
    }

    render() {
        // Prepare template data with color mappings
        const baseData = {
            ...this.mockData,
            color_primary: this.config.front.primary_color,
            color_secondary: this.config.front.secondary_color
        };

        // Render Front
        const frontCanvas = document.getElementById('designerPreview');
        if (frontCanvas) {
            let html = document.getElementById('idFrontTemplate').innerHTML;
            const data = { ...baseData, ...this.config.front };
            
            // Real QR Logic
            const qrText = this.applyPlaceholders(this.config.front.qr_template || '{{employee_code}}', data);
            data.qr_code = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(qrText)}" class="qr-preview" style="width:100%; height:100%;">`;
            
            frontCanvas.innerHTML = this.applyPlaceholders(html, data);
        }

        // Render Back
        const backCanvas = document.getElementById('designerBackPreview');
        if (backCanvas) {
            let html = document.getElementById('idBackTemplate').innerHTML;
            const data = { ...baseData, ...this.config.back, ...this.config.front }; // Back inherits colors
            
            // Back QR & Data Logic
            const qrTextBack = this.applyPlaceholders(this.config.front.qr_template || '{{employee_code}}', data);
            const qrSize = this.config.back.qr_size_back || 65;
            data.qr_placeholder_back = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=${qrSize}x${qrSize}&data=${encodeURIComponent(qrTextBack)}" class="qr-preview" style="width:100%; height:100%; display:${this.config.back.show_qr_back || 'block'}">`;
            
            // Explicitly ensure back-specific text placeholders are in the data object
            data.business_address = this.config.back.business_address;
            data.back_title = this.config.back.back_title;
            data.back_content = this.config.back.back_content;
            
            backCanvas.innerHTML = this.applyPlaceholders(html, data);
        }

        // Global CSS Rules (colors)
        document.documentElement.style.setProperty('--primary-color', this.config.front.primary_color);
        document.documentElement.style.setProperty('--secondary-color', this.config.front.secondary_color);

        // Update hidden inputs
        const hiddenConfig = document.getElementById('hiddenConfig');
        if (hiddenConfig) hiddenConfig.value = JSON.stringify(this.config);
    }

    applyPlaceholders(str, data) {
        if (!str) return '';
        return str.replace(/{{(\w+)}}/g, (match, key) => {
            return typeof data[key] !== 'undefined' ? data[key] : match;
        });
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    window.designer = new IDCardAdvancedDesigner(window.SavedConfig || {});
});
