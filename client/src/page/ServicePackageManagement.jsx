import React, { useState } from 'react';
import { useAuth } from '@/hooks/useAuth';
import { useServicePackages } from '@/features/service-package/hooks/useServicePackages';
import PackageFilterBar from '@/features/service-package/components/PackageFilterBar';
import PackageTable from '@/features/service-package/components/PackageTable';
import Pagination from '@/features/service-package/components/Pagination';
import PackageDetailPanel from '@/features/service-package/components/PackageDetailPanel';
import PackageFormWizard from '@/features/service-package/components/PackageFormWizard';
import PackageStatusModal from '@/features/service-package/components/PackageStatusModal';
import PackageClonePromptModal from '@/features/service-package/components/PackageClonePromptModal';
import { servicePackageApi } from '@/api/servicePackageApi';

const ServicePackageManagement = () => {
  const { userRole } = useAuth();
  const canManage = userRole === 'admin';

  const {
    items,
    meta,
    loading,
    filters,
    setFilter,
    resetFilters,
    page,
    setPage,
    perPage,
    setPerPage,
    create,
    update,
    changeStatus,
    remove,
    clone,
    newVersion,
  } = useServicePackages();

  const [selectedId, setSelectedId] = useState(null);
  const [formOpen, setFormOpen] = useState(false);
  const [formInitial, setFormInitial] = useState(null);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState('');

  const [statusModal, setStatusModal] = useState({ open: false, pkg: null });
  const [statusSaving, setStatusSaving] = useState(false);
  const [statusError, setStatusError] = useState('');

  const [cloneModal, setCloneModal] = useState({ open: false, pkg: null, mode: 'clone' });
  const [cloneSaving, setCloneSaving] = useState(false);
  const [cloneError, setCloneError] = useState('');

  const extractError = (err, fallback) =>
    err?.response?.data?.message ||
    Object.values(err?.response?.data?.errors || {})
      .flat()
      .join(' · ') ||
    fallback;

  const handleCreate = () => {
    setFormInitial(null);
    setFormError('');
    setFormOpen(true);
  };

  const handleEdit = async (pkg) => {
    setFormError('');
    // The list endpoint only returns items_count, not the full items relation.
    // Fetch the full package so the wizard can prefill the service items step.
    if (pkg && (!pkg.items || pkg.items.length === 0) && pkg.id) {
      try {
        const { data } = await servicePackageApi.get(pkg.id);
        setFormInitial(data);
      } catch {
        setFormInitial(pkg);
      }
    } else {
      setFormInitial(pkg);
    }
    setFormOpen(true);
  };

  const handleFormSubmit = async (payload) => {
    setSaving(true);
    setFormError('');
    try {
      if (formInitial?.id) {
        await update(formInitial.id, payload);
      } else {
        const created = await create(payload);
        if (created?.id) setSelectedId(created.id);
      }
      setFormOpen(false);
    } catch (err) {
      setFormError(extractError(err, 'Lưu gói dịch vụ thất bại'));
    } finally {
      setSaving(false);
    }
  };

  const handleChangeStatus = (pkg) => {
    setStatusError('');
    setStatusModal({ open: true, pkg });
  };

  const handleSubmitStatus = async ({ status, reason }) => {
    if (!statusModal.pkg) return;
    setStatusSaving(true);
    setStatusError('');
    try {
      await changeStatus(statusModal.pkg.id, status, reason);
      setStatusModal({ open: false, pkg: null });
    } catch (err) {
      setStatusError(extractError(err, 'Đổi trạng thái thất bại'));
    } finally {
      setStatusSaving(false);
    }
  };

  const handleClone = (pkg) => {
    setCloneError('');
    setCloneModal({ open: true, pkg, mode: 'clone' });
  };

  const handleNewVersion = (pkg) => {
    setCloneError('');
    setCloneModal({ open: true, pkg, mode: 'version' });
  };

  const handleSubmitClone = async ({ name, code, reason }) => {
    if (!cloneModal.pkg) return;
    setCloneSaving(true);
    setCloneError('');
    try {
      if (cloneModal.mode === 'clone') {
        const created = await clone(cloneModal.pkg.id, {
          name: name || undefined,
          code: code || undefined,
          reason: reason || undefined,
        });
        if (created?.id) setSelectedId(created.id);
      } else {
        await newVersion(cloneModal.pkg.id, { reason: reason || undefined });
      }
      setCloneModal({ open: false, pkg: null, mode: 'clone' });
    } catch (err) {
      setCloneError(extractError(err, 'Thao tác thất bại'));
    } finally {
      setCloneSaving(false);
    }
  };

  const handleDelete = async (pkg) => {
    if (!window.confirm(`Xóa gói "${pkg.name}"? Thao tác này không thể hoàn tác.`)) return;
    try {
      await remove(pkg.id);
      if (selectedId === pkg.id) setSelectedId(null);
    } catch (err) {
      window.alert(extractError(err, 'Xóa thất bại'));
    }
  };

  return (
    <div className="p-4 flex flex-col gap-4">
      <div className="flex flex-col sm:flex-row justify-between sm:items-center gap-3">
        <div>
          <h1 className="text-lg font-bold text-gray-800">Quản lý gói dịch vụ</h1>
          <p className="text-gray-500 text-xs mt-0.5">
            Quản lý danh sách gói dịch vụ và phạm vi sử dụng
          </p>
        </div>
        {canManage && (
          <div className="flex gap-2">
            <button
              type="button"
              onClick={handleCreate}
              className="px-3 py-1.5 rounded bg-blue-600 text-white hover:bg-blue-700 font-medium text-xs"
            >
              + Thêm gói dịch vụ
            </button>
          </div>
        )}
      </div>

      <div className="flex flex-col lg:flex-row gap-4 items-start">
        <div className="flex-1 bg-white border rounded-lg shadow-sm flex flex-col min-w-0 w-full">
          <PackageFilterBar filters={filters} onChange={setFilter} onReset={resetFilters} />
          <div className="px-4 py-2 border-b bg-gray-50/50 text-[11px] text-gray-500 font-medium">
            Tổng {meta.total} gói dịch vụ
          </div>
          <PackageTable
            items={items}
            selectedId={selectedId}
            onSelect={setSelectedId}
            onEdit={canManage ? handleEdit : undefined}
            onChangeStatus={canManage ? handleChangeStatus : undefined}
            loading={loading}
            page={meta.current_page}
            perPage={perPage}
          />
          <Pagination
            page={page}
            lastPage={meta.last_page}
            total={meta.total}
            perPage={perPage}
            onChangePage={setPage}
            onChangePerPage={setPerPage}
          />
        </div>

        <PackageDetailPanel
          packageId={selectedId}
          onClose={() => setSelectedId(null)}
          onEdit={handleEdit}
          onChangeStatus={handleChangeStatus}
          onClone={handleClone}
          onCreateNewVersion={handleNewVersion}
          onDelete={handleDelete}
          canManage={canManage}
        />
      </div>

      <PackageFormWizard
        open={formOpen}
        initial={formInitial}
        saving={saving}
        error={formError}
        onClose={() => setFormOpen(false)}
        onSubmit={handleFormSubmit}
      />

      <PackageStatusModal
        open={statusModal.open}
        pkg={statusModal.pkg}
        saving={statusSaving}
        error={statusError}
        onClose={() => setStatusModal({ open: false, pkg: null })}
        onSubmit={handleSubmitStatus}
      />

      <PackageClonePromptModal
        open={cloneModal.open}
        pkg={cloneModal.pkg}
        mode={cloneModal.mode}
        saving={cloneSaving}
        error={cloneError}
        onClose={() => setCloneModal({ open: false, pkg: null, mode: 'clone' })}
        onSubmit={handleSubmitClone}
      />
    </div>
  );
};

export default ServicePackageManagement;
