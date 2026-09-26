import { Form, router } from '@inertiajs/react';
import { Camera, ImageUp, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
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

/** Mirrors AvatarUpdateRequest. */
const MAX_BYTES = 2 * 1024 * 1024;

export function ProfilePhotoCard({ user }: { user: User }) {
    const getInitials = useInitials();
    const inputRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [clientError, setClientError] = useState<string | null>(null);
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
    };

    const choose = (files: FileList | null) => {
        const chosen = files?.[0] ?? null;
        setClientError(null);

        if (chosen && chosen.size > MAX_BYTES) {
            setClientError('Choose a photo under 2 MB.');
            reset();

            return;
        }

        setFile(chosen);
    };

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
                            Shown in the sidebar and to your team. JPG, PNG or
                            WEBP, up to 2 MB.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <Form
                    {...update.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={reset}
                    className="flex flex-col items-center gap-5 sm:flex-row sm:items-center"
                >
                    {({ errors, processing, progress }) => (
                        <>
                            <button
                                type="button"
                                onClick={() => inputRef.current?.click()}
                                className="group relative shrink-0 rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                aria-label="Choose a new profile picture"
                            >
                                <Avatar className="size-24 overflow-hidden rounded-full ring-4 ring-primary/20 ring-offset-2 ring-offset-card">
                                    <AvatarImage
                                        src={
                                            preview ?? user.avatar ?? undefined
                                        }
                                        alt=""
                                        className="object-cover"
                                    />
                                    <AvatarFallback className="bg-primary/10 text-2xl font-semibold text-primary">
                                        {getInitials(user.name)}
                                    </AvatarFallback>
                                </Avatar>
                                <span className="absolute inset-0 flex items-center justify-center rounded-full bg-black/45 text-white opacity-0 transition-opacity group-hover:opacity-100">
                                    <Camera className="size-6" />
                                </span>
                            </button>
                            <input
                                ref={inputRef}
                                type="file"
                                name="avatar"
                                accept="image/jpeg,image/png,image/webp"
                                className="sr-only"
                                onChange={(event) => choose(event.target.files)}
                            />
                            <div className="flex w-full flex-col items-center gap-3 sm:items-start">
                                <div className="text-center sm:text-left">
                                    <p className="font-medium">{user.name}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {file
                                            ? `${file.name} · ready to save`
                                            : user.avatar
                                              ? 'Click the photo to change it.'
                                              : 'No photo yet — your initials are shown instead.'}
                                    </p>
                                </div>
                                <div className="flex flex-wrap justify-center gap-2">
                                    {file ? (
                                        <>
                                            <Button
                                                type="submit"
                                                size="sm"
                                                disabled={processing}
                                            >
                                                <ImageUp />
                                                {processing
                                                    ? 'Saving…'
                                                    : 'Save photo'}
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                onClick={reset}
                                                disabled={processing}
                                            >
                                                Cancel
                                            </Button>
                                        </>
                                    ) : (
                                        <Button
                                            type="button"
                                            size="sm"
                                            onClick={() =>
                                                inputRef.current?.click()
                                            }
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
                                            onClick={() =>
                                                router.delete(destroy.url(), {
                                                    preserveScroll: true,
                                                })
                                            }
                                        >
                                            <Trash2 />
                                            Remove
                                        </Button>
                                    )}
                                </div>
                                {progress && (
                                    <div className="h-1.5 w-full max-w-60 overflow-hidden rounded-full bg-muted">
                                        <div
                                            className="h-full bg-primary transition-all"
                                            style={{
                                                width: `${progress.percentage}%`,
                                            }}
                                        />
                                    </div>
                                )}
                                <InputError
                                    message={clientError ?? errors.avatar}
                                />
                            </div>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}
