<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="30">
    <title>Maintenance — LeadGen Central</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-[#07111f] font-sans text-white antialiased">
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden px-5 py-10">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_15%_10%,rgba(34,211,238,.16),transparent_35%),radial-gradient(circle_at_85%_85%,rgba(99,102,241,.16),transparent_32%)]"></div>
        <div class="pointer-events-none absolute inset-0 [background-image:linear-gradient(rgba(148,163,184,.10)_1px,transparent_1px),linear-gradient(90deg,rgba(148,163,184,.10)_1px,transparent_1px)] [background-size:52px_52px] opacity-25"></div>

        <div class="relative w-full max-w-md">
            <div class="rounded-3xl border border-white/10 bg-white/[.045] p-8 text-center shadow-2xl shadow-black/40 sm:p-10">
                <div class="mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl border border-white/10 bg-white/5">
                    <img src="/logo.png" alt="LeadGen Central" class="size-11 object-contain">
                </div>

                <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-cyan-300/20 bg-cyan-300/8 px-3 py-1.5 text-xs font-medium text-cyan-200">
                    <span class="relative flex size-1.5">
                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-cyan-300 opacity-75"></span>
                        <span class="relative inline-flex size-1.5 rounded-full bg-cyan-300"></span>
                    </span>
                    Scheduled maintenance
                </div>

                <h1 class="text-2xl font-semibold tracking-tight text-white sm:text-3xl">
                    We&rsquo;ll be right back
                </h1>
                <p class="mt-3 text-sm leading-6 text-slate-400">
                    LeadGen Central is undergoing brief maintenance. This
                    usually only takes a few minutes &mdash; this page checks
                    again automatically, no need to refresh.
                </p>
            </div>

            <p class="mt-6 text-center text-xs tracking-wide text-slate-500 uppercase">
                LeadGen Central &middot; Lead Operations
            </p>
        </div>
    </div>
</body>
</html>
