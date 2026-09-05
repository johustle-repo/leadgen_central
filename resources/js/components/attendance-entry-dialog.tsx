import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { useAttendanceEntryEditor } from '@/hooks/use-attendance-entry-editor';

type Editor = ReturnType<typeof useAttendanceEntryEditor>;

export function AttendanceEntryDialog({
    editingCell,
    editForm,
    onSubmit,
    onClear,
    onClose,
}: {
    editingCell: Editor['editingCell'];
    editForm: Editor['editForm'];
    onSubmit: Editor['submitEdit'];
    onClear: Editor['clearEntry'];
    onClose: () => void;
}) {
    return (
        <Dialog
            open={editingCell !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <DialogTitle>
                    {editingCell?.entryType === 'time_in'
                        ? 'Edit Time In'
                        : 'Edit Time Out'}
                </DialogTitle>
                <DialogDescription>
                    {editingCell?.userName} &mdash; {editingCell?.date}
                </DialogDescription>
                <form onSubmit={onSubmit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="recorded-at">Time</Label>
                        <Input
                            id="recorded-at"
                            type="datetime-local"
                            value={editForm.data.recorded_at}
                            onChange={(event) =>
                                editForm.setData(
                                    'recorded_at',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={editForm.errors.recorded_at} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClear}
                            disabled={editForm.processing}
                        >
                            Clear
                        </Button>
                        <Button type="submit" disabled={editForm.processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
