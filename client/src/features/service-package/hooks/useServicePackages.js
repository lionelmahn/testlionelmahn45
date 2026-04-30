import { useCallback, useEffect, useMemo, useState } from 'react';
import { servicePackageApi } from '@/api/servicePackageApi';

const DEFAULT_FILTERS = {
  search: '',
  status: 'all',
  visibility: 'all',
  effective_from: '',
  effective_to: '',
};

export const useServicePackages = () => {
  const [filters, setFilters] = useState(DEFAULT_FILTERS);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState({ total: 0, current_page: 1, last_page: 1 });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const params = useMemo(
    () => ({
      ...filters,
      page,
      per_page: perPage,
    }),
    [filters, page, perPage]
  );

  const refresh = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const { data } = await servicePackageApi.list(params);
      setItems(data?.data || []);
      setMeta({
        total: data?.total || 0,
        current_page: data?.current_page || 1,
        last_page: data?.last_page || 1,
      });
    } catch (err) {
      setError(err?.response?.data?.message || 'Không thể tải danh sách gói dịch vụ');
    } finally {
      setLoading(false);
    }
  }, [params]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    refresh();
  }, [refresh]);

  const setFilter = useCallback((key, value) => {
    setPage(1);
    setFilters((prev) => ({ ...prev, [key]: value }));
  }, []);

  const resetFilters = useCallback(() => {
    setPage(1);
    setFilters(DEFAULT_FILTERS);
  }, []);

  const changePerPage = useCallback((next) => {
    setPage(1);
    setPerPage(next);
  }, []);

  const create = useCallback(
    async (payload) => {
      const { data } = await servicePackageApi.create(payload);
      await refresh();
      return data;
    },
    [refresh]
  );

  const update = useCallback(
    async (id, payload) => {
      const { data } = await servicePackageApi.update(id, payload);
      await refresh();
      return data;
    },
    [refresh]
  );

  const changeStatus = useCallback(
    async (id, status, reason) => {
      const { data } = await servicePackageApi.changeStatus(id, { status, reason });
      await refresh();
      return data;
    },
    [refresh]
  );

  const remove = useCallback(
    async (id) => {
      await servicePackageApi.remove(id);
      await refresh();
    },
    [refresh]
  );

  const clone = useCallback(
    async (id, payload) => {
      const { data } = await servicePackageApi.clone(id, payload);
      await refresh();
      return data;
    },
    [refresh]
  );

  const newVersion = useCallback(
    async (id, payload) => {
      const { data } = await servicePackageApi.newVersion(id, payload);
      await refresh();
      return data;
    },
    [refresh]
  );

  return {
    filters,
    setFilter,
    resetFilters,
    page,
    setPage,
    perPage,
    setPerPage: changePerPage,
    items,
    meta,
    loading,
    error,
    refresh,
    create,
    update,
    changeStatus,
    remove,
    clone,
    newVersion,
  };
};
