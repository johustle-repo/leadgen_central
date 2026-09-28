import { router } from '@inertiajs/react';
import { Camera, ImageUp, LoaderCircle, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useInitials } from '@/hooks/use-initials';
import { destroy, update } from '@/routes/avatar';
import type { User } from '@/types';

/** Longest side of the square photo actually uploaded. */
const PHOTO_SIZE = 512;

/**
 * Centre-crops the chosen picture to a square and scales it down in the
 * browser, so large phone photos upload quickly and never exceed the
 * server's size limit. Rejects formats the browser cannot open (e.g. HEIC).
 */
async function preparePhoto(file: File): Promise<File> {
    const url = URL.createObjectURL(file);

    try {
        const image = await new Promise<HTMLImageElement>((resolve, reject) => {
            const element = new Image();
            element.onload = () => resolve(element);
            element.onerror = () => reject(new Error('unreadable'));
            element.src = url;
        });
        const side = Math.min(image.naturalWidth, image.naturalHeight);

        if (side < 64) {
            throw new Error('too-small');
        }

        const target = Math.min(PHOTO_SIZE, side);
        const canvas = document.createElement('canvas');
        canvas.width = target;
        canvas.height = target;
        const context = canvas.getContext('2d');

        if (!context) {
            return file;
        }

        context.drawImage(
            image,
            (image.naturalWidth - side) / 2,
            (image.naturalHeight - side) / 2,
            side,
            side,
            0,
            0,
            target,
            target,
        );
        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/jpeg', 0.9),
        );

        return blob
            ? new File([blob], 'profile-photo.jpg', { type: 'image/jpeg' })
            : file;
    } finally {
        URL.revokeObjectURL(url);
    }
}

export function ProfilePhotoCard({ user }: { user: User }) {
    const getInitials = useInitials();
    const inputRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [preparing, setPreparing] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [progress, setProgress] = useState<number | null>(null);
    const preview = useMemo(
        () => (file ? URL.createObjectURL(file) : null),
        [file],
    );

    useEffect(
        () => () => {
            if (preview) {
                URL.revokeObjectURL(preview);
            }
        },
        [preview],
    );

    const reset = () => {
        if (inputRef.current) {
            inputRef.current.value = '';
        }

        setFile(null);
        setProgress(null);
    };

    const choose = async (files: FileList | null) => {
        const chosen = files?.[0];
        setError(null);

        if (!chosen) {
            return;
        }

        setPreparing(true);

        try {
            setFile(await preparePhoto(chosen));
        } catch (reason) {
            setError(
                reason instanceof Error && reason.message === 'too-small'
                    ? 'That picture is too small. Use one at least 64 × 64 pixels.'
                    : 'That file could not be opened as a picture. Use a JPG, PNG or WEBP image.',
            );
            reset();
        } finally {
            setPreparing(false);
        }
    };

    const save = () => {
        if (!file) {
            return;
        }

        router.post(
            update.url(),
            { avatar: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setUploading(true),
                onProgress: (event) => setProgress(event?.percentage ?? null),
                onSuccess: () => reset(),
                onError: (errors) => {
                    const message =
                        errors.avatar ?? 'The photo could not be saved.';
                    setError(message);
                    toast.error(message);
                },
                onFinish: () => {
                    setUploading(false);
                    setProgress(null);
                },
            },
        );
    };

    const remove = () =>
        router.delete(destroy.url(), {
            preserveScroll: true,
            onError: () => toast.error('The photo could not be removed.'),
        });

    const busy = preparing || uploading;

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center gap-3">
                    <div className="rounded-lg bg-primary/10 p-2 text-primary">
                        <Camera className="size-5" />
                    </div>
                    <div>
                        <CardTitle>Profile picture</CardTitle>
                        <CardDescription>
                            Shown in the sidebar and to your team. Any JPG, PNG
                            or WEBP photo — it is cropped to a square for you.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <div className="flex flex-col items-center gap-5 sm:flex-row">
                    <button
                        type="button"
                        onClick={() => inputRef.current?.click()}
                        disabled={busy}
                        className="group relative shrink-0 rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        aria-label="Choose a new profile picture"
                    >
                        <Avatar className="size-24 overflow-hidden rounded-full ring-4 ring-primary/25 ring-offset-2 ring-offset-card">
                            <AvatarImage
                                key={preview ?? user.avatar ?? 'none'}
                                src={preview ?? user.avatar ?? undefined}
                                alt=""
                                className="object-cover"
                            />
                            <AvatarFallback className="bg-primary/10 text-2xl font-semibold text-primary">
                                {getInitials(user.name)}
                            </AvatarFallback>
                        </Avatar>
                        <span className="absolute inset-0 flex items-center justify-center rounded-full bg-black/45 text-white opacity-0 transition-opacity group-hover:opacity-100">
                            {busy ? (
                                <LoaderCircle className="size-6 animate-spin" />
                            ) : (
                                <Camera className="size-6" />
                            )}
                        </span>
                    </button>
                    <input
                        ref={inputRef}
                        type="file"
                        accept="image/*"
                        className="sr-only"
                        onChange={(event) => void choose(event.target.files)}
                    />
                    <div className="flex w-full flex-col items-center gap-3 sm:items-start">
                        <div className="text-center sm:text-left">
                            <p className="font-medium">{user.name}</p>
                            <p className="text-sm text-muted-foreground">
                                {preparing
                                    ? 'Preparing your photo…'
                                    : file
                                      ? 'Looks good? Save it to update everywhere.'
                                      : user.avatar
                                        ? 'Click the photo to change it.'
                                        : 'No photo yet — your initials are shown instead.'}
                            </p>
                        </div>
                        <div className="flex flex-wrap justify-center gap-2">
                            {file ? (
                                <>
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={save}
                                        disabled={busy}
                                    >
                                        {uploading ? (
                                            <LoaderCircle className="animate-spin" />
                                        ) : (
                                            <ImageUp />
                                        )}
                                        {uploading ? 'Saving…' : 'Save photo'}
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={reset}
                                        disabled={uploading}
                                    >
                                        Cancel
                                    </Button>
                                </>
                            ) : (
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={() => inputRef.current?.click()}
                                    disabled={busy}
                                >
                                    <ImageUp />
                                    {user.avatar
                                        ? 'Change photo'
                                        : 'Upload photo'}
                                </Button>
                            )}
                            {user.avatar && !file && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={remove}
                                    disabled={busy}
                                >
                                    <Trash2 />
                                    Remove
                                </Button>
                            )}
                        </div>
                        {progress !== null && (
                            <div className="h-1.5 w-full max-w-60 overflow-hidden rounded-full bg-muted">
                                <div
                                    className="h-full bg-primary transition-all"
                                    style={{ width: `${progress}%` }}
                                />
                            </div>
                        )}
                        <InputError message={error ?? undefined} />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
