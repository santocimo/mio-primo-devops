import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { AlertController } from '@ionic/angular';
import { ApiService } from '../../services/api.service';
import { AuthService } from '../../services/auth.service';
import { Gym } from '../../models/business.model';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { LanguageService } from '../../i18n/language.service';

export interface ServiceItem {
  id: number;
  name: string;
  slug: string;
  gym_id: number;
  gym_name: string;
  category: string;
  provider_name?: string | null;
  provider_type?: 'internal' | 'external' | null;
  duration_minutes: number;
  capacity: number;
  price: number | null;
  description: string;
}

@Component({
  selector: 'app-admin-services',
  templateUrl: './admin-services.page.html',
  styleUrls: ['./admin-services.page.scss'],
})
export class AdminServicesPage implements OnInit, OnDestroy {
  readonly serviceCategories = [
    { value: 'appointment', label: 'services.category.appointment' },
    { value: 'course', label: 'services.category.course' },
  ];
  readonly providerTypes = [
    { value: 'internal', label: 'services.internal' },
    { value: 'external', label: 'services.external' },
  ];
  services: ServiceItem[] = [];
  gyms: Gym[] = [];
  selectedGymId: number | null = null;
  loading = true;
  showForm = false;
  editingService: ServiceItem | null = null;
  formData = this.emptyForm();
  private destroy$ = new Subject<void>();

  constructor(
    private apiService: ApiService,
    private authService: AuthService,
    private alertController: AlertController,
    private language: LanguageService,
    public router: Router
  ) {}

  ngOnInit(): void {
    this.selectedGymId = this.authService.getSelectedGymId();
    this.authService.getSelectedGymIdStream().pipe(takeUntil(this.destroy$)).subscribe(gymId => {
      this.selectedGymId = gymId;
      this.load();
    });
    this.load();
    this.apiService.getGyms().pipe(takeUntil(this.destroy$)).subscribe({ next: g => (this.gyms = g), error: () => {} });
  }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    if (!this.selectedGymId) {
      this.services = [];
      this.loading = false;
      return;
    }

    this.loading = true;
    this.apiService.getAllServices(this.selectedGymId).pipe(takeUntil(this.destroy$)).subscribe({
      next: s => { this.services = s; this.loading = false; },
      error: () => { this.loading = false; },
    });
  }

  openAdd(): void {
    this.editingService = null;
    this.formData = this.emptyForm();
    if (this.selectedGymId) {
      this.formData.gym_id = this.selectedGymId;
    }
    this.showForm = true;
  }

  openEdit(s: ServiceItem): void {
    this.editingService = s;
    this.formData = {
      name: s.name,
      slug: s.slug,
      gym_id: s.gym_id,
      category: s.category,
      provider_name: s.provider_name ?? '',
      provider_type: s.provider_type ?? 'internal',
      duration_minutes: s.duration_minutes,
      capacity: s.capacity,
      price: s.price,
      description: s.description,
    };
    this.showForm = true;
  }

  closeForm(): void { this.showForm = false; this.editingService = null; }

  save(): void {
    if (!this.formData.name || !this.formData.gym_id) return;
    const call = this.editingService
      ? this.apiService.updateService(this.editingService.id, this.formData)
      : this.apiService.createService(this.formData);
    call.pipe(takeUntil(this.destroy$)).subscribe({
      next: () => { this.closeForm(); this.load(); },
      error: async () => {
        const alert = await this.alertController.create({
          header: this.language.instant('services.saveErrorTitle'),
          message: this.language.instant('services.saveError'),
          buttons: [this.language.instant('common.ok')],
        });
        await alert.present();
      },
    });
  }

  async del(s: ServiceItem): Promise<void> {
    const alert = await this.alertController.create({
      header: this.language.instant('common.delete'), message: this.language.instant('services.deleteConfirm', { name: s.name }),
      buttons: [
        { text: this.language.instant('common.cancel'), role: 'cancel' },
        { text: this.language.instant('common.delete'), role: 'destructive', handler: () => {
            this.apiService.deleteService(s.id).pipe(takeUntil(this.destroy$)).subscribe(() => this.load());
          }
        },
      ],
    });
    await alert.present();
  }

  categoryLabel(category: string): string {
    const value = (category ?? '').toLowerCase();
    return ['course', 'class', 'group', 'corso', 'gruppo'].some(item => value.includes(item))
      ? 'services.category.course'
      : 'services.category.appointment';
  }

  providerTypeLabel(providerType?: string | null): string {
    const value = (providerType ?? 'internal').toLowerCase();
    return value === 'external' ? 'services.external' : 'services.internal';
  }

  private emptyForm() {
    return {
      name: '',
      slug: '',
      gym_id: 0,
      category: 'appointment',
      provider_name: '',
      provider_type: 'internal' as 'internal' | 'external',
      duration_minutes: 60,
      capacity: 1,
      price: null as number | null,
      description: '',
    };
  }
}
