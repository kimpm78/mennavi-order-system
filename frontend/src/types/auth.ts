export type UserRole = 'user' | 'admin'
export type UserStatus = 'active' | 'suspended' | 'withdrawn'

export type User = {
  id: number
  name: string
  email: string
  phone?: string | null
  postal_code?: string | null
  address?: string | null
  role: UserRole
  status: UserStatus
  last_login_at?: string | null
  created_at: string
}

export type AuthResponse = {
  user: User
  token: string
}

export type LoginPayload = {
  email: string
  password: string
}

export type RegisterPayload = LoginPayload & {
  name: string
  phone?: string
  password_confirmation: string
}
