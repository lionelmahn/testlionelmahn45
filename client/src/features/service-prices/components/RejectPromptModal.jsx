import React, { useEffect, useState } from 'react';
import { X } from 'lucide-react';

const RejectPromptModal = ({ open, onClose, onSubmit, saving, error }) => {
  const [reason, setReason] = useState('');

  useEffect(() => {
    if (!open) return;
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setReason('');
  }, [open]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div className="w-full max-w-sm rounded-lg bg-white shadow-xl">
        <div className="flex items-center justify-between border-b px-4 py-3">
          <h3 className="text-sm font-semibold">Từ chối đề xuất giá</h3>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
            <X size={16} />
          </button>
        </div>
        <div className="space-y-3 p-4 text-xs">
          <div>
            <label className="mb-1 block text-[10px] font-medium text-gray-700">Lý do từ chối</label>
            <textarea
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              rows={3}
              className="w-full rounded border px-2 py-1.5"
              placeholder="Nhập lý do để gửi lại cho người đề xuất"
            />
          </div>
          {error && (
            <div className="rounded border border-red-200 bg-red-50 p-2 text-[11px] text-red-700">{error}</div>
          )}
          <div className="flex justify-end gap-2">
            <button
              onClick={onClose}
              className="rounded border bg-white px-3 py-1.5 text-xs hover:bg-gray-50"
              disabled={saving}
            >
              Huỷ
            </button>
            <button
              onClick={() => onSubmit?.(reason)}
              disabled={saving}
              className="rounded bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-60"
            >
              {saving ? 'Đang xử lý...' : 'Từ chối'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default RejectPromptModal;
