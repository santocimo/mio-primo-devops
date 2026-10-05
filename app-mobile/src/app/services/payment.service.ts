import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable } from 'rxjs';
import { finalize, map } from 'rxjs/operators';
import { environment } from '@env';
import { SubscriptionStatus } from '../models/auth.model';
import { AuthService } from './auth.service';

export interface Product {
  id: string;
  name: string;
  description: string;
  price: string;
  currency: string;
  duration: 'monthly' | 'yearly';
}

export interface PurchaseResult {
  success: boolean;
  message: string;
  transactionId?: string;
  expiryDate?: string;
}

@Injectable({
  providedIn: 'root',
})
export class PaymentService {
  private products$ = new BehaviorSubject<Product[]>([]);
  private purchaseInProgress$ = new BehaviorSubject<boolean>(false);

  constructor(private authService: AuthService, private http: HttpClient) {
    this.initializePayments();
  }

  /**
   * Inizializza il sistema di pagamento
   * In produzione, collegherebbe RevenueCat
   */
  private initializePayments(): void {
    // Mock products - In production, questi verrebbero da RevenueCat
    const mockProducts: Product[] = [
      {
        id: 'businessregistry_monthly',
        name: 'Piano Mensile',
        description: 'Accesso completo, rinnovo mensile',
        price: '4,99',
        currency: '€',
        duration: 'monthly',
      },
      {
        id: 'businessregistry_yearly',
        name: 'Piano Annuale',
        description: 'Accesso completo per 1 anno — risparmi il 17%',
        price: '49,99',
        currency: '€',
        duration: 'yearly',
      },
    ];

    this.products$.next(mockProducts);
  }

  /**
   * Ottiene i prodotti disponibili per l'acquisto
   */
  getProducts(): Observable<Product[]> {
    return this.products$.asObservable();
  }

  /**
   * Avvia il pagamento PayPal: crea l'ordine sul server e reindirizza all'approvazione.
   */
  startPayPalCheckout(productId: string): Observable<void> {
    const plan = productId.endsWith('_yearly') ? 'yearly' : 'monthly';
    const returnUrl = `${window.location.origin}/subscribe`;
    this.purchaseInProgress$.next(true);
    return this.http
      .post<{ approve_url: string }>(`${environment.apiUrl}/api/payments/paypal.php`, {
        action: 'create',
        plan,
        return_url: returnUrl,
        cancel_url: `${returnUrl}?cancelled=1`,
      })
      .pipe(
        map((res) => {
          window.location.href = res.approve_url;
        }),
        finalize(() => this.purchaseInProgress$.next(false))
      );
  }

  /**
   * Conferma il pagamento PayPal al ritorno dall'approvazione.
   */
  capturePayPalOrder(orderId: string): Observable<PurchaseResult> {
    return this.http
      .post<{ transaction_id?: string; subscription?: { expires_at?: string } }>(
        `${environment.apiUrl}/api/payments/paypal.php`,
        { action: 'capture', order_id: orderId }
      )
      .pipe(
        map((res) => {
          this.authService.updateSubscriptionStatus(SubscriptionStatus.ACTIVE);
          return {
            success: true,
            message: 'Abbonamento attivato con successo',
            transactionId: res.transaction_id,
            expiryDate: res.subscription?.expires_at,
          };
        })
      );
  }

  /**
   * Controlla se un acquisto è in corso
   */
  isPurchaseInProgress(): Observable<boolean> {
    return this.purchaseInProgress$.asObservable();
  }

  /**
   * Verifica se l'utente ha un abbonamento attivo
   */
  hasActiveSubscription(): Observable<boolean> {
    return new Observable((observer) => {
      this.authService.getSubscriptionStatus().subscribe((status) => {
        observer.next(status === SubscriptionStatus.ACTIVE);
      });
    });
  }
}
