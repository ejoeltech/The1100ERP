/**
 * ID Card Designer - Structured Logic
 * Handles state, live preview, and export to database format.
 */

class IDCardDesigner {
    constructor(config) {
        this.config = {
            header_layout: 'vertical',
            logo_width: 60,
            logo_height: 60,
            logo_text_size: 24,
            logo_text_color: '#1a1a1a',
            subtitle_size: 10,
            primary_color: '#0072bc',
            secondary_color: '#39b54a',
            name_size: 22,
            role_size: 16,
            photo_radius: 50,
            photo_border_width: 4,
            photo_border_color: '#0072bc',
            show_qr: 'block',
            subtitle: 'TECHNOLOGIES',
            wave_opacity: 0.9,
            ...config
        };
        
        this.mockData = {
            company_name: window.AppMock?.company_name || 'COMPANY NAME',
            company_logo: window.AppMock?.company_logo || '',
            full_name: 'John Doe',
            designation: 'Software Engineer',
            employee_code: 'EMP-001',
            phone: '+234 800 123 4567',
            email: 'john@example.com',
            photo_url: 'https://ui-avatars.com/api/?name=John+Doe&size=200&background=ccc&color=fff',
            qr_code: '<div style="width:70px; height:70px; background:#ddd;">QR</div>',
            signature: '<div style="font-family:cursive;">JohnDoe</div>',
            ...window.AppMock
        };

        this.init();
    }

    init() {
        this.bindEvents();
        this.render();
    }

    bindEvents() {
        document.querySelectorAll('.design-control').forEach(el => {
            el.addEventListener('input', (e) => {
                const key = e.target.name;
                const val = e.target.type === 'checkbox' ? (e.target.checked ? 'block' : 'none') : e.target.value;
                this.updateConfig(key, val);
            });
        });
    }

    updateConfig(key, val) {
        this.config[key] = val;
        this.render();
    }

    render() {
        const preview = document.getElementById('designerPreview');
        if (!preview) return;

        // Apply global styles
        document.documentElement.style.setProperty('--primary-color', this.config.primary_color);
        document.documentElement.style.setProperty('--secondary-color', this.config.secondary_color);

        // Render Front
        let frontHtml = document.getElementById('idFrontTemplate').innerHTML;
        const allData = { 
            ...this.mockData, 
            ...this.config,
            color_primary: this.config.primary_color,
            color_secondary: this.config.secondary_color,
            photo_border_color: this.config.photo_border_color || this.config.primary_color 
        };
        const frontFinal = this.applyPlaceholders(frontHtml, allData);
        document.getElementById('designerPreview').innerHTML = frontFinal;

        // Render Back
        const backPreview = document.getElementById('designerBackPreview');
        if (backPreview) {
            let backHtml = document.getElementById('idBackTemplate').innerHTML;
            backPreview.innerHTML = this.applyPlaceholders(backHtml, allData);
        }
        
        // Update hidden inputs for saving
        document.getElementById('hiddenFrontHtml').value = frontFinal;
        document.getElementById('hiddenConfig').value = JSON.stringify(this.config);
    }

    applyPlaceholders(str, data) {
        return str.replace(/{{(\w+)}}/g, (match, key) => {
            return typeof data[key] !== 'undefined' ? data[key] : match;
        });
    }
}

// Initialize on load
document.addEventListener('DOMContentLoaded', () => {
    window.designer = new IDCardDesigner(window.SavedConfig || {});
});
