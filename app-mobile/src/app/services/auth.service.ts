import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';
import { HttpClient } from '@angular/common/http';
import { map } from 'rxjs/operators';
import { environment } from '@env';
import { AuthState, LoginRequest, LoginResponse, User, SubscriptionStatus, ServerSubscription } from '../models/auth.model';

@Injectable({
  providedIn: 'root',
})
export class AuthService {
  private authState$ = new BehaviorSubject<AuthState>({
    isLoggedIn: false,
    subscriptionStatus: SubscriptionStatus.NONE,
  });

  private selectedGymId$ = new BehaviorSubject<number | null>(null);

  constructor(private http: HttpClient) {
    this.loadStoredAuth();
  }

  /**
   * Restore auth state synchronously from localStorage if present.
   * Useful for guards that need immediate knowledge of auth state.
   */
  restoreFromLocalStorage(): void {
    const stored = localStorage.getItem('authState');
    if (stored) {
      try {
        const state = JSON.parse(stored) as AuthState;
        this.authState$.next(state);
        if (state.selectedGymId != null) this.selectedGymId$.next(state.selectedGymId);
        return;
      } catch (e) {
        // ignore
      }
    }
    // fallback: if token exists but no authState, leave loadStoredAuth to handle async verify
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
        // Ripristina selectedGymId
        if (state.selectedGymId != null) {
          this.selectedGymId$.next(state.selectedGymId);
        }
      } catch (e) {
        console.error('Errore nel caricamento dello stato auth:', e);
        localStorage.removeItem('authState');
      }
    }
    else {
      // Se non esiste authState ma c'è un token salvato (es. test manuale), verifichiamolo
      const tokenOnly = localStorage.getItem('token');
      if (tokenOnly) {
        // Chiamiamo l'endpoint di verifica token per ripristinare lo stato utente
        try {
          // Import HttpClient lazily to avoid circular DI at constructor
          // Usa fetch per non dipendere da Angular HttpClient in questa fase di bootstrap
          const apiBase = (window as any).__env?.apiUrl || environment.apiUrl;
          const resp = fetch(`${apiBase}/api/auth/verify_token.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${tokenOnly}` },
            body: JSON.stringify({ token: tokenOnly }),
          }).then(r => r.json()).catch(() => null);

          Promise.resolve(resp).then((data: any) => {
            if (data && data.success && data.user) {
              const sub = data.subscription
                ? this.mapServerSubscription(data.subscription)
                : { subscriptionStatus: SubscriptionStatus.EXPIRED, trialStartDate: undefined };
              const recoveredState: AuthState = {
                isLoggedIn: true,
                user: data.user,
                token: tokenOnly,
                subscriptionStatus: sub.subscriptionStatus,
                trialStartDate: sub.trialStartDate,
              };
              this.authState$.next(recoveredState);
              localStorage.setItem('authState', JSON.stringify(recoveredState));
            }
          });
        } catch (e) {
          // ignore
        }
      }
    }
  }

  /**
   * Effettua il login e gestisce trial/abbonamento
   */
  login(credentials: LoginRequest): Observable<LoginResponse> {
    return this.http
      .post<LoginResponse>(
        `${environment.apiUrl}/api/auth/login.php`,
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
            const userGymId = response.user.gym_id != null ? Number(response.user.gym_id) : null;

            // Lo stato abbonamento/trial arriva dal server (fonte di verità)
            let subscriptionStatus = isAdmin ? SubscriptionStatus.ACTIVE : SubscriptionStatus.NONE;
            let trialStartDate: string | undefined;
            if (!isAdmin && response.subscription) {
              ({ subscriptionStatus, trialStartDate } = this.mapServerSubscription(response.subscription));
            }

            const selectedGymId = isAdmin ? (prevState.selectedGymId ?? null) : userGymId;

            const newState: AuthState = {
              isLoggedIn: true,
              user: response.user,
              token: response.token,
              subscriptionStatus,
              trialStartDate,
              selectedGymId,
            };
            this.authState$.next(newState);
            this.selectedGymId$.next(selectedGymId);
            localStorage.setItem('authState', JSON.stringify(newState));
            localStorage.setItem('token', response.token);
          }
          return response;
        })
      );
  }

  /**
   * Converte lo stato abbonamento restituito dal server nello stato client
   */
  mapServerSubscription(sub: ServerSubscription): { subscriptionStatus: SubscriptionStatus; trialStartDate?: string } {
    const map: Record<ServerSubscription['status'], SubscriptionStatus> = {
      trial: SubscriptionStatus.TRIAL,
      active: SubscriptionStatus.ACTIVE,
      expired: SubscriptionStatus.EXPIRED,
    };
    return {
      subscriptionStatus: map[sub.status] ?? SubscriptionStatus.EXPIRED,
      trialStartDate: sub.trial_start_date ?? undefined,
    };
  }

  /**
   * Effettua il logout
   */
  logout(): void {
    this.authState$.next({
      isLoggedIn: false,
      subscriptionStatus: SubscriptionStatus.NONE,
    });
    this.selectedGymId$.next(null);
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

  updateCurrentUser(data: Partial<Pick<User, 'name' | 'email'>>): void {
    const state = this.authState$.getValue();
    if (!state.user) return;
    const updatedState = { ...state, user: { ...state.user, ...data } };
    this.authState$.next(updatedState);
    localStorage.setItem('authState', JSON.stringify(updatedState));
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

  // ── Gym / Sede selection ──────────────────────────────────────────────────

  getSelectedGymId(): number | null {
    return this.selectedGymId$.getValue();
  }

  getSelectedGymName(): string {
    return this.authState$.getValue().selectedGymName ?? '';
  }

  getSelectedGymIdStream(): Observable<number | null> {
    return this.selectedGymId$.asObservable();
  }

  setSelectedGymId(gymId: number | null): void {
    this.selectedGymId$.next(gymId);
    const state = { ...this.authState$.getValue(), selectedGymId: gymId };
    this.authState$.next(state);
    localStorage.setItem('authState', JSON.stringify(state));
  }

  setSelectedGymName(name: string): void {
    const state = { ...this.authState$.getValue(), selectedGymName: name };
    this.authState$.next(state);
    localStorage.setItem('authState', JSON.stringify(state));
  }
}
