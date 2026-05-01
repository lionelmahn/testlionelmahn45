import { STATUS_LABELS, VISIBILITY_LABELS } from './constants';

export const formatVnd = (value) => {
  const num = Number(value || 0);
  return new Intl.NumberFormat('vi-VN').format(num) + ' đ';
};

export const formatDate = (value) => {
  if (!value) return '';
  try {
    return new Date(value).toLocaleDateString('vi-VN');
  } catch {
    return String(value);
  }
};

export const formatDateTime = (value) => {
  if (!value) return '';
  try {
    return new Date(value).toLocaleString('vi-VN');
  } catch {
    return String(value);
  }
};

export const statusLabel = (s) => STATUS_LABELS[s] || s;
export const visibilityLabel = (v) => VISIBILITY_LABELS[v] || v;

export const buildEmptyForm = () => ({
  code: '',
  name: '',
  description: '',
  status: 'draft',
  visibility: 'public',
  package_price: '',
  effective_from: '',
  effective_to: '',
  usage_validity_days: '',
  conditions: '',
  notes: '',
  items: [],
});

export const toFormState = (pkg) => ({
  code: pkg?.code || '',
  name: pkg?.name || '',
  description: pkg?.description || '',
  status: pkg?.status || 'draft',
  visibility: pkg?.visibility || 'public',
  package_price: pkg?.package_price ?? '',
  effective_from: pkg?.effective_from ? String(pkg.effective_from).slice(0, 10) : '',
  effective_to: pkg?.effective_to ? String(pkg.effective_to).slice(0, 10) : '',
  usage_validity_days: pkg?.usage_validity_days ?? '',
  conditions: pkg?.conditions || '',
  notes: pkg?.notes || '',
  items: (pkg?.items || []).map((it) => ({
    service_id: it.service_id,
    service_code: it.service?.service_code || '',
    service_name: it.service?.name || '',
    quantity: it.quantity,
    unit_price: Number(it.unit_price),
    note: it.note || '',
    service_status: it.service?.status,
  })),
});

export const computeOriginalPrice = (items) =>
  (items || []).reduce(
    (sum, it) => sum + Number(it.unit_price || 0) * Number(it.quantity || 0),
    0
  );

export const computeDiscount = (originalPrice, packagePrice) => {
  const original = Number(originalPrice || 0);
  const pkg = Number(packagePrice || 0);
  const amount = Math.max(0, original - pkg);
  const percent = original > 0 ? (amount / original) * 100 : 0;
  return { amount, percent };
};
