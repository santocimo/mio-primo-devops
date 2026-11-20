import { Component, OnDestroy, OnInit } from '@angular/core';
import { forkJoin, of, Subject } from 'rxjs';
import { catchError, takeUntil } from 'rxjs/operators';
import { ToastController } from '@ionic/angular';
import { ApiService } from '../../services/api.service';
import { AppUser, Gym } from '../../models/business.model';
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
  users: AppUser[] = [];
  usersLoadError = false;
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
    const manager = this.billingManager();
    return manager?.name || manager?.username || '—';
  }

  managerUsername(): string {
    return this.billingManager()?.username || '—';
  }

  managerEmail(): string {
    return this.billingManager()?.email || '—';
  }

  billingManager(): AppUser | undefined {
    return this.users.find(user => Number(user.is_billing_owner) === 1);
  }

  get operators(): AppUser[] {
    return this.users.filter(user => ['OPERATORE', 'OPERATOR'].includes(user.role.toUpperCase()));
  }

  canEditManager(): boolean {
    const manager = this.billingManager();
    return !!manager && (this.isAdminUser || manager.id === this.auth.getCurrentUser()?.id);
  }

  managerTaxCode(): string {
    return this.billingManager()?.tax_code || '';
  }

  startEdit(): void {
    if (!this.gym) return;
    this.formData = {
      name: this.gym.name,
      category: this.gym.category,
      activity_name: this.gym.activity_name || '',
      manager_name: this.billingManager()?.name ?? '',
      manager_email: this.billingManager()?.email ?? '',
      manager_cf: this.managerTaxCode(),
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
    if (this.canEditManager()) {
      if (!this.formData.manager_name.trim()) return 'gym.validation.manager';
      const email = this.formData.manager_email.trim();
      const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
        || email.toLowerCase() === this.managerEmail().trim().toLowerCase();
      if (!emailValid) return 'gym.validation.email';
      const cf = this.normalizedManagerCf();
      if (cf !== '' && !/^[A-Z0-9]{16}$/.test(cf)) {
        return 'gym.validation.taxCode';
      }
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

    const { manager_name, manager_email, manager_cf, ...activityUpdate } = update;
    const payload = this.canEditManager() ? update : activityUpdate;
    this.api.updateGym(this.gym.id, payload)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: async () => {
          if (!this.isAdminUser && this.canEditManager()) {
            this.auth.updateCurrentUser({ name: update.manager_name, email: update.manager_email });
          }
          this.gym = {
            ...this.gym!,
            name: update.name,
            category: update.category,
            activity_name: update.activity_name,
            manager_name: this.canEditManager() ? update.manager_name : this.gym!.manager_name,
            manager_email: this.canEditManager() ? update.manager_email : this.gym!.manager_email,
            manager_cf: this.canEditManager() ? update.manager_cf : this.gym!.manager_cf,
            address: update.address,
            city: update.city,
            phone: update.phone,
          };
          if (this.canEditManager()) {
            const manager = this.billingManager();
            if (manager) {
              manager.name = update.manager_name;
              manager.email = update.manager_email;
              manager.tax_code = update.manager_cf;
              this.gym.manager_username = manager.username;
            }
          }
          this.saving = false;
          this.editing = false;
          window.dispatchEvent(new Event('business-type-changed'));
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
      this.users = [];
      this.loading = false;
      return;
    }

    this.loading = true;
    this.users = [];
    this.usersLoadError = false;
    const role = (this.auth.getCurrentUser()?.role ?? '').toUpperCase();
    const isAdmin = role.includes('ADMIN') || role.includes('SUPER');
    forkJoin({
      gyms: this.api.getGyms(isAdmin),
      users: this.api.getUsers().pipe(catchError(() => of(null))),
    })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: ({ gyms, users }) => {
          if (this.auth.getSelectedGymId() !== gymId && this.auth.getCurrentUser()?.gym_id !== gymId) return;
          this.gym = gyms.find(g => g.id === gymId) ?? null;
          this.users = users?.filter(user => Number(user.gym_id) === gymId) ?? [];
          this.usersLoadError = users === null;
          this.loading = false;
        },
        error: () => {
          this.gym = null;
          this.users = [];
          this.usersLoadError = true;
          this.loading = false;
        },
      });
  }
}
