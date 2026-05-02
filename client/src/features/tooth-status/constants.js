/**
 * UC4.4 — Tooth status master data constants.
 *
 * Color palette is intentionally a small curated set so swatches in the form
 * map 1:1 to the values stored in DB (hex strings). The hex codes match the
 * defaults seeded by `ToothStatusSeeder`.
 */
export const COLOR_PALETTE = [
  { hex: '#22C55E', label: 'Xanh lá' },
  { hex: '#FACC15', label: 'Vàng' },
  { hex: '#FB923C', label: 'Cam nhạt' },
  { hex: '#F97316', label: 'Cam' },
  { hex: '#EF4444', label: 'Đỏ' },
  { hex: '#DC2626', label: 'Đỏ đậm' },
  { hex: '#A855F7', label: 'Tím' },
  { hex: '#7C3AED', label: 'Tím đậm' },
  { hex: '#2563EB', label: 'Xanh dương' },
  { hex: '#0EA5E9', label: 'Xanh trời' },
  { hex: '#0891B2', label: 'Xanh ngọc' },
  { hex: '#84CC16', label: 'Xanh non' },
  { hex: '#9CA3AF', label: 'Xám' },
  { hex: '#6366F1', label: 'Chàm' },
  { hex: '#D946EF', label: 'Hồng' },
];

export const ICON_PALETTE = ['🦷', '⚪', '⬛', '―', '🔩', '⚠️', '⭐', '🩹'];

export const DEFAULT_FILTERS = {
  search: '',
  group_id: 'all',
  is_active: 'all',
};

export const ACTIVE_OPTIONS = [
  { value: 'all', label: 'Tất cả' },
  { value: 'true', label: 'Đang sử dụng' },
  { value: 'false', label: 'Ngừng sử dụng' },
];
