import React, { useEffect, useState } from 'react';
import { servicePackageApi } from '@/api/servicePackageApi';
import { DETAIL_TABS, HISTORY_ACTION_LABELS, STATUS_BADGE_CLASS, STATUS_LABELS, VISIBILITY_LABELS } from '../constants';
import { formatDate, formatDateTime, formatVnd } from '../utils';

const PackageDetailPanel = ({
  packageId,
  onClose,
  onEdit,
  onChangeStatus,
  onClone,
  onCreateNewVersion,
  onDelete,
  canManage,
}) => {
  const [tab, setTab] = useState('general');
  const [pkg, setPkg] = useState(null);
  const [warnings, setWarnings] = useState([]);
  const [loading, setLoading] = useState(false);
  const [actionsOpen, setActionsOpen] = useState(false);

  useEffect(() => {
    if (!packageId) {
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setPkg(null);
      setWarnings([]);
      return;
    }
    let cancelled = false;
    setLoading(true);
    Promise.all([
      servicePackageApi.get(packageId),
      servicePackageApi.discontinuedWarnings(packageId).catch(() => ({ data: [] })),
    ])
      .then(([detailRes, warnRes]) => {
        if (cancelled) return;
        setPkg(detailRes.data);
        setWarnings(warnRes.data || []);
      })
      .catch(() => {
        if (cancelled) return;
        setPkg(null);
        setWarnings([]);
      })
      .finally(() => {
        if (cancelled) return;
        setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [packageId]);

  if (!packageId) {
    return (
      <div className="w-full lg:w-[360px] bg-white border rounded-lg shadow-sm flex-shrink-0 p-6 text-xs text-gray-500 text-center">
        Chọn một gói trong danh sách để xem chi tiết
      </div>
    );
  }

  if (loading || !pkg) {
    return (
      <div className="w-full lg:w-[360px] bg-white border rounded-lg shadow-sm flex-shrink-0 p-6 text-xs text-gray-500">
        Đang tải...
      </div>
    );
  }

  return (
    <div className="w-full lg:w-[420px] bg-white border rounded-lg shadow-sm flex flex-col flex-shrink-0 max-h-[calc(100vh-160px)]">
      <div className="px-4 py-3 flex justify-between items-center border-b">
        <h2 className="font-semibold text-gray-800 text-sm">Chi tiết gói dịch vụ</h2>
        <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
          ✕
        </button>
      </div>

      <div className="p-4 border-b">
        <div className="flex justify-between items-start mb-1">
          <span className="text-[10px] text-gray-500">{pkg.code}</span>
          <span
            className={`inline-flex px-2 py-0.5 rounded text-[10px] border ${
              STATUS_BADGE_CLASS[pkg.status] || ''
            }`}
          >
            {STATUS_LABELS[pkg.status] || pkg.status}
          </span>
        </div>
        <h3 className="font-bold text-gray-900 text-sm leading-tight mb-1.5">{pkg.name}</h3>
        <div className="flex flex-wrap gap-1.5">
          <span className="text-[10px] px-2 py-0.5 rounded border bg-gray-50 text-gray-600">
            {VISIBILITY_LABELS[pkg.visibility] || pkg.visibility}
          </span>
          <span className="text-[10px] px-2 py-0.5 rounded border bg-gray-50 text-gray-600">
            {pkg.items?.length || 0} dịch vụ
          </span>
          <span className="text-[10px] px-2 py-0.5 rounded border bg-gray-50 text-gray-600">
            v{pkg.version_number}
          </span>
        </div>
        {warnings.length > 0 && (
          <div className="mt-3 p-2 border rounded bg-orange-50 border-orange-200 text-[11px] text-orange-700">
            <div className="font-medium mb-0.5">⚠ Cảnh báo dịch vụ thành phần:</div>
            <ul className="list-disc pl-4 space-y-0.5">
              {warnings.map((w, i) => (
                <li key={i}>
                  {w.service_code ? `${w.service_code} · ` : ''}
                  {w.name || `Dịch vụ #${w.service_id}`} ({w.status || w.reason})
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>

      <div className="flex border-b text-[11px] overflow-x-auto">
        {DETAIL_TABS.map((t) => (
          <button
            key={t.id}
            type="button"
            onClick={() => setTab(t.id)}
            className={`px-3 py-2 whitespace-nowrap border-b-2 ${
              tab === t.id
                ? 'border-blue-600 text-blue-600 font-medium'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      <div className="flex-1 overflow-y-auto p-4 text-xs">
        {tab === 'general' && (
          <div className="space-y-3">
            <div className="grid grid-cols-2 gap-3">
              <div>
                <div className="text-[10px] text-gray-500">Giá gói</div>
                <div className="font-bold text-gray-900 text-sm">{formatVnd(pkg.package_price)}</div>
              </div>
              <div>
                <div className="text-[10px] text-gray-500">Giá gốc</div>
                <div className="font-medium text-gray-500 text-[11px] line-through">
                  {formatVnd(pkg.original_price)}
                </div>
              </div>
              <div>
                <div className="text-[10px] text-gray-500">Giảm giá</div>
                <div className="font-medium text-gray-900 text-[11px]">
                  {formatVnd(pkg.discount_amount)} ({Number(pkg.discount_percent).toFixed(2)}%)
                </div>
              </div>
              <div>
                <div className="text-[10px] text-gray-500">Hiệu lực</div>
                <div className="font-medium text-gray-900 text-[11px]">
                  {formatDate(pkg.effective_from)} - {formatDate(pkg.effective_to)}
                </div>
              </div>
              <div>
                <div className="text-[10px] text-gray-500">Thời hạn sử dụng</div>
                <div className="font-medium text-[11px]">
                  {pkg.usage_validity_days ? `${pkg.usage_validity_days} ngày` : '—'}
                </div>
              </div>
              <div>
                <div className="text-[10px] text-gray-500">Phiên bản</div>
                <div className="font-medium text-[11px]">v{pkg.version_number}</div>
              </div>
            </div>
            <div>
              <div className="text-[10px] text-gray-500 mb-1">Mô tả</div>
              <div className="text-[11px] leading-relaxed">{pkg.description || '—'}</div>
            </div>
            {pkg.notes && (
              <div>
                <div className="text-[10px] text-gray-500 mb-1">Ghi chú</div>
                <div className="text-[11px] leading-relaxed">{pkg.notes}</div>
              </div>
            )}
          </div>
        )}

        {tab === 'items' && (
          <div className="overflow-x-auto">
            <table className="w-full text-[11px]">
              <thead>
                <tr className="text-gray-500 border-b">
                  <th className="py-1.5 px-2 text-left">Mã</th>
                  <th className="py-1.5 px-2 text-left">Tên</th>
                  <th className="py-1.5 px-2 text-center">SL</th>
                  <th className="py-1.5 px-2 text-right">Đơn giá</th>
                </tr>
              </thead>
              <tbody>
                {(pkg.items || []).map((it) => (
                  <tr key={it.id} className="border-b">
                    <td className="py-1.5 px-2 font-medium text-gray-700">
                      {it.service?.service_code}
                    </td>
                    <td className="py-1.5 px-2 text-gray-700">
                      {it.service?.name}
                      {it.service?.status && it.service.status !== 'active' && (
                        <span className="ml-1 text-orange-600">
                          ({STATUS_LABELS[it.service.status] || it.service.status})
                        </span>
                      )}
                    </td>
                    <td className="py-1.5 px-2 text-center">{it.quantity}</td>
                    <td className="py-1.5 px-2 text-right">{formatVnd(it.unit_price)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {tab === 'pricing' && (
          <div className="space-y-3">
            <div className="border rounded p-3 bg-gray-50">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <div className="text-[10px] text-gray-500">Tổng giá gốc</div>
                  <div className="font-medium">{formatVnd(pkg.original_price)}</div>
                </div>
                <div>
                  <div className="text-[10px] text-gray-500">Giá gói</div>
                  <div className="font-bold text-blue-600">{formatVnd(pkg.package_price)}</div>
                </div>
                <div>
                  <div className="text-[10px] text-gray-500">Giảm</div>
                  <div className="font-medium">
                    {formatVnd(pkg.discount_amount)} ({Number(pkg.discount_percent).toFixed(2)}%)
                  </div>
                </div>
                <div>
                  <div className="text-[10px] text-gray-500">Số dịch vụ</div>
                  <div className="font-medium">{pkg.items?.length || 0}</div>
                </div>
              </div>
            </div>
            <div className="text-[11px] text-gray-500 leading-relaxed">
              Giá gói được tính tự động dựa trên các dịch vụ thành phần × số lượng.
              Theo nghiệp vụ, giá gói không được lớn hơn tổng giá gốc.
            </div>
          </div>
        )}

        {tab === 'conditions' && (
          <div className="space-y-3">
            <div>
              <div className="text-[10px] text-gray-500 mb-1">Điều kiện áp dụng</div>
              <div className="text-[11px] leading-relaxed whitespace-pre-line">
                {pkg.conditions || '—'}
              </div>
            </div>
            <div className="border-t pt-3">
              <div className="text-[10px] text-gray-500 mb-1">Thời hạn sử dụng sau khi mua</div>
              <div className="text-[11px]">
                {pkg.usage_validity_days ? `${pkg.usage_validity_days} ngày` : 'Không giới hạn'}
              </div>
            </div>
          </div>
        )}

        {tab === 'history' && (
          <div className="space-y-2">
            {(pkg.history || []).length === 0 && (
              <div className="text-[11px] text-gray-500">Chưa có lịch sử thay đổi</div>
            )}
            {(pkg.history || []).map((h) => (
              <div key={h.id} className="border rounded p-2 bg-gray-50/50">
                <div className="flex justify-between items-start">
                  <span className="font-medium text-[11px] text-gray-800">
                    {HISTORY_ACTION_LABELS[h.action] || h.action}
                  </span>
                  <span className="text-[10px] text-gray-500">
                    {formatDateTime(h.created_at)}
                  </span>
                </div>
                {h.payload && (
                  <pre className="mt-1 text-[10px] text-gray-600 whitespace-pre-wrap break-words">
                    {JSON.stringify(h.payload)}
                  </pre>
                )}
                {h.reason && (
                  <div className="text-[10px] text-gray-500 mt-1">Lý do: {h.reason}</div>
                )}
                {h.changer && (
                  <div className="text-[10px] text-gray-500">Bởi: {h.changer.name}</div>
                )}
              </div>
            ))}
          </div>
        )}
      </div>

      {canManage && (
        <div className="p-3 border-t bg-gray-50 flex gap-2 justify-end relative">
          <button
            type="button"
            onClick={() => onEdit(pkg)}
            className="px-3 py-1.5 border rounded bg-white text-gray-700 hover:bg-gray-100 text-xs"
          >
            ✎ Chỉnh sửa
          </button>
          <button
            type="button"
            onClick={() => onChangeStatus(pkg)}
            className="px-3 py-1.5 border rounded bg-white text-gray-700 hover:bg-gray-100 text-xs"
          >
            Đổi trạng thái
          </button>
          <div className="relative">
            <button
              type="button"
              onClick={() => setActionsOpen((v) => !v)}
              className="px-3 py-1.5 border rounded bg-white text-gray-700 hover:bg-gray-100 text-xs"
            >
              •••
            </button>
            {actionsOpen && (
              <div className="absolute right-0 bottom-full mb-1 w-44 border rounded bg-white shadow text-xs z-10">
                <button
                  type="button"
                  onClick={() => {
                    setActionsOpen(false);
                    onClone(pkg);
                  }}
                  className="w-full text-left px-3 py-1.5 hover:bg-gray-50"
                >
                  Nhân bản gói
                </button>
                <button
                  type="button"
                  onClick={() => {
                    setActionsOpen(false);
                    onCreateNewVersion(pkg);
                  }}
                  className="w-full text-left px-3 py-1.5 hover:bg-gray-50"
                >
                  Tạo phiên bản mới
                </button>
                <button
                  type="button"
                  onClick={() => {
                    setActionsOpen(false);
                    onDelete(pkg);
                  }}
                  className="w-full text-left px-3 py-1.5 text-red-600 hover:bg-red-50"
                >
                  Xóa gói
                </button>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default PackageDetailPanel;
