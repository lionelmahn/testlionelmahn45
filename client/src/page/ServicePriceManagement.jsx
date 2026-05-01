import React, { useCallback, useEffect, useState } from 'react';
import { useAuth } from '@/hooks/useAuth';
import { useServicePrices } from '@/features/service-prices/hooks/useServicePrices';
import { servicePriceApi } from '@/api/servicePriceApi';
import { serviceCatalogApi } from '@/api/serviceCatalogApi';
import PriceFilterBar from '@/features/service-prices/components/PriceFilterBar';
import PriceServiceTable from '@/features/service-prices/components/PriceServiceTable';
import PriceTimelinePanel from '@/features/service-prices/components/PriceTimelinePanel';
import PriceFormModal from '@/features/service-prices/components/PriceFormModal';
import RejectPromptModal from '@/features/service-prices/components/RejectPromptModal';
import Pagination from '@/features/service-package/components/Pagination';

const extractError = (err, fallback) =>
  err?.response?.data?.message ||
  Object.values(err?.response?.data?.errors || {}).flat().join(' · ') ||
  fallback;

const ServicePriceManagement = () => {
  const { userRole, hasPermission } = useAuth();
  const isAdmin = userRole === 'admin';
  const isAccountant = userRole === 'ke_toan';
  const canCreate = isAdmin || hasPermission('prices.create');
  const canEdit = isAdmin || hasPermission('prices.edit');
  const canDelete = isAdmin || hasPermission('prices.delete');
  const canApprove = isAdmin || hasPermission('prices.approve');

  const {
    items,
    meta,
    loading,
    error,
    filters,
    setFilter,
    resetFilters,
    setPage,
    perPage,
    setPerPage,
    refetch,
  } = useServicePrices();

  const [groups, setGroups] = useState([]);
  const [selectedService, setSelectedService] = useState(null);
  const [timeline, setTimeline] = useState(null);
  const [timelineLoading, setTimelineLoading] = useState(false);

  const [formOpen, setFormOpen] = useState(false);
  const [formInitial, setFormInitial] = useState(null);
  const [formError, setFormError] = useState('');
  const [saving, setSaving] = useState(false);

  const [rejectModal, setRejectModal] = useState({ open: false, record: null });
  const [rejectError, setRejectError] = useState('');
  const [rejectSaving, setRejectSaving] = useState(false);

  useEffect(() => {
    serviceCatalogApi.groups().then(({ data }) => setGroups(data || [])).catch(() => setGroups([]));
  }, []);

  const loadTimeline = useCallback(async (serviceId) => {
    if (!serviceId) {
      setTimeline(null);
      return;
    }
    setTimelineLoading(true);
    try {
      const { data } = await servicePriceApi.timeline(serviceId);
      setTimeline(data);
    } catch {
      setTimeline(null);
    } finally {
      setTimelineLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!selectedService?.id) return;
    // eslint-disable-next-line react-hooks/set-state-in-effect
    loadTimeline(selectedService.id);
  }, [selectedService?.id, loadTimeline]);

  const handleSelect = (svc) => {
    setSelectedService(svc);
  };

  const handleAddPrice = () => {
    setFormInitial(null);
    setFormError('');
    setFormOpen(true);
  };

  const handleEditPrice = (record) => {
    setFormInitial(record);
    setFormError('');
    setFormOpen(true);
  };

  const handleSubmitForm = async (payload) => {
    setSaving(true);
    setFormError('');
    try {
      if (formInitial?.id) {
        await servicePriceApi.update(formInitial.id, payload);
      } else {
        await servicePriceApi.create(payload);
      }
      setFormOpen(false);
      await Promise.all([refetch(), loadTimeline(selectedService?.id)]);
    } catch (err) {
      setFormError(extractError(err, 'Lưu giá thất bại.'));
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (record) => {
    if (!window.confirm('Bạn có chắc muốn xoá bản ghi giá này?')) return;
    try {
      await servicePriceApi.remove(record.id);
      await Promise.all([refetch(), loadTimeline(selectedService?.id)]);
    } catch (err) {
      window.alert(extractError(err, 'Xoá thất bại.'));
    }
  };

  const handleApprove = async (record) => {
    try {
      await servicePriceApi.approve(record.id);
      await Promise.all([refetch(), loadTimeline(selectedService?.id)]);
    } catch (err) {
      window.alert(extractError(err, 'Duyệt thất bại.'));
    }
  };

  const handleRejectClick = (record) => {
    setRejectError('');
    setRejectModal({ open: true, record });
  };

  const handleSubmitReject = async (reason) => {
    if (!rejectModal.record) return;
    setRejectSaving(true);
    setRejectError('');
    try {
      await servicePriceApi.reject(rejectModal.record.id, reason);
      setRejectModal({ open: false, record: null });
      await Promise.all([refetch(), loadTimeline(selectedService?.id)]);
    } catch (err) {
      setRejectError(extractError(err, 'Từ chối thất bại.'));
    } finally {
      setRejectSaving(false);
    }
  };

  return (
    <div className="flex h-full min-h-0 flex-col bg-slate-50 p-4 lg:p-6">
      <div className="mb-3 flex items-end justify-between">
        <div>
          <h1 className="text-lg font-semibold text-gray-900">Quản lý bảng giá dịch vụ</h1>
          <p className="text-xs text-gray-500">
            UC4.3 — Lịch sử giá theo thời gian, đề xuất và phê duyệt thay đổi giá.
          </p>
        </div>
      </div>

      <div className="flex flex-1 flex-col gap-3 overflow-hidden lg:flex-row">
        <section className="flex flex-1 flex-col overflow-hidden rounded-lg border bg-white shadow-sm">
          <PriceFilterBar
            filters={filters}
            setFilter={setFilter}
            resetFilters={resetFilters}
            groups={groups}
          />
          {error && (
            <div className="border-b bg-red-50 px-4 py-2 text-[11px] text-red-700">{error}</div>
          )}
          <div className="flex-1 overflow-auto">
            <PriceServiceTable
              items={items}
              loading={loading}
              selectedId={selectedService?.id}
              onSelect={handleSelect}
            />
          </div>
          <Pagination
            page={meta.current_page}
            lastPage={meta.last_page}
            perPage={perPage}
            total={meta.total}
            onChangePage={setPage}
            onChangePerPage={setPerPage}
          />
        </section>

        <section className="flex w-full flex-col overflow-hidden rounded-lg border bg-white shadow-sm lg:w-[480px]">
          <PriceTimelinePanel
            timeline={timeline}
            loading={timelineLoading}
            onClose={() => setSelectedService(null)}
            onAddPrice={handleAddPrice}
            canCreate={canCreate}
            canApprove={canApprove}
            canEdit={canEdit}
            canDelete={canDelete}
            onEdit={handleEditPrice}
            onDelete={handleDelete}
            onApprove={handleApprove}
            onReject={handleRejectClick}
          />
        </section>
      </div>

      <PriceFormModal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        onSubmit={handleSubmitForm}
        service={selectedService}
        initial={formInitial}
        saving={saving}
        error={formError}
        isAdmin={isAdmin}
        isAccountant={isAccountant}
      />

      <RejectPromptModal
        open={rejectModal.open}
        onClose={() => setRejectModal({ open: false, record: null })}
        onSubmit={handleSubmitReject}
        saving={rejectSaving}
        error={rejectError}
      />
    </div>
  );
};

export default ServicePriceManagement;
