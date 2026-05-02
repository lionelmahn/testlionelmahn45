import axiosClient from './axiosClient';

export const servicePriceApi = {
  list: (params) => axiosClient.get('/service-prices', { params }),
  pending: () => axiosClient.get('/service-prices/pending'),
  timeline: (serviceId) => axiosClient.get(`/service-prices/services/${serviceId}/timeline`),
  create: (payload) => axiosClient.post('/service-prices', payload),
  update: (id, payload) => axiosClient.put(`/service-prices/${id}`, payload),
  remove: (id) => axiosClient.delete(`/service-prices/${id}`),
  approve: (id) => axiosClient.post(`/service-prices/${id}/approve`),
  reject: (id, reason) => axiosClient.post(`/service-prices/${id}/reject`, { reason }),
  auditLogs: () => axiosClient.get('/service-prices/audit-logs'),
};
