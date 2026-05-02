import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toothStatusApi } from '@/api/toothStatusApi';
import { DEFAULT_FILTERS } from '../constants';

export const useToothStatuses = () => {
  const [filters, setFilters] = useState(DEFAULT_FILTERS);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState({ total: 0, current_page: 1, last_page: 1 });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [groups, setGroups] = useState([]);

  const params = useMemo(
    () => ({ ...filters, page, per_page: perPage }),
    [filters, page, perPage],
  );

  const reqId = useRef(0);

  const refresh = useCallback(async () => {
    setLoading(true);
    setError(null);
    const id = ++reqId.current;
    try {
      const { data } = await toothStatusApi.list(params);
      if (id !== reqId.current) return;
      setItems(data?.data || []);
      setMeta({
        total: data?.total || 0,
        current_page: data?.current_page || 1,
        last_page: data?.last_page || 1,
      });
    } catch (err) {
      if (id !== reqId.current) return;
      setError(err?.response?.data?.message || 'Không thể tải danh sách trạng thái răng');
    } finally {
      if (id === reqId.current) setLoading(false);
    }
  }, [params]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    refresh();
  }, [refresh]);

  useEffect(() => {
    let mounted = true;
    toothStatusApi.groups()
      .then((res) => {
        if (mounted) setGroups(res.data || []);
      })
      .catch(() => {});
    return () => {
      mounted = false;
    };
  }, []);

  const setFilter = useCallback((key, value) => {
    setPage(1);
    setFilters((prev) => ({ ...prev, [key]: value }));
  }, []);

  const resetFilters = useCallback(() => {
    setPage(1);
    setFilters(DEFAULT_FILTERS);
  }, []);

  const create = useCallback(async (payload) => {
    const { data } = await toothStatusApi.create(payload);
    await refresh();
    return data;
  }, [refresh]);

  const update = useCallback(async (id, payload) => {
    const { data } = await toothStatusApi.update(id, payload);
    await refresh();
    return data;
  }, [refresh]);

  const toggleActive = useCallback(async (id, isActive, note) => {
    const { data } = await toothStatusApi.toggleActive(id, { is_active: isActive, note });
    await refresh();
    return data;
  }, [refresh]);

  const remove = useCallback(async (id) => {
    await toothStatusApi.remove(id);
    await refresh();
  }, [refresh]);

  const reorder = useCallback(async (orderedIds) => {
    // Optimistic local reorder for UX, then sync with server.
    setItems((prev) => {
      const byId = new Map(prev.map((it) => [it.id, it]));
      return orderedIds.map((id, idx) => {
        const it = byId.get(id);
        return it ? { ...it, display_order: idx + 1 } : null;
      }).filter(Boolean);
    });
    await toothStatusApi.reorder(orderedIds);
    await refresh();
  }, [refresh]);

  return {
    filters,
    setFilter,
    resetFilters,
    page,
    setPage,
    perPage,
    setPerPage,
    items,
    meta,
    loading,
    error,
    groups,
    refresh,
    create,
    update,
    toggleActive,
    remove,
    reorder,
  };
};
