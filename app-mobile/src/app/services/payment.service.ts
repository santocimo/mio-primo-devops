import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable, of } from 'rxjs';
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

  constructor(private authService: AuthService) {
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
        name: 'Pro Monthly',
        description: 'Accesso completo per 1 mese',
        price: '4.99',
        currency: 'EUR',
        duration: 'monthly',
      },
      {
        id: 'businessregistry_yearly',
        name: 'Pro Yearly',
        description: 'Accesso completo per 1 anno (sconto 20%)',
        price: '49.99',
        currency: 'EUR',
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
   * Effettua un acquisto in-app
   * In produzione, questo userebbe RevenueCat o Stripe
   */
  purchase(productId: string): Observable<PurchaseResult> {
    this.purchaseInProgress$.next(true);

    // Mock implementation - In production usare RevenueCat SDK
    return new Observable((observer) => {
      setTimeout(() => {
        // Simula il successo dell'acquisto
        const result: PurchaseResult = {
          success: true,
          message: 'Abbonamento attivato con successo',
          transactionId: `TXN_${Date.now()}`,
          expiryDate: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString(),
        };

        // Aggiorna lo stato della sottoscrizione
        this.authService.updateSubscriptionStatus(SubscriptionStatus.ACTIVE);

        this.purchaseInProgress$.next(false);
        observer.next(result);
        observer.complete();
      }, 2000);
    });
  }

  /**
   * Controlla se un acquisto è in corso
   */
  isPurchaseInProgress(): Observable<boolean> {
    return this.purchaseInProgress$.asObservable();
  }

  /**
   * Ripristina gli acquisti precedenti
   */
  restorePurchases(): Observable<PurchaseResult> {
    return new Observable((observer) => {
      // In produzione, usare RevenueCat per ripristinare acquisti
      this.authService.updateSubscriptionStatus(SubscriptionStatus.ACTIVE);
      observer.next({
        success: true,
        message: 'Acquisti ripristinati',
      });
      observer.complete();
    });
  }

  /**
   * Annulla la sottoscrizione
   */
  cancelSubscription(): Observable<PurchaseResult> {
    return new Observable((observer) => {
      // In produzione, comunicare il cancellamento a RevenueCat/Stripe
      this.authService.updateSubscriptionStatus(SubscriptionStatus.NONE);
      observer.next({
        success: true,
        message: 'Sottoscrizione annullata',
      });
      observer.complete();
    });
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
