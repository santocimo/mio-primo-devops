import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '@env';
import { AuthService } from './auth.service';
import { Appointment, Service, Gym, Contact, ContactStats } from '../models/business.model';

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

  // ── Contacts (Visitatori) ────────────────────────────────────────────────

  getContactStats(): Observable<ContactStats> {
    return this.http.get<ContactStats>(
      `${environment.apiUrl}/api/contacts?stats=1`,
      { headers: this.getHeaders() }
    );
  }

  getContacts(query: string = ''): Observable<Contact[]> {
    const url = query
      ? `${environment.apiUrl}/api/contacts?q=${encodeURIComponent(query)}`
      : `${environment.apiUrl}/api/contacts`;
    return this.http.get<Contact[]>(url, { headers: this.getHeaders() });
  }

  createContact(data: Omit<Contact, 'id'>): Observable<{ success: boolean; id: number }> {
    return this.http.post<{ success: boolean; id: number }>(
      `${environment.apiUrl}/api/contacts`,
      data,
      { headers: this.getHeaders() }
    );
  }

  updateContact(id: number, data: Omit<Contact, 'id'>): Observable<{ success: boolean }> {
    return this.http.put<{ success: boolean }>(
      `${environment.apiUrl}/api/contacts/${id}`,
      data,
      { headers: this.getHeaders() }
    );
  }

  deleteContact(id: number): Observable<{ success: boolean }> {
    return this.http.delete<{ success: boolean }>(
      `${environment.apiUrl}/api/contacts/${id}`,
      { headers: this.getHeaders() }
    );
  }

  // ── Users (Admin only) ──────────────────────────────────────────────────

  getUsers(): Observable<any[]> {
    return this.http.get<any[]>(`${environment.apiUrl}/api/users`, { headers: this.getHeaders() });
  }

  createUser(data: any): Observable<any> {
    return this.http.post<any>(`${environment.apiUrl}/api/users`, data, { headers: this.getHeaders() });
  }

  updateUser(id: number, data: any): Observable<any> {
    return this.http.put<any>(`${environment.apiUrl}/api/users/${id}`, data, { headers: this.getHeaders() });
  }

  deleteUser(id: number): Observable<any> {
    return this.http.delete<any>(`${environment.apiUrl}/api/users/${id}`, { headers: this.getHeaders() });
  }

  // ── Gyms full CRUD ──────────────────────────────────────────────────────

  createGym(data: any): Observable<any> {
    return this.http.post<any>(`${environment.apiUrl}/api/gyms`, data, { headers: this.getHeaders() });
  }

  updateGym(id: number, data: any): Observable<any> {
    return this.http.put<any>(`${environment.apiUrl}/api/gyms/${id}`, data, { headers: this.getHeaders() });
  }

  deleteGym(id: number): Observable<any> {
    return this.http.delete<any>(`${environment.apiUrl}/api/gyms/${id}`, { headers: this.getHeaders() });
  }

  // ── Services full CRUD ──────────────────────────────────────────────────

  getAllServices(): Observable<any[]> {
    return this.http.get<any[]>(`${environment.apiUrl}/api/services`, { headers: this.getHeaders() });
  }

  createService(data: any): Observable<any> {
    return this.http.post<any>(`${environment.apiUrl}/api/services`, data, { headers: this.getHeaders() });
  }

  updateService(id: number, data: any): Observable<any> {
    return this.http.put<any>(`${environment.apiUrl}/api/services/${id}`, data, { headers: this.getHeaders() });
  }

  deleteService(id: number): Observable<any> {
    return this.http.delete<any>(`${environment.apiUrl}/api/services/${id}`, { headers: this.getHeaders() });
  }

  // ── Appointments full CRUD ───────────────────────────────────────────────

  getAllAppointments(): Observable<any[]> {
    return this.http.get<any[]>(`${environment.apiUrl}/api/appointments`, { headers: this.getHeaders() });
  }

  createAppointmentAdmin(data: any): Observable<any> {
    return this.http.post<any>(`${environment.apiUrl}/api/appointments`, data, { headers: this.getHeaders() });
  }

  updateAppointment(id: number, data: any): Observable<any> {
    return this.http.put<any>(`${environment.apiUrl}/api/appointments/${id}`, data, { headers: this.getHeaders() });
  }

  deleteAppointment(id: number): Observable<any> {
    return this.http.delete<any>(`${environment.apiUrl}/api/appointments/${id}`, { headers: this.getHeaders() });
  }

  // ── App Settings ────────────────────────────────────────────────────────

  getSettings(): Observable<any> {
    return this.http.get<any>(`${environment.apiUrl}/api/settings`, { headers: this.getHeaders() });
  }

  saveSettings(data: any): Observable<any> {
    return this.http.post<any>(`${environment.apiUrl}/api/settings`, data, { headers: this.getHeaders() });
  }
}
