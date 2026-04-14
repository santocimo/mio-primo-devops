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
        this.authState$.next(state);
      } catch (e) {
        console.error('Errore nel caricamento dello stato auth:', e);
      }
    }
  }

  /**
   * Effettua il login
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
            const newState: AuthState = {
              isLoggedIn: true,
              user: response.user,
              token: response.token,
              subscriptionStatus: SubscriptionStatus.ACTIVE,
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
}
