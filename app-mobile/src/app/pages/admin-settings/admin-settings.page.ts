import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { ToastController } from '@ionic/angular';
import { ApiService } from '../../services/api.service';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';

@Component({
  selector: 'app-admin-settings',
  templateUrl: './admin-settings.page.html',
  styleUrls: ['./admin-settings.page.scss'],
})
export class AdminSettingsPage implements OnInit, OnDestroy {
  businessType = 'gym';
  businessTypes = [
    { value: 'gym', label: 'Palestra / Centro Fitness' },
    { value: 'salon', label: 'Salone di Bellezza' },
    { value: 'studio', label: 'Studio' },
    { value: 'other', label: 'Altro' },
  ];
  saving = false;
  private destroy$ = new Subject<void>();

  constructor(
    private apiService: ApiService,
    private toastController: ToastController,
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
    this.saving = true;
    this.apiService.saveSettings({ default_business_type: this.businessType })
      .pipe(takeUntil(this.destroy$)).subscribe({
        next: async () => {
          this.saving = false;
          const toast = await this.toastController.create({ message: 'Impostazioni salvate', duration: 2000, color: 'success', position: 'bottom' });
          await toast.present();
        },
        error: async () => {
          this.saving = false;
          const toast = await this.toastController.create({ message: 'Errore salvataggio', duration: 2000, color: 'danger', position: 'bottom' });
          await toast.present();
        },
      });
  }
}
