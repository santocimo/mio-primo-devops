import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { AlertController } from '@ionic/angular';
import { ApiService } from '../../services/api.service';
import { Gym } from '../../models/business.model';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';

export interface ServiceItem {
  id: number;
  name: string;
  slug: string;
  gym_id: number;
  gym_name: string;
  category: string;
  duration_minutes: number;
  capacity: number;
  price: number | null;
  description: string;
}

@Component({
  selector: 'app-admin-services',
  templateUrl: './admin-services.page.html',
  styleUrls: ['./admin-services.page.scss'],
})
export class AdminServicesPage implements OnInit, OnDestroy {
  services: ServiceItem[] = [];
  gyms: Gym[] = [];
  loading = true;
  showForm = false;
  editingService: ServiceItem | null = null;
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
    this.apiService.getAllServices().pipe(takeUntil(this.destroy$)).subscribe({
      next: s => { this.services = s; this.loading = false; },
      error: () => { this.loading = false; },
    });
  }

  openAdd(): void { this.editingService = null; this.formData = this.emptyForm(); this.showForm = true; }

  openEdit(s: ServiceItem): void {
    this.editingService = s;
    this.formData = { name: s.name, slug: s.slug, gym_id: s.gym_id, category: s.category, duration_minutes: s.duration_minutes, capacity: s.capacity, price: s.price, description: s.description };
    this.showForm = true;
  }

  closeForm(): void { this.showForm = false; this.editingService = null; }

  save(): void {
    if (!this.formData.name || !this.formData.gym_id) return;
    const call = this.editingService
      ? this.apiService.updateService(this.editingService.id, this.formData)
      : this.apiService.createService(this.formData);
    call.pipe(takeUntil(this.destroy$)).subscribe(() => { this.closeForm(); this.load(); });
  }

  async del(s: ServiceItem): Promise<void> {
    const alert = await this.alertController.create({
      header: 'Elimina', message: `Eliminare ${s.name}?`,
      buttons: [
        { text: 'Annulla', role: 'cancel' },
        { text: 'Elimina', role: 'destructive', handler: () => {
            this.apiService.deleteService(s.id).pipe(takeUntil(this.destroy$)).subscribe(() => this.load());
          }
        },
      ],
    });
    await alert.present();
  }

  private emptyForm() {
    return { name: '', slug: '', gym_id: 0, category: 'general', duration_minutes: 60, capacity: 1, price: null as number | null, description: '' };
  }
}
