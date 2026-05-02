import React, { useEffect, useState } from 'react';
import { useAuth } from '@/hooks/useAuth';
import { toothStatusApi } from '@/api/toothStatusApi';
import { useToothStatuses } from '@/features/tooth-status/hooks/useToothStatuses';
import ToothStatusFilterBar from '@/features/tooth-status/components/ToothStatusFilterBar';
import ToothStatusTable from '@/features/tooth-status/components/ToothStatusTable';
import Pagination from '@/features/tooth-status/components/Pagination';
import ToothStatusDetailPanel from '@/features/tooth-status/components/ToothStatusDetailPanel';
import ToothStatusFormModal from '@/features/tooth-status/components/ToothStatusFormModal';
import HistoryCard from '@/features/tooth-status/components/HistoryCard';
import ProposalsModal from '@/features/tooth-status/components/ProposalsModal';

const errorMessage = (err, fallback) =>
  err?.response?.data?.message ||
  Object.values(err?.response?.data?.errors || {})
    .flat()
    .join(' · ') ||
  fallback;

const ToothStatusManagement = () => {
  const { userRole } = useAuth();
  const isAdmin = userRole === 'admin';
  const isDoctor = userRole === 'bac_si';
  const canManage = isAdmin;

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
    groups,
    refresh,
    create,
    update,
    toggleActive,
    remove,
    reorder,
  } = useToothStatuses();

  const [selectedId, setSelectedId] = useState(null);
  const [formOpen, setFormOpen] = useState(false);
  const [formInitial, setFormInitial] = useState(null);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState('');
  const [pageError, setPageError] = useState('');
  const [historyKey, setHistoryKey] = useState(0);
  const [proposalsOpen, setProposalsOpen] = useState(false);
  const [pendingCount, setPendingCount] = useState(0);

  // Admin-only: poll the pending-proposal count to drive the badge.
  useEffect(() => {
    if (!isAdmin) return undefined;
    let mounted = true;
    const fetchCount = () =>
      toothStatusApi.listProposals({ status: 'pending' })
        .then((res) => {
          if (mounted) setPendingCount(res.data?.pending_count || 0);
        })
        .catch(() => {});
    fetchCount();
    const id = setInterval(fetchCount, 30000);
    return () => {
      mounted = false;
      clearInterval(id);
    };
  }, [isAdmin]);

  const handleCreate = () => {
    setFormInitial(null);
    setFormError('');
    setFormOpen(true);
  };

  const handleEdit = (item) => {
    setFormInitial(item);
    setFormError('');
    setFormOpen(true);
  };

  const handleSubmit = async (payload) => {
    setSaving(true);
    setFormError('');
    try {
      if (isDoctor) {
        // Doctor flow (A1) — submit a proposal instead of writing directly.
        await toothStatusApi.createProposal({
          action: formInitial?.id ? 'update' : 'create',
          tooth_status_id: formInitial?.id || null,
          payload,
        });
        setFormOpen(false);
        return;
      }

      if (formInitial?.id) {
        const updated = await update(formInitial.id, payload);
        if (updated?.id) setSelectedId(updated.id);
      } else {
        const created = await create(payload);
        if (created?.id) setSelectedId(created.id);
      }
      setFormOpen(false);
      setHistoryKey((k) => k + 1);
    } catch (err) {
      setFormError(errorMessage(err, 'Lưu trạng thái thất bại'));
    } finally {
      setSaving(false);
    }
  };

  const handleToggleActive = async (item) => {
    setPageError('');
    try {
      await toggleActive(item.id, !item.is_active);
      setHistoryKey((k) => k + 1);
    } catch (err) {
      setPageError(errorMessage(err, 'Không thể đổi trạng thái'));
    }
  };

  const handleDelete = async (item, usage) => {
    if ((usage?.used_in_records || 0) > 0) {
      window.alert(
        'Trạng thái đã được dùng trong hồ sơ bệnh nhân, không thể xóa (E3).',
      );
      return;
    }
    if (!window.confirm(`Xác nhận xóa trạng thái "${item.name}" (${item.code})?`)) {
      return;
    }
    setPageError('');
    try {
      await remove(item.id);
      if (selectedId === item.id) setSelectedId(null);
      setHistoryKey((k) => k + 1);
    } catch (err) {
      setPageError(errorMessage(err, 'Không thể xóa trạng thái'));
    }
  };

  const handleReorder = async (orderedIds) => {
    setPageError('');
    try {
      await reorder(orderedIds);
      setHistoryKey((k) => k + 1);
    } catch (err) {
      setPageError(errorMessage(err, 'Không thể cập nhật thứ tự'));
    }
  };

  return (
    <div className="p-4 flex flex-col gap-4">
      <div className="flex flex-col sm:flex-row justify-between sm:items-center gap-3">
        <div>
          <h1 className="text-lg font-bold text-gray-800">Quản lý trạng thái răng</h1>
          <p className="text-gray-500 text-xs mt-0.5">
            Quản lý danh mục trạng thái răng dùng trong hồ sơ khám và điều trị
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          {isAdmin && (
            <button
              type="button"
              onClick={() => setProposalsOpen(true)}
              className="relative px-3 py-1.5 border border-gray-300 rounded bg-white text-gray-700 hover:bg-gray-50 text-xs font-medium flex items-center gap-2 shadow-sm"
            >
              📑 Đề xuất từ bác sĩ
              {pendingCount > 0 && (
                <span className="absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-500 text-white text-[9px] rounded-full flex items-center justify-center border-2 border-white">
                  {pendingCount}
                </span>
              )}
            </button>
          )}
          {(isAdmin || isDoctor) && (
            <button
              type="button"
              onClick={handleCreate}
              className="px-3 py-1.5 rounded bg-blue-600 text-white hover:bg-blue-700 text-xs font-medium shadow-sm"
            >
              + {isDoctor ? 'Đề xuất trạng thái mới' : 'Thêm mới'}
            </button>
          )}
        </div>
      </div>

      {pageError && (
        <div className="p-2 bg-red-50 border border-red-200 text-red-700 rounded text-xs">
          {pageError}
        </div>
      )}

      <div className="flex flex-col lg:flex-row gap-4 items-stretch">
        <div className="flex-1 bg-white border rounded-lg shadow-sm flex flex-col min-w-0 w-full">
          <ToothStatusFilterBar
            filters={filters}
            groups={groups}
            onChange={setFilter}
            onReset={resetFilters}
          />
          <div className="px-4 py-2 border-b bg-gray-50/50 text-[11px] text-gray-500 font-medium">
            Tổng {meta?.total ?? 0} trạng thái
          </div>
          <ToothStatusTable
            items={items}
            loading={loading}
            selectedId={selectedId}
            onSelect={setSelectedId}
            onEdit={handleEdit}
            onToggleActive={handleToggleActive}
            onDelete={(item) => handleDelete(item, item.usage)}
            onReorder={handleReorder}
            canManage={canManage}
            page={page}
            perPage={perPage}
          />
          <Pagination
            meta={meta}
            page={page}
            onPageChange={setPage}
            perPage={perPage}
            onPerPageChange={setPerPage}
          />
        </div>

        <ToothStatusDetailPanel
          statusId={selectedId}
          canManage={canManage}
          onEdit={(status) => handleEdit(status)}
          onToggleActive={(status) => handleToggleActive(status)}
          onDelete={(status, usage) => handleDelete(status, usage)}
          onClose={() => setSelectedId(null)}
        />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
        <div className="lg:col-span-2">
          <HistoryCard statusId={selectedId} refreshKey={historyKey} />
        </div>
        <div className="bg-white border rounded-lg shadow-sm p-4 text-xs text-gray-600 leading-relaxed">
          <h3 className="font-semibold text-gray-800 text-sm mb-2">Quy tắc sử dụng</h3>
          <ul className="list-disc pl-5 space-y-1">
            <li>Mã trạng thái phải duy nhất, không thay đổi sau khi tạo.</li>
            <li>Trạng thái đã ngừng sử dụng vẫn hiển thị trong dữ liệu lịch sử bệnh nhân.</li>
            <li>Không thể xoá trạng thái đã được sử dụng trong hồ sơ bệnh nhân (E3).</li>
            {isDoctor ? (
              <li>Bác sĩ chỉ có thể đề xuất; admin sẽ phê duyệt trước khi áp dụng.</li>
            ) : (
              <li>Kéo thả ⋮⋮ ở cột STT để cập nhật thứ tự hiển thị.</li>
            )}
          </ul>
        </div>
      </div>

      <ToothStatusFormModal
        open={formOpen}
        initial={formInitial}
        groups={groups}
        saving={saving}
        error={formError}
        onClose={() => setFormOpen(false)}
        onSubmit={handleSubmit}
        mode={isDoctor ? 'doctor' : 'admin'}
      />

      {isAdmin && (
        <ProposalsModal
          open={proposalsOpen}
          onClose={() => setProposalsOpen(false)}
          onApproved={async () => {
            await refresh();
            setHistoryKey((k) => k + 1);
          }}
        />
      )}
    </div>
  );
};

export default ToothStatusManagement;
