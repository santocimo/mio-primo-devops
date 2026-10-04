import { Injectable } from '@angular/core';
import { CanActivate, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

@Injectable({
  providedIn: 'root',
})
export class UsersGuard implements CanActivate {
  constructor(private authService: AuthService, private router: Router) {}

  canActivate(): boolean {
    const user = this.authService.getCurrentUser();
    const role = (user?.role ?? '').toUpperCase();

    if (role.includes('ADMIN') || role.includes('SUPER') || role.includes('OPERATORE')) {
      return true;
    }

    this.router.navigate(['/contacts']);
    return false;
  }
}