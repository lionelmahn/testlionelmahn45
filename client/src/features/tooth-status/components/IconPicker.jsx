import React, { useState } from 'react';
import { ICON_PALETTE } from '../constants';

const IconPicker = ({ value, onChange }) => {
  const [custom, setCustom] = useState(value && !ICON_PALETTE.includes(value));

  return (
    <div>
      <div className="flex flex-wrap items-center gap-1.5">
        {ICON_PALETTE.map((icon) => {
          const active = value === icon;
          return (
            <button
              key={icon}
              type="button"
              onClick={() => {
                onChange(icon);
                setCustom(false);
              }}
              className={`w-9 h-9 border rounded text-lg flex items-center justify-center bg-white ${
                active ? 'border-blue-500 ring-2 ring-blue-100 text-blue-600' : 'border-gray-200 text-gray-700'
              }`}
            >
              {icon}
            </button>
          );
        })}
        <button
          type="button"
          onClick={() => setCustom((v) => !v)}
          className="w-9 h-9 border border-dashed rounded text-base text-gray-500 hover:bg-gray-50"
        >
          +
        </button>
      </div>
      {custom && (
        <input
          type="text"
          value={value || ''}
          maxLength={4}
          onChange={(e) => onChange(e.target.value)}
          placeholder="Nhập emoji hoặc ký hiệu"
          className="mt-2 border rounded px-2 py-1 text-xs w-40"
        />
      )}
    </div>
  );
};

export default IconPicker;
