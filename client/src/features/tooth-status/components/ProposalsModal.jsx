import React, { useState } from 'react';
import { useToothStatusProposals } from '../hooks/useToothStatusProposals';
import { formatDateTime } from '../utils';

const STATUS_TABS = [
  { value: 'pending', label: 'Đang chờ' },
  { value: 'approved', label: 'Đã duyệt' },
  { value: 'rejected', label: 'Đã từ chối' },
  { value: 'all', label: 'Tất cả' },
];

const ProposalsModal = ({ open, onClose, onApproved }) => {
  const {
    items,
    pendingCount,
    loading,
    error,
    statusFilter,
    setStatusFilter,
    approve,
    reject,
  } = useToothStatusProposals({ enabled: open });
  const [actionError, setActionError] = useState('');
  const [acting, setActing] = useState(null);

  if (!open) return null;

  const handleApprove = async (id) => {
    setActionError('');
    setActing(id);
    try {
      await approve(id);
      onApproved?.();
    } catch (err) {
      setActionError(err?.response?.data?.message || 'Phê duyệt thất bại');
    } finally {
      setActing(null);
    }
  };

  const handleReject = async (id) => {
    const note = window.prompt('Lý do từ chối (tùy chọn):', '');
    if (note === null) return;
    setActionError('');
    setActing(id);
    try {
      await reject(id, note);
    } catch (err) {
      setActionError(err?.response?.data?.message || 'Từ chối thất bại');
    } finally {
      setActing(null);
    }
  };

  return (
    <div className="fixed inset-0 z-40 bg-black/40 flex items-center justify-center p-4">
      <div className="bg-white w-full max-w-3xl rounded-lg shadow-xl flex flex-col max-h-[92vh]">
        <div className="px-4 py-3 flex justify-between items-center border-b">
          <div>
            <h2 className="font-semibold text-gray-800 text-sm">
              Đề xuất từ bác sĩ
              {pendingCount > 0 && (
                <span className="ml-2 px-2 py-0.5 rounded-full bg-red-500 text-white text-[10px]">
                  {pendingCount} chờ duyệt
                </span>
              )}
            </h2>
            <p className="text-[11px] text-gray-500">
              Phê duyệt hoặc từ chối các đề xuất bác sĩ gửi lên hệ thống.
            </p>
          </div>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
            ✕
          </button>
        </div>

        <div className="px-4 pt-3 flex gap-1 text-xs">
          {STATUS_TABS.map((tab) => (
            <button
              key={tab.value}
              type="button"
              onClick={() => setStatusFilter(tab.value)}
              className={`px-3 py-1 rounded border text-xs ${
                statusFilter === tab.value
                  ? 'bg-blue-600 text-white border-blue-600'
                  : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
              }`}
            >
              {tab.label}
            </button>
          ))}
        </div>

        <div className="p-4 flex-1 overflow-auto text-xs">
          {error && <div className="mb-2 text-red-500">{error}</div>}
          {actionError && <div className="mb-2 text-red-500">{actionError}</div>}
          {loading && <div className="text-gray-400">Đang tải...</div>}
          {!loading && !items.length && (
            <div className="text-gray-400 italic">Không có đề xuất nào.</div>
          )}
          {items.length > 0 && (
            <table className="w-full text-left">
              <thead className="text-[11px] text-gray-500 border-b">
                <tr>
                  <th className="py-2 font-medium">Thời gian</th>
                  <th className="py-2 font-medium">Bác sĩ</th>
                  <th className="py-2 font-medium">Hành động</th>
                  <th className="py-2 font-medium">Đề xuất</th>
                  <th className="py-2 font-medium">Trạng thái</th>
                  <th className="py-2 font-medium text-right">Thao tác</th>
                </tr>
              </thead>
              <tbody>
                {items.map((p) => (
                  <tr key={p.id} className="border-b align-top">
                    <td className="py-2 text-gray-500 whitespace-nowrap">
                      {formatDateTime(p.created_at)}
                    </td>
                    <td className="py-2">{p.proposer?.name || '-'}</td>
                    <td className="py-2">
                      {p.action === 'create' ? 'Thêm mới' : 'Chỉnh sửa'}
                    </td>
                    <td className="py-2">
                      <div className="font-medium text-gray-800">
                        {p.payload?.name || p.toothStatus?.name || '(không có tên)'}
                      </div>
                      <div className="text-[10px] text-gray-500">
                        Mã: {p.payload?.code || p.toothStatus?.code || '-'}
                      </div>
                    </td>
                    <td className="py-2">
                      <span
                        className={`px-2 py-0.5 rounded text-[10px] border ${
                          p.status === 'pending'
                            ? 'bg-yellow-50 text-yellow-700 border-yellow-200'
                            : p.status === 'approved'
                              ? 'bg-green-50 text-green-700 border-green-200'
                              : 'bg-red-50 text-red-700 border-red-200'
                        }`}
                      >
                        {p.status === 'pending'
                          ? 'Chờ duyệt'
                          : p.status === 'approved'
                            ? 'Đã duyệt'
                            : 'Đã từ chối'}
                      </span>
                      {p.review_note && (
                        <div className="text-[10px] text-gray-500 mt-1">
                          Lý do: {p.review_note}
                        </div>
                      )}
                    </td>
                    <td className="py-2 text-right">
                      {p.status === 'pending' ? (
                        <div className="flex justify-end gap-1.5">
                          <button
                            type="button"
                            onClick={() => handleApprove(p.id)}
                            disabled={acting === p.id}
                            className="px-2 py-1 border border-green-200 text-green-700 bg-white hover:bg-green-50 rounded text-[11px]"
                          >
                            ✓ Duyệt
                          </button>
                          <button
                            type="button"
                            onClick={() => handleReject(p.id)}
                            disabled={acting === p.id}
                            className="px-2 py-1 border border-red-200 text-red-600 bg-white hover:bg-red-50 rounded text-[11px]"
                          >
                            ✕ Từ chối
                          </button>
                        </div>
                      ) : (
                        <span className="text-[10px] text-gray-400">
                          {p.reviewer?.name ? `Bởi ${p.reviewer.name}` : ''}
                        </span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>

        <div className="p-3 border-t bg-gray-50 flex justify-end">
          <button
            type="button"
            onClick={onClose}
            className="px-5 py-1.5 border rounded bg-white hover:bg-gray-100 text-xs"
          >
            Đóng
          </button>
        </div>
      </div>
    </div>
  );
};

export default ProposalsModal;
