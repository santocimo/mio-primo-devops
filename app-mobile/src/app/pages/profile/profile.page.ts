import { Component, OnDestroy, OnInit } from '@angular/core';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { AuthService } from '../../services/auth.service';
import { ApiService } from '../../services/api.service';
import { Gym } from '../../models/business.model';
import { User } from '../../models/auth.model';
import { NavController } from '@ionic/angular';

@Component({
  selector: 'app-profile',
  templateUrl: './profile.page.html',
  styleUrls: ['./profile.page.scss'],
})
export class ProfilePage implements OnInit, OnDestroy {
  user?: User;
  activities: Gym[] = [];
  loading = true;
  loadError = false;
  private destroy$ = new Subject<void>();

  constructor(
    private auth: AuthService,
    private api: ApiService,
    private nav: NavController
  ) {}

  ngOnInit(): void {
    this.user = this.auth.getCurrentUser();
    this.loadActivities();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadActivities(): void {
    this.loading = true;
    this.loadError = false;
    this.api.getGyms(true)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: activities => {
          this.activities = activities;
          this.loading = false;
        },
        error: () => {
          this.loadError = true;
          this.loading = false;
        },
      });
  }

  isAdmin(): boolean {
    const role = (this.user?.role ?? '').toUpperCase();
    return role.includes('ADMIN') || role.includes('SUPER');
  }

  categoryKey(activity: Gym): string {
    return `gym.category.${['gym', 'salon', 'studio', 'other'].includes(activity.category) ? activity.category : 'other'}`;
  }

  requestAccountDeletion(): void {
    this.nav.navigateForward('/account-deletion');
  }
}
