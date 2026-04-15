import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';
import { HttpClient } from '@angular/common/http';
import { map } from 'rxjs/operators';
import { environment } from '@env';
import { AuthState, LoginRequest, LoginResponse, User, SubscriptionStatus } from '../models/auth.model';

@Injectable({
  providedIn: 'root',
})
export class AuthService {
  private authState$ = new BehaviorSubject<AuthState>({
    isLoggedIn: false,
    subscriptionStatus: SubscriptionStatus.NONE,
  });

  constructor(private http: HttpClient) {
    this.loadStoredAuth();
  }

  /**
   * Carica lo stato di autenticazione dalla memoria locale
   */
  private loadStoredAuth(): void {
    const stored = localStorage.getItem('authState');
    if (stored) {
      try {
        const state = JSON.parse(stored) as AuthState;
        // Ricalcola se il trial è ancora valido al reload
        if (state.subscriptionStatus === SubscriptionStatus.TRIAL && state.trialStartDate) {
          const days = this.calcTrialDaysRemaining(state.trialStartDate);
          if (days <= 0) {
            state.subscriptionStatus = SubscriptionStatus.EXPIRED;
            localStorage.setItem('authState', JSON.stringify(state));
          }
        }
        this.authState$.next(state);
      } catch (e) {
        console.error('Errore nel caricamento dello stato auth:', e);
        localStorage.removeItem('authState');
      }
    }
  }

  /**
   * Effettua il login e gestisce trial/abbonamento
   */
  login(credentials: LoginRequest): Observable<LoginResponse> {
    return this.http
      .post<LoginResponse>(
        `${environment.apiUrl}/api/auth/login`,
        credentials
      )
      .pipe(
        map((response) => {
          if (response.success && response.user && response.token) {
            // Recupera lo stato precedente per preservare trial e abbonamento
            const stored = localStorage.getItem('authState');
            const prevState: Partial<AuthState> = stored ? JSON.parse(stored) : {};

            // Admin non soggetto a trial/pagamento
            const role = (response.user.role ?? '').toUpperCase();
            const isAdmin = role.includes('ADMIN') || role.includes('SUPER');

            // Determina lo stato abbonamento
            let subscriptionStatus = isAdmin
              ? SubscriptionStatus.ACTIVE
              : (prevState.subscriptionStatus ?? SubscriptionStatus.NONE);
            let trialStartDate = isAdmin ? undefined : prevState.trialStartDate;

            // Primo accesso: avvia il trial
            if (!trialStartDate && subscriptionStatus !== SubscriptionStatus.ACTIVE) {
              trialStartDate = new Date().toISOString();
              subscriptionStatus = SubscriptionStatus.TRIAL;
            }

            // Se era in trial, verifica se è ancora valido
            if (subscriptionStatus === SubscriptionStatus.TRIAL && trialStartDate) {
              const trialDays = this.calcTrialDaysRemaining(trialStartDate);
              if (trialDays <= 0) {
                subscriptionStatus = SubscriptionStatus.EXPIRED;
              }
            }

            const newState: AuthState = {
              isLoggedIn: true,
              user: response.user,
              token: response.token,
              subscriptionStatus,
              trialStartDate,
            };
            this.authState$.next(newState);
            localStorage.setItem('authState', JSON.stringify(newState));
            localStorage.setItem('token', response.token);
          }
          return response;
        })
      );
  }

  /**
   * Effettua il logout
   */
  logout(): void {
    this.authState$.next({
      isLoggedIn: false,
      subscriptionStatus: SubscriptionStatus.NONE,
    });
    localStorage.removeItem('authState');
    localStorage.removeItem('token');
  }

  /**
   * Ottiene lo stato di autenticazione
   */
  getAuthState(): Observable<AuthState> {
    return this.authState$.asObservable();
  }

  /**
   * Ottiene l'utente corrente
   */
  getCurrentUser(): User | undefined {
    return this.authState$.getValue().user;
  }

  /**
   * Controlla se l'utente è loggato
   */
  isLoggedIn(): boolean {
    return this.authState$.getValue().isLoggedIn;
  }

  /**
   * Ottiene il token di autenticazione
   */
  getToken(): string | undefined {
    return this.authState$.getValue().token;
  }

  /**
   * Ottiene lo stato della sottoscrizione
   */
  getSubscriptionStatus(): Observable<SubscriptionStatus | undefined> {
    return this.authState$.pipe(map((state) => state.subscriptionStatus));
  }

  /**
   * Aggiorna lo stato della sottoscrizione
   */
  updateSubscriptionStatus(status: SubscriptionStatus): void {
    const state = this.authState$.getValue();
    state.subscriptionStatus = status;
    this.authState$.next(state);
    localStorage.setItem('authState', JSON.stringify(state));
  }

  /**
   * Calcola i giorni rimasti nel trial (0 se scaduto)
   */
  calcTrialDaysRemaining(trialStartDate: string): number {
    const TRIAL_DAYS = 7;
    const start = new Date(trialStartDate).getTime();
    const now = Date.now();
    const elapsed = Math.floor((now - start) / (1000 * 60 * 60 * 24));
    return Math.max(0, TRIAL_DAYS - elapsed);
  }

  /**
   * Giorni rimasti nel trial per l'utente corrente
   */
  getTrialDaysRemaining(): number {
    const state = this.authState$.getValue();
    if (!state.trialStartDate) return 0;
    return this.calcTrialDaysRemaining(state.trialStartDate);
  }

  /**
   * Controlla se l'utente ha accesso (trial attivo o abbonato)
   */
  hasAccess(): boolean {
    const state = this.authState$.getValue();
    if (state.subscriptionStatus === SubscriptionStatus.ACTIVE) return true;
    if (state.subscriptionStatus === SubscriptionStatus.TRIAL) {
      return this.getTrialDaysRemaining() > 0;
    }
    return false;
  }
}
