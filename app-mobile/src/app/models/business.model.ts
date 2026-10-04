export interface Appointment {
  id: number;
  service_id: number;
  contact_id?: number | null;
  gym_id: number;
  customer_name: string;
  customer_email?: string | null;
  scheduled_at: string;
  status: 'pending' | 'confirmed' | 'scheduled' | 'completed' | 'cancelled';
  notes?: string;
  service_name?: string;
  service_provider_name?: string | null;
  service_provider_type?: 'internal' | 'external' | null;
  gym_name?: string;
  created_at?: string;
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
  slug?: string;
  category: string;
  description?: string;
  provider_name?: string | null;
  provider_type?: 'internal' | 'external' | null;
  duration_minutes: number;
  capacity: number;
  price?: number;
  gym_id: number;
  created_at: string;
  updated_at: string;
}

export interface Gym {
  id: number;
  name: string;
  slug?: string;
  description?: string;
  address?: string;
  city?: string;
  phone?: string;
  email?: string;
  manager_name?: string;
  manager_email?: string;
  manager_username?: string;
  manager_cf?: string;
  activity_name?: string;
  settings?: any;
  category: 'gym' | 'salon' | 'studio' | 'other';
  created_at: string;
  updated_at: string;
}
