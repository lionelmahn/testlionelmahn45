import React from 'react';

const PER_PAGE_OPTIONS = [10, 20, 50];

const Pagination = ({ meta, page, onPageChange, perPage, onPerPageChange }) => {
  const lastPage = meta?.last_page || 1;
  const total = meta?.total || 0;

  const goTo = (next) => {
    const p = Math.max(1, Math.min(lastPage, next));
    if (p !== page) onPageChange(p);
  };

  return (
    <div className="p-2 border-t flex flex-col sm:flex-row gap-2 justify-between items-center text-xs text-gray-500 bg-white rounded-b-lg">
      <div className="italic text-gray-400 text-[10px]">
        Tổng {total} trạng thái · Kéo thả ⋮⋮ để sắp xếp thứ tự hiển thị
      </div>
      <div className="flex items-center gap-1">
        <button
          type="button"
          onClick={() => goTo(page - 1)}
          disabled={page <= 1}
          className="w-7 h-7 border rounded hover:bg-gray-50 disabled:opacity-50"
        >
          ‹
        </button>
        <span className="px-2 text-gray-700">
          {page}/{lastPage}
        </span>
        <button
          type="button"
          onClick={() => goTo(page + 1)}
          disabled={page >= lastPage}
          className="w-7 h-7 border rounded hover:bg-gray-50 disabled:opacity-50"
        >
          ›
        </button>
        <select
          value={perPage}
          onChange={(e) => onPerPageChange(Number(e.target.value))}
          className="ml-2 border rounded py-1 px-1 focus:outline-none"
        >
          {PER_PAGE_OPTIONS.map((opt) => (
            <option key={opt} value={opt}>
              {opt} / trang
            </option>
          ))}
        </select>
      </div>
    </div>
  );
};

export default Pagination;
