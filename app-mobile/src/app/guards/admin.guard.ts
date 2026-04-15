import { Injectable } from '@angular/core';
import { Router, CanActivate } from '@angular/router';
import { AuthService } from '../services/auth.service';

@Injectable({
  providedIn: 'root',
})
export class AdminGuard implements CanActivate {
  constructor(private authService: AuthService, private router: Router) {}

  canActivate(): boolean {
    const user = this.authService.getCurrentUser();
    const role = (user?.role ?? '').toUpperCase();
    if (role.includes('ADMIN') || role.includes('SUPER')) {
      return true;
    }
    this.router.navigate(['/dashboard']);
    return false;
  }
}
