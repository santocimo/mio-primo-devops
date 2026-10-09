import { Component, NgZone, OnInit, OnDestroy } from '@angular/core';
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
import { Gym } from './models/business.model';
import { AppLanguage, LanguageService } from './i18n/language.service';

@Component({
  selector: 'app-root',
  templateUrl: 'app.component.html',
  styleUrls: ['app.component.scss'],
})
export class AppComponent implements OnInit, OnDestroy {
  authState$!: Observable<AuthState>;
  gyms: Gym[] = [];
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
    public router: Router
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
    this.apiService.getGyms(isAdmin).subscribe({
      next: (gyms) => {
        this.gyms = gyms;
        const saved = this.authService.getSelectedGymId();
        const savedStillVisible = saved != null ? gyms.some(g => g.id === saved) : false;
        const userGymId = this.authService.getCurrentUser()?.gym_id ?? null;

        if (savedStillVisible) {
          this.selectedGymId = saved;
          this.selectedGymName = this.authService.getSelectedGymName();
          return;
        }

        if (!isAdmin && userGymId != null && gyms.some(g => g.id === userGymId)) {
          this.selectedGymId = userGymId;
          const gym = gyms.find(g => g.id === userGymId);
          this.selectedGymName = gym?.name ?? '';
          this.authService.setSelectedGymName(this.selectedGymName);
          this.authService.setSelectedGymId(this.selectedGymId);
          return;
        }

        if (isAdmin) {
          this.selectedGymId = null;
          this.selectedGymName = '';
          this.authService.setSelectedGymName('');
          this.authService.setSelectedGymId(null);
          return;
        }

        if (gyms.length > 0) {
          this.selectedGymId = gyms[0].id;
          this.selectedGymName = gyms[0].name;
          this.authService.setSelectedGymName(gyms[0].name);
          this.authService.setSelectedGymId(gyms[0].id);
        } else {
          this.selectedGymId = null;
          this.selectedGymName = '';
          this.authService.setSelectedGymName('');
          this.authService.setSelectedGymId(null);
        }
      },
      error: () => {},
    });
  }

  onGymChange(gymId: number | null): void {
    this.selectedGymId = gymId;
    const gym = this.gyms.find(g => g.id === gymId);
    this.selectedGymName = gym?.name ?? '';
    this.authService.setSelectedGymName(gym?.name ?? '');
    this.authService.setSelectedGymId(gymId);
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
