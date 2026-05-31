import { Injectable } from '@angular/core';
import {
  HttpRequest,
  HttpHandler,
  HttpEvent,
  HttpInterceptor,
  HttpErrorResponse,
} from '@angular/common/http';
import { EMPTY, Observable, throwError } from 'rxjs';
import { catchError } from 'rxjs/operators';
import { AuthService } from './auth.service';
import { Router } from '@angular/router';

@Injectable()
export class AuthInterceptor implements HttpInterceptor {
  private redirectingToLogin = false;

  constructor(
    private authService: AuthService,
    private router: Router
  ) {}

  intercept(
    request: HttpRequest<unknown>,
    next: HttpHandler
  ): Observable<HttpEvent<unknown>> {
    // Dopo un nuovo login ripristiniamo il flusso API normale.
    if (this.redirectingToLogin && this.authService.isLoggedIn()) {
      this.redirectingToLogin = false;
    }

    const isApiRequest = request.url.includes('/api/');
    const isLoginRequest = request.url.includes('/api/auth/login');

    // Durante il teardown auth evitiamo nuove chiamate API protette che produrrebbero 401 a cascata.
    if (this.redirectingToLogin && isApiRequest && !isLoginRequest) {
      return EMPTY;
    }

    const token = this.authService.getToken();

    if (token) {
      request = request.clone({
        setHeaders: {
          Authorization: `Bearer ${token}`,
        },
      });
    }

    return next.handle(request).pipe(
      catchError((error: HttpErrorResponse) => {
        if (error.status === 401) {
          // Sul login lasciamo gestire l'errore alla pagina senza riavviare tutta la SPA.
          if (!isLoginRequest && !this.redirectingToLogin) {
            this.redirectingToLogin = true;
            this.authService.logout();
            void this.router.navigateByUrl('/login', { replaceUrl: true });
            return EMPTY;
          }

          if (!isLoginRequest && this.redirectingToLogin) {
            return EMPTY;
          }
        }
        return throwError(() => error);
      })
    );
  }
}
