<?php
/** /admin/locations — states & cities catalog (super-admin). */
$tab = $tab ?? 'states';
$statesJson = htmlspecialchars(json_encode($states ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
$citiesJson = htmlspecialchars(json_encode($cities ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>States &amp; Cities — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100"
      x-data="ecpLocationsAdmin(<?= $statesJson ?>, <?= $citiesJson ?>, '<?= htmlspecialchars($tab, ENT_QUOTES) ?>')">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-6xl space-y-6 p-6">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">States &amp; Cities</h1>
                <p class="mt-1 text-sm text-slate-500">Catalog used by doctor “Listed on eClinicPro” location pickers.</p>
            </div>
            <div class="inline-flex rounded-lg border border-slate-200 bg-white p-1 text-sm">
                <button type="button" @click="tab = 'states'"
                        :class="tab === 'states' ? 'bg-slate-900 text-white' : 'text-slate-600'"
                        class="rounded-md px-3 py-1.5 font-medium">States</button>
                <button type="button" @click="tab = 'cities'"
                        :class="tab === 'cities' ? 'bg-slate-900 text-white' : 'text-slate-600'"
                        class="rounded-md px-3 py-1.5 font-medium">Cities</button>
            </div>
        </div>

        <?php if (!empty($message)): ?>
        <div class="rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
            <?= htmlspecialchars(str_replace('_', ' ', (string) $message)) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($tableMissing)): ?>
        <div class="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Tables are missing. Run
            <code class="rounded bg-amber-100 px-1">app/database/patches/2026_07_10_directory_states_cities.sql</code>
            on this database (and production) first.
        </div>
        <?php endif; ?>

        <!-- ===== States ===== -->
        <section x-show="tab === 'states'" x-cloak class="space-y-4">
            <form method="post" action="/admin/locations/states" class="space-y-3 rounded-xl border bg-white p-5 shadow-sm">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="id" :value="editingState ? editingState.id : ''">
                <h2 class="font-semibold" x-text="editingState ? ('Edit state: ' + editingState.name) : 'Add a state'"></h2>
                <div class="flex flex-wrap items-end gap-3">
                    <label class="min-w-[12rem] flex-1 text-sm">
                        <span class="text-slate-600">Name *</span>
                        <input type="text" name="name" required maxlength="80"
                               :value="editingState ? editingState.name : ''"
                               placeholder="Gujarat"
                               class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                    </label>
                    <label class="w-20 shrink-0 text-sm">
                        <span class="text-slate-600">Sort</span>
                        <input type="number" name="sort_order" min="0" max="9999"
                               :value="editingState ? editingState.sort_order : 100"
                               class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                    </label>
                    <label class="mb-1.5 flex shrink-0 items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" value="1"
                               :checked="editingState ? Number(editingState.is_active) === 1 : true">
                        Active
                    </label>
                    <button type="submit" class="shrink-0 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                            x-text="editingState ? 'Save state' : 'Add state'"></button>
                    <button type="button" x-show="editingState" @click="editingState = null"
                            class="shrink-0 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                </div>
            </form>

            <div class="flex flex-wrap items-center gap-3">
                <label class="text-sm text-slate-600">
                    Status
                    <select x-model="stateFilterStatus" class="ml-2 rounded border px-2 py-1.5 text-sm">
                        <option value="">All</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </label>
            </div>

            <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2">Name</th>
                            <th class="px-4 py-2">Cities</th>
                            <th class="px-4 py-2">Sort</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="s in filteredStates" :key="s.id">
                            <tr class="border-t">
                                <td class="px-4 py-2 font-medium" x-text="s.name"></td>
                                <td class="px-4 py-2" x-text="cityCountForState(s.id)"></td>
                                <td class="px-4 py-2" x-text="s.sort_order"></td>
                                <td class="px-4 py-2">
                                    <span class="rounded-full px-2 py-0.5 text-xs"
                                          :class="Number(s.is_active) === 1 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                                          x-text="Number(s.is_active) === 1 ? 'Active' : 'Inactive'"></span>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-2">
                                        <button type="button"
                                                class="rounded-lg border border-emerald-600 bg-white px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50"
                                                @click="editingState = s; tab = 'states'">Edit</button>
                                        <form method="post" :action="'/admin/locations/states/' + s.id + '/toggle'" class="inline">
                                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                            <button type="submit"
                                                    class="rounded-lg border px-3 py-1.5 text-xs font-medium"
                                                    :class="Number(s.is_active) === 1
                                                        ? 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                                                        : 'border-emerald-600 bg-emerald-50 text-emerald-800 hover:bg-emerald-100'"
                                                    x-text="Number(s.is_active) === 1 ? 'Inactive' : 'Active'"></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="filteredStates.length === 0"><td colspan="5" class="px-4 py-6 text-center text-slate-500">No states match.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ===== Cities ===== -->
        <section x-show="tab === 'cities'" x-cloak class="space-y-4">
            <form method="post" action="/admin/locations/cities" class="space-y-3 rounded-xl border bg-white p-5 shadow-sm">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="id" :value="editingCity ? editingCity.id : ''">
                <h2 class="font-semibold" x-text="editingCity ? ('Edit city: ' + editingCity.name) : 'Add a city'"></h2>
                <div class="flex flex-wrap items-end gap-3">
                    <label class="min-w-[10rem] flex-1 text-sm">
                        <span class="text-slate-600">State *</span>
                        <select name="state_id" required class="mt-1 w-full rounded border px-2 py-1.5 text-sm"
                                :value="editingCity ? editingCity.state_id : ''">
                            <option value="">Select state…</option>
                            <template x-for="s in states" :key="'opt-' + s.id">
                                <option :value="s.id" x-text="s.name" :selected="editingCity && Number(editingCity.state_id) === Number(s.id)"></option>
                            </template>
                        </select>
                    </label>
                    <label class="min-w-[10rem] flex-1 text-sm">
                        <span class="text-slate-600">City name *</span>
                        <input type="text" name="name" required maxlength="80"
                               :value="editingCity ? editingCity.name : ''"
                               placeholder="Ahmedabad"
                               class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                    </label>
                    <label class="w-20 shrink-0 text-sm">
                        <span class="text-slate-600">Sort</span>
                        <input type="number" name="sort_order" min="0" max="9999"
                               :value="editingCity ? editingCity.sort_order : 100"
                               class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                    </label>
                    <label class="mb-1.5 flex shrink-0 items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" value="1"
                               :checked="editingCity ? Number(editingCity.is_active) === 1 : true">
                        Active
                    </label>
                    <button type="submit" class="shrink-0 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                            x-text="editingCity ? 'Save city' : 'Add city'"></button>
                    <button type="button" x-show="editingCity" @click="editingCity = null"
                            class="shrink-0 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                </div>
            </form>

            <div class="flex flex-wrap items-center gap-3">
                <label class="text-sm text-slate-600">
                    Filter by state
                    <select x-model="cityFilterState" class="ml-2 rounded border px-2 py-1.5 text-sm">
                        <option value="">All states</option>
                        <template x-for="s in states" :key="'f-' + s.id">
                            <option :value="String(s.id)" x-text="s.name"></option>
                        </template>
                    </select>
                </label>
                <label class="text-sm text-slate-600">
                    Status
                    <select x-model="cityFilterStatus" class="ml-2 rounded border px-2 py-1.5 text-sm">
                        <option value="">All</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </label>
                <input type="search" x-model="citySearch" placeholder="Search cities…"
                       class="rounded border px-2 py-1.5 text-sm">
            </div>

            <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2">City</th>
                            <th class="px-4 py-2">State</th>
                            <th class="px-4 py-2">Sort</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="c in filteredCities" :key="c.id">
                            <tr class="border-t">
                                <td class="px-4 py-2 font-medium" x-text="c.name"></td>
                                <td class="px-4 py-2" x-text="c.state_name || c.state || '—'"></td>
                                <td class="px-4 py-2" x-text="c.sort_order"></td>
                                <td class="px-4 py-2">
                                    <span class="rounded-full px-2 py-0.5 text-xs"
                                          :class="Number(c.is_active) === 1 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                                          x-text="Number(c.is_active) === 1 ? 'Active' : 'Inactive'"></span>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-2">
                                        <button type="button"
                                                class="rounded-lg border border-emerald-600 bg-white px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50"
                                                @click="editingCity = c; tab = 'cities'">Edit</button>
                                        <form method="post" :action="'/admin/locations/cities/' + c.id + '/toggle'" class="inline">
                                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                            <button type="submit"
                                                    class="rounded-lg border px-3 py-1.5 text-xs font-medium"
                                                    :class="Number(c.is_active) === 1
                                                        ? 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                                                        : 'border-emerald-600 bg-emerald-50 text-emerald-800 hover:bg-emerald-100'"
                                                    x-text="Number(c.is_active) === 1 ? 'Inactive' : 'Active'"></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="filteredCities.length === 0"><td colspan="5" class="px-4 py-6 text-center text-slate-500">No cities match.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
<script>
function ecpLocationsAdmin(states, cities, initialTab) {
  return {
    tab: initialTab || 'states',
    states: Array.isArray(states) ? states : [],
    cities: Array.isArray(cities) ? cities : [],
    editingState: null,
    editingCity: null,
    stateFilterStatus: '',
    cityFilterState: '',
    cityFilterStatus: '',
    citySearch: '',
    cityCountForState(stateId) {
      return this.cities.filter((c) => String(c.state_id) === String(stateId)).length;
    },
    get filteredStates() {
      if (this.stateFilterStatus === '') return this.states;
      return this.states.filter((s) => String(Number(s.is_active)) === String(this.stateFilterStatus));
    },
    get filteredCities() {
      const q = this.citySearch.trim().toLowerCase();
      return this.cities.filter((c) => {
        if (this.cityFilterState && String(c.state_id) !== String(this.cityFilterState)) return false;
        if (this.cityFilterStatus !== '' && String(Number(c.is_active)) !== String(this.cityFilterStatus)) return false;
        if (!q) return true;
        return String(c.name || '').toLowerCase().includes(q)
            || String(c.state_name || c.state || '').toLowerCase().includes(q);
      });
    },
  };
}
</script>
</html>
