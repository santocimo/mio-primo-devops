import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { PaymentService } from '../../services/payment.service';
import { ToastController, LoadingController } from '@ionic/angular';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';

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
    private toastController: ToastController,
    private loadingController: LoadingController
  ) {}

  ngOnInit(): void {
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

  async confirmPayment(): Promise<void> {
    const loader = await this.loadingController.create({
      message: 'Attivazione in corso...',
    });
    await loader.present();

    this.paymentService
      .purchase(this.planId)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: async (result) => {
          await loader.dismiss();
          if (result.success) {
            await this.showToast('Abbonamento attivato con successo!', 'success');
            this.router.navigate(['/dashboard']);
          } else {
            await this.showToast('Errore durante l\'attivazione', 'danger');
          }
        },
        error: async () => {
          await loader.dismiss();
          await this.showToast('Errore di connessione', 'danger');
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
