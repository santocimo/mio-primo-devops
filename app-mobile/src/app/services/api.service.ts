import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '@env';
import { AuthService } from './auth.service';
import { Appointment, Service, Gym } from '../models/business.model';

@Injectable({
  providedIn: 'root',
})
export class ApiService {
  constructor(
    private http: HttpClient,
    private authService: AuthService
  ) {}

  /**
   * Ottiene gli header HTTP con il token di autenticazione
   */
  private getHeaders(): HttpHeaders {
    const token = this.authService.getToken();
    const headers = new HttpHeaders({
      'Content-Type': 'application/json',
    });

    if (token) {
      return headers.set('Authorization', `Bearer ${token}`);
    }

    return headers;
  }

  /**
   * Ottiene la lista delle palestre/location
   */
  getGyms(): Observable<Gym[]> {
    return this.http.get<Gym[]>(
      `${environment.apiUrl}/api/gyms`,
      { headers: this.getHeaders() }
    );
  }

  /**
   * Ottiene i servizi di una palestra
   */
  getServices(gymId: number): Observable<Service[]> {
    return this.http.get<Service[]>(
      `${environment.apiUrl}/api/services?gym_id=${gymId}`,
      { headers: this.getHeaders() }
    );
  }

  /**
   * Ottiene gli appuntamenti dell'utente
   */
  getAppointments(): Observable<Appointment[]> {
    return this.http.get<Appointment[]>(
      `${environment.apiUrl}/api/appointments`,
      { headers: this.getHeaders() }
    );
  }

  /**
   * Crea un nuovo appuntamento
   */
  createAppointment(data: {
    service_id: number;
    gym_id: number;
    appointment_date: string;
    appointment_time: string;
  }): Observable<{ success: boolean; appointment_id?: number; message: string }> {
    return this.http.post<{ success: boolean; appointment_id?: number; message: string }>(
      `${environment.apiUrl}/api/appointments`,
      data,
      { headers: this.getHeaders() }
    );
  }

  /**
   * Cancella un appuntamento
   */
  cancelAppointment(appointmentId: number): Observable<{ success: boolean; message: string }> {
    return this.http.delete<{ success: boolean; message: string }>(
      `${environment.apiUrl}/api/appointments/${appointmentId}`,
      { headers: this.getHeaders() }
    );
  }

  /**
   * Aggiorna il profilo utente
   */
  updateUserProfile(data: {
    name?: string;
    email?: string;
    phone?: string;
  }): Observable<{ success: boolean; message: string }> {
    return this.http.put<{ success: boolean; message: string }>(
      `${environment.apiUrl}/api/user/profile`,
      data,
      { headers: this.getHeaders() }
    );
  }
}
