import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from './services/auth.service';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { AuthState } from './models/auth.model';

@Component({
  selector: 'app-root',
  templateUrl: 'app.component.html',
  styleUrls: ['app.component.scss'],
})
export class AppComponent implements OnInit {
  authState$!: Observable<AuthState>;

  constructor(public authService: AuthService, public router: Router) {}

  ngOnInit(): void {
    this.authState$ = this.authService.getAuthState();
  }

  isAdmin(): boolean {
    const role = (this.authService.getCurrentUser()?.role ?? '').toUpperCase();
    return role.includes('ADMIN') || role.includes('SUPER');
  }

  logout(): void {
    this.authService.logout();
    this.router.navigate(['/login']);
  }
}
