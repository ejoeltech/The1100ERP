<?php
require_once __DIR__ . '/../config.php';

$new_html = '
<div class="id-card">
    <!-- HEADER -->
    <div class="header">
        <div class="logo-graphic">
            {{company_logo}}
        </div>
        <div class="brand-name">{{company_name}}</div>
        <div class="brand-subtitle">{{subtitle}}</div>
    </div>

    <!-- CURVE DIVIDER -->
    <div class="top-curve"></div>

    <!-- PHOTO -->
    <div class="photo-container">
        <div class="photo-frame">
            <img src="{{photo_url}}" alt="Photo">
        </div>
    </div>

    <!-- NAME -->
    <div class="person-info">
        <div class="person-name">{{full_name}}</div>
        <div class="person-role">{{designation}}</div>
    </div>

    <!-- CONTACT -->
    <div class="contact-list">
        <div class="contact-item">
            <div class="icon-box green">
                <i class="fa-regular fa-id-card"></i>
            </div>
            <div class="contact-text">ID: {{employee_code}}</div>
        </div>

        <div class="contact-item">
            <div class="icon-box green">
                <i class="fa-solid fa-phone"></i>
            </div>
            <div class="contact-text">{{phone}}</div>
        </div>

        <div class="contact-item">
            <div class="icon-box blue">
                <i class="fa-regular fa-envelope"></i>
            </div>
            <div class="contact-text small">{{email}}</div>
        </div>
    </div>

    <!-- QR -->
    <div class="qr-box">
        {{qr_code}}
    </div>

    <!-- FOOTER WAVES -->
    <div class="wave-footer">
        <svg viewBox="0 0 350 180" preserveAspectRatio="none">
            <path d="M0,80 C100,60 200,120 350,60 L350,180 L0,180 Z"
                fill="{{color_secondary}}" opacity="0.9" />
            <path d="M0,100 C120,80 250,150 350,100 L350,180 L0,180 Z"
                fill="{{color_primary}}" opacity="0.85" />
            <path d="M0,130 C80,110 180,160 350,120 L350,180 L0,180 Z"
                fill="{{color_tertiary}}" opacity="0.6" />
        </svg>
    </div>
</div>';

try {
    $stmt = $pdo->prepare("REPLACE INTO hr_settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->execute(['id_card_front_html', $new_html]);
    echo "ID card template updated successfully.\n";
} catch (Exception $e) {
    echo "Error updating template: " . $e->getMessage() . "\n";
}
