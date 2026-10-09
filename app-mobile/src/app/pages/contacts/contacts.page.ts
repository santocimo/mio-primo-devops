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

  comuneSearch = '';
  comuniSuggestions: { label: string; value: string; codice: string }[] = [];
  showSuggestions = false;
  citySearchFailed = false;
  belfiore = '';

  selectedGymId: number | null = null;
  selectedGymName = '';
  isAdminUser = false;

  private destroy$ = new Subject<void>();
  private readonly CF_MONTHS = ['A', 'B', 'C', 'D', 'E', 'H', 'L', 'M', 'P', 'R', 'S', 'T'];
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
    this.resetComuneSearch();
    this.showModal = true;
  }

  openEdit(c: Contact): void {
    this.editingContact = c;
    this.formData = {
      nome: c.nome, cognome: c.cognome, codice_fiscale: c.codice_fiscale ?? '',
      data_nascita: c.data_nascita ?? '', luogo_nascita: c.luogo_nascita ?? '',
      indirizzo: c.indirizzo ?? '', recapito: c.recapito ?? '', sesso: c.sesso ?? '',
    };
    this.resetComuneSearch();
    this.comuneSearch = c.luogo_nascita ?? '';
    this.belfiore = this.extractBelfioreCode(c.codice_fiscale ?? '');
    this.showModal = true;
    if (this.comuneSearch.trim().length >= 2) {
      this.fetchComuniSuggestions(this.comuneSearch);
    }
  }

  closeModal(): void {
    this.showModal = false;
    this.editingContact = null;
    this.formData = this.emptyForm();
    this.resetComuneSearch();
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

  onComuneInput(event: CustomEvent<{ value?: string | null }>): void {
    const value = (event.detail?.value ?? '').toString();
    this.comuneSearch = value;
    this.formData.luogo_nascita = value;
    this.belfiore = '';
    this.formData.codice_fiscale = '';
    this.citySearchFailed = false;

    if (value.trim().length < 2) {
      this.comuniSuggestions = [];
      this.showSuggestions = false;
      return;
    }
    this.fetchComuniSuggestions(value);
  }

  selectComune(comune: { label: string; value: string; codice: string }): void {
    this.comuneSearch = comune.value;
    this.formData.luogo_nascita = comune.value;
    this.belfiore = comune.codice;
    this.comuniSuggestions = [];
    this.showSuggestions = false;
    this.citySearchFailed = false;
    this.updateCodiceFiscale();
  }

  onFormFieldChange(): void {
    this.updateCodiceFiscale();
  }

  isFormValid(): boolean {
    const codiceFiscale = this.formData.codice_fiscale.trim().toUpperCase();
    return Boolean(
      this.formData.nome.trim() &&
      this.formData.cognome.trim() &&
      this.isValidBirthDate(this.formData.data_nascita) &&
      ['M', 'F'].includes(this.formData.sesso) &&
      this.formData.luogo_nascita.trim() &&
      this.belfiore &&
      /^[A-Z0-9]{15}[A-Z]$/.test(codiceFiscale) &&
      codiceFiscale[15] === this.calcolaControllo(codiceFiscale.substring(0, 15))
    );
  }

  private fetchComuniSuggestions(term: string): void {
    this.apiService.searchComuni(term.trim()).pipe(takeUntil(this.destroy$)).subscribe({
      next: suggestions => {
        if (!this.showModal || this.comuneSearch.trim() !== term.trim()) return;
        if (!Array.isArray(suggestions)) {
          this.comuniSuggestions = [];
          this.showSuggestions = false;
          this.citySearchFailed = true;
          return;
        }
        this.comuniSuggestions = suggestions;
        this.showSuggestions = suggestions.length > 0;
        this.citySearchFailed = false;
        if (this.showSuggestions) {
          this.revealComuneSuggestions();
        }
      },
      error: () => {
        if (!this.showModal || this.comuneSearch.trim() !== term.trim()) return;
        this.comuniSuggestions = [];
        this.showSuggestions = false;
        this.citySearchFailed = true;
      },
    });
  }

  private updateCodiceFiscale(): void {
    const calculated = this.calculateCF();
    if (calculated) this.formData.codice_fiscale = calculated;
  }

  private calculateCF(): string {
    const firstName = this.getLetters(this.formData.nome, true);
    const lastName = this.getLetters(this.formData.cognome, false);
    const birthDate = this.formData.data_nascita;
    const gender = this.formData.sesso;
    if (!firstName || !lastName || !birthDate || !this.belfiore || !['M', 'F'].includes(gender)) return '';

    const parts = birthDate.split('-').map(Number);
    if (parts.length !== 3) return '';
    const [year, month, day] = parts;
    const date = new Date(Date.UTC(year, month - 1, day));
    if (
      !year || !month || !day ||
      date.getUTCFullYear() !== year ||
      date.getUTCMonth() !== month - 1 ||
      date.getUTCDate() !== day
    ) return '';

    const monthCode = this.CF_MONTHS[month - 1];
    if (!monthCode) return '';

    const encodedDay = day + (gender === 'F' ? 40 : 0);
    const first15 = (
      lastName +
      firstName +
      String(year).slice(-2).padStart(2, '0') +
      monthCode +
      String(encodedDay).padStart(2, '0') +
      this.belfiore
    ).toUpperCase();
    return first15 + this.calcolaControllo(first15);
  }

  private isValidBirthDate(value: string): boolean {
    const parts = value.split('-').map(Number);
    if (parts.length !== 3) return false;
    const [year, month, day] = parts;
    const date = new Date(Date.UTC(year, month - 1, day));
    return Boolean(
      year && month && day &&
      date.getUTCFullYear() === year &&
      date.getUTCMonth() === month - 1 &&
      date.getUTCDate() === day
    );
  }

  private getLetters(value: string, isName: boolean): string {
    const normalized = value
      .toUpperCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^A-Z]/g, '');
    if (!normalized) return '';
    const consonants = normalized.replace(/[AEIOU]/g, '');
    const vowels = normalized.replace(/[^AEIOU]/g, '');

    if (isName && consonants.length >= 4) {
      return consonants[0] + consonants[2] + consonants[3];
    }
    return (consonants + vowels + 'XXX').substring(0, 3);
  }

  private calcolaControllo(first15: string): string {
    const oddValues: Record<string, number> = {
      '0': 1, '1': 0, '2': 5, '3': 7, '4': 9, '5': 13, '6': 15, '7': 17, '8': 19, '9': 21,
      A: 1, B: 0, C: 5, D: 7, E: 9, F: 13, G: 15, H: 17, I: 19, J: 21, K: 2, L: 4,
      M: 18, N: 20, O: 11, P: 3, Q: 6, R: 8, S: 12, T: 14, U: 16, V: 10, W: 22,
      X: 25, Y: 24, Z: 23,
    };
    const evenValues: Record<string, number> = {
      '0': 0, '1': 1, '2': 2, '3': 3, '4': 4, '5': 5, '6': 6, '7': 7, '8': 8, '9': 9,
      A: 0, B: 1, C: 2, D: 3, E: 4, F: 5, G: 6, H: 7, I: 8, J: 9, K: 10, L: 11,
      M: 12, N: 13, O: 14, P: 15, Q: 16, R: 17, S: 18, T: 19, U: 20, V: 21, W: 22,
      X: 23, Y: 24, Z: 25,
    };
    let sum = 0;
    for (let index = 0; index < 15; index++) {
      const character = first15[index];
      sum += index % 2 === 0 ? oddValues[character] : evenValues[character];
    }
    return String.fromCharCode(65 + (sum % 26));
  }

  private extractBelfioreCode(code: string): string {
    const normalized = code.trim().toUpperCase();
    return /^[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]$/.test(normalized)
      ? normalized.substring(11, 15)
      : '';
  }

  private resetComuneSearch(): void {
    this.comuneSearch = '';
    this.comuniSuggestions = [];
    this.showSuggestions = false;
    this.citySearchFailed = false;
    this.belfiore = '';
  }

  private revealComuneSuggestions(): void {
    window.requestAnimationFrame(() => {
      document.querySelector<HTMLElement>('.modal-sheet .suggestions')
        ?.scrollIntoView({ block: 'end', behavior: 'smooth' });
    });
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
