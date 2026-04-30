import React from 'react';
import { STATUS_BADGE_CLASS, STATUS_LABELS, VISIBILITY_LABELS } from '../constants';
import { formatDate, formatVnd } from '../utils';

const PackageTable = ({ items, selectedId, onSelect, onEdit, onMore, loading, page, perPage }) => {
  if (loading) {
    return <div className="p-6 text-xs text-gray-500">Đang tải...</div>;
  }

  if (!items.length) {
    return <div className="p-6 text-xs text-gray-500 text-center">Không có gói dịch vụ nào</div>;
  }

  return (
    <div className="overflow-x-auto">
      <table className="w-full text-left border-collapse whitespace-nowrap">
        <thead>
          <tr className="text-[11px] text-gray-500 border-b bg-gray-50">
            <th className="py-2.5 px-3 font-medium w-10 text-center">STT</th>
            <th className="py-2.5 px-3 font-medium">Mã gói</th>
            <th className="py-2.5 px-3 font-medium">Tên gói dịch vụ</th>
            <th className="py-2.5 px-3 font-medium text-right">Giá gói</th>
            <th className="py-2.5 px-3 font-medium text-center">Trạng thái</th>
            <th className="py-2.5 px-3 font-medium text-center">Phạm vi sử dụng</th>
            <th className="py-2.5 px-3 font-medium">Thời gian hiệu lực</th>
            <th className="py-2.5 px-3 font-medium text-center">Số dịch vụ</th>
            <th className="py-2.5 px-3 font-medium text-center">Thao tác</th>
          </tr>
        </thead>
        <tbody className="text-[11px]">
          {items.map((p, idx) => {
            const isSelected = selectedId === p.id;
            return (
              <tr
                key={p.id}
                className={`border-b cursor-pointer ${
                  isSelected ? 'bg-blue-50/60' : 'hover:bg-gray-50'
                }`}
                onClick={() => onSelect(p.id)}
              >
                <td className="py-2 px-3 text-center text-gray-500">{(page - 1) * perPage + idx + 1}</td>
                <td className="py-2 px-3 font-medium text-gray-900">{p.code}</td>
                <td className="py-2 px-3 text-gray-800">{p.name}</td>
                <td className="py-2 px-3 text-right">{formatVnd(p.package_price)}</td>
                <td className="py-2 px-3 text-center">
                  <span
                    className={`inline-flex items-center justify-center px-2 py-0.5 rounded text-[10px] border ${
                      STATUS_BADGE_CLASS[p.status] || 'bg-slate-100 text-slate-600 border-slate-200'
                    }`}
                  >
                    {STATUS_LABELS[p.status] || p.status}
                  </span>
                </td>
                <td className="py-2 px-3 text-center text-gray-600">
                  {VISIBILITY_LABELS[p.visibility] || p.visibility}
                </td>
                <td className="py-2 px-3 text-gray-600">
                  {formatDate(p.effective_from)} - {formatDate(p.effective_to)}
                </td>
                <td className="py-2 px-3 text-center text-gray-600">{p.items_count ?? 0}</td>
                <td className="py-2 px-3">
                  <div className="flex justify-center gap-1" onClick={(e) => e.stopPropagation()}>
                    <button
                      type="button"
                      onClick={() => onSelect(p.id)}
                      className="p-1 border rounded bg-white text-gray-600 hover:bg-gray-50"
                      title="Xem chi tiết"
                    >
                      👁
                    </button>
                    {onEdit && (
                      <button
                        type="button"
                        onClick={() => onEdit(p)}
                        className="p-1 border rounded bg-white text-gray-600 hover:bg-gray-50"
                        title="Chỉnh sửa"
                      >
                        ✎
                      </button>
                    )}
                    {onMore && (
                      <button
                        type="button"
                        onClick={() => onMore(p)}
                        className="p-1 border rounded bg-white text-gray-600 hover:bg-gray-50"
                        title="Tác vụ khác"
                      >
                        •••
                      </button>
                    )}
                  </div>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
};

export default PackageTable;
