import { Component, NgZone, OnInit, OnDestroy, ChangeDetectionStrategy } from '@angular/core';
import { App as CapacitorApp } from '@capacitor/app';
import { Browser } from '@capacitor/browser';
import { Capacitor, PluginListenerHandle } from '@capacitor/core';
import { Router } from '@angular/router';
import { MenuController, NavController } from '@ionic/angular';
import { AuthService } from './services/auth.service';
import { ApiService } from './services/api.service';
import { Observable } from 'rxjs';
import { distinctUntilChanged, map } from 'rxjs/operators';
import { AuthState } from './models/auth.model';
import { Gym, isSalonCategory } from './models/business.model';
import { AppLanguage, LanguageService } from './i18n/language.service';
import { TerminologyService } from './services/terminology.service';

@Component({
    selector: 'app-root',
    templateUrl: 'app.component.html',
    styleUrls: ['app.component.scss'],
    changeDetection: ChangeDetectionStrategy.Eager,
    standalone: false
})
export class AppComponent implements OnInit, OnDestroy {
  authState$!: Observable<AuthState>;
  gyms: Gym[] = [];
  gymsLoading = false;
  gymsLoadError = false;
  selectedGymId: number | null = null;
  selectedGymName = '';
  currentLanguage: AppLanguage = 'it';
  private deepLinkListener?: PluginListenerHandle;
  private readonly onBusinessTypeChanged = () => {
    if (this.authService.isLoggedIn()) {
      this.loadGyms();
    }
  };

  constructor(
    public authService: AuthService,
    private apiService: ApiService,
    private languageService: LanguageService,
    private menuCtrl: MenuController,
    private navCtrl: NavController,
    private zone: NgZone,
    public router: Router,
    private terminologyService: TerminologyService
  ) {}

  ngOnInit(): void {
    this.authState$ = this.authService.getAuthState();
    this.currentLanguage = this.languageService.currentLanguage;
    this.selectedGymId = this.authService.getSelectedGymId();
    this.selectedGymName = this.authService.getSelectedGymName();

    // Reagisce SOLO quando isLoggedIn cambia, non ad ogni aggiornamento di authState
    this.authState$.pipe(
      map(s => s.isLoggedIn),
      distinctUntilChanged()
    ).subscribe(isLoggedIn => {
      if (isLoggedIn) {
        this.selectedGymId = this.authService.getSelectedGymId();
        this.selectedGymName = this.authService.getSelectedGymName();
        this.loadGyms();
      } else {
        this.gyms = [];
        this.gymsLoading = false;
        this.gymsLoadError = false;
        this.selectedGymId = null;
        this.selectedGymName = '';
      }
    });

    window.addEventListener('business-type-changed', this.onBusinessTypeChanged);
    this.listenForDeepLinks();
  }

  // Dopo il checkout nel browser, businessregistry://subscribe?... riporta nell'app
  private listenForDeepLinks(): void {
    if (!Capacitor.isNativePlatform()) {
      return;
    }
    void CapacitorApp.addListener('appUrlOpen', ({ url }) => {
      const parsed = new URL(url);
      if (parsed.protocol !== 'businessregistry:') {
        return;
      }
      const path = parsed.hostname === 'subscribe' || parsed.hostname === 'paywall'
        ? `/${parsed.hostname}`
        : null;
      if (!path) {
        return;
      }
      void Browser.close().catch(() => undefined);
      const queryParams: Record<string, string> = {};
      parsed.searchParams.forEach((value, key) => (queryParams[key] = value));
      this.zone.run(() => {
        void this.router.navigate([path], { queryParams });
      });
    }).then((handle) => (this.deepLinkListener = handle));
  }

  ngOnDestroy(): void {
    window.removeEventListener('business-type-changed', this.onBusinessTypeChanged);
    void this.deepLinkListener?.remove();
  }

  loadGyms(): void {
    const isAdmin = this.isAdmin();
    this.gymsLoading = true;
    this.gymsLoadError = false;
    this.apiService.getGyms(isAdmin).subscribe({
      next: (gyms) => {
        this.gyms = gyms;
        this.gymsLoading = false;
        const saved = this.authService.getSelectedGymId();
        const savedStillVisible = saved != null ? gyms.some(g => g.id === saved) : false;
        const userGymId = this.authService.getCurrentUser()?.gym_id ?? null;

        if (savedStillVisible) {
          this.selectedGymId = saved;
          this.selectedGymName = gyms.find(g => g.id === saved)?.name ?? '';
          this.authService.setSelectedGymName(this.selectedGymName);
          this.authService.setSelectedGymCategory(gyms.find(g => g.id === saved)?.category ?? null);
          return;
        }

        if (!isAdmin && userGymId != null && gyms.some(g => g.id === userGymId)) {
          this.selectedGymId = userGymId;
          const gym = gyms.find(g => g.id === userGymId);
          this.selectedGymName = gym?.name ?? '';
          this.authService.setSelectedGymName(this.selectedGymName);
          this.authService.setSelectedGymId(this.selectedGymId);
          this.authService.setSelectedGymCategory(gym?.category ?? null);
          return;
        }

        if (isAdmin) {
          const firstGym = gyms[0];
          this.selectedGymId = firstGym?.id ?? null;
          this.selectedGymName = firstGym?.name ?? '';
          this.authService.setSelectedGymName(this.selectedGymName);
          this.authService.setSelectedGymId(this.selectedGymId);
          this.authService.setSelectedGymCategory(firstGym?.category ?? null);
          return;
        }

        if (gyms.length > 0) {
          this.selectedGymId = gyms[0].id;
          this.selectedGymName = gyms[0].name;
          this.authService.setSelectedGymName(gyms[0].name);
          this.authService.setSelectedGymId(gyms[0].id);
          this.authService.setSelectedGymCategory(gyms[0].category);
        } else {
          this.selectedGymId = null;
          this.selectedGymName = '';
          this.authService.setSelectedGymName('');
          this.authService.setSelectedGymId(null);
          this.authService.setSelectedGymCategory(null);
        }
      },
      error: () => {
        this.gymsLoading = false;
        this.gymsLoadError = true;
      },
    });
  }

  onGymChange(gymId: number | null): void {
    if (this.isAdmin() && (gymId == null || !this.gyms.some(gym => gym.id === gymId))) {
      return;
    }
    this.selectedGymId = gymId;
    const gym = this.gyms.find(g => g.id === gymId);
    this.selectedGymName = gym?.name ?? '';
    this.authService.setSelectedGymName(gym?.name ?? '');
    this.authService.setSelectedGymId(gymId);
    this.authService.setSelectedGymCategory(gym?.category ?? null);
  }

  get contactsNavLabelKey(): string {
    const category = this.gyms.find(gym => gym.id === this.selectedGymId)?.category;
    return this.terminologyService.getTranslationKey(category, 'nav');
  }

  onLanguageChange(language: string): void {
    this.languageService.setLanguage(language);
    this.currentLanguage = this.languageService.currentLanguage;
  }

  closeMenu(): void {
    this.menuCtrl.close();
  }

  navigateTo(path: string): void {
    this.menuCtrl.close();
    this.navCtrl.navigateRoot(path, { animated: false });
  }

  isAdmin(): boolean {
    const role = (this.authService.getCurrentUser()?.role ?? '').toUpperCase();
    return role.includes('ADMIN') || role.includes('SUPER');
  }

  isBillingManager(): boolean {
    const user = this.authService.getCurrentUser();
    const gym = this.gyms.find(g => Number(g.id) === Number(user?.gym_id));
    return !this.gymsLoadError && !!user && !!gym?.billing_owner_user_id
      && Number(gym.billing_owner_user_id) === Number(user.id);
  }

  canManageUsers(): boolean {
    const role = (this.authService.getCurrentUser()?.role ?? '').toUpperCase();
    return role.includes('ADMIN') || role.includes('SUPER') || role.includes('OPERATORE') || role === 'GESTORE';
  }

  get trialDaysRemaining(): number {
    return this.authService.getTrialDaysRemaining();
  }

  async logout(): Promise<void> {
    await this.menuCtrl.close();
    this.authService.logout();
    await this.router.navigateByUrl('/login', { replaceUrl: true });
  }
}
