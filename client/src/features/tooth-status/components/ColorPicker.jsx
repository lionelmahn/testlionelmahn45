import React, { useState } from 'react';
import { COLOR_PALETTE } from '../constants';

const isHex = (value) => /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(value || '');

const ColorPicker = ({ value, onChange, error }) => {
  const [showCustom, setShowCustom] = useState(
    Boolean(value) && !COLOR_PALETTE.some((c) => c.hex.toLowerCase() === String(value).toLowerCase()),
  );

  return (
    <div>
      <div className="flex flex-wrap items-center gap-2">
        {COLOR_PALETTE.map((color) => {
          const active = String(value || '').toLowerCase() === color.hex.toLowerCase();
          return (
            <button
              key={color.hex}
              type="button"
              title={color.label}
              onClick={() => onChange(color.hex)}
              style={{ backgroundColor: color.hex }}
              className={`w-6 h-6 rounded-full border-2 transition ${
                active ? 'border-gray-900 ring-2 ring-offset-1 ring-blue-500' : 'border-white shadow-sm'
              }`}
            />
          );
        })}
        <button
          type="button"
          onClick={() => setShowCustom((v) => !v)}
          className="w-6 h-6 rounded-full border border-dashed border-gray-400 text-[10px] text-gray-500 hover:bg-gray-50"
        >
          +
        </button>
      </div>

      {showCustom && (
        <div className="mt-2 flex items-center gap-2">
          <input
            type="color"
            value={isHex(value) ? value : '#000000'}
            onChange={(e) => onChange(e.target.value.toUpperCase())}
            className="w-8 h-8 border rounded cursor-pointer"
          />
          <input
            type="text"
            value={value || ''}
            onChange={(e) => onChange(e.target.value)}
            placeholder="#RRGGBB"
            className="border rounded px-2 py-1 text-xs w-28"
          />
        </div>
      )}

      {error && <div className="text-[10px] text-red-500 mt-1">{error}</div>}
    </div>
  );
};

export default ColorPicker;
