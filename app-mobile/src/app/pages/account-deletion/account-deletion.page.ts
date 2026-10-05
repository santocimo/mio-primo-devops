import { Component, OnDestroy, OnInit } from '@angular/core';
import { AlertController, ToastController } from '@ionic/angular';
import { Router } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { AuthService } from '../../services/auth.service';
import { ApiService } from '../../services/api.service';
import { LanguageService } from '../../i18n/language.service';
import { User } from '../../models/auth.model';

@Component({
  selector: 'app-account-deletion',
  templateUrl: './account-deletion.page.html',
  styleUrls: ['./account-deletion.page.scss'],
})
export class AccountDeletionPage implements OnInit, OnDestroy {
  user?: User;
  username = '';
  password = '';
  confirmation = '';
  deleting = false;
  private destroy$ = new Subject<void>();

  constructor(
    private auth: AuthService,
    private api: ApiService,
    private alertController: AlertController,
    private toastController: ToastController,
    private language: LanguageService,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.user = this.auth.getCurrentUser();
    this.username = this.user?.username ?? '';
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  get confirmationPhrase(): string {
    return this.language.currentLanguage === 'en' ? 'DELETE' : 'ELIMINA';
  }

  canSubmit(): boolean {
    return !this.deleting
      && this.username.trim() !== ''
      && this.password !== ''
      && this.confirmation === this.confirmationPhrase;
  }

  async requestDeletion(): Promise<void> {
    if (!this.canSubmit()) return;

    const alert = await this.alertController.create({
      header: this.language.instant('accountDeletion.confirmTitle'),
      message: this.language.instant('accountDeletion.confirmMessage'),
      buttons: [
        { text: this.language.instant('common.cancel'), role: 'cancel' },
        {
          text: this.language.instant('accountDeletion.confirmButton'),
          role: 'destructive',
          handler: () => this.deleteAccount(),
        },
      ],
    });
    await alert.present();
  }

  private deleteAccount(): void {
    if (this.deleting) return;
    this.deleting = true;
    this.auth.login({ username: this.username.trim(), password: this.password })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: response => {
          if (!response.success || !response.token) {
            this.deleting = false;
            void this.showToast(this.language.instant('accountDeletion.invalidCredentials'), 'danger');
            return;
          }
          this.api.deleteCurrentAccount({
            password: this.password,
            confirmation: this.confirmation,
          })
            .pipe(takeUntil(this.destroy$))
            .subscribe({
              next: () => {
                this.auth.logout();
                void this.showToast(this.language.instant('accountDeletion.success'), 'success');
                void this.router.navigateByUrl('/login', { replaceUrl: true });
              },
              error: error => {
                this.deleting = false;
                const message = error?.error?.message || this.language.instant('accountDeletion.error');
                void this.showToast(message, 'danger');
              },
            });
        },
        error: error => {
          this.deleting = false;
          const message = error?.status === 401
            ? this.language.instant('accountDeletion.invalidCredentials')
            : error?.error?.message || this.language.instant('accountDeletion.error');
          void this.showToast(message, 'danger');
        },
      });
  }

  private async showToast(message: string, color: 'success' | 'danger'): Promise<void> {
    const toast = await this.toastController.create({
      message,
      duration: 3500,
      color,
      position: 'bottom',
    });
    await toast.present();
  }
}
