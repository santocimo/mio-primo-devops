import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { PaymentService, Product } from '../../services/payment.service';
import { AuthService } from '../../services/auth.service';
import { SubscriptionStatus } from '../../models/auth.model';
import { ToastController, LoadingController } from '@ionic/angular';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';

@Component({
  selector: 'app-paywall',
  templateUrl: './paywall.page.html',
  styleUrls: ['./paywall.page.scss'],
})
export class PaywallPage implements OnInit, OnDestroy {
  products: Product[] = [];
  selectedProductId: string | null = null;
  purchaseInProgress = false;
  isTrialExpired = false;
  private destroy$ = new Subject<void>();

  constructor(
    private paymentService: PaymentService,
    private authService: AuthService,
    private router: Router,
    private toastController: ToastController,
    private loadingController: LoadingController
  ) {}

  ngOnInit(): void {
    const state = this.authService['authState$'].getValue();
    // Utente già abbonato: torna alla dashboard
    if (state.subscriptionStatus === SubscriptionStatus.ACTIVE) {
      this.router.navigate(['/dashboard']);
      return;
    }
    this.isTrialExpired = state.subscriptionStatus === SubscriptionStatus.EXPIRED;
    this.loadProducts();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  private loadProducts(): void {
    this.paymentService
      .getProducts()
      .pipe(takeUntil(this.destroy$))
      .subscribe((products) => {
        this.products = products;
        // Seleziona il piano annuale per default
        if (products && products.length > 1) {
          this.selectedProductId = products[1].id;
        }
      });
  }

  onPurchase(productId: string): void {
    this.selectedProductId = productId;
    const product = this.products.find((p) => p.id === productId);
    const planLabel = product?.name ?? '';
    const planPrice = product ? `${product.price} ${product.currency}/${product.duration === 'monthly' ? 'mese' : 'anno'}` : '';
    this.router.navigate(['/subscribe'], {
      state: { planId: productId, planLabel, planPrice },
    });
  }

  async restorePurchases(): Promise<void> {
    const loader = await this.loadingController.create({
      message: 'Ripristino acquisti...',
    });
    await loader.present();

    this.paymentService
      .restorePurchases()
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: async (result) => {
          await loader.dismiss();
          await this.showToast(result.message, 'success');
          this.router.navigate(['/dashboard']);
        },
        error: async (error) => {
          await loader.dismiss();
          console.error('Errore:', error);
          await this.showToast('Errore nel ripristino', 'danger');
        },
      });
  }

  private async showToast(
    message: string,
    color: 'success' | 'danger' | 'warning'
  ): Promise<void> {
    const toast = await this.toastController.create({
      message,
      duration: 2000,
      color,
      position: 'bottom',
    });
    await toast.present();
  }
}
