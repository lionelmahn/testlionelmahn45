export const buildEmptyForm = () => ({
  code: '',
  name: '',
  tooth_status_group_id: '',
  color: '#22C55E',
  icon: '🦷',
  description: '',
  notes: '',
  is_active: true,
  display_order: '',
});

export const toFormState = (item) => ({
  id: item.id,
  code: item.code || '',
  name: item.name || '',
  tooth_status_group_id: item.tooth_status_group_id ? String(item.tooth_status_group_id) : '',
  color: item.color || '#22C55E',
  icon: item.icon || '🦷',
  description: item.description || '',
  notes: item.notes || '',
  is_active: Boolean(item.is_active),
  display_order: item.display_order ?? '',
});

export const toPayload = (form) => ({
  ...(form.code ? { code: form.code.trim().toUpperCase() } : {}),
  name: form.name.trim(),
  tooth_status_group_id: form.tooth_status_group_id ? Number(form.tooth_status_group_id) : null,
  color: form.color,
  icon: form.icon || null,
  description: form.description || null,
  notes: form.notes || null,
  is_active: Boolean(form.is_active),
  ...(form.display_order !== '' && form.display_order !== null
    ? { display_order: Number(form.display_order) }
    : {}),
});

export const validateForm = (form) => {
  const errors = {};
  if (!form.name?.trim()) errors.name = 'Tên trạng thái là bắt buộc';
  if (!form.tooth_status_group_id) errors.tooth_status_group_id = 'Chọn nhóm trạng thái';
  if (!form.color) errors.color = 'Chọn màu hiển thị';
  if (form.code && !/^[A-Za-z0-9_-]{2,30}$/.test(form.code.trim())) {
    errors.code = 'Mã chỉ gồm chữ, số, gạch nối (2-30 ký tự)';
  }
  return errors;
};

export const formatDateTime = (input) => {
  if (!input) return '-';
  const d = new Date(input);
  if (Number.isNaN(d.getTime())) return String(input);
  const pad = (n) => String(n).padStart(2, '0');
  return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const labelMap = {
  code: 'Mã',
  name: 'Tên',
  color: 'Màu',
  icon: 'Biểu tượng',
  description: 'Mô tả',
  notes: 'Ghi chú',
  is_active: 'Trạng thái',
  display_order: 'Thứ tự',
  tooth_status_group_id: 'Nhóm',
};

export const summarizeChanges = (record, side = 'after') => {
  const data = record?.[side];
  if (!data || typeof data !== 'object') return '-';
  const interesting = ['name', 'color', 'is_active', 'display_order', 'icon'];
  const lines = interesting
    .filter((k) => data[k] !== undefined && data[k] !== null)
    .map((k) => {
      let value = data[k];
      if (k === 'is_active') value = value ? 'Đang sử dụng' : 'Ngừng sử dụng';
      return `${labelMap[k] || k}: ${value}`;
    });
  return lines.length ? lines.join(' · ') : '-';
};

export const actionLabel = (action) => {
  const map = {
    created: 'Thêm mới',
    updated: 'Cập nhật',
    activated: 'Đổi sang Đang sử dụng',
    deactivated: 'Đổi sang Ngừng sử dụng',
    deleted: 'Xóa',
    proposal_submitted: 'Đề xuất từ bác sĩ',
  };
  return map[action] || action || '-';
};
