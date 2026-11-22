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
   * Get translation key for a category
   * Used to select the correct i18n key
   */
  getTranslationKey(category: string): string {
    const keyMap: { [key: string]: string } = {
      'gym': 'member',
      'studio': 'member',
      'salon': 'client',
      'medical': 'patient',
      'other': 'contact'
    };

    return keyMap[category] || 'contact';
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
