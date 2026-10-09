import { Component, OnInit, OnDestroy } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { CheckoutConfig, PaymentService } from '../../services/payment.service';
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
  providers: CheckoutConfig['providers'] = { paypal: false, stripe: false };
  processing = false;
  private finalizedSessionId = '';
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
    // La pagina può restare viva durante il checkout: il deep link cambia solo i query param
    this.route.queryParamMap.pipe(takeUntil(this.destroy$)).subscribe((params) => {
      const returnedSessionId = params.get('session_id');
      if (returnedSessionId && returnedSessionId !== this.finalizedSessionId) {
        this.finalizedSessionId = returnedSessionId;
        void this.finalizeStripe(returnedSessionId);
      }
    });
    if (this.route.snapshot.queryParamMap.get('session_id')) {
      return;
    }

    const paypalSubscriptionId = this.route.snapshot.queryParamMap.get('subscription_id');
    if (paypalSubscriptionId) {
      void this.finalizePayPal(paypalSubscriptionId);
      return;
    }
    if (this.route.snapshot.queryParamMap.get('cancelled')) {
      void this.showToast(this.language.instant('subscribe.error'), 'danger');
      this.router.navigate(['/paywall']);
      return;
    }
    const nav = this.router.getCurrentNavigation();
    const state = (nav?.extras?.state ?? history.state) as {
      planId?: string;
      planLabel?: string;
      planPrice?: string;
      providers?: CheckoutConfig['providers'];
    };
    if (state?.planId) {
      this.planId = state.planId;
      this.planLabel = state.planLabel ?? '';
      this.planPrice = state.planPrice ?? '';
      this.providers = state.providers ?? this.providers;
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

  private async finalizePayPal(subscriptionId: string): Promise<void> {
    const loader = await this.loadingController.create({
      message: this.language.instant('subscribe.loading'),
    });
    await loader.present();
    this.paymentService
      .confirmPayPalSubscription(subscriptionId)
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

  private async finalizeStripe(sessionId: string): Promise<void> {
    const loader = await this.loadingController.create({
      message: this.language.instant('subscribe.loading'),
    });
    await loader.present();
    this.paymentService
      .confirmStripeCheckout(sessionId)
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

  async confirmPayment(provider: 'paypal' | 'stripe'): Promise<void> {
    if (this.processing) return;
    this.processing = true;
    const loader = await this.loadingController.create({
      message: this.language.instant('subscribe.loading'),
    });
    await loader.present();

    const checkout = provider === 'stripe'
      ? this.paymentService.startStripeCheckout(this.planId)
      : this.paymentService.startPayPalCheckout(this.planId);
    checkout
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: () => loader.dismiss(),
        error: async () => {
          this.processing = false;
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
