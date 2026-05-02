import React, { useEffect, useState } from 'react';
import { toothStatusApi } from '@/api/toothStatusApi';
import { actionLabel, formatDateTime, summarizeChanges } from '../utils';

const HistoryCard = ({ statusId, refreshKey }) => {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    let mounted = true;
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setLoading(true);
    setError('');
    const fetcher = statusId
      ? toothStatusApi.history(statusId)
      : toothStatusApi.recentHistory();

    fetcher
      .then((res) => {
        if (!mounted) return;
        setItems(res.data || []);
      })
      .catch((err) => {
        if (mounted) setError(err?.response?.data?.message || 'Không thể tải lịch sử');
      })
      .finally(() => {
        if (mounted) setLoading(false);
      });
    return () => {
      mounted = false;
    };
  }, [statusId, refreshKey]);

  return (
    <div className="bg-white border rounded-lg shadow-sm flex flex-col h-full">
      <div className="px-4 py-3 border-b flex justify-between items-center">
        <h2 className="font-semibold text-gray-800 text-sm">Lịch sử thay đổi</h2>
        <span className="text-[10px] text-gray-400">
          {statusId ? 'Theo trạng thái đã chọn' : 'Toàn bộ danh mục'}
        </span>
      </div>
      <div className="p-3 flex-1 overflow-y-auto max-h-[360px]">
        {loading && <div className="text-xs text-gray-400">Đang tải...</div>}
        {error && <div className="text-xs text-red-500">{error}</div>}
        {!loading && !items.length && (
          <div className="text-xs text-gray-400 italic">Chưa có lịch sử thay đổi.</div>
        )}
        {items.length > 0 && (
          <table className="w-full text-left text-[11px]">
            <thead className="text-gray-500 border-b">
              <tr>
                <th className="py-2 font-medium">Thời gian</th>
                <th className="py-2 font-medium">Người thao tác</th>
                <th className="py-2 font-medium">Hành động</th>
                <th className="py-2 font-medium">Trước</th>
                <th className="py-2 font-medium">Sau</th>
              </tr>
            </thead>
            <tbody>
              {items.map((row) => (
                <tr key={row.id} className="border-b hover:bg-gray-50 align-top">
                  <td className="py-2 text-gray-500 w-24 whitespace-nowrap">
                    {formatDateTime(row.created_at)}
                  </td>
                  <td className="py-2">{row.performer?.name || 'Hệ thống'}</td>
                  <td className="py-2">{actionLabel(row.action)}</td>
                  <td className="py-2 text-gray-500">
                    {row.before ? summarizeChanges(row, 'before') : '-'}
                  </td>
                  <td className="py-2 text-gray-800">
                    {row.after ? summarizeChanges(row, 'after') : '-'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
};

export default HistoryCard;
