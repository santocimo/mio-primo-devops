import { Component } from '@angular/core';
import { FormBuilder, FormGroup, Validators, AbstractControl, ValidationErrors } from '@angular/forms';
import { Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { ToastController, LoadingController, NavController } from '@ionic/angular';
import { environment } from '@env';
import { AuthService } from '../../services/auth.service';
import { AuthState, SubscriptionStatus } from '../../models/auth.model';
import { LanguageService } from '../../i18n/language.service';

const categories = [
  { value: 'gym',      label: 'register.category.gym' },
  { value: 'salon',    label: 'register.category.salon' },
  { value: 'studio',   label: 'register.category.studio' },
  { value: 'other',    label: 'register.category.other' },
];

function passwordMatchValidator(form: AbstractControl): ValidationErrors | null {
  const p  = form.get('password')?.value;
  const p2 = form.get('password2')?.value;
  return p && p2 && p !== p2 ? { passwordMismatch: true } : null;
}

@Component({
  selector: 'app-register',
  templateUrl: './register.page.html',
  styleUrls: ['./register.page.scss'],
})
export class RegisterPage {
  step = 1;
  categories = categories;

  step1Form!: FormGroup;
  step2Form!: FormGroup;

  constructor(
    private fb: FormBuilder,
    private http: HttpClient,
    public router: Router,
    private authService: AuthService,
    private toastController: ToastController,
    private loadingController: LoadingController,
    private navCtrl: NavController,
    private language: LanguageService,
  ) {
    this.step1Form = this.fb.group({
      gym_name:     ['', [Validators.required, Validators.minLength(2), Validators.maxLength(150)]],
      gym_category: ['', Validators.required],
      gym_address:  ['', [Validators.required, Validators.minLength(5), Validators.maxLength(200)]],
      gym_city:     ['', [Validators.maxLength(120)]],
      gym_phone:    ['', [Validators.maxLength(30)]],
      activity_name:[''],
    });

    this.step2Form = this.fb.group({
      name:          ['', [Validators.required, Validators.minLength(2)]],
      surname:       ['', [Validators.required, Validators.minLength(2)]],
      codice_fiscale:['', [Validators.required, Validators.pattern(/^[A-Za-z0-9]{16}$/)]],
      email:         ['', [Validators.required, Validators.email]],
      username:      ['', [Validators.required, Validators.minLength(3), Validators.pattern(/^[A-Za-z0-9._-]{3,50}$/)]],
      password:      ['', [Validators.required, Validators.minLength(8)]],
      password2:     ['', Validators.required],
    }, { validators: passwordMatchValidator });
  }

  nextStep(): void {
    if (this.step1Form.invalid) {
      this.step1Form.markAllAsTouched();
      return;
    }
    if (this.step1Form.get('gym_category')?.value === 'other') {
      const activityName = String(this.step1Form.get('activity_name')?.value ?? '').trim();
      if (!activityName) {
        this.step1Form.get('activity_name')?.setErrors({ required: true });
        this.step1Form.get('activity_name')?.markAsTouched();
        return;
      }
    }
    this.step = 2;
  }

  prevStep(): void {
    this.step = 1;
  }

  onCategorySelect(category: string): void {
    this.step1Form.get('gym_category')?.setValue(category);
    const activityControl = this.step1Form.get('activity_name');
    if (!activityControl) return;
    if (category === 'other') {
      activityControl.setValidators([Validators.required, Validators.minLength(2), Validators.maxLength(150)]);
    } else {
      activityControl.clearValidators();
      activityControl.setValue('');
    }
    activityControl.updateValueAndValidity();
  }

  isOtherCategory(): boolean {
    return this.step1Form.get('gym_category')?.value === 'other';
  }

  async onRegister(): Promise<void> {
    if (this.step2Form.invalid) {
      this.step2Form.markAllAsTouched();
      return;
    }

    const loader = await this.loadingController.create({ message: this.language.instant('register.loading') });
    await loader.present();

    const body = {
      ...this.step1Form.value,
      ...this.step2Form.value,
      name: `${this.step2Form.get('name')?.value?.trim()} ${this.step2Form.get('surname')?.value?.trim()}`.trim(),
      codice_fiscale: String(this.step2Form.get('codice_fiscale')?.value ?? '').toUpperCase(),
    };
    delete body['password2'];

    this.http.post<any>(`${environment.apiUrl}/api/register`, body).subscribe({
      next: async (res) => {
        await loader.dismiss();
        if (res.success) {
          // Auto-login: salva stato auth
          const sub = res.subscription
            ? this.authService.mapServerSubscription(res.subscription)
            : { subscriptionStatus: SubscriptionStatus.TRIAL, trialStartDate: new Date().toISOString() };
          const state: AuthState = {
            isLoggedIn: true,
            user: res.user,
            token: res.token,
            subscriptionStatus: sub.subscriptionStatus,
            trialStartDate: sub.trialStartDate,
            selectedGymId: res.user?.gym_id ?? null,
          };
          (this.authService as any).authState$.next(state);
          (this.authService as any).selectedGymId$?.next(state.selectedGymId ?? null);
          localStorage.setItem('authState', JSON.stringify(state));
          localStorage.setItem('token', res.token);

          await this.showToast(this.language.instant('register.welcome'), 'success');
          this.navCtrl.navigateRoot('/contacts');
        } else {
          await this.showToast(res.message || this.language.instant('register.error'), 'danger');
        }
      },
      error: async (err) => {
        await loader.dismiss();
        const msg = err.error?.message || this.language.instant('register.connectionError');
        await this.showToast(msg, 'danger');
      },
    });
  }

  private async showToast(message: string, color: 'success' | 'danger'): Promise<void> {
    const toast = await this.toastController.create({ message, duration: 3000, color, position: 'bottom' });
    await toast.present();
  }
}
