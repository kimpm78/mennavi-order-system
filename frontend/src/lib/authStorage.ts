const customerTokenKey = 'mennavi_customer_token'
const adminTokenKey = 'mennavi_admin_token'

export function getCustomerToken() {
  return localStorage.getItem(customerTokenKey)
}

export function setCustomerToken(token: string) {
  localStorage.setItem(customerTokenKey, token)
}

export function clearCustomerToken() {
  localStorage.removeItem(customerTokenKey)
}

export function getAdminToken() {
  return localStorage.getItem(adminTokenKey)
}

export function setAdminToken(token: string) {
  localStorage.setItem(adminTokenKey, token)
}

export function clearAdminToken() {
  localStorage.removeItem(adminTokenKey)
}
