import React from 'react';
import { Edit2, Trash2, ExternalLink, Eye, EyeOff, Layers, GripVertical } from 'lucide-react';
import { AkademikMenuItem } from '../../types';

interface AkademikCardProps {
  item: AkademikMenuItem;
  onEdit: (item: AkademikMenuItem) => void;
  onDelete: (item: AkademikMenuItem) => void;
  onDragStart?: (e: React.DragEvent, index: number) => void;
  onDragOver?: (e: React.DragEvent) => void;
  onDrop?: (e: React.DragEvent, index: number) => void;
  draggedIndex?: number | null;
  index?: number;
}

export const AkademikCard: React.FC<AkademikCardProps> = ({ 
  item, onEdit, onDelete, 
  onDragStart, onDragOver, onDrop, draggedIndex, index 
}) => {
  const isModul = Number(item.is_modul) === 1;
  const isAktif = Number(item.aktif) === 1;
  const displayUrutan = item.urutan !== undefined && item.urutan > 0 ? item.urutan + 1 : 1;

  return (
    <div
      draggable={draggedIndex !== null}
      onDragStart={onDragStart ? (e) => onDragStart(e, index || 0) : undefined}
      onDragOver={onDragOver}
      onDrop={onDrop ? (e) => onDrop(e, index || 0) : undefined}
      className={`flex flex-col md:flex-row items-start md:items-center gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200 cursor-grab active:cursor-grabbing transition-all ${
        draggedIndex === index ? 'opacity-40 border-teal-500 scale-[0.98]' : 'hover:border-teal-400'
      }`}
    >
      <div className="flex items-center gap-3 shrink-0">
        <GripVertical className="text-slate-400 shrink-0" size={20} />
        <span className="w-7 h-7 rounded-lg bg-teal-50 text-teal-700 font-bold text-sm flex items-center justify-center border border-teal-100 shrink-0">
          {displayUrutan}
        </span>
        <span className="font-bold text-sm text-slate-800 capitalize flex-1">
          {item.label}
        </span>
        {isModul && (
          <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600 border border-indigo-200 shrink-0">
            Modul
          </span>
        )}
      </div>

      <div className="flex-grow flex items-center justify-between gap-3 w-full mt-2 md:mt-0">
        <span className="text-xs text-slate-500 truncate">
          {item.link_gdrive}
        </span>
        <div className="flex items-center gap-1 shrink-0">
          <a
            href={item.link_gdrive}
            target="_blank"
            rel="noopener noreferrer"
            className="text-teal-600 hover:text-teal-800 p-1 hover:bg-teal-50 rounded-lg transition"
            title="Buka Link Google Drive"
          >
            <ExternalLink size={14} />
          </a>
          <span
            className={`text-[10px] font-bold px-2 py-0.5 rounded-full flex items-center gap-1 ${
              isAktif
                ? 'bg-emerald-50 text-emerald-600 border border-emerald-200'
                : 'bg-slate-100 text-slate-500 border border-slate-200'
            }`}
          >
            {isAktif ? <Eye size={10} /> : <EyeOff size={10} />}
            {isAktif ? 'Aktif' : 'Nonaktif'}
          </span>
          <button
            onClick={() => onEdit(item)}
            className="p-1.5 text-slate-600 hover:text-teal-600 hover:bg-teal-50 rounded-lg transition cursor-pointer"
            title="Edit"
          >
            <Edit2 size={14} />
          </button>
          <button
            onClick={() => onDelete(item)}
            className="p-1.5 text-slate-600 hover:text-red-600 hover:bg-red-50 rounded-lg transition cursor-pointer"
            title="Hapus"
          >
            <Trash2 size={14} />
          </button>
        </div>
      </div>
    </div>
  );
};
