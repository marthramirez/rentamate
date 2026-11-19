import client from './client'

export const dashboardApi = {
  get: () => client.get('/dashboard'),
}

export const analyticsApi = {
  get: () => client.get('/analytics'),
}

export const propertiesApi = {
  list: (params) => client.get('/properties', { params }),
  get: (id) => client.get(`/properties/${id}`),
  create: (payload) => client.post('/properties', payload),
  update: (id, payload) => client.put(`/properties/${id}`, payload),
  remove: (id) => client.delete(`/properties/${id}`),
}

export const roomsApi = {
  list: (params) => client.get('/rooms', { params }),
  get: (id) => client.get(`/rooms/${id}`),
  create: (payload) => client.post('/rooms', payload),
  update: (id, payload) => client.put(`/rooms/${id}`, payload),
  remove: (id) => client.delete(`/rooms/${id}`),
}

export const tenantsApi = {
  list: (params) => client.get('/tenants', { params }),
  get: (id) => client.get(`/tenants/${id}`),
  create: (payload) => client.post('/tenants', payload),
  update: (id, payload) => client.put(`/tenants/${id}`, payload),
  remove: (id) => client.delete(`/tenants/${id}`),
}

export const leasesApi = {
  list: (params) => client.get('/leases', { params }),
  get: (id) => client.get(`/leases/${id}`),
  create: (payload) => client.post('/leases', payload),
  update: (id, payload) => client.put(`/leases/${id}`, payload),
  remove: (id) => client.delete(`/leases/${id}`),
  allocate: (id, payload) => client.post(`/leases/${id}/allocate`, payload),
  terminate: (id, payload) => client.post(`/leases/${id}/terminate`, payload),
  expire: (id, payload) => client.post(`/leases/${id}/expire`, payload),
  cancel: (id) => client.post(`/leases/${id}/cancel`),
}

export const assignmentsApi = {
  list: (params) => client.get('/room-assignments', { params }),
  moveOut: (id, payload) => client.post(`/room-assignments/${id}/move-out`, payload),
  transfer: (id, payload) => client.post(`/room-assignments/${id}/transfer`, payload),
}

export const billingApi = {
  list: (params) => client.get('/billing-records', { params }),
  get: (id) => client.get(`/billing-records/${id}`),
  create: (payload) => client.post('/billing-records', payload),
  cancel: (id) => client.post(`/billing-records/${id}/cancel`),
  pay: (id, payload) => client.post(`/billing-records/${id}/payments`, payload),
}

export const paymentsApi = {
  list: (params) => client.get('/payments', { params }),
  void: (id, payload) => client.post(`/payments/${id}/void`, payload),
}

export const authApi = {
  login: (payload) => client.post('/login', payload),
  me: () => client.get('/me'),
  logout: () => client.post('/logout'),
  updatePassword: (payload) => client.put('/account/password', payload),
}

export const usersApi = {
  list: (params) => client.get('/users', { params }),
  get: (id) => client.get(`/users/${id}`),
  create: (payload) => client.post('/users', payload),
  update: (id, payload) => client.put(`/users/${id}`, payload),
  remove: (id) => client.delete(`/users/${id}`),
}
