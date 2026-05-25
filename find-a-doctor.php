<?php
// =====================================================================
// find-a-doctor.php — public doctor directory (search + filters)
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';

$pageTitle = 'Find a Doctor — eClinicPro';
$metaDesc  = 'Search verified clinicians across India, the US, UK and more — see availability, fees and ratings before you book.';
$activePage = 'find';

$data = require __DIR__ . '/partials/find-doctor-data.php';

// If the directory_doctors table has rows, replace the seed doctors with them.
// Countries / specialties / locations stay from the seed so the UI chrome works
// even with a small or specialty-skewed DB.
$dbDoctors = ecp_directory_doctors();
if ($dbDoctors !== null && count($dbDoctors) > 0) {
    $data['doctors'] = $dbDoctors;
}

// Replace seed locations with distinct areas/cities/states from the DB so the
// autocomplete only suggests places that actually have results.
$dbLocations = ecp_directory_locations();
if ($dbLocations !== null && count($dbLocations) > 0) {
    $data['locations'] = $dbLocations;
}

// Real total (unaffected by the LIMIT) for hero copy.
$totalDoctors = ecp_directory_doctor_count();
if ($totalDoctors === 0) $totalDoctors = count($data['doctors']);

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
                           @focus="acOpen = true"
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

        <!-- Empty state -->
        <div class="fd-empty" x-show="pageItems().length === 0">
            <div class="glyph">🩺</div>
            <h3>No doctors match your filters</h3>
            <p>Try widening your search — clear a few filters or pick a different area.</p>
        </div>

        <!-- Results grid -->
        <div class="fd-grid" x-show="pageItems().length > 0">
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
                            <template x-if="d.phone">
                                <a :href="'tel:' + d.phone" class="fd-btn primary">📞 Call</a>
                            </template>
                            <template x-if="!d.phone">
                                <button type="button" class="fd-btn primary"
                                        @click="bookDoctor(d)">Book</button>
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
window.FD_DATA = <?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function findDoctor() {
    return {
        // ----- data -----
        countries: window.FD_DATA.countries,
        specialties: window.FD_DATA.specialties,
        specialty_groups: window.FD_DATA.specialty_groups || [],
        locations: window.FD_DATA.locations,
        doctors: window.FD_DATA.doctors,

        // ----- state -----
        country: 'IN',
        countryOpen: false,
        q: '',
        loc: '',
        locValue: null,
        acOpen: false,
        spec: 'all',
        specPanelOpen: false,
        avail: 'any', availOpen: false,
        video: false,
        gender: 'any', genderOpen: false,
        minRating: 0, ratingOpen: false,
        lang: 'any', langOpen: false,
        sort: 'relevance', sortOpen: false,
        pageSize: 10, psOpen: false,
        page: 1,
        favs: [],
        hoursOpen: {},
        // Distance / geolocation
        userLoc: null,                    // {lat, lng} when permission granted
        maxDistanceKm: 0,                 // 0 = no filter
        distanceOpen: false,

        sortOptions: [
            ['relevance','Best match'],
            ['distance','Nearest first'],
            ['available','Soonest available'],
            ['rating','Highest rated'],
            ['fee_asc','Fee — low to high'],
            ['fee_desc','Fee — high to low'],
            ['exp','Most experienced'],
        ],

        init() {
            // Restore country preference from localStorage (no auth required).
            const savedC = localStorage.getItem('fd:country');
            if (savedC && this.countries.find(c => c.code === savedC)) {
                this.country = savedC;
            }
            this.$watch('country', v => { localStorage.setItem('fd:country', v); this.page = 1; });
            // Reset to page 1 whenever filters change
            ['q','loc','locValue','spec','avail','video','gender','minRating','lang','sort','maxDistanceKm','userLoc'].forEach(k => {
                this.$watch(k, () => this.page = 1);
            });

            // Restore saved location (set when the user previously accepted the
            // permission prompt). Stored as JSON {lat,lng,at}. Expire after 7 days.
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
            });
            this.$watch('maxDistanceKm', v => localStorage.setItem('fd:maxDistKm', String(v)));

            // If logged in, fetch the real saved-doctor IDs from the server
            // so the heart icons show pre-saved state correctly on first paint.
            this.loadFavsFromServer();
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

        // Haversine distance in km. Returns null when either point is missing.
        distanceFor(d) {
            if (!this.userLoc || !d.lat || !d.lng) return null;
            const toRad = x => x * Math.PI / 180;
            const R = 6371;  // km
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
            } catch (e) { /* stay with empty favs */ }
        },

        currentCountry() {
            return this.countries.find(c => c.code === this.country) || this.countries[0];
        },

        filteredLocations() {
            const term = (this.loc || '').trim().toLowerCase();
            let list = this.locations.filter(e => e.country === this.country);
            if (term) {
                list = this.locations.filter(e =>
                    e.label.toLowerCase().includes(term) ||
                    (e.sub || '').toLowerCase().includes(term)
                );
            }
            // Sort: starts-with first, then country preference, then type
            list = list.slice().sort((a, b) => {
                const at = a.label.toLowerCase(), bt = b.label.toLowerCase();
                const aS = at.startsWith(term) ? 0 : 1;
                const bS = bt.startsWith(term) ? 0 : 1;
                if (aS !== bS) return aS - bS;
                const aC = a.country === this.country ? 0 : 1;
                const bC = b.country === this.country ? 0 : 1;
                if (aC !== bC) return aC - bC;
                const order = { area: 0, city: 1, state: 2, country: 3 };
                return order[a.type] - order[b.type];
            });
            return list.slice(0, 8);
        },

        filteredResults() {
            const term = this.q.trim().toLowerCase();
            const locTerm = this.loc.trim().toLowerCase();

            let list = this.doctors.filter(d => {
                if (d.country !== this.country) return false;
                if (term) {
                    const hay = [
                        d.name, d.doctorName, d.clinicName, d.hospital,
                        d.specLabel, d.area, d.city
                    ].filter(Boolean).join(' ').toLowerCase();
                    if (!hay.includes(term)) return false;
                }
                if (this.locValue) {
                    if (this.locValue.country && d.country !== this.locValue.country) return false;
                    if (this.locValue.state && d.state !== this.locValue.state) return false;
                    if (this.locValue.city && d.city !== this.locValue.city) return false;
                    if (this.locValue.area && d.area !== this.locValue.area) return false;
                } else if (locTerm) {
                    const hay = (d.area + ' ' + d.city + ' ' + d.state + ' ' + d.countryName).toLowerCase();
                    if (!hay.includes(locTerm)) return false;
                }
                if (this.spec !== 'all' && d.spec !== this.spec) return false;
                if (this.avail === 'today' && d.next.when !== 'today') return false;
                if (this.avail === 'tomorrow' && !(d.next.when === 'today' || d.next.when === 'tomorrow')) return false;
                if (this.avail === 'week' && d.next.when === 'later') {
                    const m = /\d+/.exec(d.next.label);
                    if (m && parseInt(m[0], 10) > 7) return false;
                }
                if (this.video && !d.video) return false;
                if (this.gender !== 'any' && d.gender !== this.gender) return false;
                if (this.minRating > 0 && d.rating < this.minRating) return false;
                if (this.lang !== 'any' && !d.langs.includes(this.lang)) return false;

                // Distance filter — only applies when we have both the user's
                // location AND the doctor's lat/lng. Doctors without coords
                // are kept (we can't prove they're far).
                if (this.maxDistanceKm > 0 && this.userLoc && d.lat && d.lng) {
                    const km = this.distanceFor(d);
                    if (km !== null && km > this.maxDistanceKm) return false;
                }
                return true;
            });

            list.sort((a, b) => {
                if (this.sort === 'distance') {
                    const da = this.distanceFor(a);
                    const db = this.distanceFor(b);
                    // Doctors with no coords go to the end.
                    if (da === null && db === null) return b.rating - a.rating;
                    if (da === null) return 1;
                    if (db === null) return -1;
                    return da - db;
                }
                if (this.sort === 'rating')   return b.rating - a.rating;
                if (this.sort === 'fee_asc')  return a.fee - b.fee;
                if (this.sort === 'fee_desc') return b.fee - a.fee;
                if (this.sort === 'exp')      return b.years - a.years;
                if (this.sort === 'available') {
                    const ord = w => w === 'today' ? 0 : w === 'tomorrow' ? 1 : 2;
                    const diff = ord(a.next.when) - ord(b.next.when);
                    if (diff) return diff;
                    return b.rating - a.rating;
                }
                if (b.verified !== a.verified) return (b.verified ? 1 : 0) - (a.verified ? 1 : 0);
                return b.rating - a.rating;
            });

            return list;
        },

        totalPages() {
            return Math.max(1, Math.ceil(this.filteredResults().length / this.pageSize));
        },

        pageItems() {
            const start = (this.page - 1) * this.pageSize;
            return this.filteredResults().slice(start, start + this.pageSize);
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

        goPage(p) {
            if (p < 1 || p > this.totalPages()) return;
            this.page = p;
            const el = document.querySelector('.fd-main');
            if (el) window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - 80, behavior: 'smooth' });
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
            const auth = window.ecpAuth;
            if (!auth) return;
            auth.require('book', () => {
                // After login we'll route to the booking flow. For now, send
                // the user to the patient panel so they at least land
                // somewhere meaningful. Replace with a real booking page later.
                alert("Booking flow is coming soon. Dr " + (d.doctorName || d.name) + " saved to your shortlist?");
                this._persistFav(d.id);
            });
        },
    };
}
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
