import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { AlertController, ToastController } from '@ionic/angular';
import { ApiService } from '../../services/api.service';
import { AuthService } from '../../services/auth.service';
import { Contact } from '../../models/business.model';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { LanguageService } from '../../i18n/language.service';

export interface AppointmentItem {
  id: number;
  service_id: number;
  gym_id: number;
  customer_name: string;
  customer_email: string;
  scheduled_at: string;
  status: string;
  notes: string;
  service_name: string;
  gym_name: string;
}

@Component({
  selector: 'app-admin-appointments',
  templateUrl: './admin-appointments.page.html',
  styleUrls: ['./admin-appointments.page.scss'],
})
export class AdminAppointmentsPage implements OnInit, OnDestroy {
  appointments: AppointmentItem[] = [];
  services: any[] = [];
  contacts: Contact[] = [];
  selectedGymId: number | null = null;
  loading = true;
  showForm = false;
  editingApt: AppointmentItem | null = null;
  formData = this.emptyForm();
  statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
  private destroy$ = new Subject<void>();

  constructor(
    private apiService: ApiService,
    private authService: AuthService,
    private alertController: AlertController,
    private toastController: ToastController,
    public language: LanguageService,
    public router: Router
  ) {}

  ngOnInit(): void {
    this.selectedGymId = this.authService.getSelectedGymId();
    this.authService.getSelectedGymIdStream().pipe(takeUntil(this.destroy$)).subscribe(gymId => {
      this.selectedGymId = gymId;
      this.loadServices();
      this.loadContacts();
      this.load();
    });
    this.loadServices();
    this.loadContacts();
    this.load();
  }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    if (!this.selectedGymId) {
      this.appointments = [];
      this.loading = false;
      return;
    }

    this.loading = true;
    this.apiService.getAllAppointments(this.selectedGymId).pipe(takeUntil(this.destroy$)).subscribe({
      next: a => { this.appointments = a; this.loading = false; },
      error: () => { this.loading = false; },
    });
  }

  private loadServices(): void {
    if (!this.selectedGymId) {
      this.services = [];
      return;
    }

    this.apiService.getAllServices(this.selectedGymId).pipe(takeUntil(this.destroy$)).subscribe({
      next: s => (this.services = s),
      error: () => { this.services = []; },
    });
  }

  private loadContacts(): void {
    if (!this.selectedGymId) {
      this.contacts = [];
      return;
    }

    this.apiService.getContacts('', this.selectedGymId).pipe(takeUntil(this.destroy$)).subscribe({
      next: c => (this.contacts = c),
      error: () => { this.contacts = []; },
    });
  }

  contactLabel(c: Contact): string {
    return `${c.nome} ${c.cognome}`.trim();
  }

  openAdd(): void { this.editingApt = null; this.formData = this.emptyForm(); this.showForm = true; }

  openEdit(a: AppointmentItem): void {
    this.editingApt = a;
    this.formData = {
      service_id: a.service_id,
      customer_name: a.customer_name,
      customer_email: a.customer_email,
      scheduled_at: this.toFormDateTime(a.scheduled_at),
      status: a.status,
      notes: a.notes,
    };
    this.showForm = true;
  }

  closeForm(): void { this.showForm = false; this.editingApt = null; }

  async save(): Promise<void> {
    if (!this.formData.customer_name || !this.formData.service_id || !this.formData.scheduled_at) {
      await this.presentToast(this.language.instant('adminAppointments.validation'), 'warning');
      return;
    }

    const payload = {
      ...this.formData,
      scheduled_at: this.toApiDateTime(this.formData.scheduled_at),
    };

    const call = this.editingApt
      ? this.apiService.updateAppointment(this.editingApt.id, payload)
      : this.apiService.createAppointmentAdmin(payload);
    call.pipe(takeUntil(this.destroy$)).subscribe({
      next: async () => {
        this.closeForm();
        this.load();
        await this.presentToast(this.language.instant('adminAppointments.saved'), 'success');
      },
      error: async () => {
        await this.presentToast(this.language.instant('adminAppointments.saveError'), 'danger');
      },
    });
  }

  async del(a: AppointmentItem): Promise<void> {
    const alert = await this.alertController.create({
      header: this.language.instant('common.delete'), message: this.language.instant('adminAppointments.deleteConfirm', { name: a.customer_name }),
      buttons: [
        { text: this.language.instant('common.cancel'), role: 'cancel' },
        { text: this.language.instant('common.delete'), role: 'destructive', handler: () => {
            this.apiService.deleteAppointment(a.id).pipe(takeUntil(this.destroy$)).subscribe(() => this.load());
          }
        },
      ],
    });
    await alert.present();
  }

  statusColor(status: string): string {
    switch (status) {
      case 'confirmed':  return 'success';
      case 'completed':  return 'primary';
      case 'cancelled':  return 'danger';
      default:           return 'warning';
    }
  }

  private emptyForm() {
    return { service_id: 0, customer_name: '', customer_email: '', scheduled_at: '', status: 'pending', notes: '' };
  }

  private toFormDateTime(value: string): string {
    if (!value) return '';
    const v = value.trim();
    if (v.includes('T')) return v.substring(0, 16);
    if (v.includes(' ')) return v.replace(' ', 'T').substring(0, 16);
    return v.substring(0, 16);
  }

  private toApiDateTime(value: string): string {
    if (!value) return '';
    const v = value.trim();
    if (v.includes('T')) {
      return `${v.replace('T', ' ')}:00`;
    }
    return v;
  }

  private async presentToast(message: string, color: 'success' | 'warning' | 'danger'): Promise<void> {
    const toast = await this.toastController.create({
      message,
      color,
      duration: 1800,
      position: 'bottom',
    });
    await toast.present();
  }
}
