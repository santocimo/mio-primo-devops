import { Component, OnDestroy, OnInit } from '@angular/core';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { AuthService } from '../../services/auth.service';
import { ApiService } from '../../services/api.service';
import { Gym } from '../../models/business.model';
import { User } from '../../models/auth.model';
import { AlertController, NavController, ToastController } from '@ionic/angular';
import { CheckoutConfig, PaymentService } from '../../services/payment.service';
import { LanguageService } from '../../i18n/language.service';
import { SubscriptionUpdate } from '../../models/auth.model';

@Component({
  selector: 'app-profile',
  templateUrl: './profile.page.html',
  styleUrls: ['./profile.page.scss'],
})
export class ProfilePage implements OnInit, OnDestroy {
  user?: User;
  activities: Gym[] = [];
  loading = true;
  loadError = false;
  subscription?: SubscriptionUpdate | null;
  canManageSubscription = false;
  subscriptionLoadError = false;
  managingSubscription = false;
  private destroy$ = new Subject<void>();

  constructor(
    private auth: AuthService,
    private api: ApiService,
    private nav: NavController,
    private payments: PaymentService,
    private alerts: AlertController,
    private toast: ToastController,
    private language: LanguageService
  ) {}

  ngOnInit(): void {
    this.user = this.auth.getCurrentUser();
    this.loadActivities();
    this.loadSubscription();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadActivities(): void {
    this.loading = true;
    this.loadError = false;
    this.api.getGyms(true)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: activities => {
          this.activities = activities;
          this.loading = false;
        },
        error: () => {
          this.loadError = true;
          this.loading = false;
        },
      });
  }

  isAdmin(): boolean {
    const role = (this.user?.role ?? '').toUpperCase();
    return role.includes('ADMIN') || role.includes('SUPER');
  }

  isBillingManager(): boolean {
    return (this.user?.role ?? '').toUpperCase() === 'GESTORE';
  }

  categoryKey(activity: Gym): string {
    return `gym.category.${['gym', 'salon', 'studio', 'other'].includes(activity.category) ? activity.category : 'other'}`;
  }

  requestAccountDeletion(): void {
    this.nav.navigateForward('/account-deletion');
  }

  private loadSubscription(): void {
    this.payments.getCheckoutConfig()
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (config: CheckoutConfig) => {
          this.subscription = config.subscription;
          this.canManageSubscription = config.canManageSubscription;
        },
        error: () => {
          this.subscriptionLoadError = true;
        },
      });
  }

  async cancelSubscription(): Promise<void> {
    if (!this.canManageSubscription || !this.subscription?.auto_renew || this.managingSubscription) return;
    const alert = await this.alerts.create({
      header: this.language.instant('profile.cancelSubscriptionTitle'),
      message: this.language.instant('profile.cancelSubscriptionMessage'),
      buttons: [
        { text: this.language.instant('common.cancel'), role: 'cancel' },
        {
          text: this.language.instant('profile.cancelSubscriptionAction'),
          role: 'destructive',
          handler: () => this.confirmCancelSubscription(),
        },
      ],
    });
    await alert.present();
  }

  private confirmCancelSubscription(): void {
    this.managingSubscription = true;
    this.payments.cancelSubscription()
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: async result => {
          this.subscription = result.subscription;
          this.managingSubscription = false;
          const toast = await this.toast.create({
            message: this.language.instant('profile.cancelSubscriptionSuccess'),
            duration: 3500,
            color: 'success',
          });
          await toast.present();
        },
        error: async () => {
          this.managingSubscription = false;
          const toast = await this.toast.create({
            message: this.language.instant('profile.cancelSubscriptionError'),
            duration: 3500,
            color: 'danger',
          });
          await toast.present();
        },
      });
  }
}
