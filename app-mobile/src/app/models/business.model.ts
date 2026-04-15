export interface Appointment {
  id: number;
  user_id: number;
  service_id: number;
  gym_id: number;
  appointment_date: string;
  appointment_time: string;
  status: 'scheduled' | 'completed' | 'cancelled';
  notes?: string;
  created_at: string;
  updated_at: string;
}

export interface Contact {
  id: number;
  nome: string;
  cognome: string;
  codice_fiscale: string;
  data_nascita: string;
  luogo_nascita: string;
  indirizzo: string;
  recapito: string;
  sesso: 'M' | 'F';
}

export interface ContactStats {
  total: number;
  men: number;
  women: number;
}

export interface Service {
  id: number;
  name: string;
  description?: string;
  duration: number; // in minutes
  price?: number;
  gym_id: number;
  created_at: string;
  updated_at: string;
}

export interface Gym {
  id: number;
  name: string;
  slug?: string;
  address?: string;
  city?: string;
  phone?: string;
  email?: string;
  category: 'gym' | 'salon' | 'studio' | 'other';
  created_at: string;
  updated_at: string;
}
