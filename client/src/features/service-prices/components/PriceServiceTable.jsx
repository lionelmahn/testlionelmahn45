import React from 'react';
import { ChevronRight, AlertCircle } from 'lucide-react';
import { formatVnd, formatDate, STATUS_LABEL } from '../utils';

const Badge = ({ status }) => {
  const styles = {
    active: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
    scheduled: 'bg-blue-50 text-blue-700 border border-blue-200',
    expired: 'bg-gray-100 text-gray-600 border border-gray-200',
    none: 'bg-amber-50 text-amber-700 border border-amber-200',
  };
  const cls = styles[status] || styles.none;
  return (
    <span className={`inline-block rounded px-1.5 py-0.5 text-[11px] font-medium ${cls}`}>
      {STATUS_LABEL[status] || 'Chưa có giá'}
    </span>
  );
};

const PriceServiceTable = ({ items, loading, selectedId, onSelect }) => {
  if (loading) {
    return (
      <div className="flex flex-col gap-2 p-4">
        {Array.from({ length: 6 }).map((_, i) => (
          <div key={i} className="h-10 animate-pulse rounded bg-gray-100" />
        ))}
      </div>
    );
  }

  if (!items?.length) {
    return (
      <div className="flex flex-col items-center gap-2 p-10 text-center">
        <AlertCircle className="h-8 w-8 text-gray-400" />
        <div className="text-sm text-gray-500">Chưa có dịch vụ nào phù hợp với bộ lọc.</div>
      </div>
    );
  }

  return (
    <div className="overflow-x-auto">
      <table className="w-full text-left text-xs">
        <thead className="border-b bg-gray-50 text-[11px] text-gray-500">
          <tr>
            <th className="px-4 py-2.5 font-medium">Mã DV</th>
            <th className="px-2 py-2.5 font-medium">Tên dịch vụ</th>
            <th className="px-2 py-2.5 font-medium">Nhóm</th>
            <th className="px-2 py-2.5 text-right font-medium">Giá hiện tại</th>
            <th className="px-2 py-2.5 text-center font-medium">Trạng thái</th>
            <th className="px-4 py-2.5 text-center font-medium">Hiệu lực</th>
            <th className="w-8 px-2 py-2.5"></th>
          </tr>
        </thead>
        <tbody className="text-gray-700">
          {items.map((svc) => {
            const active = svc.active_price;
            const isSel = selectedId === svc.id;
            return (
              <tr
                key={svc.id}
                onClick={() => onSelect?.(svc)}
                className={`cursor-pointer border-b hover:bg-blue-50/50 ${isSel ? 'bg-blue-50' : ''}`}
              >
                <td className="px-4 py-2.5 font-medium">{svc.service_code || '-'}</td>
                <td className={`px-2 py-2.5 ${isSel ? 'font-medium text-gray-900' : ''}`}>{svc.name}</td>
                <td className="px-2 py-2.5 text-gray-500">{svc.group?.name || '-'}</td>
                <td className="px-2 py-2.5 text-right font-medium">
                  {formatVnd(active?.price ?? svc.price)}
                </td>
                <td className="px-2 py-2.5 text-center">
                  <Badge status={active ? 'active' : 'none'} />
                </td>
                <td className="px-4 py-2.5 text-center text-gray-500">
                  {active?.effective_from ? `Từ ${formatDate(active.effective_from)}` : '-'}
                </td>
                <td className="px-2 py-2.5 text-gray-400">
                  <ChevronRight size={14} />
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
};

export default PriceServiceTable;
