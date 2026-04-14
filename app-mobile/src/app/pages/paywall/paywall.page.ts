import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { PaymentService, Product } from '../../services/payment.service';
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
  private destroy$ = new Subject<void>();

  constructor(
    private paymentService: PaymentService,
    private router: Router,
    private toastController: ToastController,
    private loadingController: LoadingController
  ) {}

  ngOnInit(): void {
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
      .subscribe({
        next: (products) => {
          this.products = products;
          // Seleziona il piano annuale per default
          if (products.length > 1) {
            this.selectedProductId = products[1].id;
          }
        },
      });
  }

  async onPurchase(productId: string): Promise<void> {
    this.selectedProductId = productId;
    const loader = await this.loadingController.create({
      message: 'Elaborazione pagamento...',
    });
    await loader.present();

    this.paymentService
      .purchase(productId)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: async (result) => {
          await loader.dismiss();

          if (result.success) {
            await this.showToast('Abbonamento attivato con successo!', 'success');
            this.router.navigate(['/dashboard']);
          } else {
            await this.showToast('Errore durante l\'acquisto', 'danger');
          }
        },
        error: async (error) => {
          await loader.dismiss();
          console.error('Errore di pagamento:', error);
          await this.showToast('Errore di connessione', 'danger');
        },
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
