<?php
function getBusinessTypeLabel(string $type): string {
    switch (strtolower($type)) {
        case 'pilates': return 'Pilates Studio';
        case 'yoga': return 'Yoga Studio';
        case 'wellness': return 'Wellness Center';
        case 'studio': return 'Studio';
        case 'medical': return 'Medical Office';
        case 'real_estate': return 'Real Estate Agency';
        case 'gym':
        default:
            return 'Gym';
    }
}

function getBusinessTypePluralLabel(string $type): string {
    switch (strtolower($type)) {
        case 'pilates': return 'Pilates Studios';
        case 'yoga': return 'Yoga Studios';
        case 'wellness': return 'Wellness Centers';
        case 'studio': return 'Studios';
        case 'medical': return 'Medical Offices';
        case 'real_estate': return 'Real Estate Agencies';
        case 'gym':
        default:
            return 'Gyms';
    }
}

function getSupportedBusinessTypes(): array {
    return [
        'gym' => 'Gym',
        'pilates' => 'Pilates Studio',
        'yoga' => 'Yoga Studio',
        'wellness' => 'Wellness Center',
        'studio' => 'Studio',
        'medical' => 'Medical Office',
        'real_estate' => 'Real Estate Agency'
    ];
}

function getPersonLabel(string $type): string {
    switch (strtolower($type)) {
        case 'medical': return 'Patient';
        case 'real_estate': return 'Client';
        case 'pilates':
        case 'yoga':
        case 'wellness':
        case 'studio':
            return 'Member';
        case 'gym':
        default:
            return 'Member';
    }
}

function getPersonPluralLabel(string $type): string {
    return getPersonLabel($type) . 's';
}

function getLocationEntityLabel(string $type): string {
    return getBusinessTypeLabel($type);
}

function getLocationEntityPluralLabel(string $type): string {
    return getBusinessTypePluralLabel($type);
}

function getServiceLabel(string $category): string {
    switch (strtolower($category)) {
        case 'appointment': return 'Appointment';
        case 'wellness': return 'Wellness Service';
        case 'personal': return 'Personal Service';
        case 'event': return 'Event';
        case 'class':
        default:
            return 'Class';
    }
}
