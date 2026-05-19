<header class="border-b bg-slate-900 text-white">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-3">
        <a href="/admin/dashboard" class="font-semibold">ManageClinic Admin</a>
        <nav class="flex gap-4 text-sm">
            <a href="/admin/dashboard" class="hover:underline">Dashboard</a>
            <a href="/admin/clinics" class="hover:underline">Clinics</a>
            <a href="/admin/reviews" class="hover:underline">Reviews</a>
            <form method="post" action="/admin/logout" class="inline">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
                <button type="submit" class="hover:underline">Log out</button>
            </form>
        </nav>
    </div>
</header>
