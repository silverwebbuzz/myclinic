<?php
// =====================================================================
// find-a-doctor.php — public doctor directory (search + filters)
//
// Architecture (rewritten 2026 to handle 100k+ rows):
//   - The first page of results is rendered SERVER-SIDE from URL query
//     params. Visitor sees real doctors immediately (good SEO + 80 KB HTML).
//   - All subsequent filter changes / pagination fetch /api/search_doctors
//     and replace the list client-side.
//   - The autocomplete fetches /api/locations as the user types.
//   - We ship a tiny bootstrap blob (specialties + first page only) instead
//     of the entire directory.
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/search_doctors_query.php';

$pageTitle = 'Find a Doctor — eClinicPro';
$metaDesc  = 'Search verified clinicians across India — see availability, fees and ratings before you book.';
$activePage = 'find';

// Seed data only used for UI chrome (countries list, specialty taxonomy).
// Doctors + locations now come from the live DB via the search API.
$seed = require __DIR__ . '/partials/find-doctor-data.php';

// ---- Parse search filters from the URL (for shareable links / SEO) ----
$initialFilters = [
    'q'          => $_GET['q']        ?? '',
    'country'    => $_GET['country']  ?? 'IN',
    'state'      => $_GET['state']    ?? '',
    'city'       => $_GET['city']     ?? '',
    'area'       => $_GET['area']     ?? '',
    'spec'       => $_GET['spec']     ?? 'all',  // 'all' means no filter
    'min_rating' => (float) ($_GET['min_rating'] ?? 0),
    'sort'       => $_GET['sort']     ?? 'relevance',
    'page'       => 1,
    'per_page'   => 20,
];

// ---- SSR: load just the first page of results matching those filters ----
$searchInput = $initialFilters;
if ($searchInput['spec'] === 'all') $searchInput['spec'] = '';
$searchResult = ecp_search_doctors($searchInput);
$firstPage    = $searchResult['items']        ?? [];
$totalMatches = $searchResult['total']        ?? count($firstPage);
$hasMore      = $searchResult['has_more']     ?? false;

// Real DB total (unaffected by filters) — for the hero "X+ doctors" copy.
$totalDoctors = ecp_directory_doctor_count();
if ($totalDoctors === 0) $totalDoctors = $totalMatches;

require __DIR__ . '/partials/header.php';
?>

<style>
[x-cloak] { display: none !important; }
/* Belt-and-suspenders: hide the panel by default via raw CSS so it never
   flashes as un-styled HTML if Alpine is slow to load. */
.fd-spec-panel { display: none; }
.fd-spec-panel.is-open { display: grid; }
</style>

<div x-data="findDoctor()" x-init="init()" x-cloak>

<section class="fd-hero">
    <div class="wrap-wide">
        <h1>Find a doctor you can <span class="grad">actually trust.</span></h1>
        <p class="lede">Search <?= ecp_num($totalDoctors) ?>+ verified clinicians across India, the US, UK and more — see real availability, fees and ratings before you book.</p>

        <!-- Country pill -->
        <div class="fd-country-row" style="position: relative;">
            <span class="fd-country">
                🌐
                <span class="flag" x-text="currentCountry().flag"></span>
                <span class="label">Showing doctors in <span x-text="currentCountry().name"></span></span>
                <button type="button" class="change" @click="countryOpen = !countryOpen">Change</button>
            </span>

            <div class="fd-cmenu" x-show="countryOpen" @click.outside="countryOpen = false" x-transition.opacity style="left: 50%; transform: translateX(-50%);">
                <template x-for="c in countries" :key="c.code">
                    <div class="item" :class="c.code === country ? 'active' : ''"
                         @click="country = c.code; locValue = null; loc = ''; countryOpen = false; page = 1">
                        <span style="font-size: 16px;" x-text="c.flag"></span>
                        <span x-text="c.name"></span>
                        <template x-if="c.code === country">
                            <span class="check">✓</span>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <!-- Search bar -->
        <div class="fd-search">
            <label class="fd-sfield">
                <span class="ico">🔍</span>
                <div class="col">
                    <div class="lbl">Doctor / Hospital</div>
                    <input type="text" x-model="q" placeholder="e.g. Dr. Mehta or Apollo Hospitals">
                </div>
            </label>

            <label class="fd-sfield" style="position: relative;" @click.outside="acOpen = false">
                <span class="ico">📍</span>
                <div class="col">
                    <div class="lbl">Area, city, state or country</div>
                    <input type="text" x-model="loc"
                           @input="acOpen = true"
                           @focus="acOpen = true; fetchLocations()"
                           placeholder="Type a place — Bandra, Mumbai, Maharashtra…">
                </div>

                <div class="fd-ac" x-show="acOpen" @mousedown.prevent x-transition.opacity>
                    <div class="fd-ac-item use-loc"
                         @click="loc = ''; locValue = null; acOpen = false">
                        <div class="ic">📌</div>
                        <div>
                            <div class="nm">Use my precise location</div>
                            <div class="sb">Allow browser to share where you are</div>
                        </div>
                    </div>

                    <template x-if="filteredLocations().length === 0">
                        <div class="fd-ac-item" style="color: var(--mute);">
                            <div class="ic">·</div>
                            <div><div class="nm">No matches</div><div class="sb">Try a city or area name</div></div>
                        </div>
                    </template>

                    <template x-for="(item, i) in filteredLocations()" :key="i">
                        <div class="fd-ac-item"
                             @click="loc = item.label; locValue = item.value; if (item.value.country && item.value.country !== country) { country = item.value.country; } acOpen = false">
                            <div class="ic" x-text="item.flag"></div>
                            <div>
                                <div class="nm" x-text="item.label"></div>
                                <template x-if="item.sub">
                                    <div class="sb" x-text="item.sub"></div>
                                </template>
                            </div>
                            <span class="kind" x-text="item.type"></span>
                        </div>
                    </template>
                </div>
            </label>

            <button type="button" class="btn-search" aria-label="Search">→</button>
        </div>

        <!-- Specialty selector: collapsed quick row + expandable 4-group panel -->
        <div class="fd-specs" style="display:flex;align-items:center;gap:8px;">
            <button type="button" class="fd-spec" :class="spec === 'all' ? 'active' : ''" @click="spec = 'all'">
                All specialties
            </button>
            <!-- Show top ~6 most common as quick chips -->
            <template x-for="s in specialties.slice(0, 6)" :key="s.id">
                <button type="button" class="fd-spec" :class="spec === s.id ? 'active' : ''" @click="spec = s.id">
                    <span class="ic" x-text="s.icon"></span>
                    <span x-text="s.label"></span>
                </button>
            </template>
            <button type="button" class="fd-spec" :class="specPanelOpen ? 'active' : ''" @click="specPanelOpen = !specPanelOpen">
                <span x-text="specPanelOpen ? '✕ Close' : '+ All specialties'"></span>
            </button>
        </div>

        <!-- Full 5-group panel (toggled) -->
        <div class="fd-spec-panel" :class="specPanelOpen ? 'is-open' : ''">
            <template x-for="g in specialty_groups" :key="g.label">
                <div class="fd-spec-group">
                    <h4 x-text="g.label"></h4>
                    <div class="fd-spec-list">
                        <template x-for="s in g.items" :key="s.id">
                            <button type="button"
                                class="fd-spec-link"
                                :class="spec === s.id ? 'is-active' : ''"
                                @click="spec = s.id; specPanelOpen = false">
                                <span x-text="s.label"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
</section>

<section class="fd-main">
    <div class="wrap-wide">

        <!-- Result count + sort -->
        <div class="fd-bar">
            <div class="fd-count">
                <strong x-text="filteredResults().length.toLocaleString()"></strong>
                <span x-text="filteredResults().length === 1 ? 'doctor' : 'doctors'"></span>
                in <span x-text="currentCountry().name"></span>
                <template x-if="spec !== 'all'">
                    <span> · <strong x-text="specialties.find(s => s.id === spec)?.label || ''"></strong></span>
                </template>
                <template x-if="q">
                    <span> · matching "<strong x-text="q"></strong>"</span>
                </template>
            </div>

            <div class="fd-bar-actions" style="position: relative;">
                <button type="button" class="fd-chip" :class="sort !== 'relevance' ? 'has-val' : ''" @click="sortOpen = !sortOpen">
                    Sort: <span x-text="sortLabel()"></span>
                    <span class="caret"></span>
                </button>
                <div class="fd-pop" x-show="sortOpen" @click.outside="sortOpen = false" x-transition.opacity style="right: 0; left: auto;">
                    <template x-for="opt in sortOptions" :key="opt[0]">
                        <div class="row" :class="sort === opt[0] ? 'on' : ''" @click="sort = opt[0]; sortOpen = false">
                            <span class="ck" x-show="sort === opt[0]">✓</span>
                            <span class="ck" x-show="sort !== opt[0]"></span>
                            <span x-text="opt[1]"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Filter chips row -->
        <div class="fd-filters" style="margin-bottom: 18px;">

            <!-- Availability -->
            <div style="position: relative;">
                <button type="button" class="fd-chip" :class="avail !== 'any' ? 'has-val' : ''" @click="availOpen = !availOpen">
                    <span x-text="avail === 'any' ? 'Available' : ('Available: ' + avail)"></span>
                    <span class="caret"></span>
                </button>
                <div class="fd-pop" x-show="availOpen" @click.outside="availOpen = false" x-transition.opacity>
                    <template x-for="opt in [['any','Any time'],['today','Today'],['tomorrow','Today or tomorrow'],['week','Within a week']]" :key="opt[0]">
                        <div class="row" :class="avail === opt[0] ? 'on' : ''" @click="avail = opt[0]; availOpen = false">
                            <span class="ck" x-text="avail === opt[0] ? '✓' : ''"></span>
                            <span x-text="opt[1]"></span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Video -->
            <button type="button" class="fd-chip" :class="video ? 'on' : ''" @click="video = !video">
                🎥 Video consult
            </button>

            <!-- Gender -->
            <div style="position: relative;">
                <button type="button" class="fd-chip" :class="gender !== 'any' ? 'has-val' : ''" @click="genderOpen = !genderOpen">
                    <span x-text="gender === 'any' ? 'Gender' : (gender === 'F' ? 'Female doctor' : 'Male doctor')"></span>
                    <span class="caret"></span>
                </button>
                <div class="fd-pop" x-show="genderOpen" @click.outside="genderOpen = false" x-transition.opacity>
                    <template x-for="opt in [['any','Any'],['F','Female'],['M','Male']]" :key="opt[0]">
                        <div class="row" :class="gender === opt[0] ? 'on' : ''" @click="gender = opt[0]; genderOpen = false">
                            <span class="ck" x-text="gender === opt[0] ? '✓' : ''"></span>
                            <span x-text="opt[1]"></span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Rating -->
            <div style="position: relative;">
                <button type="button" class="fd-chip" :class="minRating > 0 ? 'has-val' : ''" @click="ratingOpen = !ratingOpen">
                    <span x-text="minRating === 0 ? 'Rating' : ('★ ' + minRating + '+')"></span>
                    <span class="caret"></span>
                </button>
                <div class="fd-pop" x-show="ratingOpen" @click.outside="ratingOpen = false" x-transition.opacity>
                    <template x-for="opt in [[0,'Any rating'],[4,'4.0+ stars'],[4.5,'4.5+ stars'],[4.8,'4.8+ stars']]" :key="opt[0]">
                        <div class="row" :class="minRating === opt[0] ? 'on' : ''" @click="minRating = opt[0]; ratingOpen = false">
                            <span class="ck" x-text="minRating === opt[0] ? '✓' : ''"></span>
                            <span x-text="opt[1]"></span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Distance — requires browser location permission -->
            <div style="position: relative;">
                <button type="button" class="fd-chip" :class="maxDistanceKm > 0 ? 'has-val' : ''"
                        @click="distanceOpen = !distanceOpen">
                    <span x-show="!userLoc">📍 Distance</span>
                    <span x-show="userLoc && maxDistanceKm === 0">📍 Any distance</span>
                    <span x-show="userLoc && maxDistanceKm > 0" x-text="'📍 Within ' + maxDistanceKm + ' km'"></span>
                    <span class="caret"></span>
                </button>
                <div class="fd-pop" x-show="distanceOpen" @click.outside="distanceOpen = false" x-transition.opacity>
                    <template x-if="!userLoc">
                        <div class="row" @click="requestLocation(); distanceOpen = false">
                            <span class="ck">📌</span>
                            <span>Use my location</span>
                        </div>
                    </template>
                    <template x-if="userLoc">
                        <template x-for="opt in [[0,'Any distance'],[5,'Within 5 km'],[10,'Within 10 km'],[25,'Within 25 km'],[50,'Within 50 km'],[100,'Within 100 km']]" :key="opt[0]">
                            <div class="row" :class="maxDistanceKm === opt[0] ? 'on' : ''" @click="maxDistanceKm = opt[0]; distanceOpen = false">
                                <span class="ck" x-text="maxDistanceKm === opt[0] ? '✓' : ''"></span>
                                <span x-text="opt[1]"></span>
                            </div>
                        </template>
                    </template>
                    <template x-if="userLoc">
                        <div class="row" style="border-top: 1px solid var(--line); color: var(--mute); font-size: 12px;"
                             @click="clearLocation(); distanceOpen = false">
                            <span class="ck">✕</span>
                            <span>Clear my location</span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Language -->
            <div style="position: relative;">
                <button type="button" class="fd-chip" :class="lang !== 'any' ? 'has-val' : ''" @click="langOpen = !langOpen">
                    <span x-text="lang === 'any' ? 'Language' : lang"></span>
                    <span class="caret"></span>
                </button>
                <div class="fd-pop" x-show="langOpen" @click.outside="langOpen = false" x-transition.opacity style="max-height: 300px; overflow-y: auto;">
                    <div class="row" :class="lang === 'any' ? 'on' : ''" @click="lang = 'any'; langOpen = false">
                        <span class="ck" x-text="lang === 'any' ? '✓' : ''"></span>
                        Any language
                    </div>
                    <template x-for="l in currentCountry().langs" :key="l">
                        <div class="row" :class="lang === l ? 'on' : ''" @click="lang = l; langOpen = false">
                            <span class="ck" x-text="lang === l ? '✓' : ''"></span>
                            <span x-text="l"></span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Per page -->
            <div style="position: relative;">
                <button type="button" class="fd-chip" @click="psOpen = !psOpen">
                    <span x-text="pageSize + ' / page'"></span>
                    <span class="caret"></span>
                </button>
                <div class="fd-pop" x-show="psOpen" @click.outside="psOpen = false" x-transition.opacity>
                    <template x-for="n in [10, 20, 50]" :key="n">
                        <div class="row" :class="pageSize === n ? 'on' : ''" @click="pageSize = n; psOpen = false; page = 1">
                            <span class="ck" x-text="pageSize === n ? '✓' : ''"></span>
                            <span x-text="n + ' per page'"></span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Clear -->
            <button type="button" class="fd-chip-clear" x-show="activeFilterCount() > 0" @click="clearFilters()">
                Clear all (<span x-text="activeFilterCount()"></span>)
            </button>
        </div>

        <!-- Loading shimmer — shown while a fetch is in flight -->
        <div class="fd-grid" x-show="loading && pageItems().length === 0" x-cloak>
            <template x-for="i in 5" :key="i">
                <div class="fd-card fd-card-skeleton">
                    <div class="fd-avatar fd-skel-avatar"></div>
                    <div class="fd-identity">
                        <div class="fd-skel-line fd-skel-w60"></div>
                        <div class="fd-skel-line fd-skel-w40"></div>
                        <div class="fd-skel-line fd-skel-w30"></div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Empty state -->
        <div class="fd-empty" x-show="!loading && pageItems().length === 0">
            <div class="glyph">🩺</div>
            <h3>No doctors match your filters</h3>
            <p>Try widening your search — clear a few filters or pick a different area.</p>
        </div>

        <!-- Results grid -->
        <div class="fd-grid" x-show="pageItems().length > 0"
             :class="loading ? 'fd-grid-loading' : ''">
            <template x-for="d in pageItems()" :key="d.id">
                <div class="fd-card">
                    <div class="fd-avatar" :class="'g' + (1 + (d.id % 6))"
                         x-text="d.firstInitial + d.lastInitial"></div>

                    <button type="button" class="fd-fav" :class="favs.includes(d.id) ? 'on' : ''"
                            @click="toggleFav(d.id)" aria-label="Save doctor">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 21s-7-4.5-9.5-9.5C.8 7.7 3.3 4 7 4c2 0 3.5 1 5 3 1.5-2 3-3 5-3 3.7 0 6.2 3.7 4.5 7.5C19 16.5 12 21 12 21z"/>
                        </svg>
                    </button>

                    <div class="fd-identity">
                        <div class="fd-name-row">
                            <span class="fd-name" x-text="d.name"></span>
                            <template x-if="d.verified">
                                <span class="fd-verified">✓ Verified</span>
                            </template>
                        </div>
                        <template x-if="d.hospital">
                            <div class="fd-qual" x-text="d.hospital"></div>
                        </template>
                        <template x-if="d.qual && d.years > 0">
                            <div class="fd-qual" x-text="d.qual + ' · ' + d.years + ' yrs exp'"></div>
                        </template>
                        <template x-if="!d.qual && d.years > 0">
                            <div class="fd-qual" x-text="d.years + ' yrs exp'"></div>
                        </template>
                        <template x-if="d.qual && !d.years">
                            <div class="fd-qual" x-text="d.qual"></div>
                        </template>
                        <div class="fd-spec-row">
                            <span class="fd-pill" x-text="d.specLabel"></span>
                            <template x-if="d.video">
                                <span class="fd-pill video">🎥 Video</span>
                            </template>
                            <template x-if="d.rating > 0">
                                <span class="fd-rating">
                                    <span class="star">★</span>
                                    <span style="font-weight: 600; color: var(--ink);" x-text="d.rating.toFixed(1)"></span>
                                    <span class="rv" x-text="'(' + d.reviews + ')'"></span>
                                </span>
                            </template>
                        </div>
                        <div class="fd-meta">
                            <div class="fd-meta-row">
                                <span class="mi">📍</span>
                                <span class="fd-wrap">
                                    <template x-if="d.address">
                                        <span x-text="d.address"></span>
                                    </template>
                                    <template x-if="!d.address">
                                        <span x-text="[d.area, d.city, d.state].filter(Boolean).join(', ')"></span>
                                    </template>
                                    <template x-if="distanceFor(d) !== null">
                                        <span class="fd-distance" x-text="' · ' + formatDistance(distanceFor(d))"></span>
                                    </template>
                                </span>
                            </div>
                            <template x-if="d.phone">
                                <div class="fd-meta-row">
                                    <span class="mi">📞</span>
                                    <a :href="'tel:' + d.phone" x-text="d.phone"></a>
                                </div>
                            </template>
                            <template x-if="todayHours(d)">
                                <div class="fd-meta-row fd-hours-row">
                                    <span class="mi">🕒</span>
                                    <div class="fd-hours">
                                        <button type="button" class="fd-hours-toggle"
                                                @click="hoursOpen[d.id] = !hoursOpen[d.id]"
                                                :aria-expanded="!!hoursOpen[d.id]">
                                            <span x-text="todayHours(d)"></span>
                                            <template x-if="d.opening_hours && d.opening_hours.length > 1">
                                                <span class="caret" :class="hoursOpen[d.id] ? 'open' : ''">▾</span>
                                            </template>
                                        </button>
                                        <template x-if="hoursOpen[d.id] && d.opening_hours">
                                            <ul class="fd-hours-list">
                                                <template x-for="line in d.opening_hours" :key="line">
                                                    <li x-text="line"></li>
                                                </template>
                                            </ul>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="fd-book">
                        <span class="fd-slot" :class="slotClass(d.next.when)">
                            <span class="dot"></span>
                            <span x-text="d.next.label + (d.next.sub ? ' · ' + d.next.sub : '')"></span>
                        </span>
                        <template x-if="d.fee > 0">
                            <div class="fd-price">
                                Consultation
                                <strong x-text="formatFee(d.currency, d.fee)"></strong>
                            </div>
                        </template>
                        <div class="fd-actions">
                            <template x-if="d.gmaps_url">
                                <a :href="d.gmaps_url" target="_blank" rel="noopener" class="fd-btn">View on map</a>
                            </template>
                            <template x-if="!d.gmaps_url && d.website">
                                <a :href="d.website" target="_blank" rel="noopener" class="fd-btn">Website</a>
                            </template>
                            <template x-if="!d.gmaps_url && !d.website">
                                <button type="button" class="fd-btn">View profile</button>
                            </template>
                            <!-- Book is always available; Call only when we have a number. -->
                            <button type="button" class="fd-btn primary"
                                    @click="bookDoctor(d)">📅 Book</button>
                            <template x-if="d.phone">
                                <a :href="'tel:' + d.phone" class="fd-btn"
                                   @click="trackCall(d)">📞 Call</a>
                            </template>
                        </div>

                        <!-- Claim link — only on unclaimed listings -->
                        <template x-if="!d.is_claimed">
                            <button type="button" class="fd-claim-link"
                                    @click="claimListing(d)">
                                Is this your clinic? <strong>Claim it</strong>
                            </button>
                        </template>
                        <template x-if="d.is_claimed">
                            <div class="fd-claim-link verified">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                Verified by doctor
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        <!-- "Not listed?" CTA — shown at the bottom of the results -->
        <div class="fd-listme" x-show="pageItems().length > 0">
            <div class="fd-listme-inner">
                <div>
                    <h3>Are you a doctor not listed here?</h3>
                    <p>Add your clinic in a minute — we'll review and get back to you.</p>
                </div>
                <button type="button" class="fd-listme-btn"
                        @click="window.ecpClaim && window.ecpClaim.open('new_listing')">
                    List my clinic
                </button>
            </div>
        </div>

        <!-- Pagination -->
        <div class="fd-pager" x-show="totalPages() > 1">
            <button type="button" class="fd-pg" :disabled="page === 1" @click="goPage(page - 1)">← Prev</button>
            <template x-for="p in pageNumbers()" :key="p + '-' + Math.random()">
                <template x-if="p === '…'">
                    <span class="fd-pg-ellipsis">…</span>
                </template>
                <template x-if="p !== '…'">
                    <button type="button" class="fd-pg" :class="p === page ? 'is-active' : ''" @click="goPage(p)" x-text="p"></button>
                </template>
            </template>
            <button type="button" class="fd-pg" :disabled="page === totalPages()" @click="goPage(page + 1)">Next →</button>
        </div>

        <div class="fd-page-info" x-show="filteredResults().length > 0">
            Showing
            <strong x-text="((page - 1) * pageSize) + 1"></strong>–<strong x-text="Math.min(page * pageSize, filteredResults().length)"></strong>
            of <strong x-text="filteredResults().length.toLocaleString()"></strong>
        </div>

    </div>
</section>

</div>

<script>
// Bootstrap payload — kept tiny. Only the UI taxonomy + the first
// server-rendered page of doctors. Everything else loads on demand.
window.FD_DATA = {
    countries:        <?= json_encode($seed['countries'],        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    specialties:      <?= json_encode($seed['specialties'],      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    specialty_groups: <?= json_encode($seed['specialty_groups'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    firstPage:        <?= json_encode($firstPage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    initialTotal:     <?= (int) $totalMatches ?>,
    initialHasMore:   <?= $hasMore ? 'true' : 'false' ?>,
    initialFilters:   <?= json_encode($initialFilters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
};

function findDoctor() {
    return {
        // ----- taxonomy (small, ships in HTML) -----
        countries: window.FD_DATA.countries,
        specialties: window.FD_DATA.specialties,
        specialty_groups: window.FD_DATA.specialty_groups || [],

        // ----- live results (replaced on every fetch) -----
        doctors: window.FD_DATA.firstPage,
        totalResults: window.FD_DATA.initialTotal,
        hasMore: window.FD_DATA.initialHasMore,
        loading: false,

        // ----- location autocomplete (fetched as user types) -----
        locOptions: [],
        locLoading: false,

        // ----- filter state (hydrated from URL via PHP) -----
        country:       window.FD_DATA.initialFilters.country || 'IN',
        countryOpen:   false,
        q:             window.FD_DATA.initialFilters.q || '',
        loc:           '',
        locValue:      (window.FD_DATA.initialFilters.city || window.FD_DATA.initialFilters.area || window.FD_DATA.initialFilters.state)
                         ? { country:  window.FD_DATA.initialFilters.country || 'IN',
                             state:    window.FD_DATA.initialFilters.state || null,
                             city:     window.FD_DATA.initialFilters.city  || null,
                             area:     window.FD_DATA.initialFilters.area  || null }
                         : null,
        acOpen:        false,
        spec:          window.FD_DATA.initialFilters.spec || 'all',
        specPanelOpen: false,
        avail: 'any', availOpen: false,                    // client-only (no API equivalent yet)
        video: false,
        gender: 'any', genderOpen: false,                  // client-only
        minRating:    Number(window.FD_DATA.initialFilters.min_rating || 0),
        ratingOpen:   false,
        lang: 'any',  langOpen: false,                     // client-only
        sort:         window.FD_DATA.initialFilters.sort || 'relevance',
        sortOpen:     false,
        pageSize: 20, psOpen: false,
        page: 1,
        favs: [],
        hoursOpen: {},
        // Geolocation
        userLoc: null,
        maxDistanceKm: 0,
        distanceOpen: false,

        // Debounce timers
        _qDebounce:   null,
        _locDebounce: null,
        _suppressUrlSync: false,

        sortOptions: [
            ['relevance','Best match'],
            ['distance','Nearest first'],
            ['rating','Highest rated'],
            ['fee_asc','Fee — low to high'],
            ['fee_desc','Fee — high to low'],
            ['claimed','Verified clinics first'],
        ],

        init() {
            // Restore country preference (only if URL didn't set one).
            if (!('country' in (new URLSearchParams(location.search).keys()))) {
                const savedC = localStorage.getItem('fd:country');
                if (savedC && this.countries.find(c => c.code === savedC)) {
                    this.country = savedC;
                }
            }
            this.$watch('country', v => { localStorage.setItem('fd:country', v); });

            // Restore saved location (geolocation permission already granted).
            try {
                const raw = localStorage.getItem('fd:loc');
                if (raw) {
                    const v = JSON.parse(raw);
                    if (v && v.lat && v.lng && (Date.now() - (v.at || 0)) < 7 * 86400 * 1000) {
                        this.userLoc = { lat: v.lat, lng: v.lng };
                    }
                }
                const savedDist = localStorage.getItem('fd:maxDistKm');
                if (savedDist) this.maxDistanceKm = parseInt(savedDist, 10) || 0;
            } catch (e) {}
            this.$watch('userLoc', v => {
                if (v) localStorage.setItem('fd:loc', JSON.stringify({ ...v, at: Date.now() }));
                else   localStorage.removeItem('fd:loc');
                this.refresh();
            });
            this.$watch('maxDistanceKm', v => {
                localStorage.setItem('fd:maxDistKm', String(v));
                this.refresh();
            });

            // Re-fetch whenever any server-side filter changes.
            // Country / spec / sort / location-value / rating / search all reset to page 1.
            this.$watch('country',   () => this.refresh());
            this.$watch('spec',      () => this.refresh());
            this.$watch('sort',      () => this.refresh());
            this.$watch('minRating', () => this.refresh());
            this.$watch('locValue',  () => this.refresh());
            // Search box: debounce so we don't fire a request per keystroke.
            this.$watch('q', () => {
                clearTimeout(this._qDebounce);
                this._qDebounce = setTimeout(() => this.refresh(), 300);
            });
            // Location autocomplete: fetch suggestions as the user types.
            this.$watch('loc', () => {
                clearTimeout(this._locDebounce);
                this._locDebounce = setTimeout(() => this.fetchLocations(), 200);
            });

            // Pull wishlist state from the server for the heart icons.
            this.loadFavsFromServer();

            // Sync filters into the URL so the page is shareable.
            this.syncUrl();

            // Browser back/forward → re-read URL and refresh.
            window.addEventListener('popstate', () => this.loadFromUrl());
        },

        // ---- Geolocation ----
        requestLocation() {
            if (!navigator.geolocation) {
                alert("Your browser doesn't support location. Try entering an area name instead.");
                return;
            }
            navigator.geolocation.getCurrentPosition(
                pos => { this.userLoc = { lat: pos.coords.latitude, lng: pos.coords.longitude }; },
                err => {
                    const msg = err.code === 1
                        ? 'Location permission denied. You can enable it in browser settings.'
                        : "Couldn't read your location. Try again.";
                    alert(msg);
                },
                { enableHighAccuracy: false, timeout: 8000, maximumAge: 5 * 60 * 1000 }
            );
        },
        clearLocation() { this.userLoc = null; this.maxDistanceKm = 0; },

        // Distance now comes from the server (d.distance_km). Helpers stay
        // for any UI code that still references them.
        distanceFor(d) {
            if (typeof d.distance_km === 'number') return d.distance_km;
            if (!this.userLoc || !d.lat || !d.lng)  return null;
            const toRad = x => x * Math.PI / 180;
            const R = 6371;
            const dLat = toRad(d.lat - this.userLoc.lat);
            const dLng = toRad(d.lng - this.userLoc.lng);
            const a = Math.sin(dLat / 2) ** 2 +
                      Math.cos(toRad(this.userLoc.lat)) * Math.cos(toRad(d.lat)) *
                      Math.sin(dLng / 2) ** 2;
            return 2 * R * Math.asin(Math.sqrt(a));
        },
        formatDistance(km) {
            if (km === null || km === undefined) return '';
            if (km < 1)  return Math.round(km * 1000) + ' m away';
            if (km < 10) return km.toFixed(1) + ' km away';
            return Math.round(km) + ' km away';
        },

        async loadFavsFromServer() {
            try {
                const r = await fetch('/api/wishlist', { credentials: 'same-origin' });
                if (r.status === 401) { this.favs = []; return; }
                const j = await r.json();
                if (j.ok) this.favs = (j.items || []).map(d => d.id);
            } catch (e) {}
        },

        currentCountry() {
            return this.countries.find(c => c.code === this.country) || this.countries[0];
        },

        // ============================================================
        //  SERVER-SIDE SEARCH
        // ============================================================

        buildFilterParams() {
            const p = new URLSearchParams();
            if (this.q.trim())            p.set('q', this.q.trim());
            if (this.country && this.country !== 'IN') p.set('country', this.country);
            if (this.country === 'IN')    p.set('country', 'IN');
            if (this.locValue?.state)     p.set('state', this.locValue.state);
            if (this.locValue?.city)      p.set('city',  this.locValue.city);
            if (this.locValue?.area)      p.set('area',  this.locValue.area);
            if (this.spec && this.spec !== 'all') p.set('spec', this.spec);
            if (this.minRating > 0)       p.set('min_rating', String(this.minRating));
            if (this.sort && this.sort !== 'relevance') p.set('sort', this.sort);
            if (this.userLoc) {
                p.set('lat', this.userLoc.lat.toFixed(5));
                p.set('lng', this.userLoc.lng.toFixed(5));
            }
            if (this.maxDistanceKm > 0)   p.set('max_km', String(this.maxDistanceKm));
            return p;
        },

        // Re-fetch from the server with current filters. Called whenever a
        // server-side filter changes. Resets to page 1.
        async refresh() {
            this.page = 1;
            await this.fetchPage(1, /*replace=*/true);
            this.syncUrl();
        },

        async fetchPage(page, replace) {
            this.loading = true;
            const params = this.buildFilterParams();
            params.set('page', String(page));
            params.set('per_page', String(this.pageSize));

            try {
                const r = await fetch('/api/search_doctors?' + params.toString());
                const j = await r.json();
                if (j.ok) {
                    this.doctors      = replace ? j.items : [...this.doctors, ...j.items];
                    this.hasMore      = !!j.has_more;
                    if (typeof j.total === 'number') this.totalResults = j.total;
                    this.page         = j.page || page;
                }
            } catch (e) {
                // Stay on whatever we had; surface a tiny toast?
                console.error('[fd search]', e);
            } finally {
                this.loading = false;
            }
        },

        // For paginated nav — fetches the page and REPLACES the list (not infinite scroll).
        async goPage(p) {
            if (p < 1) return;
            if (p > this.totalPages()) return;
            await this.fetchPage(p, /*replace=*/true);
            this.syncUrl();
            const el = document.querySelector('.fd-main');
            if (el) window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - 80, behavior: 'smooth' });
        },

        // ---- URL state ----
        syncUrl() {
            if (this._suppressUrlSync) return;
            const params = this.buildFilterParams();
            if (this.page > 1) params.set('page', String(this.page));
            const qs = params.toString();
            const target = qs ? location.pathname + '?' + qs : location.pathname;
            history.replaceState(null, '', target);
        },

        loadFromUrl() {
            this._suppressUrlSync = true;
            const u = new URLSearchParams(location.search);
            this.q         = u.get('q')          || '';
            this.country   = u.get('country')    || 'IN';
            this.spec      = u.get('spec')       || 'all';
            this.minRating = Number(u.get('min_rating') || 0);
            this.sort      = u.get('sort')       || 'relevance';
            this.locValue  = (u.get('city') || u.get('area') || u.get('state'))
                ? { country: this.country, state: u.get('state'), city: u.get('city'), area: u.get('area') }
                : null;
            this.page = Math.max(1, parseInt(u.get('page') || '1', 10));
            this.fetchPage(this.page, true).finally(() => { this._suppressUrlSync = false; });
        },

        // ============================================================
        //  LOCATION AUTOCOMPLETE
        // ============================================================

        async fetchLocations() {
            const q = (this.loc || '').trim();
            this.locLoading = true;
            try {
                const u = new URL('/api/locations', location.origin);
                u.searchParams.set('q', q);
                u.searchParams.set('country', this.country);
                const r = await fetch(u.toString());
                const j = await r.json();
                this.locOptions = j.ok ? (j.items || []) : [];
            } catch (e) { this.locOptions = []; }
            finally { this.locLoading = false; }
        },

        // Template still calls filteredLocations(); just return the cached
        // list so we don't have to rewire all the existing markup.
        filteredLocations() { return this.locOptions; },

        // Client-side filters layered on top of server results. Only used for
        // features the API doesn't support yet (video, gender, lang, avail).
        // These rarely match in real data, so they almost never reduce the list.
        _applyClientFilters(list) {
            return list.filter(d => {
                if (this.video && !d.video) return false;
                if (this.gender !== 'any' && d.gender !== this.gender) return false;
                if (this.lang   !== 'any' && (!d.langs || !d.langs.includes(this.lang))) return false;
                if (this.avail !== 'any' && d.next) {
                    if (this.avail === 'today' && d.next.when !== 'today') return false;
                    if (this.avail === 'tomorrow' && !(d.next.when === 'today' || d.next.when === 'tomorrow')) return false;
                }
                return true;
            });
        },

        // The list of doctors shown on the current page. The server already
        // paginates; we layer a few client-only filters.
        pageItems() { return this._applyClientFilters(this.doctors); },

        // For the "X results" counter — show the server total. (Client-only
        // filters might reduce what's visible, but the total reflects the
        // broader set; we hide the page-info counter when zero locally.)
        filteredResults() { return { length: this.totalResults }; },

        totalPages() {
            return Math.max(1, Math.ceil(this.totalResults / this.pageSize));
        },

        pageNumbers() {
            const total = this.totalPages();
            const out = [];
            const win = 1;
            for (let i = 1; i <= total; i++) {
                if (i === 1 || i === total || (i >= this.page - win && i <= this.page + win)) {
                    out.push(i);
                } else if (out[out.length - 1] !== '…') {
                    out.push('…');
                }
            }
            return out;
        },

        slotClass(when) {
            return when === 'today' ? '' : when === 'tomorrow' ? 'tomorrow' : 'later';
        },

        formatFee(cur, fee) {
            if (cur === '₹' || cur === '$' || cur === '£') return cur + fee;
            return cur + ' ' + fee;
        },

        /**
         * Pulls today's opening hours out of Google's 7-line weekday_text array.
         * The lines look like: "Monday: 9:00 AM – 1:00 PM, 4:00 PM – 8:00 PM"
         * Returns "Today: <hours>" or null if we can't match.
         */
        todayHours(d) {
            if (!d.opening_hours || !Array.isArray(d.opening_hours)) return null;
            const today = new Date().toLocaleDateString('en-US', { weekday: 'long' });
            for (const line of d.opening_hours) {
                if (line.startsWith(today + ':')) {
                    const hours = line.substring(today.length + 1).trim();
                    return 'Today: ' + hours;
                }
            }
            return null;
        },

        sortLabel() {
            const found = this.sortOptions.find(o => o[0] === this.sort);
            return found ? found[1] : 'Best match';
        },

        activeFilterCount() {
            return (this.avail !== 'any' ? 1 : 0)
                 + (this.video ? 1 : 0)
                 + (this.gender !== 'any' ? 1 : 0)
                 + (this.minRating > 0 ? 1 : 0)
                 + (this.lang !== 'any' ? 1 : 0)
                 + (this.maxDistanceKm > 0 ? 1 : 0);
        },

        clearFilters() {
            this.avail = 'any';
            this.video = false;
            this.gender = 'any';
            this.minRating = 0;
            this.lang = 'any';
            this.maxDistanceKm = 0;
        },

        toggleFav(id) {
            // Always gate through ecpAuth.require so logged-out users see
            // the modal first. After login the callback fires and we perform
            // the actual add/remove against the API.
            const auth = window.ecpAuth;
            if (!auth) return;                  // modal not loaded yet
            auth.require('save_doctor', () => this._persistFav(id));
        },

        async _persistFav(id) {
            const wasOn = this.favs.includes(id);
            if (wasOn) {
                // Optimistic remove
                this.favs = this.favs.filter(x => x !== id);
                try {
                    await fetch('/api/wishlist?action=remove', {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ doctor_id: id }),
                    });
                } catch (e) {
                    this.favs = [...this.favs, id];   // rollback
                }
                return;
            }

            // Adding
            if (this.favs.length >= 5) {
                alert('Your shortlist is full (5 max). Remove one from your patient panel first.');
                return;
            }
            this.favs = [...this.favs, id];   // optimistic
            try {
                const r = await fetch('/api/wishlist?action=add', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ doctor_id: id }),
                });
                const j = await r.json();
                if (!j.ok) {
                    this.favs = this.favs.filter(x => x !== id);
                    if (j.error === 'limit_reached') {
                        alert('Your shortlist is full (5 max).');
                    }
                }
            } catch (e) {
                this.favs = this.favs.filter(x => x !== id);   // rollback
            }
        },

        claimListing(d) {
            if (!window.ecpClaim) return;
            window.ecpClaim.open('claim', {
                id:         d.id,
                name:       d.clinicName || d.name,
                doctorName: d.doctorName || '',
                area:       d.area  || '',
                city:       d.city  || '',
                spec:       d.spec  || '',
                clinicName: d.clinicName || d.name,
            });
        },

        bookDoctor(d) {
            // Claimed clinic → send to the real portal booking page.
            if (d.is_claimed && d.slug) {
                window.open('https://app.eclinicpro.com/book/' + d.slug, '_blank');
                return;
            }
            // Unclaimed → patient must be logged in; lead is recorded + clinic notified.
            const auth = window.ecpAuth;
            if (!auth) return;
            auth.require('book', () => {
                if (window.ecpBook) window.ecpBook.open(d);
            });
        },

        // Fire-and-forget call-click analytics (no UI change, no await).
        trackCall(d) {
            try {
                fetch('/api/lead?action=track', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ doctor_id: d.id, type: 'call' }),
                });
            } catch (e) { /* ignore */ }
        },
    };
}
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
