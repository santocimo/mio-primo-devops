import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { AlertController, ToastController, ViewWillEnter } from '@ionic/angular';
import { Subject } from 'rxjs';
import { FormControl } from '@angular/forms';
import { takeUntil, debounceTime, distinctUntilChanged, finalize } from 'rxjs/operators';
import { ApiService } from '../../services/api.service';
import { AuthService } from '../../services/auth.service';
import { Contact } from '../../models/business.model';
import { LanguageService } from '../../i18n/language.service';

@Component({
  selector: 'app-contacts',
  templateUrl: './contacts.page.html',
  styleUrls: ['./contacts.page.scss'],
})
export class ContactsPage implements OnInit, OnDestroy, ViewWillEnter {
  contacts: Contact[] = [];
  loading = true;
  exporting = false;
  searchCtrl = new FormControl('');

  showModal = false;
  editingContact: Contact | null = null;
  formData = this.emptyForm();

  selectedGymId: number | null = null;
  selectedGymName = '';
  isAdminUser = false;

  private destroy$ = new Subject<void>();
  constructor(
    private apiService: ApiService,
    private authService: AuthService,
    private alertController: AlertController,
    private toastController: ToastController,
    public language: LanguageService,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    this.selectedGymId = this.authService.getSelectedGymId();
    this.selectedGymName = this.authService.getSelectedGymName();
    this.isAdminUser = this.isAdmin();

    // Reagisce al cambio sede anche se la pagina è già aperta
    this.authService.getSelectedGymIdStream()
      .pipe(takeUntil(this.destroy$))
      .subscribe(gymId => {
        this.selectedGymId = gymId;
        this.selectedGymName = this.authService.getSelectedGymName();
        this.loadContacts('');
        this.searchCtrl.setValue('', { emitEvent: false });
      });

    this.searchCtrl.valueChanges
      .pipe(debounceTime(300), distinctUntilChanged(), takeUntil(this.destroy$))
      .subscribe((q: string | null) => this.loadContacts(q ?? ''));
  }

  ionViewWillEnter(): void {
    this.selectedGymId = this.authService.getSelectedGymId();
    this.selectedGymName = this.authService.getSelectedGymName();
    this.loadContacts('');
    this.searchCtrl.setValue('', { emitEvent: false });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadContacts(q: string = ''): void {
    const assignedGymId = this.authService.getCurrentUser()?.gym_id ?? null;
    const gymFilter = this.selectedGymId ?? (this.isAdminUser ? null : assignedGymId);
    if (!this.isAdminUser && !gymFilter) {
      this.contacts = [];
      this.loading = false;
      this.cdr.detectChanges();
      return;
    }

    this.loading = true;
    // Operators fall back to their assigned gym while the selected-gym state restores.
    this.apiService.getContacts(q, gymFilter)
      .pipe(takeUntil(this.destroy$), finalize(() => { this.loading = false; this.cdr.detectChanges(); }))
      .subscribe({
        next: c => { this.contacts = c; this.cdr.detectChanges(); },
        error: () => { this.contacts = []; this.presentToast(this.language.instant('contacts.errorLoad'), 'danger'); },
      });
  }

  openAdd(): void {
    this.editingContact = null;
    this.formData = this.emptyForm();
    this.showModal = true;
  }

  openEdit(c: Contact): void {
    this.editingContact = c;
    this.formData = {
      nome: c.nome, cognome: c.cognome, codice_fiscale: c.codice_fiscale ?? '',
      data_nascita: c.data_nascita ?? '', luogo_nascita: c.luogo_nascita ?? '',
      indirizzo: c.indirizzo ?? '', recapito: c.recapito ?? '', sesso: c.sesso ?? '',
    };
    this.showModal = true;
  }

  closeModal(): void {
    this.showModal = false;
    this.editingContact = null;
    this.formData = this.emptyForm();
  }

  save(): void {
    if (!this.formData.nome || !this.formData.cognome) return;
    const data = {
      ...this.formData,
      codice_fiscale: this.formData.codice_fiscale.trim().toUpperCase(),
    };

    if (this.editingContact) {
      this.apiService.updateContact(this.editingContact.id, data)
        .pipe(takeUntil(this.destroy$)).subscribe({
          next: () => {
            this.closeModal();
            this.loadContacts(this.searchCtrl.value ?? '');
            this.presentToast(this.language.instant('contacts.updated'), 'success');
          },
          error: () => { this.presentToast(this.language.instant('contacts.errorUpdate'), 'danger'); },
        });
    } else {
      this.apiService.createContact(data)
        .pipe(takeUntil(this.destroy$)).subscribe({
          next: () => {
            this.closeModal();
            this.loadContacts(this.searchCtrl.value ?? '');
            this.presentToast(this.language.instant('contacts.created'), 'success');
          },
          error: () => { this.presentToast(this.language.instant('contacts.errorSave'), 'danger'); },
        });
    }
  }

  async confirmDelete(c: Contact): Promise<void> {
    const alert = await this.alertController.create({
      header: this.language.instant('contacts.deleteTitle'),
      message: `${c.nome} ${c.cognome}`,
      buttons: [
        { text: this.language.instant('common.cancel'), role: 'cancel' },
        {
          text: 'Elimina',
          role: 'destructive',
          handler: () => {
            this.apiService.deleteContact(c.id).pipe(takeUntil(this.destroy$)).subscribe({
              next: () => {
                this.loadContacts(this.searchCtrl.value ?? '');
                this.presentToast(this.language.instant('contacts.deleted'), 'success');
              },
              error: () => { this.presentToast(this.language.instant('contacts.errorDelete'), 'danger'); },
            });
          },
        },
      ],
    });
    await alert.present();
  }

  exportCsv(): void {
    if (!this.isAdminUser || this.exporting) return;

    this.exporting = true;
    this.apiService.exportContactsCsv(this.selectedGymId)
      .pipe(takeUntil(this.destroy$), finalize(() => { this.exporting = false; }))
      .subscribe({
        next: (blob) => {
          const url = URL.createObjectURL(blob);
          const link = document.createElement('a');
          const suffix = this.selectedGymName
            ? '_' + this.selectedGymName.toLowerCase().replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '')
            : '';
          link.href = url;
          link.download = `registro${suffix || ''}.csv`;
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
          URL.revokeObjectURL(url);
          this.presentToast(this.language.instant('contacts.csvExported'), 'success');
        },
        error: () => { this.presentToast(this.language.instant('contacts.errorCsv'), 'danger'); },
      });
  }

  formatDate(d: string | null): string {
    if (!d) return '';
    const parts = d.split('-');
        if (parts.length === 3) {
          return this.language.currentLanguage === 'en'
            ? `${parts[1]}/${parts[2]}/${parts[0]}`
            : `${parts[2]}/${parts[1]}/${parts[0]}`;
        }
    return d;
  }

  isAdmin(): boolean {
    const role = (this.authService.getCurrentUser()?.role ?? '').toUpperCase();
    return role.includes('ADMIN') || role.includes('SUPER');
  }

  currentUser() {
    return this.authService.getCurrentUser();
  }

  private async presentToast(message: string, color: 'success' | 'danger'): Promise<void> {
    const toast = await this.toastController.create({
      message,
      color,
      duration: 1800,
      position: 'bottom',
    });
    await toast.present();
  }

  private emptyForm() {
    return { nome: '', cognome: '', codice_fiscale: '', data_nascita: '', luogo_nascita: '', indirizzo: '', recapito: '', sesso: '' as 'M' | 'F' | '' };
  }
}
