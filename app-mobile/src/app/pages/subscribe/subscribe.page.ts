import { Component, OnInit, OnDestroy } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { PaymentService } from '../../services/payment.service';
import { ToastController, LoadingController } from '@ionic/angular';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { LanguageService } from '../../i18n/language.service';

@Component({
  selector: 'app-subscribe',
  templateUrl: './subscribe.page.html',
  styleUrls: ['./subscribe.page.scss'],
})
export class SubscribePage implements OnInit, OnDestroy {
  planId: string = '';
  planLabel: string = '';
  planPrice: string = '';
  private destroy$ = new Subject<void>();

  constructor(
    private paymentService: PaymentService,
    private router: Router,
    private route: ActivatedRoute,
    private toastController: ToastController,
    private loadingController: LoadingController,
    private language: LanguageService
  ) {}

  ngOnInit(): void {
    // Ritorno da PayPal: ?token=<orderId>
    const orderId = this.route.snapshot.queryParamMap.get('token');
    if (orderId) {
      void this.finalizePayPal(orderId);
      return;
    }
    if (this.route.snapshot.queryParamMap.get('cancelled')) {
      void this.showToast(this.language.instant('subscribe.error'), 'danger');
      this.router.navigate(['/paywall']);
      return;
    }
    const nav = this.router.getCurrentNavigation();
    const state = nav?.extras?.state as { planId?: string; planLabel?: string; planPrice?: string } | undefined;
    if (state?.planId) {
      this.planId = state.planId;
      this.planLabel = state.planLabel ?? '';
      this.planPrice = state.planPrice ?? '';
    } else {
      // Nessun piano selezionato: torna al paywall
      this.router.navigate(['/paywall']);
    }
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  goBack(): void {
    this.router.navigate(['/paywall']);
  }

  private async finalizePayPal(orderId: string): Promise<void> {
    const loader = await this.loadingController.create({
      message: this.language.instant('subscribe.loading'),
    });
    await loader.present();
    this.paymentService
      .capturePayPalOrder(orderId)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: async () => {
          await loader.dismiss();
          await this.showToast(this.language.instant('subscribe.success'), 'success');
          this.router.navigate(['/contacts'], { replaceUrl: true });
        },
        error: async () => {
          await loader.dismiss();
          await this.showToast(this.language.instant('subscribe.error'), 'danger');
          this.router.navigate(['/paywall'], { replaceUrl: true });
        },
      });
  }

  async confirmPayment(): Promise<void> {
    const loader = await this.loadingController.create({
      message: this.language.instant('subscribe.loading'),
    });
    await loader.present();

    this.paymentService
      .startPayPalCheckout(this.planId)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: () => loader.dismiss(),
        error: async () => {
          await loader.dismiss();
          await this.showToast(this.language.instant('subscribe.connectionError'), 'danger');
        },
      });
  }

  private async showToast(message: string, color: string): Promise<void> {
    const toast = await this.toastController.create({
      message,
      duration: 3000,
      color,
      position: 'bottom',
    });
    await toast.present();
  }
}
