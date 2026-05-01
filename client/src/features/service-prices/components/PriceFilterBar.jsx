import React from 'react';
import { Search, RotateCcw } from 'lucide-react';

const PriceFilterBar = ({ filters, setFilter, resetFilters, groups }) => (
  <div className="flex flex-wrap items-end gap-3 border-b bg-white p-3">
    <div className="min-w-[200px] flex-[1.5]">
      <label className="mb-1 block text-[10px] text-gray-500">Tìm kiếm</label>
      <div className="relative">
        <Search size={14} className="absolute left-2 top-2 text-gray-400" />
        <input
          type="text"
          value={filters.search}
          onChange={(e) => setFilter('search', e.target.value)}
          placeholder="Mã hoặc tên dịch vụ"
          className="w-full rounded border bg-white px-7 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
        />
      </div>
    </div>

    <div className="min-w-[140px] flex-1">
      <label className="mb-1 block text-[10px] text-gray-500">Nhóm dịch vụ</label>
      <select
        value={filters.service_group_id}
        onChange={(e) => setFilter('service_group_id', e.target.value)}
        className="w-full rounded border bg-white px-2 py-1.5 text-xs focus:outline-none"
      >
        <option value="all">Tất cả</option>
        {(groups || []).map((g) => (
          <option key={g.id} value={g.id}>
            {g.name}
          </option>
        ))}
      </select>
    </div>

    <div className="min-w-[140px] flex-1">
      <label className="mb-1 block text-[10px] text-gray-500">Trạng thái dịch vụ</label>
      <select
        value={filters.status}
        onChange={(e) => setFilter('status', e.target.value)}
        className="w-full rounded border bg-white px-2 py-1.5 text-xs focus:outline-none"
      >
        <option value="all">Tất cả</option>
        <option value="active">Đang áp dụng</option>
        <option value="draft">Nháp</option>
        <option value="hidden">Tạm ẩn</option>
        <option value="discontinued">Ngừng áp dụng</option>
      </select>
    </div>

    <div className="min-w-[140px] flex-1">
      <label className="mb-1 block text-[10px] text-gray-500">Chỉ hiển thị</label>
      <select
        value={filters.only}
        onChange={(e) => setFilter('only', e.target.value)}
        className="w-full rounded border bg-white px-2 py-1.5 text-xs focus:outline-none"
      >
        <option value="">Tất cả</option>
        <option value="with_pending">Có đề xuất chờ duyệt</option>
      </select>
    </div>

    <button
      onClick={resetFilters}
      className="flex items-center gap-1 rounded border bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
    >
      <RotateCcw size={12} /> Làm mới
    </button>
  </div>
);

export default PriceFilterBar;
