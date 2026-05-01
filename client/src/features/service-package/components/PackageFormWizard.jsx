import React, { useEffect, useMemo, useState } from 'react';
import { FORM_STEPS } from '../constants';
import {
  buildEmptyForm,
  computeDiscount,
  computeOriginalPrice,
  formatVnd,
  toFormState,
} from '../utils';
import PackageItemSelector from './PackageItemSelector';

const PackageFormWizard = ({ open, initial, saving, error, onClose, onSubmit }) => {
  const [step, setStep] = useState(1);
  const [form, setForm] = useState(buildEmptyForm());
  const [localError, setLocalError] = useState('');

  useEffect(() => {
    if (!open) return;
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setStep(1);
    setLocalError('');
    setForm(initial ? toFormState(initial) : buildEmptyForm());
  }, [open, initial]);

  const original = useMemo(() => computeOriginalPrice(form.items), [form.items]);
  const { amount: discountAmount, percent: discountPercent } = useMemo(
    () => computeDiscount(original, form.package_price),
    [original, form.package_price]
  );

  if (!open) return null;

  const updateField = (key, value) => {
    setForm((prev) => ({ ...prev, [key]: value }));
  };

  const validateStep = (target) => {
    if (target === 2 || target === 5) {
      if (!form.code?.trim() && !initial) {
        // code is auto-generated when blank on create, allow
      }
      if (!form.name?.trim()) {
        setLocalError('Tên gói là bắt buộc (E10).');
        return false;
      }
      if (form.effective_from && form.effective_to && form.effective_from > form.effective_to) {
        setLocalError('Thời gian hiệu lực không hợp lệ: from > to (E8).');
        return false;
      }
    }
    if (target === 3 || target === 5) {
      if (!form.items?.length) {
        setLocalError('Gói phải có ít nhất 1 dịch vụ thành phần (E2).');
        return false;
      }
    }
    if (target === 4 || target === 5) {
      const pkg = Number(form.package_price || 0);
      if (pkg < 0) {
        setLocalError('Giá gói không hợp lệ (E4).');
        return false;
      }
      if (original > 0 && pkg > original) {
        setLocalError('Giá gói không được lớn hơn tổng giá thành phần (E3).');
        return false;
      }
    }
    if (target === 5) {
      if (form.usage_validity_days !== '' && Number(form.usage_validity_days) < 0) {
        setLocalError('Thời hạn sử dụng không hợp lệ (E9).');
        return false;
      }
    }
    setLocalError('');
    return true;
  };

  const goToStep = (target) => {
    if (target > step && !validateStep(target)) return;
    setStep(target);
  };

  const handleSubmit = async () => {
    if (!validateStep(5)) return;
    const payload = {
      ...form,
      package_price: form.package_price === '' ? 0 : Number(form.package_price),
      usage_validity_days:
        form.usage_validity_days === '' ? null : Number(form.usage_validity_days),
      effective_from: form.effective_from || null,
      effective_to: form.effective_to || null,
      items: form.items.map((it) => ({
        service_id: it.service_id,
        quantity: Number(it.quantity || 1),
        unit_price: Number(it.unit_price || 0),
        note: it.note || null,
      })),
    };
    await onSubmit(payload);
  };

  const renderStep = () => {
    switch (step) {
      case 1:
        return (
          <div className="flex flex-col gap-3 text-xs">
            <div className="font-bold text-gray-800 uppercase text-xs">Thông tin chung</div>
            <div className="flex gap-3">
              <div className="flex-1">
                <label className="text-gray-500 mb-1 block">Mã gói</label>
                <input
                  type="text"
                  value={form.code}
                  onChange={(e) => updateField('code', e.target.value)}
                  placeholder="Tự sinh nếu để trống"
                  className="w-full border rounded px-2 py-1.5 focus:outline-none"
                />
              </div>
              <div className="flex-[1.5]">
                <label className="text-gray-500 mb-1 block">
                  Tên gói <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  value={form.name}
                  onChange={(e) => updateField('name', e.target.value)}
                  className="w-full border rounded px-2 py-1.5 focus:outline-none"
                />
              </div>
            </div>
            <div>
              <label className="text-gray-500 mb-1 block">Mô tả</label>
              <textarea
                rows={3}
                value={form.description}
                onChange={(e) => updateField('description', e.target.value)}
                className="w-full border rounded px-2 py-1.5 focus:outline-none resize-none"
              />
            </div>
            <div className="flex gap-3 items-end">
              <div className="flex-1">
                <label className="text-gray-500 mb-1 block">
                  Phạm vi <span className="text-red-500">*</span>
                </label>
                <div className="flex items-center gap-3 mt-1">
                  <label className="flex items-center gap-1 cursor-pointer">
                    <input
                      type="radio"
                      checked={form.visibility === 'public'}
                      onChange={() => updateField('visibility', 'public')}
                    />
                    Công khai
                  </label>
                  <label className="flex items-center gap-1 cursor-pointer">
                    <input
                      type="radio"
                      checked={form.visibility === 'internal'}
                      onChange={() => updateField('visibility', 'internal')}
                    />
                    Nội bộ
                  </label>
                </div>
              </div>
              <div className="flex-1">
                <label className="text-gray-500 mb-1 block">Trạng thái</label>
                <select
                  value={form.status}
                  onChange={(e) => updateField('status', e.target.value)}
                  className="w-full border rounded px-2 py-1.5 focus:outline-none"
                >
                  <option value="draft">Nháp</option>
                  <option value="active">Đang áp dụng</option>
                  <option value="hidden">Tạm ẩn</option>
                  <option value="discontinued">Ngừng áp dụng</option>
                </select>
              </div>
            </div>
            <div className="flex gap-3">
              <div className="flex-1">
                <label className="text-gray-500 mb-1 block">Hiệu lực từ</label>
                <input
                  type="date"
                  value={form.effective_from}
                  onChange={(e) => updateField('effective_from', e.target.value)}
                  className="w-full border rounded px-2 py-1.5 focus:outline-none"
                />
              </div>
              <div className="flex-1">
                <label className="text-gray-500 mb-1 block">Hiệu lực đến</label>
                <input
                  type="date"
                  value={form.effective_to}
                  onChange={(e) => updateField('effective_to', e.target.value)}
                  className="w-full border rounded px-2 py-1.5 focus:outline-none"
                />
              </div>
              <div className="flex-1">
                <label className="text-gray-500 mb-1 block">Thời hạn sử dụng (ngày)</label>
                <input
                  type="number"
                  min={0}
                  value={form.usage_validity_days}
                  onChange={(e) => updateField('usage_validity_days', e.target.value)}
                  className="w-full border rounded px-2 py-1.5 focus:outline-none"
                />
              </div>
            </div>
          </div>
        );
      case 2:
        return (
          <div className="flex flex-col gap-2 text-xs">
            <div className="font-bold text-gray-800 uppercase text-xs">Dịch vụ thành phần</div>
            <PackageItemSelector
              items={form.items}
              onChange={(items) => updateField('items', items)}
            />
          </div>
        );
      case 3:
        return (
          <div className="flex flex-col gap-3 text-xs">
            <div className="font-bold text-gray-800 uppercase text-xs">Giá &amp; giảm giá</div>
            <div className="border rounded p-3 bg-gray-50 grid grid-cols-2 gap-3">
              <div>
                <div className="text-gray-500 text-[11px]">Tổng giá thành phần (gốc)</div>
                <div className="font-medium text-gray-800">{formatVnd(original)}</div>
              </div>
              <div>
                <div className="text-gray-500 text-[11px]">Số dịch vụ</div>
                <div className="font-medium text-gray-800">{form.items.length}</div>
              </div>
              <div>
                <div className="text-gray-500 text-[11px]">Giảm giá (tính tự động)</div>
                <div className="font-medium text-gray-800">
                  {formatVnd(discountAmount)} ({discountPercent.toFixed(2)}%)
                </div>
              </div>
              <div>
                <div className="text-gray-500 text-[11px]">Giá cuối</div>
                <div className="font-bold text-blue-600">{formatVnd(form.package_price || 0)}</div>
              </div>
            </div>
            <div>
              <label className="text-gray-500 mb-1 block">
                Giá gói <span className="text-red-500">*</span>
              </label>
              <input
                type="number"
                min={0}
                value={form.package_price}
                onChange={(e) => updateField('package_price', e.target.value)}
                className="w-full border rounded px-2 py-1.5 focus:outline-none"
              />
              <div className="text-[10px] text-gray-500 mt-1">
                Giá gói không được lớn hơn tổng giá dịch vụ thành phần (E3).
              </div>
            </div>
          </div>
        );
      case 4:
        return (
          <div className="flex flex-col gap-3 text-xs">
            <div className="font-bold text-gray-800 uppercase text-xs">Điều kiện áp dụng</div>
            <div>
              <label className="text-gray-500 mb-1 block">Điều kiện áp dụng</label>
              <textarea
                rows={4}
                value={form.conditions}
                onChange={(e) => updateField('conditions', e.target.value)}
                placeholder="Ví dụ: Áp dụng cho khách hàng đăng ký mới, không áp dụng cùng chương trình khác..."
                className="w-full border rounded px-2 py-1.5 focus:outline-none resize-none"
              />
            </div>
            <div>
              <label className="text-gray-500 mb-1 block">Ghi chú</label>
              <textarea
                rows={3}
                value={form.notes}
                onChange={(e) => updateField('notes', e.target.value)}
                className="w-full border rounded px-2 py-1.5 focus:outline-none resize-none"
              />
            </div>
          </div>
        );
      case 5:
        return (
          <div className="flex flex-col gap-3 text-xs">
            <div className="font-bold text-gray-800 uppercase text-xs">Xác nhận</div>
            <div className="border rounded p-3 grid grid-cols-2 gap-3 bg-gray-50">
              <div>
                <div className="text-gray-500 text-[11px]">Mã gói</div>
                <div className="font-medium">{form.code || '(tự sinh)'}</div>
              </div>
              <div>
                <div className="text-gray-500 text-[11px]">Tên gói</div>
                <div className="font-medium">{form.name}</div>
              </div>
              <div>
                <div className="text-gray-500 text-[11px]">Trạng thái</div>
                <div className="font-medium">{form.status}</div>
              </div>
              <div>
                <div className="text-gray-500 text-[11px]">Phạm vi</div>
                <div className="font-medium">{form.visibility}</div>
              </div>
              <div>
                <div className="text-gray-500 text-[11px]">Hiệu lực</div>
                <div className="font-medium">
                  {form.effective_from || '—'} → {form.effective_to || '—'}
                </div>
              </div>
              <div>
                <div className="text-gray-500 text-[11px]">Thời hạn dùng (ngày)</div>
                <div className="font-medium">{form.usage_validity_days || '—'}</div>
              </div>
              <div>
                <div className="text-gray-500 text-[11px]">Số dịch vụ</div>
                <div className="font-medium">{form.items.length}</div>
              </div>
              <div>
                <div className="text-gray-500 text-[11px]">Giá gói</div>
                <div className="font-bold text-blue-600">{formatVnd(form.package_price || 0)}</div>
              </div>
            </div>
          </div>
        );
      default:
        return null;
    }
  };

  return (
    <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-3">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-3xl max-h-[90vh] flex flex-col">
        <div className="px-4 py-3 flex justify-between items-center border-b">
          <h2 className="font-semibold text-gray-800 text-sm">
            {initial ? 'Chỉnh sửa gói dịch vụ' : 'Thêm gói dịch vụ mới'}
          </h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
            ✕
          </button>
        </div>

        <div className="px-4 py-3 border-b flex justify-between items-center relative bg-gray-50/50">
          {FORM_STEPS.map((s) => {
            const active = s.id === step;
            const completed = s.id < step;
            return (
              <button
                type="button"
                key={s.id}
                onClick={() => goToStep(s.id)}
                className="flex flex-col items-center gap-1 bg-transparent px-1"
              >
                <div
                  className={`w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-semibold border ${
                    active
                      ? 'bg-blue-600 text-white border-blue-600'
                      : completed
                        ? 'border-blue-600 text-blue-600 bg-white'
                        : 'border-gray-300 text-gray-400 bg-white'
                  }`}
                >
                  {s.id}
                </div>
                <span
                  className={`text-[10px] ${
                    active ? 'text-blue-600 font-medium' : 'text-gray-500'
                  }`}
                >
                  {s.label}
                </span>
              </button>
            );
          })}
        </div>

        <div className="flex-1 overflow-y-auto p-4">{renderStep()}</div>

        {(localError || error) && (
          <div className="px-4 py-2 text-xs text-red-600 border-t bg-red-50">
            {localError || error}
          </div>
        )}

        <div className="p-3 border-t flex justify-between items-center bg-gray-50 rounded-b-lg">
          <div className="flex gap-2">
            <button
              type="button"
              onClick={onClose}
              className="px-3 py-1.5 border rounded bg-white text-gray-700 text-xs"
            >
              Hủy
            </button>
          </div>
          <div className="flex gap-2">
            {step > 1 && (
              <button
                type="button"
                onClick={() => goToStep(step - 1)}
                className="px-3 py-1.5 border rounded bg-white text-gray-700 text-xs"
              >
                ← Quay lại
              </button>
            )}
            {step < 5 ? (
              <button
                type="button"
                onClick={() => goToStep(step + 1)}
                className="px-3 py-1.5 rounded bg-blue-600 text-white text-xs hover:bg-blue-700"
              >
                Tiếp →
              </button>
            ) : (
              <button
                type="button"
                disabled={saving}
                onClick={handleSubmit}
                className="px-4 py-1.5 rounded bg-blue-600 text-white text-xs hover:bg-blue-700 disabled:opacity-60"
              >
                {saving ? 'Đang lưu...' : initial ? 'Lưu thay đổi' : 'Tạo gói'}
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default PackageFormWizard;
