import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { AlertController } from '@ionic/angular';
import { ApiService } from '../../services/api.service';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';

export interface AppUser {
  id: number;
  username: string;
  role: string;
  gym_id: number | null;
  created_at: string;
}

@Component({
  selector: 'app-admin-users',
  templateUrl: './admin-users.page.html',
  styleUrls: ['./admin-users.page.scss'],
})
export class AdminUsersPage implements OnInit, OnDestroy {
  users: AppUser[] = [];
  gyms: any[] = [];
  loading = true;
  showForm = false;
  editingUser: AppUser | null = null;
  formData = this.emptyForm();
  private destroy$ = new Subject<void>();

  constructor(
    private apiService: ApiService,
    private alertController: AlertController,
    public router: Router
  ) {}

  ngOnInit(): void {
    this.load();
    this.apiService.getGyms().pipe(takeUntil(this.destroy$)).subscribe({ next: g => (this.gyms = g), error: () => {} });
  }

  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    this.loading = true;
    this.apiService.getUsers().pipe(takeUntil(this.destroy$)).subscribe({
      next: u => { this.users = u; this.loading = false; },
      error: () => { this.loading = false; },
    });
  }

  openAdd(): void { this.editingUser = null; this.formData = this.emptyForm(); this.showForm = true; }

  openEdit(u: AppUser): void {
    this.editingUser = u;
    this.formData = { username: u.username, password: '', role: u.role, gym_id: u.gym_id };
    this.showForm = true;
  }

  closeForm(): void { this.showForm = false; this.editingUser = null; }

  save(): void {
    if (!this.formData.username) return;
    const call = this.editingUser
      ? this.apiService.updateUser(this.editingUser.id, this.formData)
      : this.apiService.createUser(this.formData);
    call.pipe(takeUntil(this.destroy$)).subscribe(() => { this.closeForm(); this.load(); });
  }

  async del(u: AppUser): Promise<void> {
    const alert = await this.alertController.create({
      header: 'Elimina', message: `Eliminare ${u.username}?`,
      buttons: [
        { text: 'Annulla', role: 'cancel' },
        { text: 'Elimina', role: 'destructive', handler: () => {
            this.apiService.deleteUser(u.id).pipe(takeUntil(this.destroy$)).subscribe(() => this.load());
          }
        },
      ],
    });
    await alert.present();
  }

  roleLabel(role: string): string {
    const r = (role || '').toUpperCase();
    if (r.includes('ADMIN') || r.includes('SUPER')) return 'Admin';
    return 'Operatore';
  }

  private emptyForm() { return { username: '', password: '', role: 'OPERATORE', gym_id: null as number | null }; }
}
