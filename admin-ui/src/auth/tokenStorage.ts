const TOKEN_KEY = 'lordserial_admin_token'

export function getAdminToken(): string {
  return localStorage.getItem(TOKEN_KEY) ?? ''
}

export function setAdminToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token.trim())
}

export function clearAdminToken(): void {
  localStorage.removeItem(TOKEN_KEY)
}

export function adminAuthHeaders(): Record<string, string> {
  const token = getAdminToken()
  return token ? { 'X-ADMIN-TOKEN': token } : {}
}
