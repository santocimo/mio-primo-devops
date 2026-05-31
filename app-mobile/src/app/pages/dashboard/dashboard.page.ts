import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { AlertController, ToastController } from '@ionic/angular';
import { ApiService } from '../../services/api.service';
import { AuthService } from '../../services/auth.service';
import { Contact, ContactStats } from '../../models/business.model';
import { SubscriptionStatus } from '../../models/auth.model';
import { Subject } from 'rxjs';
import { takeUntil, debounceTime, distinctUntilChanged } from 'rxjs/operators';
import { FormControl } from '@angular/forms';

@Component({
  selector: 'app-dashboard',
  templateUrl: './dashboard.page.html',
  styleUrls: ['./dashboard.page.scss'],
})
export class DashboardPage implements OnInit, OnDestroy {
  // Stats
  stats: ContactStats = { total: 0, men: 0, women: 0 };

  // Lista
  contacts: Contact[] = [];
  loading = true;

  // Ricerca
  searchCtrl = new FormControl('');

  // Form aggiunta/modifica
  showForm = false;
  editingContact: Contact | null = null;
  formData: Omit<Contact, 'id'> = this.emptyForm();

  // Sede selezionata
  selectedGymId: number | null = null;
  selectedGymName = '';

  // Trial
  trialDaysRemaining = 0;
  isInTrial = false;
  userName = 'Guest';

  private destroy$ = new Subject<void>();

  constructor(
    private apiService: ApiService,
    private authService: AuthService,
    private alertController: AlertController,
    private toastController: ToastController,
    public router: Router
  ) {}

  ngOnInit(): void {
    const user = this.authService.getCurrentUser();
    if (user) this.userName = user.name;

    const state = (this.authService as any).authState$.getValue();
    if (state.subscriptionStatus === SubscriptionStatus.TRIAL) {
      this.isInTrial = true;
      this.trialDaysRemaining = this.authService.getTrialDaysRemaining();
    }

    // Reagisce al cambio sede
    this.authService.getSelectedGymIdStream()
      .pipe(takeUntil(this.destroy$))
      .subscribe(gymId => {
        if (!this.authService.isLoggedIn()) {
          this.loading = false;
          return;
        }

        this.selectedGymId = gymId;
        this.selectedGymName = this.authService.getSelectedGymName();
        this.loadStats();
        this.loadContacts(this.searchCtrl.value ?? '');
        this.searchCtrl.setValue('', { emitEvent: false });
      });

    this.searchCtrl.valueChanges
      .pipe(debounceTime(300), distinctUntilChanged(), takeUntil(this.destroy$))
      .subscribe((q) => {
        if (!this.authService.isLoggedIn()) {
          return;
        }
        this.loadContacts(q ?? '');
      });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadStats(): void {
    if (!this.authService.isLoggedIn()) {
      return;
    }

    const isAdmin = this.isAdmin();
    const gymFilter = isAdmin ? null : this.selectedGymId;
    this.apiService.getContactStats(gymFilter).pipe(takeUntil(this.destroy$)).subscribe({
      next: (s) => (this.stats = s),
      error: () => { this.presentToast('Impossibile aggiornare le statistiche', 'warning'); },
    });
  }

  loadContacts(q: string = ''): void {
    if (!this.authService.isLoggedIn()) {
      this.loading = false;
      return;
    }

    this.loading = true;
    const isAdmin = this.isAdmin();
    const gymFilter = isAdmin ? null : this.selectedGymId;
    this.apiService.getContacts(q, gymFilter).pipe(takeUntil(this.destroy$)).subscribe({
      next: (c) => { this.contacts = c; this.loading = false; },
      error: () => { this.loading = false; this.presentToast('Errore nel caricamento contatti', 'danger'); },
    });
  }

  openAddForm(): void {
    this.editingContact = null;
    this.formData = this.emptyForm();
    this.showForm = true;
  }

  openEditForm(c: Contact): void {
    this.editingContact = c;
    this.formData = {
      nome: c.nome, cognome: c.cognome, codice_fiscale: c.codice_fiscale,
      data_nascita: c.data_nascita, luogo_nascita: c.luogo_nascita,
      indirizzo: c.indirizzo, recapito: c.recapito, sesso: c.sesso,
    };
    this.showForm = true;
  }

  closeForm(): void {
    this.showForm = false;
    this.editingContact = null;
    this.formData = this.emptyForm();
  }

  saveContact(): void {
    if (!this.formData.nome || !this.formData.cognome) return;
    if (this.editingContact) {
      this.apiService.updateContact(this.editingContact.id, this.formData)
        .pipe(takeUntil(this.destroy$)).subscribe({
          next: () => {
            this.closeForm(); this.loadStats(); this.loadContacts(this.searchCtrl.value ?? '');
            this.presentToast('Contatto aggiornato', 'success');
          },
          error: () => this.presentToast('Errore durante l\'aggiornamento', 'danger'),
        });
    } else {
      this.apiService.createContact(this.formData)
        .pipe(takeUntil(this.destroy$)).subscribe({
          next: () => {
            this.closeForm(); this.loadStats(); this.loadContacts(this.searchCtrl.value ?? '');
            this.presentToast('Contatto creato', 'success');
          },
          error: () => this.presentToast('Errore durante il salvataggio', 'danger'),
        });
    }
  }

  async confirmDelete(c: Contact): Promise<void> {
    const alert = await this.alertController.create({
      header: 'Elimina',
      message: `Eliminare ${c.nome} ${c.cognome}?`,
      buttons: [
        { text: 'Annulla', role: 'cancel' },
        {
          text: 'Elimina', role: 'destructive',
          handler: () => {
            this.apiService.deleteContact(c.id).pipe(takeUntil(this.destroy$)).subscribe({
              next: () => {
                this.loadStats(); this.loadContacts(this.searchCtrl.value ?? '');
                this.presentToast('Contatto eliminato', 'success');
              },
              error: () => this.presentToast('Errore durante l\'eliminazione', 'danger'),
            });
          },
        },
      ],
    });
    await alert.present();
  }

  logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/login', { replaceUrl: true });
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

  private emptyForm(): Omit<Contact, 'id'> {
    return { nome: '', cognome: '', codice_fiscale: '', data_nascita: '', luogo_nascita: '', indirizzo: '', recapito: '', sesso: 'M' };
  }

  private isAdmin(): boolean {
    const role = (this.authService.getCurrentUser()?.role ?? '').toUpperCase();
    return role.includes('ADMIN') || role.includes('SUPER');
  }
}
