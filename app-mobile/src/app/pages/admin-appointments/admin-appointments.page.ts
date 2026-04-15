import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { AlertController } from '@ionic/angular';
import { ApiService } from '../../services/api.service';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';

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
  loading = true;
  showForm = false;
  editingApt: AppointmentItem | null = null;
  formData = this.emptyForm();
  statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
  private destroy$ = new Subject<void>();

  constructor(
    private apiService: ApiService,
    private alertController: AlertController,
    public router: Router
  ) {}

  ngOnInit(): void {
    this.load();
    this.apiService.getAllServices().pipe(takeUntil(this.destroy$)).subscribe({ next: s => (this.services = s), error: () => {} });
  }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    this.loading = true;
    this.apiService.getAllAppointments().pipe(takeUntil(this.destroy$)).subscribe({
      next: a => { this.appointments = a; this.loading = false; },
      error: () => { this.loading = false; },
    });
  }

  openAdd(): void { this.editingApt = null; this.formData = this.emptyForm(); this.showForm = true; }

  openEdit(a: AppointmentItem): void {
    this.editingApt = a;
    this.formData = { service_id: a.service_id, customer_name: a.customer_name, customer_email: a.customer_email, scheduled_at: a.scheduled_at, status: a.status, notes: a.notes };
    this.showForm = true;
  }

  closeForm(): void { this.showForm = false; this.editingApt = null; }

  save(): void {
    if (!this.formData.customer_name || !this.formData.service_id) return;
    const call = this.editingApt
      ? this.apiService.updateAppointment(this.editingApt.id, this.formData)
      : this.apiService.createAppointmentAdmin(this.formData);
    call.pipe(takeUntil(this.destroy$)).subscribe(() => { this.closeForm(); this.load(); });
  }

  async del(a: AppointmentItem): Promise<void> {
    const alert = await this.alertController.create({
      header: 'Elimina', message: `Eliminare appuntamento di ${a.customer_name}?`,
      buttons: [
        { text: 'Annulla', role: 'cancel' },
        { text: 'Elimina', role: 'destructive', handler: () => {
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
}
