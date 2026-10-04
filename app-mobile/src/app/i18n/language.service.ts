import { Injectable } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { translations } from './translations';

export type AppLanguage = 'it' | 'en';

@Injectable({ providedIn: 'root' })
export class LanguageService {
  private readonly storageKey = 'businessregistry-language';
  private current: AppLanguage = 'it';

  constructor(private translate: TranslateService) {
    this.translate.setDefaultLang('it');
    this.translate.setTranslation('it', translations.it);
    this.translate.setTranslation('en', translations.en);
    const saved = localStorage.getItem(this.storageKey);
    this.setLanguage(saved === 'en' ? 'en' : 'it');
  }

  get currentLanguage(): AppLanguage {
    return this.current;
  }

  instant(key: string, params?: Record<string, unknown>): string {
    return this.translate.instant(key, params);
  }

  setLanguage(language: string): void {
    this.current = language === 'en' ? 'en' : 'it';
    localStorage.setItem(this.storageKey, this.current);
    this.translate.use(this.current);
  }
}