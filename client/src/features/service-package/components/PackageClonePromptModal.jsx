import React, { useEffect, useState } from 'react';

const PackageClonePromptModal = ({ open, pkg, mode, saving, error, onClose, onSubmit }) => {
  const [name, setName] = useState('');
  const [code, setCode] = useState('');
  const [reason, setReason] = useState('');

  useEffect(() => {
    if (!open) return;
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setName(pkg ? `${pkg.name} (Sao chép)` : '');
    setCode('');
    setReason('');
  }, [open, pkg]);

  if (!open || !pkg) return null;

  const isClone = mode === 'clone';

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-3">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-md">
        <div className="px-4 py-3 flex justify-between items-center border-b">
          <h2 className="font-semibold text-gray-800 text-sm">
            {isClone ? 'Nhân bản gói dịch vụ' : 'Tạo phiên bản mới'}
          </h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
            ✕
          </button>
        </div>
        <div className="p-4 space-y-3 text-xs">
          <div className="text-gray-600">
            Gói gốc: <span className="font-medium">{pkg.code} · {pkg.name}</span>
          </div>
          {isClone && (
            <>
              <div>
                <label className="text-gray-500 mb-1 block">Mã gói mới (để trống để tự sinh)</label>
                <input
                  type="text"
                  value={code}
                  onChange={(e) => setCode(e.target.value)}
                  className="w-full border rounded px-2 py-1.5 focus:outline-none"
                />
              </div>
              <div>
                <label className="text-gray-500 mb-1 block">Tên gói mới</label>
                <input
                  type="text"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  className="w-full border rounded px-2 py-1.5 focus:outline-none"
                />
              </div>
            </>
          )}
          <div>
            <label className="text-gray-500 mb-1 block">Lý do</label>
            <textarea
              rows={3}
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              className="w-full border rounded px-2 py-1.5 focus:outline-none resize-none"
            />
          </div>
          {error && <div className="text-red-600">{error}</div>}
        </div>
        <div className="p-3 border-t flex justify-end gap-2 bg-gray-50 rounded-b-lg">
          <button
            type="button"
            onClick={onClose}
            className="px-3 py-1.5 border rounded bg-white text-gray-700 text-xs"
          >
            Hủy
          </button>
          <button
            type="button"
            disabled={saving}
            onClick={() => onSubmit({ name, code, reason })}
            className="px-4 py-1.5 rounded bg-blue-600 text-white text-xs hover:bg-blue-700 disabled:opacity-60"
          >
            {saving ? 'Đang lưu...' : isClone ? 'Nhân bản' : 'Tạo phiên bản'}
          </button>
        </div>
      </div>
    </div>
  );
};

export default PackageClonePromptModal;
