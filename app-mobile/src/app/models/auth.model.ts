export interface User {
  id: number;
  name: string;
  email: string;
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

export interface LoginResponse {
  success: boolean;
  message: string;
  user?: User;
  token?: string;
}

export interface AuthState {
  isLoggedIn: boolean;
  user?: User;
  token?: string;
  subscriptionStatus?: SubscriptionStatus;
}

export enum SubscriptionStatus {
  EXPIRED = 'expired',
  ACTIVE = 'active',
  TRIAL = 'trial',
  NONE = 'none',
}
