const configuredApiBaseUrl = (import.meta.env.VITE_API_URL || '').replace(/\/$/, '')

export const apiBaseUrl = configuredApiBaseUrl || 'http://127.0.0.1:8000/api'

console.info('[Mennavi API URL]', apiBaseUrl)

export async function apiRequest<T>(path: string, options: RequestInit = {}): Promise<T> {
  const response = await fetch(`${apiBaseUrl}${path}`, {
    ...options,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...options.headers,
    },
  })

  const data = await response.json().catch(() => ({}))

  if (!response.ok) {
    throw new Error(readErrorMessage(data))
  }

  return data
}

export function authHeaders(token: string) {
  return {
    Authorization: `Bearer ${token}`,
  }
}

function readErrorMessage(data: unknown) {
  if (
    data &&
    typeof data === 'object' &&
    'errors' in data &&
    data.errors &&
    typeof data.errors === 'object'
  ) {
    const firstError = Object.values(data.errors as Record<string, string[]>)[0]?.[0]

    if (firstError) {
      return firstError
    }
  }

  if (data && typeof data === 'object' && 'message' in data && typeof data.message === 'string') {
    return data.message
  }

  return '通信に失敗しました。'
}
