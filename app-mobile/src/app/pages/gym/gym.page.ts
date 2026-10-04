import { Component, OnDestroy, OnInit } from '@angular/core';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { ToastController } from '@ionic/angular';
import { ApiService } from '../../services/api.service';
import { Gym } from '../../models/business.model';
import { AuthService } from '../../services/auth.service';
import { LanguageService } from '../../i18n/language.service';

@Component({
  selector: 'app-gym',
  templateUrl: './gym.page.html',
  styleUrls: ['./gym.page.scss'],
})
export class GymPage implements OnInit, OnDestroy {
  gym: Gym | null = null;
  loading = true;
  editing = false;
  saving = false;
  isAdminUser = false;
  formData = this.emptyForm();
  readonly categories = [
    { value: 'gym', label: 'gym.category.gym' },
    { value: 'salon', label: 'gym.category.salon' },
    { value: 'studio', label: 'gym.category.studio' },
    { value: 'other', label: 'gym.category.other' },
  ];
  private destroy$ = new Subject<void>();

  constructor(
    private api: ApiService,
    private auth: AuthService,
    private toastController: ToastController,
    private language: LanguageService
  ) {}

  ngOnInit(): void {
    const role = (this.auth.getCurrentUser()?.role ?? '').toUpperCase();
    this.isAdminUser = role.includes('ADMIN') || role.includes('SUPER');
    this.auth.getSelectedGymIdStream()
      .pipe(takeUntil(this.destroy$))
      .subscribe(gymId => {
        const assignedGymId = this.auth.getCurrentUser()?.gym_id ?? null;
        this.loadGym(gymId ?? assignedGymId);
      });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  managerName(): string {
    return this.gym?.manager_name || this.auth.getCurrentUser()?.name || this.auth.getCurrentUser()?.username || '—';
  }

  managerUsername(): string {
    return this.gym?.manager_username || this.auth.getCurrentUser()?.username || '—';
  }

  managerEmail(): string {
    return this.gym?.manager_email || this.auth.getCurrentUser()?.email || '—';
  }

  startEdit(): void {
    if (!this.gym) return;
    this.formData = {
      name: this.gym.name,
      category: this.gym.category,
      activity_name: this.gym.activity_name || '',
      manager_name: this.managerName() === '—' ? '' : this.managerName(),
      manager_email: this.managerEmail() === '—' ? '' : this.managerEmail(),
      manager_cf: this.gym.manager_cf ?? '',
      address: this.gym.address ?? '',
      city: this.gym.city ?? '',
      phone: this.gym.phone ?? '',
    };
    this.editing = true;
  }

  cancelEdit(): void {
    this.editing = false;
    this.formData = this.emptyForm();
  }

  canSave(): boolean {
    return this.validationMessage() === '';
  }

  validationMessage(): string {
    if (!this.formData.name.trim()) return 'gym.validation.name';
    if (!this.formData.manager_name.trim()) return 'gym.validation.manager';
    const email = this.formData.manager_email.trim();
    const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
      || email.toLowerCase() === this.managerEmail().trim().toLowerCase();
    if (!emailValid) return 'gym.validation.email';
    const cf = this.normalizedManagerCf();
    if (cf !== '' && !/^[A-Z0-9]{16}$/.test(cf)) {
      return 'gym.validation.taxCode';
    }
    return '';
  }

  save(): void {
    if (!this.gym || !this.canSave() || this.saving) return;
    this.saving = true;
    const update = {
      ...this.formData,
      name: this.formData.name.trim(),
      manager_name: this.formData.manager_name.trim(),
      manager_email: this.formData.manager_email.trim(),
      manager_cf: this.normalizedManagerCf(),
    };

    this.api.updateGym(this.gym.id, update)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: async () => {
          this.auth.updateCurrentUser({ name: update.manager_name, email: update.manager_email });
          this.gym = {
            ...this.gym!,
            name: update.name,
            category: update.category,
            activity_name: update.activity_name,
            manager_name: update.manager_name,
            manager_email: update.manager_email,
            manager_cf: update.manager_cf,
            address: update.address,
            city: update.city,
            phone: update.phone,
          };
          this.saving = false;
          this.editing = false;
          await this.presentToast(this.language.instant('gym.saved'), 'success');
        },
        error: async (err) => {
          this.saving = false;
          await this.presentToast(err?.error?.message || this.language.instant('gym.saveError'), 'danger');
        },
      });
  }

  businessActivity(): string {
    const activityName = this.gym?.activity_name;
    if (activityName) {
      const knownActivityLabels: Record<string, string> = {
        'gym': 'gym.category.gym',
        'salon': 'gym.category.salon',
        'studio': 'gym.category.studio',
        'other': 'gym.category.other',
        'Palestra': 'gym.category.gym',
        'Centro estetico': 'gym.category.salon',
        'Studio': 'gym.category.studio',
        'Altro': 'gym.category.other',
      };
      return knownActivityLabels[activityName] ?? activityName;
    }
    return this.categoryLabel();
  }

  categoryLabel(): string {
    const category = this.gym?.category ?? '';
    return `gym.category.${['gym', 'salon', 'studio', 'other'].includes(category) ? category : 'other'}`;
  }

  private emptyForm() {
    return {
      name: '',
      category: 'gym' as Gym['category'],
      activity_name: '',
      manager_name: '',
      manager_email: '',
      manager_cf: '',
      address: '',
      city: '',
      phone: '',
    };
  }

  private normalizedManagerCf(): string {
    return this.formData.manager_cf.replace(/\s+/g, '').toUpperCase();
  }

  private async presentToast(message: string, color: 'success' | 'danger'): Promise<void> {
    const toast = await this.toastController.create({ message, color, duration: 2200, position: 'bottom' });
    await toast.present();
  }

  private loadGym(gymId: number | null): void {
    if (!gymId) {
      this.gym = null;
      this.loading = false;
      return;
    }

    this.loading = true;
    const role = (this.auth.getCurrentUser()?.role ?? '').toUpperCase();
    const isAdmin = role.includes('ADMIN') || role.includes('SUPER');
    this.api.getGyms(isAdmin)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: gyms => {
          this.gym = gyms.find(g => g.id === gymId) ?? null;
          this.loading = false;
        },
        error: () => {
          this.gym = null;
          this.loading = false;
        },
      });
  }
}
