import React, { useEffect, useState } from 'react';
import { X } from 'lucide-react';
import { toDateInputValue, formatVnd } from '../utils';

const PriceFormModal = ({
  open,
  onClose,
  onSubmit,
  service,
  initial,
  saving,
  error,
  isAdmin,
  isAccountant,
}) => {
  const [price, setPrice] = useState('');
  const [applyMode, setApplyMode] = useState('now'); // now | future
  const [effectiveFrom, setEffectiveFrom] = useState('');
  const [effectiveTo, setEffectiveTo] = useState('');
  const [reason, setReason] = useState('');
  const [confirmNow, setConfirmNow] = useState(false);

  useEffect(() => {
    if (!open) return;
    if (initial) {
      const future = initial.effective_from && new Date(initial.effective_from) > new Date();
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setPrice(initial.price ?? '');
      setApplyMode(future ? 'future' : 'now');
      setEffectiveFrom(toDateInputValue(initial.effective_from));
      setEffectiveTo(toDateInputValue(initial.effective_to));
      setReason(initial.reason || '');
    } else {
      setPrice('');
      setApplyMode('now');
      setEffectiveFrom(toDateInputValue(new Date()));
      setEffectiveTo('');
      setReason('');
    }
    setConfirmNow(false);
  }, [open, initial]);

  if (!open) return null;

  const isEdit = !!initial?.id;
  const isProposalMode = !isAdmin && isAccountant;

  const handleSubmit = (e) => {
    e?.preventDefault?.();
    if (applyMode === 'now' && !isEdit && !confirmNow) {
      setConfirmNow(true);
      return;
    }
    onSubmit?.({
      service_id: service?.id,
      price: Number(price) || 0,
      apply_now: applyMode === 'now' && !isEdit,
      effective_from: effectiveFrom || undefined,
      effective_to: effectiveTo || undefined,
      reason: reason || undefined,
      mode: isProposalMode ? 'proposal' : 'direct',
    });
  };

  return (
    <div className="fixed inset-0 z-40 flex items-start justify-center bg-black/40 p-4 lg:items-center">
      <div className="flex w-full max-w-md flex-col overflow-hidden rounded-lg bg-white shadow-xl">
        <div className="flex items-center justify-between border-b px-4 py-3">
          <h3 className="text-sm font-semibold">
            {isEdit ? 'Chỉnh sửa giá dịch vụ' : isProposalMode ? 'Đề xuất giá mới' : 'Thêm giá mới'}
          </h3>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
            <X size={16} />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-3 p-4 text-xs">
          {service && (
            <div className="rounded border bg-gray-50 px-3 py-2">
              <div className="text-[10px] text-gray-500">Dịch vụ áp dụng</div>
              <div className="text-sm font-medium text-gray-900">{service.name}</div>
              <div className="text-[10px] text-gray-500">{service.service_code}</div>
            </div>
          )}

          <div>
            <label className="mb-1 block text-[10px] font-medium text-gray-700">
              Giá mới (VND) <span className="text-red-500">*</span>
            </label>
            <input
              type="number"
              min="1"
              step="1"
              value={price}
              onChange={(e) => setPrice(e.target.value)}
              required
              className="w-full rounded border px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
            {price && Number(price) > 0 && (
              <div className="mt-1 text-[10px] text-gray-500">{formatVnd(price)}</div>
            )}
          </div>

          {!isEdit && (
            <div>
              <label className="mb-1 block text-[10px] font-medium text-gray-700">Cách áp dụng</label>
              <div className="flex gap-2">
                <label
                  className={`flex flex-1 cursor-pointer items-center gap-2 rounded border px-2 py-1.5 ${applyMode === 'now' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'bg-white'}`}
                >
                  <input
                    type="radio"
                    name="apply"
                    value="now"
                    checked={applyMode === 'now'}
                    onChange={() => setApplyMode('now')}
                  />
                  Áp dụng ngay
                </label>
                <label
                  className={`flex flex-1 cursor-pointer items-center gap-2 rounded border px-2 py-1.5 ${applyMode === 'future' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'bg-white'}`}
                >
                  <input
                    type="radio"
                    name="apply"
                    value="future"
                    checked={applyMode === 'future'}
                    onChange={() => setApplyMode('future')}
                  />
                  Theo lịch
                </label>
              </div>
            </div>
          )}

          {(applyMode === 'future' || isEdit) && (
            <div className="grid grid-cols-2 gap-2">
              <div>
                <label className="mb-1 block text-[10px] font-medium text-gray-700">
                  Hiệu lực từ <span className="text-red-500">*</span>
                </label>
                <input
                  type="date"
                  value={effectiveFrom}
                  onChange={(e) => setEffectiveFrom(e.target.value)}
                  required
                  className="w-full rounded border px-2 py-1.5"
                />
              </div>
              <div>
                <label className="mb-1 block text-[10px] font-medium text-gray-700">
                  Hiệu lực đến
                </label>
                <input
                  type="date"
                  value={effectiveTo}
                  onChange={(e) => setEffectiveTo(e.target.value)}
                  className="w-full rounded border px-2 py-1.5"
                />
                <div className="text-[10px] text-gray-400">Để trống = không thời hạn</div>
              </div>
            </div>
          )}

          <div>
            <label className="mb-1 block text-[10px] font-medium text-gray-700">Ghi chú / Lý do</label>
            <textarea
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              rows={2}
              className="w-full rounded border px-2 py-1.5"
              placeholder="Lý do thay đổi giá (tuỳ chọn)"
            />
          </div>

          {confirmNow && (
            <div className="rounded border border-amber-200 bg-amber-50 p-2 text-[11px] text-amber-800">
              <strong>Xác nhận áp dụng ngay:</strong> Giá hiện tại sẽ bị kết thúc và giá mới có hiệu lực
              từ thời điểm xác nhận. Hành động này không thể hoàn tác.
            </div>
          )}

          {error && (
            <div className="rounded border border-red-200 bg-red-50 p-2 text-[11px] text-red-700">
              {error}
            </div>
          )}

          <div className="flex justify-end gap-2 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="rounded border bg-white px-3 py-1.5 text-xs hover:bg-gray-50"
              disabled={saving}
            >
              Huỷ
            </button>
            <button
              type="submit"
              disabled={saving}
              className="rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-60"
            >
              {saving
                ? 'Đang lưu...'
                : isProposalMode
                ? 'Gửi đề xuất'
                : applyMode === 'now' && !isEdit
                ? confirmNow
                  ? 'Xác nhận áp dụng ngay'
                  : 'Áp dụng ngay'
                : 'Lưu'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default PriceFormModal;
