const TOKEN_KEY = 'lordserial_admin_token'

/** In-memory only — httpOnly cookie is the persistent auth (SEC-3). */
let memoryToken = ''

export function getAdminToken(): string {
  return memoryToken
}

export function setAdminToken(token: string): void {
  memoryToken = token.trim()
  try {
    localStorage.removeItem(TOKEN_KEY)
  } catch {
    // ignore
  }
}

export function clearAdminToken(): void {
  memoryToken = ''
  try {
    localStorage.removeItem(TOKEN_KEY)
  } catch {
    // ignore
  }
}

export function adminAuthHeaders(): Record<string, string> {
  const token = getAdminToken()
  return token ? { 'X-ADMIN-TOKEN': token } : {}
}
