export const PACKAGE_STATUS = {
  DRAFT: 'draft',
  ACTIVE: 'active',
  HIDDEN: 'hidden',
  DISCONTINUED: 'discontinued',
};

export const STATUS_LABELS = {
  [PACKAGE_STATUS.DRAFT]: 'Nháp',
  [PACKAGE_STATUS.ACTIVE]: 'Đang áp dụng',
  [PACKAGE_STATUS.HIDDEN]: 'Tạm ẩn',
  [PACKAGE_STATUS.DISCONTINUED]: 'Ngừng áp dụng',
};

export const STATUS_BADGE_CLASS = {
  [PACKAGE_STATUS.DRAFT]: 'bg-slate-100 text-slate-600 border-slate-200',
  [PACKAGE_STATUS.ACTIVE]: 'bg-green-100 text-green-700 border-green-200',
  [PACKAGE_STATUS.HIDDEN]: 'bg-orange-100 text-orange-700 border-orange-200',
  [PACKAGE_STATUS.DISCONTINUED]: 'bg-red-100 text-red-700 border-red-200',
};

export const VISIBILITY = {
  PUBLIC: 'public',
  INTERNAL: 'internal',
};

export const VISIBILITY_LABELS = {
  [VISIBILITY.PUBLIC]: 'Công khai',
  [VISIBILITY.INTERNAL]: 'Nội bộ',
};

export const FORM_STEPS = [
  { id: 1, label: 'Thông tin chung' },
  { id: 2, label: 'Dịch vụ thành phần' },
  { id: 3, label: 'Giá & giảm giá' },
  { id: 4, label: 'Điều kiện áp dụng' },
  { id: 5, label: 'Xác nhận' },
];

export const DETAIL_TABS = [
  { id: 'general', label: 'Thông tin chung' },
  { id: 'items', label: 'Dịch vụ thành phần' },
  { id: 'pricing', label: 'Giá & giảm giá' },
  { id: 'conditions', label: 'Điều kiện áp dụng' },
  { id: 'history', label: 'Lịch sử thay đổi' },
];

export const HISTORY_ACTION_LABELS = {
  created: 'Tạo gói',
  updated: 'Cập nhật',
  status_changed: 'Đổi trạng thái',
  cloned: 'Nhân bản',
  version_created: 'Tạo phiên bản mới',
};
