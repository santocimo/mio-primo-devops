import { Injectable } from '@angular/core';
import { Router, CanActivate, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { AuthService } from '../services/auth.service';
import { PaymentService } from '../services/payment.service';
import { SubscriptionStatus } from '../models/auth.model';

@Injectable({
  providedIn: 'root',
})
export class AuthGuard implements CanActivate {
  constructor(
    private authService: AuthService,
    private paymentService: PaymentService,
    private router: Router
  ) {}

  canActivate(
    _route: ActivatedRouteSnapshot,
    state: RouterStateSnapshot
  ): boolean {
    const isLoggedIn = this.authService.isLoggedIn();

    if (!isLoggedIn) {
      this.router.navigate(['/login'], {
        queryParams: { returnUrl: state.url },
      });
      return false;
    }

    // Check subscription status
    const authState = (this.authService as any).authState$?.getValue?.();
    if (authState?.subscriptionStatus === SubscriptionStatus.NONE) {
      // Reindirizza al paywall se l'utente non ha un abbonamento
      this.router.navigate(['/paywall']);
      return false;
    }

    return true;
  }
}
