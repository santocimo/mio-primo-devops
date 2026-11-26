import { Injectable } from '@angular/core';
import { Router, UrlTree } from '@angular/router';
import { ToastController } from '@ionic/angular';
import { Observable, of } from 'rxjs';
import { catchError, map } from 'rxjs/operators';
import { AuthService } from '../services/auth.service';
import { ApiService } from '../services/api.service';
import { LanguageService } from '../i18n/language.service';

@Injectable({
  providedIn: 'root',
})
export class UsersGuard  {
  constructor(
    private authService: AuthService,
    private api: ApiService,
    private router: Router,
    private toast: ToastController,
    private language: LanguageService,
  ) {}

  canActivate(): boolean | UrlTree | Observable<boolean | UrlTree> {
    const user = this.authService.getCurrentUser();
    const role = (user?.role ?? '').toUpperCase();

    if (role.includes('ADMIN') || role.includes('SUPER')) {
      return true;
    }
    if (!user?.gym_id) return this.router.parseUrl('/contacts');
    return this.api.getGyms().pipe(
      map(gyms => gyms.some(gym =>
        Number(gym.id) === Number(user.gym_id)
        && Number(gym.billing_owner_user_id) === Number(user.id)
      ) || this.router.parseUrl('/contacts')),
      catchError(() => {
        void this.showLoadError();
        return of(this.router.parseUrl('/contacts'));
      }),
    );
  }

  private async showLoadError(): Promise<void> {
    const toast = await this.toast.create({
      message: this.language.instant('adminUsers.accessCheckError'),
      color: 'danger',
      duration: 3000,
    });
    await toast.present();
  }
}