import axiosClient from './axiosClient';

export const servicePackageApi = {
  list: (params) => axiosClient.get('/service-packages', { params }),
  get: (id) => axiosClient.get(`/service-packages/${id}`),
  create: (data) => axiosClient.post('/service-packages', data),
  update: (id, data) => axiosClient.put(`/service-packages/${id}`, data),
  changeStatus: (id, payload) => axiosClient.post(`/service-packages/${id}/status`, payload),
  remove: (id) => axiosClient.delete(`/service-packages/${id}`),
  clone: (id, payload) => axiosClient.post(`/service-packages/${id}/clone`, payload || {}),
  newVersion: (id, payload) => axiosClient.post(`/service-packages/${id}/new-version`, payload || {}),
  discontinuedWarnings: (id) => axiosClient.get(`/service-packages/${id}/discontinued-warnings`),
  auditLogs: () => axiosClient.get('/service-packages/audit-logs'),
  // shared with service catalog for the item picker
  servicesLookup: (params) => axiosClient.get('/services', { params }),
};
