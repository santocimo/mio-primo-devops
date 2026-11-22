<?php
/**
 * Terminology Service
 * 
 * Manages gender-aware and category-aware terminology for different activity types.
 * Provides proper labels for contacts/members based on the gym category.
 */

namespace App\Service;

class TerminologyService {
    
    /**
     * Get the contact/member label for a specific gym category
     * 
     * @param string $category The gym category (gym, studio, salon, medical, other)
     * @return array{singular: string, plural: string, plural_formal: string}
     */
    public static function getContactLabel(string $category): array {
        $category = strtolower(trim($category ?? 'other'));
        
        return match($category) {
            'gym' => [
                'singular' => 'Iscritto',
                'plural' => 'Iscritti',
                'plural_formal' => 'gli iscritti'
            ],
            'studio' => [
                'singular' => 'Iscritto',
                'plural' => 'Iscritti',
                'plural_formal' => 'gli iscritti'
            ],
            'salon' => [
                'singular' => 'Cliente',
                'plural' => 'Clienti',
                'plural_formal' => 'i clienti'
            ],
            'medical' => [
                'singular' => 'Paziente',
                'plural' => 'Pazienti',
                'plural_formal' => 'i pazienti'
            ],
            default => [
                'singular' => 'Contatto',
                'plural' => 'Contatti',
                'plural_formal' => 'i contatti'
            ]
        };
    }
    
    /**
     * Get the contact label key for i18n (translations)
     * Used to select the correct translation key
     * 
     * @param string $category The gym category
     * @return string The base key (e.g., 'member', 'client', 'patient')
     */
    public static function getContactLabelKey(string $category): string {
        $category = strtolower(trim($category ?? 'other'));
        
        return match($category) {
            'gym', 'studio' => 'member',
            'salon' => 'client',
            'medical' => 'patient',
            default => 'contact'
        };
    }
    
    /**
     * Get navigation menu label for contacts
     * 
     * @param string $category The gym category
     * @return string The navigation label
     */
    public static function getNavigationLabel(string $category): string {
        $labels = self::getContactLabel($category);
        return $labels['plural'];
    }
    
    /**
     * Check if a category uses "members" terminology (Iscritti)
     * 
     * @param string $category The gym category
     * @return bool
     */
    public static function isMemberCategory(string $category): bool {
        $category = strtolower(trim($category ?? 'other'));
        return in_array($category, ['gym', 'studio'], true);
    }
    
    /**
     * Check if a category uses "client" terminology
     * 
     * @param string $category The gym category
     * @return bool
     */
    public static function isClientCategory(string $category): bool {
        $category = strtolower(trim($category ?? 'other'));
        return $category === 'salon';
    }
    
    /**
     * Check if a category uses "patient" terminology
     * 
     * @param string $category The gym category
     * @return bool
     */
    public static function isPatientCategory(string $category): bool {
        $category = strtolower(trim($category ?? 'other'));
        return $category === 'medical';
    }
    
    /**
     * Get all supported categories with their labels
     * 
     * @return array<string, array{singular: string, plural: string}>
     */
    public static function getAllCategories(): array {
        return [
            'gym' => self::getContactLabel('gym'),
            'studio' => self::getContactLabel('studio'),
            'salon' => self::getContactLabel('salon'),
            'medical' => self::getContactLabel('medical'),
            'other' => self::getContactLabel('other'),
        ];
    }
}
