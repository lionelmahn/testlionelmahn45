import { useCallback, useEffect, useState } from 'react';
import { toothStatusApi } from '@/api/toothStatusApi';

export const useToothStatusProposals = ({ enabled = true } = {}) => {
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState({ total: 0, current_page: 1, last_page: 1 });
  const [pendingCount, setPendingCount] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [statusFilter, setStatusFilter] = useState('pending');

  const refresh = useCallback(async () => {
    if (!enabled) return;
    setLoading(true);
    setError(null);
    try {
      const { data } = await toothStatusApi.listProposals({ status: statusFilter });
      const list = data?.items;
      setItems(list?.data || []);
      setMeta({
        total: list?.total || 0,
        current_page: list?.current_page || 1,
        last_page: list?.last_page || 1,
      });
      setPendingCount(data?.pending_count || 0);
    } catch (err) {
      setError(err?.response?.data?.message || 'Không thể tải đề xuất');
    } finally {
      setLoading(false);
    }
  }, [enabled, statusFilter]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    refresh();
  }, [refresh]);

  const approve = useCallback(async (id) => {
    await toothStatusApi.approveProposal(id);
    await refresh();
  }, [refresh]);

  const reject = useCallback(async (id, note) => {
    await toothStatusApi.rejectProposal(id, note);
    await refresh();
  }, [refresh]);

  return {
    items,
    meta,
    pendingCount,
    loading,
    error,
    statusFilter,
    setStatusFilter,
    refresh,
    approve,
    reject,
  };
};
