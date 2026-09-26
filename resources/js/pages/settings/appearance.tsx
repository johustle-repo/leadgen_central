import { Head } from '@inertiajs/react';
import { Palette, Sparkles, SunMoon } from 'lucide-react';
import { AccentPicker } from '@/components/accent-picker';
import AppearanceTabs from '@/components/appearance-tabs';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title="Appearance settings" />

            <h1 className="sr-only">Appearance settings</h1>

            <Card>
                <CardHeader>
                    <div className="flex items-center gap-3">
                        <div className="rounded-lg bg-primary/10 p-2 text-primary">
                            <SunMoon className="size-5" />
                        </div>
                        <div>
                            <CardTitle>Theme</CardTitle>
                            <CardDescription>
                                Choose light, dark, or follow your device.
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <AppearanceTabs />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div className="flex items-center gap-3">
                        <div className="rounded-lg bg-primary/10 p-2 text-primary">
                            <Palette className="size-5" />
                        </div>
                        <div>
                            <CardTitle>Accent colour</CardTitle>
                            <CardDescription>
                                Colours buttons, links, highlights and the
                                active menu item. Saved on this device.
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="space-y-5">
                    <AccentPicker />
                    <div className="flex flex-wrap items-center gap-3 rounded-xl border border-dashed p-4">
                        <span className="text-xs text-muted-foreground">
                            Preview
                        </span>
                        <Button size="sm">
                            <Sparkles />
                            Primary action
                        </Button>
                        <Button size="sm" variant="outline">
                            Secondary
                        </Button>
                        <span className="rounded-full bg-primary/15 px-2.5 py-0.5 text-xs font-medium text-primary">
                            Highlight
                        </span>
                        <a
                            href="#accent"
                            className="text-sm text-primary underline-offset-4 hover:underline"
                        >
                            Link
                        </a>
                    </div>
                </CardContent>
            </Card>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Appearance settings',
            href: editAppearance(),
        },
    ],
};
