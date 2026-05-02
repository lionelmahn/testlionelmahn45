import React from 'react';
import { ArrowLeft, Plus, Edit3, Trash2, CheckCircle2, XCircle, Clock } from 'lucide-react';
import { formatVnd, formatDate, formatDateTime, PROPOSAL_STATUS_LABEL } from '../utils';

const sectionLabel = {
  current: { text: 'HIỆN TẠI', color: 'text-emerald-600', dot: 'bg-emerald-500' },
  future: { text: 'TƯƠNG LAI', color: 'text-blue-600', dot: 'bg-blue-500' },
  past: { text: 'ĐÃ HẾT', color: 'text-gray-500', dot: 'bg-gray-400' },
};

const RecordCard = ({ record, sectionType, canEdit, canDelete, canApprove, onEdit, onDelete, onApprove, onReject }) => {
  const isPending = record.proposal_status === 'pending';
  const isRejected = record.proposal_status === 'rejected';

  return (
    <div className={`relative rounded-lg border p-3 shadow-sm ${sectionType === 'past' ? 'bg-gray-50' : 'bg-white'}`}>
      <div className="flex items-start justify-between gap-3">
        <div className="flex-1 space-y-1">
          <div className="flex items-center gap-2 flex-wrap">
            <span className="text-sm font-bold text-gray-900">{formatVnd(record.price)}</span>
            {isPending && (
              <span className="rounded border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700">
                {PROPOSAL_STATUS_LABEL.pending}
              </span>
            )}
            {isRejected && (
              <span className="rounded border border-red-200 bg-red-50 px-1.5 py-0.5 text-[10px] font-medium text-red-700">
                {PROPOSAL_STATUS_LABEL.rejected}
              </span>
            )}
          </div>
          <div className="text-[11px] text-gray-600">
            Hiệu lực:{' '}
            <span className="font-medium text-gray-800">
              {formatDate(record.effective_from)} -{' '}
              {record.effective_to ? formatDate(record.effective_to) : 'Không thời hạn'}
            </span>
          </div>
          {record.reason && (
            <div className="text-[11px] text-gray-600">
              Ghi chú: <span className="text-gray-700">{record.reason}</span>
            </div>
          )}
          {record.rejected_reason && (
            <div className="text-[11px] text-red-600">
              Lý do từ chối: <span>{record.rejected_reason}</span>
            </div>
          )}
        </div>
        <div className="w-36 space-y-1 text-right text-[10px] text-gray-500">
          <div className="flex justify-between gap-2">
            <span>Tạo bởi:</span>
            <span className="text-gray-800">
              {record.proposer?.name || record.creator?.name || 'Hệ thống'}
            </span>
          </div>
          <div className="flex justify-between gap-2">
            <span>Ngày tạo:</span>
            <span className="text-gray-800">{formatDateTime(record.created_at)}</span>
          </div>
          {record.approved_at && (
            <div className="flex justify-between gap-2">
              <span>Duyệt bởi:</span>
              <span className="text-gray-800">{record.approver?.name || '-'}</span>
            </div>
          )}
          <div className="mt-2 flex flex-wrap items-center justify-end gap-1">
            {isPending && canApprove && (
              <>
                <button
                  onClick={() => onApprove?.(record)}
                  className="flex items-center gap-1 rounded bg-emerald-600 px-2 py-0.5 text-white hover:bg-emerald-700"
                  title="Duyệt"
                >
                  <CheckCircle2 size={12} /> Duyệt
                </button>
                <button
                  onClick={() => onReject?.(record)}
                  className="flex items-center gap-1 rounded bg-red-600 px-2 py-0.5 text-white hover:bg-red-700"
                  title="Từ chối"
                >
                  <XCircle size={12} /> Từ chối
                </button>
              </>
            )}
            {sectionType !== 'past' && canEdit && record.status !== 'expired' && (
              <button
                onClick={() => onEdit?.(record)}
                className="rounded border px-1.5 py-0.5 text-gray-500 hover:bg-gray-50"
                title="Sửa"
              >
                <Edit3 size={12} />
              </button>
            )}
            {sectionType === 'future' && canDelete && (
              <button
                onClick={() => onDelete?.(record)}
                className="rounded border px-1.5 py-0.5 text-red-500 hover:bg-red-50"
                title="Xoá"
              >
                <Trash2 size={12} />
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

const Section = ({ type, records, canEdit, canDelete, canApprove, onEdit, onDelete, onApprove, onReject }) => {
  if (!records?.length) return null;
  const lbl = sectionLabel[type];
  return (
    <div className="space-y-2">
      {records.map((r, idx) => (
        <div key={r.id} className="relative pl-7">
          <span className={`absolute left-0 top-2 h-3 w-3 rounded-full ${lbl.dot} ring-2 ring-white`} />
          {idx === 0 && (
            <div className={`absolute -left-1 -top-3 text-[10px] font-bold uppercase ${lbl.color}`}>
              {lbl.text}
            </div>
          )}
          <RecordCard
            record={r}
            sectionType={type}
            canEdit={canEdit}
            canDelete={canDelete}
            canApprove={canApprove}
            onEdit={onEdit}
            onDelete={onDelete}
            onApprove={onApprove}
            onReject={onReject}
          />
        </div>
      ))}
    </div>
  );
};

const PriceTimelinePanel = ({
  timeline,
  loading,
  onClose,
  onAddPrice,
  canCreate,
  canApprove,
  canEdit,
  canDelete,
  onEdit,
  onDelete,
  onApprove,
  onReject,
}) => {
  if (loading) {
    return (
      <div className="flex h-full items-center justify-center text-sm text-gray-500">
        <Clock className="mr-2 animate-spin" size={16} /> Đang tải lịch sử giá...
      </div>
    );
  }

  if (!timeline?.service) {
    return (
      <div className="flex h-full flex-col items-center justify-center gap-2 p-10 text-center text-gray-500">
        <Clock size={28} className="text-gray-300" />
        <div className="text-sm">Chọn một dịch vụ để xem lịch sử giá.</div>
      </div>
    );
  }

  const { service, current, future, past, pending } = timeline;

  return (
    <div className="flex h-full flex-col">
      <div className="flex items-center justify-between border-b px-4 py-3">
        <button
          onClick={onClose}
          className="flex items-center gap-1 text-[13px] font-semibold text-gray-800 hover:text-gray-600"
        >
          <ArrowLeft size={14} /> Lịch sử giá dịch vụ
        </button>
        {canCreate && (
          <button
            onClick={onAddPrice}
            className="flex items-center gap-1 rounded bg-blue-600 px-3 py-1.5 text-[11px] font-medium text-white hover:bg-blue-700"
          >
            <Plus size={12} /> Thêm giá mới
          </button>
        )}
      </div>

      <div className="flex items-center justify-between border-b bg-gray-50/30 p-4">
        <div>
          <div className="text-[10px] text-gray-500">{service.service_code}</div>
          <h3 className="text-sm font-bold text-gray-900">{service.name}</h3>
          <div className="text-[11px] text-gray-500">Nhóm: {service.group?.name || '-'}</div>
        </div>
        <div className="text-right">
          <div className="text-[10px] text-gray-500">Giá hiện tại</div>
          <div className="text-base font-bold text-blue-600">
            {current ? formatVnd(current.price) : 'Chưa có'}
          </div>
        </div>
      </div>

      <div className="flex-1 space-y-4 overflow-y-auto p-4">
        {pending?.length > 0 && (
          <div>
            <div className="mb-2 text-[11px] font-medium uppercase text-amber-700">
              Đề xuất chờ duyệt ({pending.length})
            </div>
            <Section
              type="future"
              records={pending}
              canEdit={canEdit}
              canDelete={canDelete}
              canApprove={canApprove}
              onEdit={onEdit}
              onDelete={onDelete}
              onApprove={onApprove}
              onReject={onReject}
            />
          </div>
        )}

        <div>
          <div className="mb-2 text-[11px] font-medium uppercase text-gray-500">Timeline giá</div>
          <Section
            type="current"
            records={current ? [current] : []}
            canEdit={canEdit}
            canDelete={canDelete}
            canApprove={canApprove}
            onEdit={onEdit}
            onDelete={onDelete}
            onApprove={onApprove}
            onReject={onReject}
          />
          <div className="my-2" />
          <Section
            type="future"
            records={future}
            canEdit={canEdit}
            canDelete={canDelete}
            canApprove={canApprove}
            onEdit={onEdit}
            onDelete={onDelete}
            onApprove={onApprove}
            onReject={onReject}
          />
          <div className="my-2" />
          <Section
            type="past"
            records={past}
            canEdit={false}
            canDelete={false}
            canApprove={false}
          />

          {!current && !future?.length && !past?.length && !pending?.length && (
            <div className="rounded border border-dashed bg-gray-50 p-6 text-center text-xs text-gray-500">
              Chưa có bảng giá nào cho dịch vụ này.{' '}
              {canCreate && (
                <button onClick={onAddPrice} className="text-blue-600 hover:underline">
                  Bấm để thêm
                </button>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default PriceTimelinePanel;
