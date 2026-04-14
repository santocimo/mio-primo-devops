import { Component, OnInit, OnDestroy } from '@angular/core';
import { ApiService } from '../../services/api.service';
import { Appointment } from '../../models/business.model';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';

@Component({
  selector: 'app-appointments',
  templateUrl: './appointments.page.html',
  styleUrls: ['./appointments.page.scss'],
})
export class AppointmentsPage implements OnInit, OnDestroy {
  appointments: Appointment[] = [];
  loading = true;
  private destroy$ = new Subject<void>();

  constructor(private apiService: ApiService) {}

  ngOnInit(): void {
    this.loadAppointments();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  private loadAppointments(): void {
    this.apiService
      .getAppointments()
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (appointments) => {
          this.appointments = appointments;
          this.loading = false;
        },
        error: (error) => {
          console.error('Errore nel caricamento appuntamenti:', error);
          this.loading = false;
        },
      });
  }

  cancelAppointment(appointmentId: number): void {
    this.apiService
      .cancelAppointment(appointmentId)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: () => {
          this.appointments = this.appointments.filter((a) => a.id !== appointmentId);
        },
        error: (error) => {
          console.error('Errore nella cancellazione:', error);
        },
      });
  }
}
