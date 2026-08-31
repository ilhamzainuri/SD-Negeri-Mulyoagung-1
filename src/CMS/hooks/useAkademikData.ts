import { useState, useEffect, useCallback, useRef } from 'react';
import { getApiBaseUrl } from '../../config/api';
import { AkademikMenuItem } from '../../types';

const API_BASE = getApiBaseUrl();

export interface AkademikDragState {
  draggedIndex: number | null;
  itemsBeforeDrag: AkademikMenuItem[];
}

export function useAkademikData(status: 'all' | 'active_only' = 'active_only') {
  const [items, setItems] = useState<AkademikMenuItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [draggedIndex, setDraggedIndex] = useState<number | null>(null);
  const [itemsBeforeDrag, setItemsBeforeDrag] = useState<AkademikMenuItem[]>([]);
  const itemsRef = useRef<AkademikMenuItem[]>(items);

  // Sync itemsRef with items
  useEffect(() => {
    itemsRef.current = items;
  }, [items]);

  // Save items before drag for rollback
  useEffect(() => {
    setItemsBeforeDrag([...items]);
  }, [items]);

  const fetchItems = useCallback(async () => {
    setLoading(true);
    try {
      const response = await fetch(`${API_BASE}/backend/API/akademik_menu.php?status=${status}&_t=${Date.now()}`, {
        cache: 'no-store',
      });
      const result = await response.json();
      if (result.status === 'success') {
        setItems(result.data || []);
        itemsRef.current = result.data || [];
      } else {
        setError(result.message || 'Gagal memuat menu akademik.');
      }
    } catch {
      setError('Gagal menghubungi server backend.');
    } finally {
      setLoading(false);
    }
  }, [status]);

  useEffect(() => {
    fetchItems();
  }, [fetchItems]);

  // Drag and drop handlers
  const handleDragStart = useCallback((e: React.DragEvent, index: number) => {
    setDraggedIndex(index);
    itemsRef.current = [...items];
    e.dataTransfer.effectAllowed = 'move';
  }, [items]);

  const handleDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault();
  }, []);

  const handleDrop = useCallback(async (e: React.DragEvent, index: number) => {
    e.preventDefault();
    if (draggedIndex === null || draggedIndex === index) {
      setDraggedIndex(null);
      return;
    }

    const newItems = [...itemsRef.current];
    const draggedItem = newItems[draggedIndex];
    newItems.splice(draggedIndex, 1);
    newItems.splice(index, 0, draggedItem);

    // Update urutan berdasarkan posisi baru
    newItems.forEach((item, idx) => {
      item.urutan = idx;
    });

    setItems(newItems);
    setDraggedIndex(null);

    // Submit urutan baru ke backend
    try {
      const bulkUpdatePromises = newItems.map((item) => {
        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('id', item.id.toString());
        formData.append('label', item.label);
        formData.append('deskripsi', item.deskripsi || '');
        formData.append('link_gdrive', item.link_gdrive);
        formData.append('is_modul', item.is_modul ? '1' : '0');
        formData.append('urutan', item.urutan.toString());
        formData.append('aktif', item.aktif ? '1' : '0');
        formData.append('role', 'ADMIN');

        return fetch(`${API_BASE}/backend/API/akademik_menu.php`, {
          method: 'POST',
          body: formData,
        });
      });

      await Promise.all(bulkUpdatePromises);
    } catch {
      // Rollback jika gagal
      setItems(itemsBeforeDrag);
    }
  }, [draggedIndex, itemsBeforeDrag]);

  const deleteItem = async (id: number, role: string = 'ADMIN') => {
    setError('');
    setSuccess('');
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id.toString());
    formData.append('role', role);

    try {
      const response = await fetch(`${API_BASE}/backend/API/akademik_menu.php`, {
        method: 'POST',
        body: formData,
      });
      const result = await response.json();
      if (result.status === 'success') {
        setSuccess(result.message);
        fetchItems();
        return true;
      } else {
        setError(result.message || 'Gagal menghapus menu.');
        return false;
      }
    } catch {
      setError('Terjadi kesalahan saat menghapus data.');
      return false;
    }
  };

  return {
    items,
    loading,
    error,
    setError,
    success,
    setSuccess,
    fetchItems,
    deleteItem,
    draggedIndex,
    handleDragStart,
    handleDragOver,
    handleDrop,
  };
}
