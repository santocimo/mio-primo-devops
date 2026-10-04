import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { ToastController } from '@ionic/angular';
import { ApiService } from '../../services/api.service';
import { AuthService } from '../../services/auth.service';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { LanguageService } from '../../i18n/language.service';

@Component({
  selector: 'app-admin-settings',
  templateUrl: './admin-settings.page.html',
  styleUrls: ['./admin-settings.page.scss'],
})
export class AdminSettingsPage implements OnInit, OnDestroy {
  businessType = 'gym';
  businessTypes = [
    { value: 'gym', label: 'gym.category.gym' },
    { value: 'salon', label: 'gym.category.salon' },
    { value: 'studio', label: 'gym.category.studio' },
    { value: 'other', label: 'gym.category.other' },
  ];
  saving = false;
  private destroy$ = new Subject<void>();

  constructor(
    private apiService: ApiService,
    private authService: AuthService,
    private toastController: ToastController,
    private language: LanguageService,
    public router: Router
  ) {}

  ngOnInit(): void {
    this.apiService.getSettings().pipe(takeUntil(this.destroy$)).subscribe({
      next: s => { this.businessType = s.default_business_type ?? 'gym'; },
      error: () => {},
    });
  }

  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  async save(): Promise<void> {
    const role = this.authService.getCurrentUser()?.role ?? '';
    const normalizedRole = String(role).toUpperCase();
    if (!normalizedRole.includes('ADMIN') && !normalizedRole.includes('SUPER')) {
      const toast = await this.toastController.create({ message: this.language.instant('adminSettings.adminOnly'), duration: 2200, color: 'warning', position: 'bottom' });
      await toast.present();
      return;
    }

    this.saving = true;
    this.apiService.saveSettings({ default_business_type: this.businessType })
      .pipe(takeUntil(this.destroy$)).subscribe({
        next: async () => {
          this.saving = false;
          window.dispatchEvent(new Event('business-type-changed'));
          const toast = await this.toastController.create({ message: this.language.instant('adminSettings.saved'), duration: 2000, color: 'success', position: 'bottom' });
          await toast.present();
        },
        error: async (err) => {
          this.saving = false;
          const serverMessage = err?.error?.message || err?.error?.error || '';
          const msg = serverMessage || this.language.instant('adminSettings.saveError');
          const toast = await this.toastController.create({ message: msg, duration: 2600, color: 'danger', position: 'bottom' });
          await toast.present();
        },
      });
  }
}
