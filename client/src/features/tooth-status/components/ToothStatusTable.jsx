import React, { useRef, useState } from 'react';

const ToothStatusTable = ({
  items,
  loading,
  selectedId,
  onSelect,
  onEdit,
  onToggleActive,
  onDelete,
  onReorder,
  canManage,
  page,
  perPage,
}) => {
  const dragId = useRef(null);
  const [dragOverId, setDragOverId] = useState(null);

  const handleDragStart = (id) => () => {
    if (!canManage) return;
    dragId.current = id;
  };

  const handleDragOver = (id) => (e) => {
    if (!canManage) return;
    e.preventDefault();
    if (dragOverId !== id) setDragOverId(id);
  };

  const handleDragEnd = () => {
    dragId.current = null;
    setDragOverId(null);
  };

  const handleDrop = (id) => (e) => {
    e.preventDefault();
    if (!canManage || dragId.current == null || dragId.current === id) {
      handleDragEnd();
      return;
    }
    const ids = items.map((it) => it.id);
    const fromIdx = ids.indexOf(dragId.current);
    const toIdx = ids.indexOf(id);
    if (fromIdx < 0 || toIdx < 0) {
      handleDragEnd();
      return;
    }
    const next = [...ids];
    next.splice(fromIdx, 1);
    next.splice(toIdx, 0, dragId.current);
    onReorder?.(next);
    handleDragEnd();
  };

  if (loading) {
    return <div className="p-6 text-center text-xs text-gray-500">Đang tải...</div>;
  }

  if (!items.length) {
    return (
      <div className="p-6 text-center text-xs text-gray-500">
        Không có trạng thái răng nào phù hợp.
      </div>
    );
  }

  return (
    <div className="overflow-x-auto">
      <table className="w-full text-left border-collapse whitespace-nowrap">
        <thead>
          <tr className="text-[11px] text-gray-500 border-b bg-gray-50">
            <th className="py-2.5 px-3 font-medium w-14 text-center">STT</th>
            <th className="py-2.5 px-2 font-medium">Mã</th>
            <th className="py-2.5 px-2 font-medium">Tên trạng thái</th>
            <th className="py-2.5 px-2 font-medium">Nhóm</th>
            <th className="py-2.5 px-2 font-medium text-center">Màu</th>
            <th className="py-2.5 px-2 font-medium text-center">Biểu tượng</th>
            <th className="py-2.5 px-2 font-medium text-center">Trạng thái</th>
            <th className="py-2.5 px-2 font-medium text-center">Thứ tự</th>
            <th className="py-2.5 px-3 font-medium text-center">Thao tác</th>
          </tr>
        </thead>
        <tbody className="text-[12px] text-gray-700">
          {items.map((item, idx) => {
            const stt = (page - 1) * perPage + idx + 1;
            const isSelected = selectedId === item.id;
            const isDragOver = dragOverId === item.id;
            return (
              <tr
                key={item.id}
                onClick={() => onSelect?.(item.id)}
                draggable={canManage}
                onDragStart={handleDragStart(item.id)}
                onDragOver={handleDragOver(item.id)}
                onDrop={handleDrop(item.id)}
                onDragEnd={handleDragEnd}
                className={`border-b cursor-pointer transition ${
                  isSelected ? 'bg-blue-50/60' : 'hover:bg-gray-50'
                } ${isDragOver ? 'bg-blue-100/40' : ''}`}
              >
                <td className="py-2 px-3 text-center text-gray-500">
                  <div className="flex items-center justify-center gap-2">
                    {canManage && (
                      <span
                        className="text-gray-400 cursor-grab select-none"
                        title="Kéo để sắp xếp"
                      >
                        ⋮⋮
                      </span>
                    )}
                    <span>{stt}</span>
                  </div>
                </td>
                <td className="py-2 px-2 font-medium text-gray-900">{item.code}</td>
                <td className="py-2 px-2 text-gray-800">{item.name}</td>
                <td className="py-2 px-2 text-gray-500">{item.group?.name || '-'}</td>
                <td className="py-2 px-2 text-center">
                  <div
                    className="w-3.5 h-3.5 rounded-full mx-auto border border-white shadow-sm"
                    style={{ backgroundColor: item.color }}
                    title={item.color}
                  />
                </td>
                <td className="py-2 px-2 text-center text-lg leading-none">
                  {item.icon || '-'}
                </td>
                <td className="py-2 px-2 text-center">
                  <span
                    className={`px-2 py-0.5 rounded text-[11px] border inline-block min-w-[88px] ${
                      item.is_active
                        ? 'bg-green-50 text-green-700 border-green-200'
                        : 'bg-gray-100 text-gray-500 border-gray-200'
                    }`}
                  >
                    {item.is_active ? 'Đang sử dụng' : 'Ngừng sử dụng'}
                  </span>
                </td>
                <td className="py-2 px-2 text-center">{item.display_order}</td>
                <td className="py-2 px-3">
                  <div className="flex justify-center gap-1.5">
                    <button
                      type="button"
                      title="Xem chi tiết"
                      onClick={(e) => {
                        e.stopPropagation();
                        onSelect?.(item.id);
                      }}
                      className="p-1 border rounded bg-white text-gray-500 hover:bg-gray-50"
                    >
                      👁
                    </button>
                    {canManage && (
                      <>
                        <button
                          type="button"
                          title="Chỉnh sửa"
                          onClick={(e) => {
                            e.stopPropagation();
                            onEdit?.(item);
                          }}
                          className="p-1 border rounded bg-white text-gray-500 hover:bg-gray-50"
                        >
                          ✎
                        </button>
                        <button
                          type="button"
                          title={item.is_active ? 'Chuyển sang ngừng sử dụng' : 'Chuyển sang đang sử dụng'}
                          onClick={(e) => {
                            e.stopPropagation();
                            onToggleActive?.(item);
                          }}
                          className="p-1 border rounded bg-white text-gray-500 hover:bg-gray-50"
                        >
                          {item.is_active ? '⏸' : '▶'}
                        </button>
                        <button
                          type="button"
                          title="Xóa"
                          onClick={(e) => {
                            e.stopPropagation();
                            onDelete?.(item);
                          }}
                          className="p-1 border rounded bg-white text-red-500 hover:bg-red-50"
                        >
                          ✕
                        </button>
                      </>
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

export default ToothStatusTable;
