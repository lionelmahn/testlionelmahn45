import React from 'react';
import { ACTIVE_OPTIONS } from '../constants';

const ToothStatusFilterBar = ({ filters, groups, onChange, onReset }) => {
  return (
    <div className="p-3 border-b flex flex-col sm:flex-row sm:items-end gap-3 bg-white">
      <div className="flex-[2] min-w-[150px]">
        <label className="text-[10px] text-gray-500 block mb-1">Tìm kiếm</label>
        <div className="relative">
          <span className="absolute left-2 top-1.5 text-gray-400 text-xs">🔍</span>
          <input
            type="text"
            value={filters.search}
            onChange={(e) => onChange('search', e.target.value)}
            placeholder="Tìm theo mã, tên trạng thái..."
            className="w-full border rounded pl-7 pr-2 py-1.5 focus:outline-none text-xs bg-white"
          />
        </div>
      </div>

      <div className="flex-1 min-w-[140px]">
        <label className="text-[10px] text-gray-500 block mb-1">Nhóm trạng thái</label>
        <select
          value={filters.group_id}
          onChange={(e) => onChange('group_id', e.target.value)}
          className="w-full border rounded px-2 py-1.5 focus:outline-none text-xs bg-white"
        >
          <option value="all">Tất cả</option>
          {groups.map((group) => (
            <option key={group.id} value={group.id}>
              {group.name}
            </option>
          ))}
        </select>
      </div>

      <div className="flex-1 min-w-[140px]">
        <label className="text-[10px] text-gray-500 block mb-1">Trạng thái hoạt động</label>
        <select
          value={filters.is_active}
          onChange={(e) => onChange('is_active', e.target.value)}
          className="w-full border rounded px-2 py-1.5 focus:outline-none text-xs bg-white"
        >
          {ACTIVE_OPTIONS.map((opt) => (
            <option key={opt.value} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>
      </div>

      <button
        type="button"
        onClick={onReset}
        className="px-3 py-1.5 border rounded bg-white text-gray-700 hover:bg-gray-50 text-xs font-medium whitespace-nowrap"
      >
        ↻ Làm mới
      </button>
    </div>
  );
};

export default ToothStatusFilterBar;
