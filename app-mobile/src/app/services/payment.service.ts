import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, throwError } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '@env';
import { SubscriptionUpdate } from '../models/auth.model';
import { AuthService } from './auth.service';
import { LanguageService } from '../i18n/language.service';

export interface Product {
  id: string;
  name: string;
  description: string;
  price: string;
  currency: string;
  duration: 'monthly' | 'yearly';
}

export interface CheckoutConfig {
  products: Product[];
  providers: {
    paypal: boolean;
    stripe: boolean;
  };
  subscription: SubscriptionUpdate | null;
  canManageSubscription: boolean;
}

export interface PurchaseResult {
  success: boolean;
  message: string;
  transactionId?: string;
  expiryDate?: string;
  subscription?: SubscriptionUpdate;
}

interface ServerPlan {
  id: string;
  duration: 'monthly' | 'yearly';
  amount_minor: number;
  currency: string;
  interval: 'month' | 'year';
}

interface PlansResponse {
  success: boolean;
  currency: string;
  providers: CheckoutConfig['providers'];
  subscription: SubscriptionUpdate | null;
  can_manage_subscription: boolean;
  plans: ServerPlan[];
}

@Injectable({
  providedIn: 'root',
})
export class PaymentService {
  constructor(
    private authService: AuthService,
    private http: HttpClient,
    private language: LanguageService
  ) {}

  getCheckoutConfig(): Observable<CheckoutConfig> {
    return this.http
      .get<PlansResponse>(`${environment.apiUrl}/api/payments/plans.php`)
      .pipe(
        map((response) => {
          if (!response.success || !Array.isArray(response.plans)) {
            throw new Error('Invalid subscription plans response');
          }
          const locale = this.language.currentLanguage === 'en' ? 'en-IE' : 'it-IT';
          return {
            providers: response.providers,
            subscription: response.subscription,
            canManageSubscription: response.can_manage_subscription,
            products: response.plans.map((plan) => ({
              id: plan.id,
              name: this.language.instant(plan.duration === 'yearly' ? 'paywall.yearlyPlan' : 'paywall.monthlyPlan'),
              description: this.language.instant(plan.duration === 'yearly' ? 'paywall.yearlyDescription' : 'paywall.monthlyDescription'),
              price: new Intl.NumberFormat(locale, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
              }).format(plan.amount_minor / 100),
              currency: new Intl.NumberFormat(locale, {
                style: 'currency',
                currency: plan.currency,
              }).formatToParts(0).find((part) => part.type === 'currency')?.value ?? plan.currency,
              duration: plan.duration,
            })),
          };
        })
      );
  }

  startPayPalCheckout(planId: string): Observable<void> {
    return this.startCheckout('paypal', planId, 'approve_url');
  }

  startStripeCheckout(planId: string): Observable<void> {
    return this.startCheckout('stripe', planId, 'checkout_url');
  }

  private startCheckout(
    provider: 'paypal' | 'stripe',
    planId: string,
    urlKey: 'approve_url' | 'checkout_url'
  ): Observable<void> {
    return this.http
      .post<Record<string, string>>(`${environment.apiUrl}/api/payments/${provider}.php`, {
        action: 'create',
        plan: planId,
      })
      .pipe(
        map((response) => {
          const url = response[urlKey];
          if (!url) {
            throw new Error(`${provider} checkout URL is missing`);
          }
          window.location.assign(url);
        })
      );
  }

  confirmPayPalSubscription(subscriptionId: string): Observable<PurchaseResult> {
    return this.http
      .post<PurchaseResult>(`${environment.apiUrl}/api/payments/paypal.php`, {
        action: 'confirm',
        subscription_id: subscriptionId,
      })
      .pipe(map((result) => this.applyPurchaseResult(result)));
  }

  confirmStripeCheckout(sessionId: string): Observable<PurchaseResult> {
    return this.http
      .post<PurchaseResult>(`${environment.apiUrl}/api/payments/stripe.php`, {
        action: 'confirm',
        session_id: sessionId,
      })
      .pipe(map((result) => this.applyPurchaseResult(result)));
  }

  cancelSubscription(): Observable<PurchaseResult> {
    return this.http
      .post<PurchaseResult>(`${environment.apiUrl}/api/payments/manage.php`, { action: 'cancel' })
      .pipe(map((result) => this.applyPurchaseResult(result)));
  }

  private applyPurchaseResult(result: PurchaseResult): PurchaseResult {
    if (!result.success || !result.subscription) {
      throw new Error('Payment response did not include the activated subscription');
    }
    this.authService.updateSubscription(result.subscription);
    return result;
  }
}
