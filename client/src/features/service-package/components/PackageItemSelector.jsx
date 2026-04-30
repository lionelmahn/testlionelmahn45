import React, { useEffect, useState } from 'react';
import { servicePackageApi } from '@/api/servicePackageApi';
import { formatVnd } from '../utils';

const PackageItemSelector = ({ items, onChange }) => {
  const [search, setSearch] = useState('');
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    let cancelled = false;
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setLoading(true);
    servicePackageApi
      .servicesLookup({ search, status: 'active', per_page: 20 })
      .then(({ data }) => {
        if (cancelled) return;
        setResults(data?.data || []);
      })
      .catch(() => {
        if (cancelled) return;
        setResults([]);
      })
      .finally(() => {
        if (cancelled) return;
        setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [search]);

  const addItem = (svc) => {
    if (items.find((it) => it.service_id === svc.id)) return;
    onChange([
      ...items,
      {
        service_id: svc.id,
        service_code: svc.service_code,
        service_name: svc.name,
        quantity: 1,
        unit_price: Number(svc.price || 0),
        note: '',
        service_status: svc.status,
      },
    ]);
  };

  const updateItem = (idx, patch) => {
    const next = items.map((it, i) => (i === idx ? { ...it, ...patch } : it));
    onChange(next);
  };

  const removeItem = (idx) => {
    onChange(items.filter((_, i) => i !== idx));
  };

  return (
    <div className="flex flex-col gap-3">
      <div className="border rounded-md">
        <div className="px-3 py-2 border-b bg-gray-50 text-xs font-medium text-gray-700">
          Tìm và chọn dịch vụ
        </div>
        <div className="p-3 flex flex-col gap-2">
          <input
            type="text"
            placeholder="Tìm theo mã/tên dịch vụ..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full border rounded px-2 py-1.5 focus:outline-none text-xs"
          />
          <div className="max-h-48 overflow-y-auto divide-y">
            {loading && <div className="text-xs text-gray-500 p-2">Đang tìm...</div>}
            {!loading && !results.length && (
              <div className="text-xs text-gray-500 p-2">Không có dịch vụ phù hợp</div>
            )}
            {results.map((svc) => {
              const added = items.find((it) => it.service_id === svc.id);
              return (
                <button
                  type="button"
                  key={svc.id}
                  onClick={() => addItem(svc)}
                  disabled={!!added}
                  className={`w-full flex justify-between items-center px-3 py-1.5 text-xs hover:bg-blue-50 text-left ${
                    added ? 'opacity-50 cursor-not-allowed' : ''
                  }`}
                >
                  <span>
                    <span className="font-medium text-gray-700">{svc.service_code}</span>
                    <span className="text-gray-500"> · {svc.name}</span>
                  </span>
                  <span className="text-gray-600">{formatVnd(svc.price)}</span>
                </button>
              );
            })}
          </div>
        </div>
      </div>

      <div className="border rounded-md">
        <div className="px-3 py-2 border-b bg-gray-50 text-xs font-medium text-gray-700 flex justify-between">
          <span>Dịch vụ thành phần ({items.length})</span>
          <span className="text-gray-500">
            Tổng giá gốc:{' '}
            {formatVnd(
              items.reduce((s, it) => s + Number(it.unit_price || 0) * Number(it.quantity || 0), 0)
            )}
          </span>
        </div>
        {items.length === 0 ? (
          <div className="text-xs text-gray-500 p-3 text-center">Chưa có dịch vụ trong gói</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-[11px]">
              <thead>
                <tr className="text-gray-500 border-b bg-gray-50/50">
                  <th className="py-2 px-3 text-left">Mã / Tên</th>
                  <th className="py-2 px-3 w-20 text-center">Số lượng</th>
                  <th className="py-2 px-3 w-32 text-right">Đơn giá</th>
                  <th className="py-2 px-3 w-32 text-right">Thành tiền</th>
                  <th className="py-2 px-3 w-10" />
                </tr>
              </thead>
              <tbody>
                {items.map((it, idx) => (
                  <tr key={it.service_id} className="border-b">
                    <td className="py-2 px-3">
                      <div className="font-medium text-gray-800">{it.service_code}</div>
                      <div className="text-gray-500">{it.service_name}</div>
                      {it.service_status && it.service_status !== 'active' && (
                        <div className="text-[10px] text-orange-600 mt-0.5">
                          ⚠ Dịch vụ không ở trạng thái Đang áp dụng
                        </div>
                      )}
                    </td>
                    <td className="py-2 px-3 text-center">
                      <input
                        type="number"
                        min={1}
                        value={it.quantity}
                        onChange={(e) =>
                          updateItem(idx, { quantity: Math.max(1, Number(e.target.value) || 1) })
                        }
                        className="w-16 border rounded px-1 py-0.5 text-xs text-center"
                      />
                    </td>
                    <td className="py-2 px-3 text-right">
                      <input
                        type="number"
                        min={0}
                        value={it.unit_price}
                        onChange={(e) =>
                          updateItem(idx, { unit_price: Math.max(0, Number(e.target.value) || 0) })
                        }
                        className="w-28 border rounded px-1 py-0.5 text-xs text-right"
                      />
                    </td>
                    <td className="py-2 px-3 text-right text-gray-700">
                      {formatVnd(Number(it.unit_price || 0) * Number(it.quantity || 0))}
                    </td>
                    <td className="py-2 px-3 text-center">
                      <button
                        type="button"
                        onClick={() => removeItem(idx)}
                        className="text-red-500 hover:text-red-700"
                        title="Xóa"
                      >
                        ✕
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
};

export default PackageItemSelector;
