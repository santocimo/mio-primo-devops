import { Injectable } from '@angular/core';
import { Router, CanActivate, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { AuthService } from '../services/auth.service';

@Injectable({
  providedIn: 'root',
})
export class AuthGuard implements CanActivate {
  constructor(
    private authService: AuthService,
    private router: Router
  ) {}

  canActivate(
    _route: ActivatedRouteSnapshot,
    state: RouterStateSnapshot
  ): boolean {
    // Ensure we restore any stored authState synchronously before checking
    this.authService.restoreFromLocalStorage();
    const isLoggedIn = this.authService.isLoggedIn();

    if (!isLoggedIn) {
      this.router.navigate(['/login'], {
        queryParams: { returnUrl: state.url },
      });
      return false;
    }

    // Permetti accesso se ha trial valido o abbonamento attivo
    // Allow OPERATORE to proceed in dev/testing even if subscription check fails
    const role = (this.authService.getCurrentUser()?.role ?? '').toUpperCase();
    if (role.includes('OPERATORE')) {
      return true;
    }

    if (this.authService.hasAccess()) {
      return true;
    }

    // Trial scaduto o nessun abbonamento → paywall
    this.router.navigate(['/paywall']);
    return false;
  }
}
