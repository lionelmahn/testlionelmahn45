export const formatVnd = (value) => {
  if (value === null || value === undefined || value === '') return '-';
  const number = Number(value);
  if (Number.isNaN(number)) return String(value);
  return `${number.toLocaleString('vi-VN', { maximumFractionDigits: 0 })} đ`;
};

export const formatDate = (value) => {
  if (!value) return '-';
  try {
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value);
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
  } catch {
    return String(value);
  }
};

export const formatDateTime = (value) => {
  if (!value) return '-';
  try {
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value);
    return `${formatDate(value)} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
  } catch {
    return String(value);
  }
};

export const toDateInputValue = (value) => {
  if (!value) return '';
  try {
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '';
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
  } catch {
    return '';
  }
};

export const STATUS_LABEL = {
  active: 'Đang hiệu lực',
  scheduled: 'Sắp hiệu lực',
  expired: 'Đã hết hiệu lực',
  cancelled: 'Đã huỷ',
};

export const PROPOSAL_STATUS_LABEL = {
  approved: 'Đã duyệt',
  pending: 'Chờ duyệt',
  rejected: 'Đã từ chối',
};
