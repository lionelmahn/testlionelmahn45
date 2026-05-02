import axiosClient from './axiosClient';

export const toothStatusApi = {
  list: (params) => axiosClient.get('/tooth-statuses', { params }),
  get: (id) => axiosClient.get(`/tooth-statuses/${id}`),
  create: (data) => axiosClient.post('/tooth-statuses', data),
  update: (id, data) => axiosClient.put(`/tooth-statuses/${id}`, data),
  toggleActive: (id, payload) =>
    axiosClient.post(`/tooth-statuses/${id}/toggle-active`, payload),
  remove: (id) => axiosClient.delete(`/tooth-statuses/${id}`),
  reorder: (orderedIds) =>
    axiosClient.post('/tooth-statuses/reorder', { ordered_ids: orderedIds }),
  history: (id) => axiosClient.get(`/tooth-statuses/${id}/history`),
  recentHistory: () => axiosClient.get('/tooth-statuses/history/recent'),

  groups: (activeOnly = false) =>
    axiosClient.get('/tooth-status-groups', { params: { active_only: activeOnly ? 1 : 0 } }),
  createGroup: (data) => axiosClient.post('/tooth-status-groups', data),
  updateGroup: (id, data) => axiosClient.put(`/tooth-status-groups/${id}`, data),

  listProposals: (params) => axiosClient.get('/tooth-status-proposals', { params }),
  createProposal: (data) => axiosClient.post('/tooth-status-proposals', data),
  approveProposal: (id) =>
    axiosClient.post(`/tooth-status-proposals/${id}/approve`),
  rejectProposal: (id, note) =>
    axiosClient.post(`/tooth-status-proposals/${id}/reject`, { note }),
};
