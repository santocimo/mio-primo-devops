import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { ToastController, LoadingController } from '@ionic/angular';

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
    private router: Router,
    private toastController: ToastController,
    private loadingController: LoadingController
  ) {}

  ngOnInit(): void {
    this.initializeForm();
  }

  private initializeForm(): void {
    this.loginForm = this.formBuilder.group({
      username: ['', [Validators.required, Validators.minLength(3)]],
      password: ['', [Validators.required, Validators.minLength(6)]],
    });
  }

  async onLogin(): Promise<void> {
    if (this.loginForm.invalid) {
      await this.showToast('Per favore compila correttamente il modulo', 'danger');
      return;
    }

    this.loading = true;
    const loader = await this.loadingController.create({
      message: 'Accesso in corso...',
    });
    await loader.present();

    this.authService.login(this.loginForm.value).subscribe({
      next: async (response) => {
        await loader.dismiss();
        this.loading = false;

        if (response.success) {
          await this.showToast('Accesso eseguito con successo', 'success');
          this.router.navigate(['/paywall']);
        } else {
          await this.showToast(response.message || 'Errore di accesso', 'danger');
        }
      },
      error: async (error) => {
        await loader.dismiss();
        this.loading = false;
        console.error('Errore di login:', error);
        await this.showToast('Errore di connessione. Riprova.', 'danger');
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
