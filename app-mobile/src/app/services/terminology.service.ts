import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { catchError, shareReplay } from 'rxjs/operators';

export interface ContactLabel {
  singular: string;
  plural: string;
  plural_formal: string;
}

export interface TerminologyResponse {
  success: boolean;
  category: string;
  labels: ContactLabel;
  label_key: string;
  is_member_category: boolean;
  is_client_category: boolean;
  is_patient_category: boolean;
}

@Injectable({
  providedIn: 'root'
})
export class TerminologyService {
  private cache = new Map<string, Observable<TerminologyResponse>>();
  private apiUrl = '/api/terminology.php';

  constructor(private http: HttpClient) {}

  /**
   * Get terminology labels for a specific category
   * Results are cached to avoid repeated API calls
   */
  getTerminology(category: string): Observable<TerminologyResponse> {
    if (!category) {
      return of(this.getDefaultTerminology('other'));
    }

    if (!this.cache.has(category)) {
      const request$ = this.http.get<TerminologyResponse>(
        `${this.apiUrl}/${category}`
      ).pipe(
        catchError(() => of(this.getDefaultTerminology(category))),
        shareReplay(1)
      );
      this.cache.set(category, request$);
    }

    return this.cache.get(category)!;
  }

  /**
   * Get local terminology labels without API call
   * Used for initial rendering while API data loads
   */
  getLocalTerminology(category: string): ContactLabel {
    const map: { [key: string]: ContactLabel } = {
      'gym': {
        singular: 'Iscritto',
        plural: 'Iscritti',
        plural_formal: 'gli iscritti'
      },
      'studio': {
        singular: 'Iscritto',
        plural: 'Iscritti',
        plural_formal: 'gli iscritti'
      },
      'salon': {
        singular: 'Cliente',
        plural: 'Clienti',
        plural_formal: 'i clienti'
      },
      'medical': {
        singular: 'Paziente',
        plural: 'Pazienti',
        plural_formal: 'i pazienti'
      },
      'other': {
        singular: 'Contatto',
        plural: 'Contatti',
        plural_formal: 'i contatti'
      }
    };

    return map[category] || map['other'];
  }

  /**
   * Get translation key for a category and action
   * Used to select the correct i18n key with suffix
   * Examples: contacts.title, contacts.titleClient, contacts.titlePatient, contacts.titleContact
   *           nav.members, nav.clients, nav.patients, nav.contacts
   */
  getTranslationKey(category: string | null | undefined, prefix: string = 'contacts', action: string = ''): string {
    const normalizedCategory = (category || '').toLowerCase();
    const suffix = this.getSuffix(normalizedCategory);
    
    if (prefix === 'nav') {
      // For nav, we use plurals: nav.members, nav.clients, nav.patients, nav.contacts
      const navMap: { [key: string]: string } = {
        'gym': 'nav.members',
        'studio': 'nav.members',
        'salon': 'nav.clients',
        'medical': 'nav.patients',
        'other': 'nav.contacts'
      };
      return navMap[normalizedCategory] || navMap['other'];
    }
    
    // For contacts prefix, append action and suffix
    return action ? `${prefix}.${action}${suffix}` : `${prefix}${suffix}`;
  }

  /**
   * Get the suffix for translation keys based on category
   * '' for members (default), 'Client', 'Patient', 'Contact'
   */
  private getSuffix(normalizedCategory: string): string {
    if (normalizedCategory === 'salon') {
      return 'Client';
    }
    if (normalizedCategory === 'medical') {
      return 'Patient';
    }
    if (normalizedCategory === 'other' || !normalizedCategory) {
      return 'Contact';
    }
    // gym, studio - no suffix (default members)
    return '';
  }

  /**
   * Check if category uses member terminology
   */
  isMemberCategory(category: string): boolean {
    return category === 'gym' || category === 'studio';
  }

  /**
   * Check if category uses client terminology
   */
  isClientCategory(category: string): boolean {
    return category === 'salon';
  }

  /**
   * Check if category uses patient terminology
   */
  isPatientCategory(category: string): boolean {
    return category === 'medical';
  }

  private getDefaultTerminology(category: string): TerminologyResponse {
    const labels = this.getLocalTerminology(category);
    const labelKey = this.getTranslationKey(category);

    return {
      success: true,
      category,
      labels,
      label_key: labelKey,
      is_member_category: this.isMemberCategory(category),
      is_client_category: this.isClientCategory(category),
      is_patient_category: this.isPatientCategory(category)
    };
  }

  clearCache(): void {
    this.cache.clear();
  }
}
