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
  address?: string;
  city?: string;
  phone?: string;
  email?: string;
  category: 'gym' | 'salon' | 'studio' | 'other';
  created_at: string;
  updated_at: string;
}
