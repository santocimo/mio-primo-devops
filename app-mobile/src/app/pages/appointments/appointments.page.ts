import { Component, OnInit, OnDestroy } from '@angular/core';
import { AlertController, NavController, ToastController, ViewWillEnter } from '@ionic/angular';
import { ApiService } from '../../services/api.service';
import { AuthService } from '../../services/auth.service';
import { Appointment } from '../../models/business.model';
import { LanguageService } from '../../i18n/language.service';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { formatDateTimeInTimeZone } from '../../utils/local-time';

@Component({
  selector: 'app-appointments',
  templateUrl: './appointments.page.html',
  styleUrls: ['./appointments.page.scss'],
})
export class AppointmentsPage implements OnInit, OnDestroy, ViewWillEnter {
  appointments: Appointment[] = [];
  loading = true;
  isAdmin = false;
  private destroy$ = new Subject<void>();
  private lastSelectedGymId: number | null = null;

  constructor(
    private apiService: ApiService,
    private authService: AuthService,
    private navCtrl: NavController,
    private alertController: AlertController,
    private toastController: ToastController,
    public language: LanguageService
  ) {}

  ngOnInit(): void {
    const role = (this.authService.getCurrentUser()?.role ?? '').toUpperCase();
    this.isAdmin = role.includes('ADMIN') || role.includes('SUPER');
    if (!this.isAdmin) return;

    this.lastSelectedGymId = this.authService.getSelectedGymId();
    this.authService.getSelectedGymIdStream()
      .pipe(takeUntil(this.destroy$))
      .subscribe(gymId => {
        if (gymId === this.lastSelectedGymId) return;
        this.lastSelectedGymId = gymId;
        this.loadAppointments();
      });
  }

  ionViewWillEnter(): void {
    this.loadAppointments();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadAppointments(): void {
    const role = (this.authService.getCurrentUser()?.role ?? '').toUpperCase();
    const isAdmin = role.includes('ADMIN') || role.includes('SUPER');
    const selectedGymId = this.authService.getSelectedGymId();
    if (isAdmin && selectedGymId == null) {
      this.appointments = [];
      this.loading = false;
      return;
    }

    this.loading = true;
    this.apiService
      .getAppointments(isAdmin ? selectedGymId : undefined)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (appointments) => {
          this.appointments = appointments;
          this.loading = false;
        },
        error: (error) => {
          console.error('Errore nel caricamento appuntamenti:', error);
          this.appointments = [];
          this.loading = false;
        },
      });
  }

  openBooking(): void {
    this.navCtrl.navigateForward('/book-appointment');
  }

  editAppointment(appointment: Appointment): void {
    this.navCtrl.navigateForward(`/book-appointment?edit=${appointment.id}`);
  }

  async cancelAppointment(appointment: Appointment): Promise<void> {
    const alert = await this.alertController.create({
      header: this.language.instant('appointments.cancelTitle'),
      message: `${appointment.customer_name} · ${appointment.service_name || this.language.instant('appointments.service')}`,
      buttons: [
        { text: this.language.instant('common.cancel'), role: 'cancel' },
        {
          text: this.language.instant('appointments.cancelAria'),
          role: 'destructive',
          handler: () => {
            this.apiService.cancelAppointment(appointment.id)
              .pipe(takeUntil(this.destroy$))
              .subscribe({
                next: () => {
                  this.appointments = this.appointments.filter(item => item.id !== appointment.id);
                  this.presentToast(this.language.instant('appointments.cancelled'), 'success');
                },
                error: () => this.presentToast(this.language.instant('appointments.cancelError'), 'danger'),
              });
          },
        },
      ],
    });
    await alert.present();
  }

  statusColor(status: Appointment['status']): string {
    if (status === 'confirmed' || status === 'scheduled') return 'success';
    if (status === 'cancelled') return 'medium';
    if (status === 'completed') return 'primary';
    return 'warning';
  }

  formatAppointmentTime(value: string, timeZone: string): string {
    return formatDateTimeInTimeZone(value, timeZone, this.language.currentLanguage);
  }

  private async presentToast(message: string, color: 'success' | 'danger'): Promise<void> {
    const toast = await this.toastController.create({ message, color, duration: 2000, position: 'bottom' });
    await toast.present();
  }
}
