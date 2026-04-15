import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { AlertController } from '@ionic/angular';
import { ApiService } from '../../services/api.service';
import { Gym } from '../../models/business.model';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';

@Component({
  selector: 'app-admin-gyms',
  templateUrl: './admin-gyms.page.html',
  styleUrls: ['./admin-gyms.page.scss'],
})
export class AdminGymsPage implements OnInit, OnDestroy {
  gyms: Gym[] = [];
  loading = true;
  showForm = false;
  editingGym: Gym | null = null;
  formData = this.emptyForm();
  categories = ['gym', 'salon', 'studio', 'other'];
  private destroy$ = new Subject<void>();

  constructor(
    private apiService: ApiService,
    private alertController: AlertController,
    public router: Router
  ) {}

  ngOnInit(): void { this.load(); }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    this.loading = true;
    this.apiService.getGyms().pipe(takeUntil(this.destroy$)).subscribe({
      next: g => { this.gyms = g; this.loading = false; },
      error: () => { this.loading = false; },
    });
  }

  openAdd(): void { this.editingGym = null; this.formData = this.emptyForm(); this.showForm = true; }

  openEdit(g: Gym): void {
    this.editingGym = g;
    this.formData = { name: g.name, slug: (g as any).slug ?? '', category: g.category };
    this.showForm = true;
  }

  closeForm(): void { this.showForm = false; this.editingGym = null; }

  save(): void {
    if (!this.formData.name) return;
    if (!this.formData.slug) {
      this.formData.slug = this.formData.name.toLowerCase().replace(/[^a-z0-9]+/g, '-');
    }
    const call = this.editingGym
      ? this.apiService.updateGym(this.editingGym.id, this.formData)
      : this.apiService.createGym(this.formData);
    call.pipe(takeUntil(this.destroy$)).subscribe(() => { this.closeForm(); this.load(); });
  }

  async del(g: Gym): Promise<void> {
    const alert = await this.alertController.create({
      header: 'Elimina', message: `Eliminare ${g.name}?`,
      buttons: [
        { text: 'Annulla', role: 'cancel' },
        { text: 'Elimina', role: 'destructive', handler: () => {
            this.apiService.deleteGym(g.id).pipe(takeUntil(this.destroy$)).subscribe(() => this.load());
          }
        },
      ],
    });
    await alert.present();
  }

  private emptyForm() { return { name: '', slug: '', category: 'gym' as Gym['category'] }; }
}
