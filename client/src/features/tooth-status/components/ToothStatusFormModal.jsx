import React, { useEffect, useState } from 'react';
import ColorPicker from './ColorPicker';
import IconPicker from './IconPicker';
import { buildEmptyForm, toFormState, toPayload, validateForm } from '../utils';

const ToothStatusFormModal = ({
  open,
  initial,
  groups,
  saving,
  error,
  onClose,
  onSubmit,
  mode = 'admin', // 'admin' | 'doctor'
}) => {
  const isEdit = Boolean(initial?.id);
  const [form, setForm] = useState(buildEmptyForm());
  const [errors, setErrors] = useState({});

  useEffect(() => {
    if (open) {
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setForm(initial?.id ? toFormState(initial) : buildEmptyForm());
      setErrors({});
    }
  }, [open, initial]);

  if (!open) return null;

  const update = (patch) => setForm((prev) => ({ ...prev, ...patch }));

  const handleSubmit = async (e) => {
    e?.preventDefault?.();
    const found = validateForm(form);
    setErrors(found);
    if (Object.keys(found).length > 0) return;
    await onSubmit(toPayload(form));
  };

  const isDoctor = mode === 'doctor';
  const titleNew = isDoctor
    ? 'Đề xuất thêm trạng thái răng mới'
    : 'Thêm trạng thái răng mới';
  const titleEdit = isDoctor
    ? 'Đề xuất chỉnh sửa trạng thái răng'
    : 'Chỉnh sửa trạng thái răng';

  return (
    <div className="fixed inset-0 z-40 bg-black/40 flex items-center justify-center p-4">
      <div className="bg-white w-full max-w-2xl rounded-lg shadow-xl flex flex-col max-h-[92vh]">
        <div className="px-4 py-3 flex justify-between items-center border-b">
          <h2 className="font-semibold text-gray-800 text-sm">
            {isEdit ? titleEdit : titleNew}
          </h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
            ✕
          </button>
        </div>

        <form
          onSubmit={handleSubmit}
          className="p-4 flex-1 overflow-auto text-xs flex flex-col gap-3"
        >
          {error && (
            <div className="p-2 bg-red-50 border border-red-200 text-red-700 rounded">
              {error}
            </div>
          )}

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className="text-gray-500 mb-1 block">
                Mã trạng thái {!isEdit && <span className="text-red-500">*</span>}
              </label>
              <input
                type="text"
                value={form.code}
                disabled={isEdit}
                onChange={(e) => update({ code: e.target.value })}
                placeholder={isDoctor ? 'Đề xuất mã (vd: STT019)' : 'Nhập mã trạng thái'}
                className={`w-full border rounded px-2 py-1.5 ${
                  isEdit ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : ''
                }`}
              />
              {errors.code && <div className="text-[10px] text-red-500 mt-0.5">{errors.code}</div>}
            </div>
            <div>
              <label className="text-gray-500 mb-1 block">
                Trạng thái hoạt động <span className="text-red-500">*</span>
              </label>
              <div className="flex gap-3 mt-2">
                <label className="flex items-center gap-1.5 cursor-pointer">
                  <input
                    type="radio"
                    checked={form.is_active === true}
                    onChange={() => update({ is_active: true })}
                    className="accent-blue-600"
                  />
                  Đang sử dụng
                </label>
                <label className="flex items-center gap-1.5 cursor-pointer">
                  <input
                    type="radio"
                    checked={form.is_active === false}
                    onChange={() => update({ is_active: false })}
                    className="accent-blue-600"
                  />
                  Ngừng sử dụng
                </label>
              </div>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className="text-gray-500 mb-1 block">
                Tên trạng thái <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                value={form.name}
                onChange={(e) => update({ name: e.target.value })}
                placeholder="Nhập tên trạng thái"
                className="w-full border rounded px-2 py-1.5"
              />
              {errors.name && <div className="text-[10px] text-red-500 mt-0.5">{errors.name}</div>}
            </div>
            <div>
              <label className="text-gray-500 mb-1 block">Thứ tự hiển thị</label>
              <input
                type="number"
                min={0}
                value={form.display_order}
                onChange={(e) => update({ display_order: e.target.value })}
                placeholder="Tự động nếu để trống"
                className="w-full border rounded px-2 py-1.5"
              />
            </div>
          </div>

          <div>
            <label className="text-gray-500 mb-1 block">
              Nhóm trạng thái <span className="text-red-500">*</span>
            </label>
            <select
              value={form.tooth_status_group_id}
              onChange={(e) => update({ tooth_status_group_id: e.target.value })}
              className="w-full border rounded px-2 py-1.5"
            >
              <option value="">Chọn nhóm trạng thái</option>
              {groups.map((g) => (
                <option key={g.id} value={g.id}>
                  {g.name}
                </option>
              ))}
            </select>
            {errors.tooth_status_group_id && (
              <div className="text-[10px] text-red-500 mt-0.5">
                {errors.tooth_status_group_id}
              </div>
            )}
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-1">
            <div className="space-y-3">
              <div>
                <label className="text-gray-500 mb-2 block">
                  Màu hiển thị <span className="text-red-500">*</span>
                </label>
                <ColorPicker
                  value={form.color}
                  onChange={(value) => update({ color: value })}
                  error={errors.color}
                />
              </div>
              <div>
                <label className="text-gray-500 mb-2 block">
                  Biểu tượng <span className="text-red-500">*</span>
                </label>
                <IconPicker
                  value={form.icon}
                  onChange={(value) => update({ icon: value })}
                />
              </div>
            </div>
            <div className="space-y-3">
              <div>
                <label className="text-gray-500 mb-1 block">Mô tả</label>
                <textarea
                  value={form.description}
                  onChange={(e) => update({ description: e.target.value })}
                  placeholder="Nhập mô tả trạng thái"
                  maxLength={255}
                  className="w-full border rounded px-2 py-1.5 h-[70px] focus:outline-none resize-none"
                />
                <div className="text-right text-[10px] text-gray-400 mt-0.5">
                  {(form.description || '').length}/255
                </div>
              </div>
              <div>
                <label className="text-gray-500 mb-1 block">Ghi chú</label>
                <textarea
                  value={form.notes}
                  onChange={(e) => update({ notes: e.target.value })}
                  placeholder="Nhập ghi chú (nếu có)"
                  maxLength={255}
                  className="w-full border rounded px-2 py-1.5 h-[50px] focus:outline-none resize-none"
                />
                <div className="text-right text-[10px] text-gray-400 mt-0.5">
                  {(form.notes || '').length}/255
                </div>
              </div>
            </div>
          </div>
        </form>

        <div className="p-3 border-t flex justify-between bg-gray-50 text-xs">
          <button
            type="button"
            onClick={onClose}
            className="px-5 py-1.5 border rounded bg-white hover:bg-gray-100"
          >
            Hủy
          </button>
          <button
            type="button"
            disabled={saving}
            onClick={handleSubmit}
            className="px-5 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
          >
            {saving ? 'Đang lưu...' : isDoctor ? 'Gửi đề xuất' : isEdit ? 'Lưu thay đổi' : 'Lưu'}
          </button>
        </div>
      </div>
    </div>
  );
};

export default ToothStatusFormModal;
