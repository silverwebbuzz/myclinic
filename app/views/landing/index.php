<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eClinicPro — The clinic OS doctors love</title>
    <meta name="description" content="Run your clinic on eClinicPro — patients, prescriptions, schedule, billing, all in one place. Free 14-day trial.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        .bg-mesh {
            background-image:
                radial-gradient(at 0% 0%, rgba(15,155,110,0.10) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(15,155,110,0.06) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(15,155,110,0.08) 0px, transparent 50%);
        }
        .logo-grad {
            background: linear-gradient(135deg, #0F9B6E, #34D399);
            -webkit-background-clip: text; background-clip: text;
            color: transparent;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">

    <!-- Top bar -->
    <header class="border-b border-slate-200 bg-white/70 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <a href="/" class="flex items-center gap-2">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600 text-base font-bold text-white">e</span>
                <span class="text-lg font-semibold">e<span class="logo-grad">ClinicPro</span></span>
            </a>
            <nav class="flex items-center gap-3 text-sm">
                <a href="https://eclinicpro.com" class="hidden text-slate-600 hover:text-slate-900 sm:inline">About</a>
                <a href="https://eclinicpro.com/pricing" class="hidden text-slate-600 hover:text-slate-900 sm:inline">Pricing</a>
                <a href="/login" class="rounded-lg border border-slate-200 bg-white px-4 py-2 font-medium hover:border-slate-400">Sign in</a>
            </nav>
        </div>
    </header>

    <!-- Hero -->
    <main class="bg-mesh">
        <div class="mx-auto max-w-6xl px-6 pt-16 pb-12 sm:pt-24 sm:pb-20">
            <div class="grid items-center gap-12 lg:grid-cols-2">

                <!-- Left: copy + CTAs -->
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        India's clinic operating system
                    </div>
                    <h1 class="mt-5 text-4xl font-bold leading-tight tracking-tight text-slate-900 sm:text-5xl">
                        Run your clinic the way<br>
                        you'd actually <span class="logo-grad">design it.</span>
                    </h1>
                    <p class="mt-5 max-w-lg text-lg leading-relaxed text-slate-600">
                        Patient records, prescriptions, appointments, billing — all in one place. Built by people who've worked in clinics, not someone who's never seen one.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <a href="/register"
                           class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-6 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 hover:shadow-md">
                            Register your clinic
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </a>
                        <a href="/login"
                           class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-6 py-3.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400">
                            Sign in
                        </a>
                    </div>

                    <p class="mt-4 text-xs text-slate-500">
                        🎁 14-day free trial · No credit card needed · Cancel anytime
                    </p>
                </div>

                <!-- Right: card stack mock -->
                <div class="relative hidden lg:block">
                    <div class="relative aspect-[5/6]">
                        <!-- Background card -->
                        <div class="absolute inset-x-4 top-8 h-full rounded-2xl border border-slate-200 bg-white shadow-xl"></div>
                        <!-- Foreground card with mock content -->
                        <div class="absolute inset-x-0 top-0 rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Today's queue</div>
                                    <div class="mt-0.5 text-lg font-bold">12 patients</div>
                                </div>
                                <div class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">3 waiting</div>
                            </div>
                            <ul class="mt-4 space-y-3">
                                <li class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50 p-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-amber-100 text-sm font-bold text-amber-800">RM</div>
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-semibold">Riya Mehta</div>
                                        <div class="text-xs text-slate-500">Fever · Token 04</div>
                                    </div>
                                    <span class="rounded-full bg-emerald-500 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-white">Now</span>
                                </li>
                                <li class="flex items-center gap-3 rounded-xl border border-slate-100 p-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-800">AK</div>
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-semibold">Arjun Khanna</div>
                                        <div class="text-xs text-slate-500">Follow-up · Token 05</div>
                                    </div>
                                    <span class="text-xs text-slate-400">3 min</span>
                                </li>
                                <li class="flex items-center gap-3 rounded-xl border border-slate-100 p-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-rose-100 text-sm font-bold text-rose-800">SP</div>
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-semibold">Sneha Patel</div>
                                        <div class="text-xs text-slate-500">New consult · Token 06</div>
                                    </div>
                                    <span class="text-xs text-slate-400">8 min</span>
                                </li>
                            </ul>
                            <div class="mt-4 grid grid-cols-2 gap-2 border-t border-slate-100 pt-4 text-center">
                                <div class="rounded-lg bg-slate-50 py-2">
                                    <div class="text-base font-bold text-slate-900">₹14,200</div>
                                    <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Today's revenue</div>
                                </div>
                                <div class="rounded-lg bg-slate-50 py-2">
                                    <div class="text-base font-bold text-slate-900">9 / 12</div>
                                    <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Completed</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3-way doors -->
        <div class="border-t border-slate-200 bg-white">
            <div class="mx-auto max-w-6xl px-6 py-12">
                <h2 class="text-center text-sm font-semibold uppercase tracking-wider text-slate-500">Choose your path</h2>
                <div class="mt-6 grid gap-4 sm:grid-cols-3">

                    <!-- Doctor sign in -->
                    <a href="/login"
                       class="group rounded-2xl border border-slate-200 bg-white p-6 transition hover:-translate-y-0.5 hover:border-emerald-400 hover:shadow-lg">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 transition group-hover:bg-emerald-100">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                        </div>
                        <h3 class="mt-4 text-lg font-bold text-slate-900">Already with us?</h3>
                        <p class="mt-1 text-sm text-slate-600">Sign in with your email + password.</p>
                        <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-emerald-700">
                            Sign in →
                        </span>
                    </a>

                    <!-- Doctor OTP sign in -->
                    <a href="/doctor/login"
                       class="group rounded-2xl border border-slate-200 bg-white p-6 transition hover:-translate-y-0.5 hover:border-emerald-400 hover:shadow-lg">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-50 text-sky-600 transition group-hover:bg-sky-100">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12" y2="18"/></svg>
                        </div>
                        <h3 class="mt-4 text-lg font-bold text-slate-900">Claimed your clinic?</h3>
                        <p class="mt-1 text-sm text-slate-600">Sign in with your phone — we'll text a code.</p>
                        <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-sky-700">
                            OTP sign in →
                        </span>
                    </a>

                    <!-- New registration -->
                    <a href="/register"
                       class="group rounded-2xl border-2 border-emerald-500 bg-emerald-50/30 p-6 transition hover:-translate-y-0.5 hover:border-emerald-600 hover:shadow-lg">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-600 text-white">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        </div>
                        <h3 class="mt-4 text-lg font-bold text-slate-900">New to eClinicPro?</h3>
                        <p class="mt-1 text-sm text-slate-600">Set up your clinic in under 5 minutes.</p>
                        <span class="mt-4 inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white">
                            Register now →
                        </span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Feature strip -->
        <div class="border-t border-slate-200 bg-slate-50">
            <div class="mx-auto max-w-6xl px-6 py-12">
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <div class="text-2xl">📋</div>
                        <h4 class="mt-2 font-bold text-slate-900">Patient records</h4>
                        <p class="mt-1 text-sm text-slate-600">EMR, history, prescriptions — searchable and shareable.</p>
                    </div>
                    <div>
                        <div class="text-2xl">📅</div>
                        <h4 class="mt-2 font-bold text-slate-900">Online booking</h4>
                        <p class="mt-1 text-sm text-slate-600">Patients book themselves. Token queue. WhatsApp reminders.</p>
                    </div>
                    <div>
                        <div class="text-2xl">💊</div>
                        <h4 class="mt-2 font-bold text-slate-900">e-Prescription</h4>
                        <p class="mt-1 text-sm text-slate-600">Beautiful prescriptions in 30 seconds. Print, SMS, or WhatsApp.</p>
                    </div>
                    <div>
                        <div class="text-2xl">💸</div>
                        <h4 class="mt-2 font-bold text-slate-900">Billing &amp; reports</h4>
                        <p class="mt-1 text-sm text-slate-600">GST-ready invoices. Daily, weekly, monthly insights.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-6 py-6 text-sm text-slate-500">
            <div>© <?= date('Y') ?> eClinicPro · Made in India</div>
            <div class="flex gap-4">
                <a href="https://eclinicpro.com/security" class="hover:text-slate-900">Security</a>
                <a href="https://eclinicpro.com/find-a-doctor" class="hover:text-slate-900">Find a doctor</a>
                <a href="mailto:hello@eclinicpro.com" class="hover:text-slate-900">Contact</a>
            </div>
        </div>
    </footer>
</body>
</html>
