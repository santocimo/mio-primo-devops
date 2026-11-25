import { Component, OnDestroy, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { NavController, ToastController } from '@ionic/angular';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { ApiService } from '../../services/api.service';
import { AuthService } from '../../services/auth.service';
import { Appointment, Contact, Service } from '../../models/business.model';
import { LanguageService } from '../../i18n/language.service';
import { apiDateTimeToLocalInput, getDeviceTimeZone, localDateTimeToUtcIso } from '../../utils/local-time';

@Component({
  selector: 'app-book-appointment',
  templateUrl: './book-appointment.page.html',
  styleUrls: ['./book-appointment.page.scss'],
})
export class BookAppointmentPage implements OnInit, OnDestroy {
  contacts: Contact[] = [];
  services: Service[] = [];
  gymId: number | null = null;
  selectedContactId: number | null = null;
  selectedServiceId: number | null = null;
  editingAppointmentId: number | null = null;
  status: Appointment['status'] = 'pending';
  date = '';
  time = '';
  notes = '';
  loading = true;
  saving = false;
  minDate = this.localToday();

  private destroy$ = new Subject<void>();
  private editAppointmentLoaded = false;

  constructor(
    private api: ApiService,
    private auth: AuthService,
    private route: ActivatedRoute,
    private navCtrl: NavController,
    private toastController: ToastController,
    private language: LanguageService
  ) {}

  ngOnInit(): void {
    this.editingAppointmentId = Number(this.route.snapshot.queryParamMap.get('edit')) || null;

    this.auth.getSelectedGymIdStream()
      .pipe(takeUntil(this.destroy$))
      .subscribe(selectedGymId => {
        const user = this.auth.getCurrentUser();
        const role = (user?.role ?? '').toUpperCase();
        const isAdmin = role.includes('ADMIN') || role.includes('SUPER');
        const assignedGymId = user?.gym_id ?? null;
        const gymId = selectedGymId ?? (isAdmin ? null : assignedGymId);
        if (gymId === this.gymId) return;
        this.gymId = gymId;
        this.selectedContactId = null;
        this.selectedServiceId = null;
        this.loadOptions();
        if (this.editingAppointmentId) {
          this.loadAppointmentForEdit(gymId);
        }
      });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  get selectedService(): Service | undefined {
    return this.services.find(service => service.id === this.selectedServiceId);
  }

  contactLabel(contact: Contact): string {
    return `${contact.nome} ${contact.cognome}`.trim();
  }

  serviceModeLabel(service: Service): string {
    const category = (service.category ?? '').toLowerCase();
    return ['course', 'class', 'group', 'corso', 'gruppo'].some(value => category.includes(value))
      ? 'services.category.course'
      : 'services.category.appointment';
  }

  providerTypeLabel(providerType?: string | null): string {
    return (providerType ?? 'internal').toLowerCase() === 'external' ? 'services.external' : 'services.internal';
  }

  canBook(): boolean {
    return !this.loading && !this.saving && !!this.gymId
      && !!this.selectedContactId && !!this.selectedServiceId
      && !!this.date && !!this.time;
  }

  book(): void {
    if (!this.canBook()) return;
    this.saving = true;
    let scheduledAt: string;
    let scheduledTimezone: string;
    try {
      scheduledAt = localDateTimeToUtcIso(`${this.date}T${this.time}`);
      scheduledTimezone = getDeviceTimeZone();
    } catch {
      this.saving = false;
      void this.presentToast(this.language.instant('booking.invalidLocalTime'), 'danger');
      return;
    }
    const data = {
      contact_id: this.selectedContactId!,
      service_id: this.selectedServiceId!,
      scheduled_at: scheduledAt,
      scheduled_timezone: scheduledTimezone,
      notes: this.notes.trim(),
      status: this.status,
    };
    const request = this.editingAppointmentId
      ? this.api.updateAppointment(this.editingAppointmentId, data)
      : this.api.createAppointment(data);

    request.pipe(takeUntil(this.destroy$)).subscribe({
      next: async () => {
        await this.presentToast(this.language.instant(this.editingAppointmentId ? 'booking.updated' : 'booking.registered'), 'success');
        this.navCtrl.navigateRoot('/appointments');
      },
      error: async err => {
        this.saving = false;
        await this.presentToast(err?.error?.message || this.language.instant('booking.saveError'), 'danger');
      },
    });
  }

  private loadAppointmentForEdit(gymId: number | null): void {
    if (this.editAppointmentLoaded || !gymId) return;
    this.editAppointmentLoaded = true;
    this.api.getAppointments(gymId).pipe(takeUntil(this.destroy$)).subscribe({
      next: appointments => {
        const appointment = appointments.find(item => item.id === this.editingAppointmentId);
        if (!appointment) {
          void this.presentToast(this.language.instant('booking.notFound'), 'danger');
          return;
        }
        this.selectedContactId = appointment.contact_id ?? null;
        this.selectedServiceId = appointment.service_id;
        const localDateTime = apiDateTimeToLocalInput(appointment.scheduled_at);
        this.date = localDateTime.slice(0, 10);
        this.time = localDateTime.slice(11, 16);
        this.notes = appointment.notes ?? '';
        this.status = appointment.status;
      },
      error: () => void this.presentToast(this.language.instant('booking.loadError'), 'danger'),
    });
  }

  private loadOptions(): void {
    if (!this.gymId) {
      this.contacts = [];
      this.services = [];
      this.loading = false;
      return;
    }

    this.loading = true;
    let pending = 2;
    const finishLoad = () => {
      pending -= 1;
      if (pending === 0) this.loading = false;
    };

    this.api.getContacts('', this.gymId).pipe(takeUntil(this.destroy$)).subscribe({
      next: contacts => { this.contacts = contacts; finishLoad(); },
      error: () => { this.contacts = []; finishLoad(); },
    });
    this.api.getServices(this.gymId).pipe(takeUntil(this.destroy$)).subscribe({
      next: services => { this.services = services; finishLoad(); },
      error: () => { this.services = []; finishLoad(); },
    });
  }

  private localToday(): string {
    const now = new Date();
    return new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
  }

  private async presentToast(message: string, color: 'success' | 'danger'): Promise<void> {
    const toast = await this.toastController.create({ message, color, duration: 2400, position: 'bottom' });
    await toast.present();
  }
}