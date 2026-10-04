import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { AlertController, ToastController } from '@ionic/angular';
import { ApiService } from '../../services/api.service';
import { AuthService } from '../../services/auth.service';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { LanguageService } from '../../i18n/language.service';

export interface AppUser {
  id: number;
  name?: string | null;
  email?: string | null;
  username: string;
  role: string;
  gym_id: number | null;
  created_at: string;
}

@Component({
  selector: 'app-admin-users',
  templateUrl: './admin-users.page.html',
  styleUrls: ['./admin-users.page.scss'],
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
    gyms$.pipe(takeUntil(this.destroy$)).subscribe({ next: g => (this.gyms = g), error: () => {} });
  }

  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    this.loading = true;
    this.apiService.getUsers().pipe(takeUntil(this.destroy$)).subscribe({
      next: u => { this.users = u; this.loading = false; },
      error: () => { this.loading = false; },
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
    this.formData = { name: u.name ?? '', email: u.email ?? '', username: u.username, password: '', role: u.role, gym_id: u.gym_id };
    if (!this.isAdminUser) {
      this.formData.gym_id = this.authService.getSelectedGymId();
    }
    this.showForm = true;
  }

  closeForm(): void { this.showForm = false; this.editingUser = null; }

  save(): void {
    if (!this.formData.username) return;
    const call = this.editingUser
      ? this.apiService.updateUser(this.editingUser.id, this.formData)
      : this.apiService.createUser(this.formData);
    call.pipe(takeUntil(this.destroy$)).subscribe(() => { this.closeForm(); this.load(); });
  }

  async del(u: AppUser): Promise<void> {
    const alert = await this.alertController.create({
      header: this.language.instant('common.delete'), message: this.language.instant('adminUsers.deleteConfirm', { name: u.username }),
      buttons: [
        { text: this.language.instant('common.cancel'), role: 'cancel' },
        { text: this.language.instant('common.delete'), role: 'destructive', handler: () => {
            this.apiService.deleteUser(u.id).pipe(takeUntil(this.destroy$)).subscribe(() => this.load());
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
    return this.roleLabel(u.role);
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

  private emptyForm() { return { name: '', email: '', username: '', password: '', role: 'OPERATORE', gym_id: null as number | null }; }
}
