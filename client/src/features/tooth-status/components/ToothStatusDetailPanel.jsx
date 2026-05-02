import React, { useEffect, useState } from 'react';
import { toothStatusApi } from '@/api/toothStatusApi';

const ToothStatusDetailPanel = ({ statusId, canManage, onEdit, onToggleActive, onDelete, onClose }) => {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!statusId) {
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setData(null);
      return undefined;
    }
    let mounted = true;
    setLoading(true);
    setError('');
    toothStatusApi
      .get(statusId)
      .then((res) => {
        if (mounted) setData(res.data);
      })
      .catch((err) => {
        if (mounted) {
          setError(err?.response?.data?.message || 'Không thể tải chi tiết');
        }
      })
      .finally(() => {
        if (mounted) setLoading(false);
      });
    return () => {
      mounted = false;
    };
  }, [statusId]);

  if (!statusId) {
    return (
      <div className="w-full lg:w-[340px] bg-white border rounded-lg shadow-sm flex items-center justify-center text-xs text-gray-400 min-h-[260px] flex-shrink-0">
        Chọn một trạng thái để xem chi tiết
      </div>
    );
  }

  const status = data?.status;
  const usage = data?.usage;

  return (
    <div className="w-full lg:w-[340px] bg-white border rounded-lg shadow-sm flex flex-col flex-shrink-0">
      <div className="px-4 py-3 flex justify-between items-center border-b">
        <h2 className="font-semibold text-gray-800 text-sm">Chi tiết trạng thái</h2>
        <button
          type="button"
          onClick={onClose}
          className="text-gray-400 hover:text-gray-600"
        >
          ✕
        </button>
      </div>

      <div className="p-4 flex-1 overflow-y-auto text-xs">
        {loading && <div className="text-gray-400">Đang tải...</div>}
        {error && <div className="text-red-500">{error}</div>}

        {status && (
          <>
            <div className="flex gap-4 items-start mb-5">
              <div
                className="w-16 h-16 bg-white border-2 rounded-lg flex items-center justify-center text-3xl text-gray-600 shadow-sm flex-shrink-0"
                style={{ borderColor: status.color }}
              >
                {status.icon || '🦷'}
              </div>
              <div className="flex-1 min-w-0">
                <div className="flex justify-between items-start mb-0.5">
                  <div className="text-[10px] text-gray-500">
                    Mã trạng thái
                    <br />
                    <span className="text-xs text-gray-800">{status.code}</span>
                  </div>
                  <span
                    className={`text-[10px] px-2 py-0.5 rounded border ${
                      status.is_active
                        ? 'bg-green-50 text-green-700 border-green-200'
                        : 'bg-gray-100 text-gray-500 border-gray-200'
                    }`}
                  >
                    {status.is_active ? 'Đang sử dụng' : 'Ngừng sử dụng'}
                  </span>
                </div>
                <div className="mt-2">
                  <div className="text-[10px] text-gray-500">Tên trạng thái</div>
                  <h3 className="font-bold text-gray-900 text-sm">{status.name}</h3>
                </div>
              </div>
            </div>

            <div className="grid grid-cols-4 gap-2 mb-5">
              <div className="col-span-2">
                <div className="text-[10px] text-gray-500 mb-1">Nhóm trạng thái</div>
                <div className="text-xs text-gray-800">{status.group?.name || '-'}</div>
              </div>
              <div className="text-center">
                <div className="text-[10px] text-gray-500 mb-1">Màu</div>
                <div
                  className="w-4 h-4 rounded-full mx-auto border"
                  style={{ backgroundColor: status.color }}
                  title={status.color}
                />
              </div>
              <div className="text-center">
                <div className="text-[10px] text-gray-500 mb-1">Biểu tượng</div>
                <div className="text-lg leading-none">{status.icon || '-'}</div>
              </div>
              <div className="col-span-4 mt-1">
                <div className="text-[10px] text-gray-500 mb-1">
                  Thứ tự hiển thị:{' '}
                  <span className="text-xs text-gray-800 font-medium">
                    {status.display_order}
                  </span>
                </div>
              </div>
            </div>

            <div className="mb-3">
              <div className="text-[10px] text-gray-500 mb-1">Mô tả</div>
              <div className="text-[12px] text-gray-800 leading-relaxed">
                {status.description || '-'}
              </div>
            </div>
            <div className="mb-4">
              <div className="text-[10px] text-gray-500 mb-1">Ghi chú</div>
              <div className="text-[12px] text-gray-800">{status.notes || '-'}</div>
            </div>

            <div className="border rounded bg-gray-50 p-3 space-y-2">
              <div className="font-bold text-gray-800 text-[11px] mb-1">
                Thông tin sử dụng
              </div>
              <div className="flex justify-between text-[12px]">
                <span className="text-gray-500">Đã dùng trong hồ sơ bệnh nhân</span>
                <span className="text-gray-900 font-medium">
                  {usage?.used_in_records ?? 0}
                </span>
              </div>
              <div className="flex justify-between text-[12px]">
                <span className="text-gray-500">Liên kết với dịch vụ</span>
                <span className="text-gray-900 font-medium">
                  {usage?.linked_services ?? 0}
                </span>
              </div>
            </div>
          </>
        )}
      </div>

      {status && canManage && (
        <div className="p-3 border-t bg-gray-50 flex flex-wrap gap-2 justify-end">
          <button
            type="button"
            onClick={() => onEdit?.(status)}
            className="px-3 py-1.5 border rounded bg-white text-gray-700 hover:bg-gray-100 text-xs font-medium shadow-sm"
          >
            ✎ Chỉnh sửa
          </button>
          <button
            type="button"
            onClick={() => onToggleActive?.(status)}
            className="px-3 py-1.5 border rounded bg-white text-gray-700 hover:bg-gray-100 text-xs font-medium shadow-sm"
          >
            Đổi trạng thái
          </button>
          <button
            type="button"
            onClick={() => onDelete?.(status, usage)}
            className="px-3 py-1.5 border border-red-200 text-red-600 bg-white hover:bg-red-50 rounded text-xs font-medium shadow-sm"
          >
            ✕ Xóa
          </button>
        </div>
      )}
    </div>
  );
};

export default ToothStatusDetailPanel;
