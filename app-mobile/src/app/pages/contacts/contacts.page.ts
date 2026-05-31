import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { AlertController, ToastController, ViewWillEnter } from '@ionic/angular';
import { Subject } from 'rxjs';
import { FormControl } from '@angular/forms';
import { takeUntil, debounceTime, distinctUntilChanged, finalize } from 'rxjs/operators';
import { ApiService } from '../../services/api.service';
import { AuthService } from '../../services/auth.service';
import { Contact } from '../../models/business.model';

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

  // Comune autocomplete
  comuneSearch = '';
  comuniSuggestions: { label: string; value: string; codice: string }[] = [];
  showSuggestions = false;
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
    this.loading = true;
    // Admin vede tutti i contatti (nessun filtro gym); operatori filtrano sulla propria sede
    const gymFilter = this.isAdminUser ? null : this.selectedGymId;
    this.apiService.getContacts(q, gymFilter)
      .pipe(takeUntil(this.destroy$), finalize(() => { this.loading = false; this.cdr.detectChanges(); }))
      .subscribe({
        next: c => { this.contacts = c; this.cdr.detectChanges(); },
        error: () => { this.contacts = []; this.presentToast('Errore nel caricamento contatti', 'danger'); },
      });
  }

  openAdd(): void {
    this.editingContact = null;
    this.formData = this.emptyForm();
    this.comuneSearch = '';
    this.belfiore = '';
    this.comuniSuggestions = [];
    this.showSuggestions = false;
    this.showModal = true;
  }

  openEdit(c: Contact): void {
    this.editingContact = c;
    this.formData = {
      nome: c.nome, cognome: c.cognome, codice_fiscale: c.codice_fiscale,
      data_nascita: c.data_nascita, luogo_nascita: c.luogo_nascita,
      indirizzo: c.indirizzo, recapito: c.recapito, sesso: c.sesso,
    };
    this.comuneSearch = c.luogo_nascita;
    this.belfiore = '';
    this.comuniSuggestions = [];
    this.showSuggestions = false;
    if (this.comuneSearch.trim().length >= 2) {
      this.fetchComuniSuggestions(this.comuneSearch);
    }
    this.showModal = true;
  }

  closeModal(): void {
    this.showModal = false;
    this.editingContact = null;
    this.formData = this.emptyForm();
    this.comuneSearch = '';
    this.belfiore = '';
  }

  save(): void {
    if (!this.formData.nome || !this.formData.cognome) return;
    const cf = this.belfiore ? this.calculateCF() : (this.formData.codice_fiscale || '');
    const data = { ...this.formData, codice_fiscale: cf.toUpperCase() };

    if (this.editingContact) {
      this.apiService.updateContact(this.editingContact.id, data)
        .pipe(takeUntil(this.destroy$)).subscribe({
          next: () => {
            this.closeModal();
            this.loadContacts(this.searchCtrl.value ?? '');
            this.presentToast('Contatto aggiornato', 'success');
          },
          error: () => { this.presentToast('Errore durante l\'aggiornamento', 'danger'); },
        });
    } else {
      this.apiService.createContact(data)
        .pipe(takeUntil(this.destroy$)).subscribe({
          next: () => {
            this.closeModal();
            this.loadContacts(this.searchCtrl.value ?? '');
            this.presentToast('Contatto creato', 'success');
          },
          error: () => { this.presentToast('Errore durante il salvataggio', 'danger'); },
        });
    }
  }

  async confirmDelete(c: Contact): Promise<void> {
    const alert = await this.alertController.create({
      header: 'Eliminare?',
      message: `${c.nome} ${c.cognome}`,
      buttons: [
        { text: 'Annulla', role: 'cancel' },
        {
          text: 'Elimina',
          role: 'destructive',
          handler: () => {
            this.apiService.deleteContact(c.id).pipe(takeUntil(this.destroy$)).subscribe({
              next: () => {
                this.loadContacts(this.searchCtrl.value ?? '');
                this.presentToast('Contatto eliminato', 'success');
              },
              error: () => { this.presentToast('Errore durante l\'eliminazione', 'danger'); },
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
          this.presentToast('CSV esportato con successo', 'success');
        },
        error: () => { this.presentToast('Errore durante l\'export CSV', 'danger'); },
      });
  }

  onComuneInput(ev: any): void {
    const val = (ev?.detail?.value ?? ev?.target?.value ?? '').toString();
    this.comuneSearch = val;
    this.formData.luogo_nascita = val;
    this.belfiore = '';
    if (val.length < 2) {
      this.comuniSuggestions = [];
      this.showSuggestions = false;
      return;
    }
    this.fetchComuniSuggestions(val);
  }

  selectComune(s: { label: string; value: string; codice: string }): void {
    this.comuneSearch = s.value;
    this.formData.luogo_nascita = s.value;
    this.belfiore = s.codice;
    this.comuniSuggestions = [];
    this.showSuggestions = false;
    this.formData.codice_fiscale = this.calculateCF();
  }

  onFormFieldChange(): void {
    if (this.belfiore) {
      this.formData.codice_fiscale = this.calculateCF();
    }
  }

  formatDate(d: string): string {
    if (!d) return '';
    const parts = d.split('-');
    if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
    return d;
  }

  isAdmin(): boolean {
    const role = (this.authService.getCurrentUser()?.role ?? '').toUpperCase();
    return role.includes('ADMIN') || role.includes('SUPER');
  }

  private fetchComuniSuggestions(term: string): void {
    this.apiService.searchComuni(term).pipe(takeUntil(this.destroy$)).subscribe({
      next: s => {
        this.comuniSuggestions = s;
        this.showSuggestions = s.length > 0;
      },
      error: () => {
        this.comuniSuggestions = [];
        this.showSuggestions = false;
      },
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
    return { nome: '', cognome: '', codice_fiscale: '', data_nascita: '', luogo_nascita: '', indirizzo: '', recapito: '', sesso: 'M' as 'M' | 'F' };
  }

  private calculateCF(): string {
    const n = this.formData.nome;
    const c = this.formData.cognome;
    const d = this.formData.data_nascita;
    const s = this.formData.sesso;
    if (!n || !c || !d || d.length < 10 || !this.belfiore) return this.formData.codice_fiscale;
    const parts = d.split('-');
    const anno = parts[0].slice(-2);
    const mese = this.CF_MONTHS[parseInt(parts[1]) - 1];
    let gg = parseInt(parts[2]);
    if (s === 'F') gg += 40;
    let cf = this.getLetters(c, false) + this.getLetters(n, true) + anno + mese + gg.toString().padStart(2, '0') + this.belfiore;
    cf = cf.toUpperCase();
    return cf + this.calcolaControllo(cf);
  }

  private getLetters(str: string, isName: boolean): string {
    const s = str.toUpperCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^A-Z]/g, '');
    const c = s.replace(/[AEIOU]/g, '');
    const v = s.replace(/[^AEIOU]/g, '');
    if (isName && c.length >= 4) return c[0] + c[2] + c[3];
    return (c + v + 'XXX').substring(0, 3);
  }

  private calcolaControllo(cf15: string): string {
    const d: Record<string, number> = { '0': 1, '1': 0, '2': 5, '3': 7, '4': 9, '5': 13, '6': 15, '7': 17, '8': 19, '9': 21, A: 1, B: 0, C: 5, D: 7, E: 9, F: 13, G: 15, H: 17, I: 19, J: 21, K: 2, L: 4, M: 18, N: 20, O: 11, P: 3, Q: 6, R: 8, S: 12, T: 14, U: 16, V: 10, W: 22, X: 25, Y: 24, Z: 23 };
    const p: Record<string, number> = { '0': 0, '1': 1, '2': 2, '3': 3, '4': 4, '5': 5, '6': 6, '7': 7, '8': 8, '9': 9, A: 0, B: 1, C: 2, D: 3, E: 4, F: 5, G: 6, H: 7, I: 8, J: 9, K: 10, L: 11, M: 12, N: 13, O: 14, P: 15, Q: 16, R: 17, S: 18, T: 19, U: 20, V: 21, W: 22, X: 23, Y: 24, Z: 25 };
    let sum = 0;
    for (let i = 0; i < 15; i++) sum += ((i + 1) % 2 !== 0) ? (d[cf15[i]] ?? 0) : (p[cf15[i]] ?? 0);
    return String.fromCharCode(65 + (sum % 26));
  }
}
