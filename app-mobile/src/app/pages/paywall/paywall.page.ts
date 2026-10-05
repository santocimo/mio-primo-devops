import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { PaymentService, Product } from '../../services/payment.service';
import { AuthService } from '../../services/auth.service';
import { SubscriptionStatus } from '../../models/auth.model';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { LanguageService } from '../../i18n/language.service';

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
    private language: LanguageService
  ) {}

  ngOnInit(): void {
    const state = this.authService['authState$'].getValue();
    // Utente già abbonato: apre la sezione iscritti
    if (state.subscriptionStatus === SubscriptionStatus.ACTIVE) {
      this.router.navigate(['/contacts']);
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
    const period = product ? this.language.instant(product.duration === 'monthly' ? 'paywall.month' : 'paywall.year') : '';
    const planPrice = product ? `${product.price} ${product.currency}/${period}` : '';
    this.router.navigate(['/subscribe'], {
      state: { planId: productId, planLabel, planPrice },
    });
  }
}
