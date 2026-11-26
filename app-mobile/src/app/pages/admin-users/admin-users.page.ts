import { Component, OnInit, OnDestroy, ChangeDetectionStrategy } from '@angular/core';
import { Router } from '@angular/router';
import { AlertController, ToastController } from '@ionic/angular';
import { ApiService } from '../../services/api.service';
import { AuthService } from '../../services/auth.service';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { LanguageService } from '../../i18n/language.service';
import { AppUser } from '../../models/business.model';

@Component({
    selector: 'app-admin-users',
    templateUrl: './admin-users.page.html',
    styleUrls: ['./admin-users.page.scss'],
    changeDetection: ChangeDetectionStrategy.Eager,
    standalone: false
})
export class AdminUsersPage implements OnInit, OnDestroy {
  users: AppUser[] = [];
  gyms: any[] = [];
  loading = true;
  showForm = false;
  editingUser: AppUser | null = null;
  formData = this.emptyForm();
  isAdminUser = false;
  private destroy$ = new Subject<void>();

  constructor(
    private apiService: ApiService,
    private alertController: AlertController,
    private toastController: ToastController,
    private authService: AuthService,
    public language: LanguageService,
    public router: Router
  ) {}

  ngOnInit(): void {
    const role = (this.authService.getCurrentUser()?.role ?? '').toUpperCase();
    this.isAdminUser = role.includes('ADMIN') || role.includes('SUPER');
    this.load();
    const gyms$ = this.isAdminUser ? this.apiService.getGyms(true) : this.apiService.getGyms();
    gyms$.pipe(takeUntil(this.destroy$)).subscribe({
      next: g => (this.gyms = g),
      error: () => void this.presentToast(this.language.instant('adminUsers.loadError'), 'danger'),
    });
  }

  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    this.loading = true;
    this.apiService.getUsers().pipe(takeUntil(this.destroy$)).subscribe({
      next: u => {
        if (!this.isAdminUser && !u.some(user =>
          Number(user.id) === Number(this.authService.getCurrentUser()?.id)
          && Number(user.is_billing_owner) === 1
        )) {
          this.loading = false;
          void this.router.navigateByUrl('/contacts');
          return;
        }
        this.users = u;
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        void this.presentToast(this.language.instant('adminUsers.loadError'), 'danger');
      },
    });
  }

  openAdd(): void {
    this.editingUser = null;
    this.formData = this.emptyForm();
    if (!this.isAdminUser) {
      this.formData.gym_id = this.authService.getSelectedGymId();
    }
    this.showForm = true;
  }

  openEdit(u: AppUser): void {
    this.editingUser = u;
    this.formData = {
      name: u.name ?? '',
      email: u.email ?? '',
      tax_code: u.tax_code ?? '',
      username: u.username,
      password: '',
      role: u.role.toUpperCase(),
      gym_id: u.gym_id,
      is_billing_owner: Number(u.is_billing_owner) === 1,
    };
    if (!this.isAdminUser) {
      this.formData.gym_id = this.authService.getSelectedGymId();
    }
    this.showForm = true;
  }

  closeForm(): void { this.showForm = false; this.editingUser = null; }

  onRoleChange(): void {
    if (this.formData.role === 'GESTORE') {
      this.formData.is_billing_owner = true;
    }
  }

  save(): void {
    if (!this.formData.username) return;
    const call = this.editingUser
      ? this.apiService.updateUser(this.editingUser.id, this.formData)
      : this.apiService.createUser(this.formData);
    call.pipe(takeUntil(this.destroy$)).subscribe({
      next: () => {
        this.closeForm();
        window.dispatchEvent(new Event('business-type-changed'));
        this.load();
      },
      error: error => {
        const message = error?.error?.message || this.language.instant('adminUsers.saveError');
        void this.presentToast(message, 'danger');
      },
    });
  }

  async del(u: AppUser): Promise<void> {
    const alert = await this.alertController.create({
      header: this.language.instant('common.delete'), message: this.language.instant('adminUsers.deleteConfirm', { name: u.username }),
      buttons: [
        { text: this.language.instant('common.cancel'), role: 'cancel' },
        { text: this.language.instant('common.delete'), role: 'destructive', handler: () => {
            this.apiService.deleteUser(u.id).pipe(takeUntil(this.destroy$)).subscribe({
              next: () => this.load(),
              error: async error => {
                const message = error?.error?.message || this.language.instant('adminUsers.deleteError');
                await this.presentToast(message, 'danger');
              },
            });
          }
        },
      ],
    });
    await alert.present();
  }

  async resetPassword(u: AppUser): Promise<void> {
    const alert = await this.alertController.create({
      header: this.language.instant('adminUsers.resetTitle', { name: u.username }),
      message: this.language.instant('adminUsers.resetPrompt'),
      inputs: [
        {
          name: 'password',
          type: 'password',
          placeholder: this.language.instant('adminUsers.newPassword'),
        },
      ],
      buttons: [
        { text: this.language.instant('common.cancel'), role: 'cancel' },
        {
          text: this.language.instant('common.save'),
          handler: async (formData) => {
            const newPassword = String(formData?.password ?? '').trim();
            if (!newPassword) {
              await this.presentToast(this.language.instant('adminUsers.passwordRequired'), 'warning');
              return false;
            }

            this.apiService.updateUser(u.id, {
              name: u.name ?? '',
              email: u.email ?? '',
              username: u.username,
              role: u.role,
              gym_id: u.gym_id,
              is_billing_owner: u.is_billing_owner,
              password: newPassword,
            }).pipe(takeUntil(this.destroy$)).subscribe({
              next: async () => {
                await this.presentToast(this.language.instant('adminUsers.passwordUpdated'), 'success');
                this.load();
              },
              error: async () => {
                await this.presentToast(this.language.instant('adminUsers.passwordError'), 'danger');
              },
            });
            return true;
          },
        },
      ],
    });
    await alert.present();
  }

  roleLabel(role: string): string {
    const r = (role || '').toUpperCase();
    if (r.includes('ADMIN') || r.includes('SUPER')) return 'adminUsers.admin';
    if (r === 'GESTORE') return 'adminUsers.billingManager';
    return this.isAdminUser ? 'adminUsers.manager' : 'adminUsers.member';
  }

  userTitle(u: AppUser): string {
    const fullName = (u.name || '').trim();
    return fullName || u.username;
  }

  userSubtitle(u: AppUser): string {
    const parts: string[] = [];
    if (u.email) parts.push(u.email);
    parts.push(`@${u.username}`);
    return parts.join(' · ');
  }

  userBadgeLabel(u: AppUser): string {
    if (Number(u.is_billing_owner) === 1 && !['ADMIN', 'SUPER'].includes(u.role.toUpperCase())) {
      return 'adminUsers.billingManager';
    }
    return u.role.toUpperCase() === 'GESTORE' ? 'adminUsers.member' : this.roleLabel(u.role);
  }

  gymName(gymId: number | null): string {
    if (!gymId) return 'Tutte le sedi';
    return this.gyms.find((gym) => gym.id === gymId)?.name ?? `Sede #${gymId}`;
  }

  private async presentToast(message: string, color: 'success' | 'warning' | 'danger'): Promise<void> {
    const toast = await this.toastController.create({
      message,
      color,
      duration: 1800,
      position: 'bottom',
    });
    await toast.present();
  }

  private emptyForm() {
    return {
      name: '',
      email: '',
      tax_code: '',
      username: '',
      password: '',
      role: 'OPERATORE',
      gym_id: null as number | null,
      is_billing_owner: false,
    };
  }
}
