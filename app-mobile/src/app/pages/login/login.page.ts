import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { NavController, ToastController } from '@ionic/angular';
import { SubscriptionStatus } from '../../models/auth.model';
import { environment } from '@env';
import { LanguageService } from '../../i18n/language.service';

@Component({
  selector: 'app-login',
  templateUrl: './login.page.html',
  styleUrls: ['./login.page.scss'],
})
export class LoginPage implements OnInit {
  loginForm!: FormGroup;
  loading = false;

  constructor(
    private formBuilder: FormBuilder,
    private authService: AuthService,
    private navCtrl: NavController,
    private router: Router,
    private toastController: ToastController,
    private language: LanguageService,
  ) {}

  ngOnInit(): void {
    this.initializeForm();
  }

  private initializeForm(): void {
    this.loginForm = this.formBuilder.group({
      username: ['', [Validators.required, Validators.minLength(2)]],
      password: ['', [Validators.required]],
    });
  }

  async onLogin(): Promise<void> {
    if (this.loginForm.invalid) {
      await this.showToast(this.language.instant('login.invalidForm'), 'danger');
      return;
    }

    this.loading = true;

    try {
      const result = await this.authService.login(this.loginForm.value).toPromise();
      this.loading = false;

      if (result && result.success && result.token) {
        const role = (result.user?.role ?? '').toUpperCase();
        const isAdmin = role.includes('ADMIN') || role.includes('SUPER');

        if (isAdmin && this.authService.getCurrentUser()?.gym_id == null) {
          this.authService.setSelectedGymId(null);
        }

        const state = this.authService.getAuthState();
        const currentState = this.authService['authState$'].getValue();
        if (currentState.subscriptionStatus === SubscriptionStatus.EXPIRED || currentState.subscriptionStatus === SubscriptionStatus.NONE) {
          this.navCtrl.navigateRoot('/paywall');
        } else {
          this.navCtrl.navigateRoot('/contacts');
        }
      } else {
        await this.showToast(this.language.instant('login.error'), 'danger');
      }
    } catch (e) {
      this.loading = false;
      const error = e as { status?: number; error?: { message?: string } };
      const message = error.status === 401
        ? this.language.instant('login.invalidCredentials')
        : this.language.instant('login.connectionError');
      await this.showToast(message, 'danger');
    }
  }
  goToRegister(): void {
    void this.router.navigate(['/register']);
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
