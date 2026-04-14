import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { ApiService } from '../../services/api.service';
import { AuthService } from '../../services/auth.service';
import { Appointment, Gym } from '../../models/business.model';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';

@Component({
  selector: 'app-dashboard',
  templateUrl: './dashboard.page.html',
  styleUrls: ['./dashboard.page.scss'],
})
export class DashboardPage implements OnInit, OnDestroy {
  appointments: Appointment[] = [];
  upcomingAppointments: Appointment[] = [];
  gyms: Gym[] = [];
  userName = 'Guest';
  loading = true;
  private destroy$ = new Subject<void>();

  constructor(
    private apiService: ApiService,
    private authService: AuthService,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.loadDashboardData();
    const user = this.authService.getCurrentUser();
    if (user) {
      this.userName = user.name;
    }
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  private loadDashboardData(): void {
    this.apiService
      .getGyms()
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (gyms) => {
          this.gyms = gyms;
        },
        error: (error) => {
          console.error('Errore nel caricamento palestre:', error);
        },
      });

    this.apiService
      .getAppointments()
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (appointments) => {
          this.appointments = appointments;
          this.upcomingAppointments = appointments
            .filter((a) => a.status === 'scheduled')
            .slice(0, 3);
          this.loading = false;
        },
        error: (error) => {
          console.error('Errore nel caricamento appuntamenti:', error);
          this.loading = false;
        },
      });
  }

  goToBookAppointment(): void {
    this.router.navigate(['/book-appointment']);
  }

  goToAppointments(): void {
    this.router.navigate(['/appointments']);
  }

  logout(): void {
    this.authService.logout();
    this.router.navigate(['/login']);
  }
}
