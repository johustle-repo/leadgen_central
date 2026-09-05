import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { updateEntry } from '@/routes/attendance';
import type { AttendanceEntryType } from '@/types';

export type EditingAttendanceCell = {
    userId: number;
    userName: string;
    date: string;
    entryType: AttendanceEntryType;
};

function toDatetimeLocalValue(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/**
 * Shared create/correct/clear behavior for a single Time In/Out cell,
 * used by both the Attendance Monitor table and the Summary page's
 * per-agent daily breakdown - both edit the same `attendance.update-entry`
 * slot (user + date + entry type), not a specific attendance record id.
 */
export function useAttendanceEntryEditor() {
    const editForm = useForm<{ recorded_at: string }>({ recorded_at: '' });
    const [editingCell, setEditingCell] = useState<EditingAttendanceCell | null>(
        null,
    );

    function openEditor(
        userId: number,
        userName: string,
        date: string,
        entryType: AttendanceEntryType,
        currentValue: string | null,
    ) {
        editForm.clearErrors();
        editForm.setData('recorded_at', toDatetimeLocalValue(currentValue));
        setEditingCell({ userId, userName, date, entryType });
    }

    function closeEditor() {
        setEditingCell(null);
    }

    function submitEdit(event: React.FormEvent) {
        event.preventDefault();

        if (!editingCell) {
            return;
        }

        editForm.put(
            updateEntry.url({
                user: editingCell.userId,
                date: editingCell.date,
                entryType: editingCell.entryType,
            }),
            { preserveScroll: true, onSuccess: closeEditor },
        );
    }

    function clearEntry() {
        if (!editingCell) {
            return;
        }

        router.put(
            updateEntry.url({
                user: editingCell.userId,
                date: editingCell.date,
                entryType: editingCell.entryType,
            }),
            { recorded_at: '' },
            { preserveScroll: true, onSuccess: closeEditor },
        );
    }

    return {
        editingCell,
        editForm,
        openEditor,
        closeEditor,
        submitEdit,
        clearEntry,
    };
}
