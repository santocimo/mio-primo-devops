export interface User {
  id: number;
  name: string;
  email: string;
  username?: string;
  phone?: string;
  role: 'admin' | 'manager' | 'operator' | 'user';
  gym_id?: number;
  created_at: string;
  updated_at: string;
}

export interface LoginRequest {
  username: string;
  password: string;
}

export interface ServerSubscription {
  status: 'trial' | 'active' | 'expired';
  trial_start_date: string | null;
  trial_ends_at: string | null;
  trial_days_remaining: number;
  expires_at: string | null;
  plan: string | null;
}

export interface SubscriptionUpdate {
  status: ServerSubscription['status'];
  trial_start_date?: string | null;
  trial_ends_at?: string | null;
  trial_days_remaining?: number;
  expires_at?: string | null;
  plan?: string | null;
}

export interface LoginResponse {
  success: boolean;
  message: string;
  user?: User;
  token?: string;
  subscription?: ServerSubscription;
}

export interface AuthState {
  isLoggedIn: boolean;
  user?: User;
  token?: string;
  subscriptionStatus?: SubscriptionStatus;
  trialStartDate?: string;
  selectedGymId?: number | null;
  selectedGymName?: string;
}

export enum SubscriptionStatus {
  EXPIRED = 'expired',
  ACTIVE = 'active',
  TRIAL = 'trial',
  NONE = 'none',
}
