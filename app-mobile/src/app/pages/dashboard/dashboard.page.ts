import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { AlertController } from '@ionic/angular';
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

  // Trial
  trialDaysRemaining = 0;
  isInTrial = false;
  userName = 'Guest';

  private destroy$ = new Subject<void>();

  constructor(
    private apiService: ApiService,
    private authService: AuthService,
    private alertController: AlertController,
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

    this.loadStats();
    this.loadContacts();

    this.searchCtrl.valueChanges
      .pipe(debounceTime(300), distinctUntilChanged(), takeUntil(this.destroy$))
      .subscribe((q) => this.loadContacts(q ?? ''));
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadStats(): void {
    this.apiService.getContactStats().pipe(takeUntil(this.destroy$)).subscribe({
      next: (s) => (this.stats = s),
      error: () => {},
    });
  }

  loadContacts(q: string = ''): void {
    this.loading = true;
    this.apiService.getContacts(q).pipe(takeUntil(this.destroy$)).subscribe({
      next: (c) => { this.contacts = c; this.loading = false; },
      error: () => { this.loading = false; },
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
        .pipe(takeUntil(this.destroy$)).subscribe(() => {
          this.closeForm(); this.loadStats(); this.loadContacts(this.searchCtrl.value ?? '');
        });
    } else {
      this.apiService.createContact(this.formData)
        .pipe(takeUntil(this.destroy$)).subscribe(() => {
          this.closeForm(); this.loadStats(); this.loadContacts(this.searchCtrl.value ?? '');
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
            this.apiService.deleteContact(c.id).pipe(takeUntil(this.destroy$)).subscribe(() => {
              this.loadStats(); this.loadContacts(this.searchCtrl.value ?? '');
            });
          },
        },
      ],
    });
    await alert.present();
  }

  logout(): void {
    this.authService.logout();
    this.router.navigate(['/login']);
  }

  private emptyForm(): Omit<Contact, 'id'> {
    return { nome: '', cognome: '', codice_fiscale: '', data_nascita: '', luogo_nascita: '', indirizzo: '', recapito: '', sesso: 'M' };
  }
}
