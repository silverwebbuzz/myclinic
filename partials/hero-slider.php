<?php
// =====================================================================
// hero-slider.php — homepage hero carousel (Healthcare / Health Store /
// Diagnostics). All copy, search forms and cards are plain HTML below.
// Styles: /assets/css/hero-slider.css (scoped to .hh, classes prefixed hh-)
// Behaviour + search/button routes: /assets/js/hero-slider.js (HH_CONFIG)
// =====================================================================

$hsJsBust = @filemtime(__DIR__ . '/../assets/js/hero-slider.js') ?: time();
?>
<!-- ============ HERO SLIDER ============ -->
<section class="hh hh-slider" id="top" aria-roledescription="carousel" aria-label="Healthcare services">

    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
        <defs>
            <symbol id="hh-i-search" viewBox="0 0 24 24">
                <circle cx="10" cy="10" r="7" />
                <path d="m15 15 6 6" />
            </symbol>
            <symbol id="hh-i-arrow" viewBox="0 0 24 24">
                <path d="M4 12h16m-6-6 6 6-6 6" />
            </symbol>
            <symbol id="hh-i-pin" viewBox="0 0 24 24">
                <path d="M19 9c0 6-7 12-7 12S5 15 5 9a7 7 0 0 1 14 0Z" />
                <circle cx="12" cy="9" r="2.5" />
            </symbol>
            <symbol id="hh-i-shield" viewBox="0 0 24 24">
                <path d="m12 2 9 4v6c0 5-9 10-9 10S3 17 3 12V6Z" />
                <path d="m7 11 4 4 6-7" />
            </symbol>
            <symbol id="hh-i-users" viewBox="0 0 24 24">
                <circle cx="12" cy="7" r="4" />
                <path d="M5 22v-4a7 7 0 0 1 14 0v4ZM3 5a3 3 0 0 0 0 6M21 5a3 3 0 0 1 0 6M1 20v-4l2-2M23 20v-4l-2-2" />
            </symbol>
            <symbol id="hh-i-calendar" viewBox="0 0 24 24">
                <rect x="3" y="5" width="18" height="17" rx="2" />
                <path d="M7 2v6M17 2v6M3 11h18M7 15h2m5 0h2m-9 4h2" />
            </symbol>
            <symbol id="hh-i-stethoscope" viewBox="0 0 24 24">
                <path d="M4 3v7a5 5 0 0 0 10 0V3M2 3h4m6 0h4M9 15v3a5 5 0 0 0 10 0v-4" />
                <circle cx="19" cy="11" r="3" />
            </symbol>
            <symbol id="hh-i-pill" viewBox="0 0 24 24">
                <path d="M4 12 12 4a5.7 5.7 0 0 1 8 8l-8 8a5.7 5.7 0 0 1-8-8ZM8 8l8 8" />
            </symbol>
            <symbol id="hh-i-wallet" viewBox="0 0 24 24">
                <rect x="3" y="5" width="18" height="16" rx="3" />
                <path d="M3 8h18M4 5l13-3v3M21 12h-6v5h6" />
            </symbol>
            <symbol id="hh-i-truck" viewBox="0 0 24 24">
                <path d="M2 5h12v13H2ZM14 10h5l3 5v3h-8" />
                <circle cx="6" cy="19" r="2" />
                <circle cx="18" cy="19" r="2" />
                <path d="M1 9h7M1 12h5" />
            </symbol>
            <symbol id="hh-i-headset" viewBox="0 0 24 24">
                <path d="M3 13v-2a9 9 0 0 1 18 0v7a4 4 0 0 1-4 4h-3" />
                <rect x="2" y="11" width="4" height="8" rx="2" />
                <rect x="18" y="11" width="4" height="8" rx="2" />
            </symbol>
            <symbol id="hh-i-leaf" viewBox="0 0 24 24">
                <path d="M3 21C0 4 10 2 21 2c0 12-5 19-18 19ZM3 21 17 6M8 15v-5m0 5h6" />
            </symbol>
            <symbol id="hh-i-bottle" viewBox="0 0 24 24">
                <path d="M8 2h8v4H8ZM9 6v3l-3 3v10h12V12l-3-3V6M6 15h12" />
            </symbol>
            <symbol id="hh-i-test" viewBox="0 0 24 24">
                <path d="M8 2h8M9 2v15a3 3 0 0 0 6 0V2M9 8h6m-6 4h3" />
            </symbol>
            <symbol id="hh-i-home" viewBox="0 0 24 24">
                <path d="m2 10 10-8 10 8M5 8v14h5v-8h4v8h5V8" />
            </symbol>
            <symbol id="hh-i-heart" viewBox="0 0 24 24">
                <path d="M12 21S1 14 2 7c1-6 8-6 10-1 2-5 9-5 10 1 1 7-10 14-10 14Z" />
            </symbol>
            <symbol id="hh-i-check" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="9" />
                <path d="m7 12 3 3 7-7" />
            </symbol>
        </defs>
    </svg>

    <div class="hh-slides">
        <!-- SLIDE 1: Healthcare -->
        <div class="hh-slide hh-active first-slide" data-name="Healthcare" aria-label="1 of 3: Healthcare" aria-roledescription="slide">
            <div class="background-image" style="background-image: url('/assets/img/hero/doctor-avatar.webp');">
            </div>
            <div class="wrap">
            <div class="hh-copy">
                <div class="hh-badge hh-entry"><i class="hh-status"></i>Now serving in India<span class="hh-divider"></span>76,750+ verified doctors<div class="hh-avatars"><span>👨🏻</span><span>👩🏽</span><span>👨🏽</span></div>
                </div>
                <h1 class="hh-headline hh-entry" style="--delay:.22s">Healthcare,<br>made <em>simple.</em></h1>
                <p class="hh-description hh-entry" style="--delay:.33s">Find trusted doctors, book appointments, manage your health records — all in one place.</p>
                <form class="hh-search hh-entry" style="--delay:.42s" data-kind="Doctors"><svg class="hh-icon">
                        <use href="#hh-i-search" />
                    </svg><input aria-label="Search doctors" placeholder="Search doctors, specialties or clinics…" required><label class="hh-location"><svg class="hh-icon">
                            <use href="#hh-i-pin" />
                        </svg><select aria-label="Location">
                            <option>Ahmedabad</option>
                            <option>Mumbai</option>
                            <option>Delhi</option>
                            <option>Surat</option>
                        </select></label><button class="hh-search-submit" aria-label="Search doctors"><svg class="hh-icon">
                            <use href="#hh-i-arrow" />
                        </svg></button></form>
                <div class="hh-popular hh-entry" style="--delay:.5s">Popular: <button type="button" class="hh-chip">General Physician</button><button type="button" class="hh-chip">Dermatologist</button><button type="button" class="hh-chip">Pediatrician</button><button type="button" class="hh-chip">Gynecologist</button><button type="button" class="hh-chip">Dentist</button></div>
                <div class="hh-stats hh-entry" style="--delay:.6s">
                    <div class="hh-stat"><svg class="hh-icon">
                            <use href="#hh-i-users" />
                        </svg>
                        <div><strong>76K+</strong><small>Verified Doctors</small></div>
                    </div>
                    <div class="hh-stat"><svg class="hh-icon">
                            <use href="#hh-i-users" />
                        </svg>
                        <div><strong>2M+</strong><small>Happy Patients</small></div>
                    </div>
                    <div class="hh-stat"><svg class="hh-icon">
                            <use href="#hh-i-shield" />
                        </svg>
                        <div><strong>99.9%</strong><small>Uptime Guarantee</small></div>
                    </div>
                </div>
            </div>
            <div class="hh-scene hh-entry">
                <div class="hh-ring">
                    <div class="hh-rings">
                        <div class="hh-orbit"></div>
                    </div>
                    <div class="hh-avatar"><img src="/assets/img/hero/doctor-avatar.webp" width="800" height="800" alt="Smiling doctor with a stethoscope holding a tablet" fetchpriority="high" decoding="async"></div>
                    <div class="hh-revenue" style="--a:200deg;--r:.52"><b>Clinic Revenue</b><strong>₹24,250</strong>
                        <div class="hh-bars"><i style="--h:20%"></i><i style="--h:35%"></i><i style="--h:50%"></i><i style="--h:75%"></i><i style="--h:100%"></i></div><small>↑ +15%<br>vs last month</small>
                    </div>
                    <div class="hh-feature hh-f1" style="--a:258deg;--r:.6" data-feature="Book Appointment" data-icon="calendar" data-sub="Choose your doctor<br>and time slot"></div>
                    <div class="hh-feature hh-f2" style="--a:322deg;--r:.53" data-feature="Doctor Availability" data-icon="stethoscope" data-sub="Real-time availability<br>& instant booking"></div>
                    <div class="hh-feature hh-f3" style="--a:358deg;--r:.47" data-feature="Prescriptions" data-icon="pill" data-sub="Digital prescriptions<br>& medicine reminders"></div>
                    <div class="hh-feature hh-f4" style="--a:49deg;--r:.45" data-feature="Secure Payments" data-icon="wallet" data-sub="Multiple payment options<br>& transparent fees"></div>
                    <div class="phone-image">

                    <img src="/assets/img/hero/phone-image.png" alt="Phone image" width="200" height="200">
            
                    </div>
                </div>
            </div>
            </div>
        </div>

        <!-- SLIDE 2: Health Store -->
        <div class="hh-slide hh-store second-slide" data-name="Health Store" aria-label="2 of 3: Health Store" aria-roledescription="slide" aria-hidden="true" inert>
            <div class="background-image" style="background-image: url('/assets/img/hero/nine.jpg');">
            </div>
            <div class="wrap">
                <div class="hh-copy">
                    <div class="hh-badge hh-entry"><i class="hh-status"></i>Trusted by 2M+ customers across India<div class="hh-avatars"><span>👩🏻</span><span>👨🏽</span><span>👩🏽</span></div>
                    </div>
                    <h1 class="hh-headline hh-entry" style="--delay:.22s">Your<br><em>Health Store,</em><br><span class="hh-under">Simplified.</span></h1>
                    <p class="hh-description hh-entry" style="--delay:.33s">Genuine medicines, wellness products, and healthcare essentials — delivered to your doorstep. Because a healthier you, is a happier tomorrow.</p>
                    <form class="hh-search hh-entry" style="--delay:.42s" data-kind="Products"><svg class="hh-icon">
                            <use href="#hh-i-search" />
                        </svg><input aria-label="Search medicines" placeholder="Search medicines, wellness products…" required><label class="hh-location"><svg class="hh-icon">
                                <use href="#hh-i-pin" />
                            </svg><select aria-label="Delivery location">
                                <option>Ahmedabad</option>
                                <option>Mumbai</option>
                                <option>Delhi</option>
                                <option>Surat</option>
                            </select></label><button class="hh-search-submit" aria-label="Search products"><svg class="hh-icon">
                                <use href="#hh-i-arrow" />
                            </svg></button></form>
                    <div class="hh-stats hh-entry" style="--delay:.55s">
                        <div class="hh-stat"><svg class="hh-icon">
                                <use href="#hh-i-shield" />
                            </svg>
                            <div><strong>100% Genuine<br>Products</strong><small>Quality you can trust</small></div>
                        </div>
                        <div class="hh-stat"><svg class="hh-icon">
                                <use href="#hh-i-truck" />
                            </svg>
                            <div><strong>Fast &amp; Reliable<br>Delivery</strong><small>Across India</small></div>
                        </div>
                        <div class="hh-stat"><svg class="hh-icon">
                                <use href="#hh-i-headset" />
                            </svg>
                            <div><strong>Expert<br>Support</strong><small>Always here to help</small></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SLIDE 3: Diagnostics -->
        <div class="hh-slide hh-lab third-slide" data-name="Diagnostics" aria-label="3 of 3: Diagnostics" aria-roledescription="slide" aria-hidden="true" inert>
            <div class="background-image" style="background-image: url('/assets/img/hero/six.jpg');"></div>
            <div class="wrap">
                <div class="hh-copy">
                    <div class="hh-badge hh-entry"><i class="hh-status"></i>Now partnered with<span class="hh-divider"></span><span class="hh-brand">Thyro<span>care</span></span><span class="hh-divider"></span><span>Trusted Diagnostics<br>Home Collection</span></div>
                    <h1 class="hh-headline hh-entry" style="--delay:.22s">Medicines. Wellness.<br>Diagnostics.<br><em class="hh-under">All in one place.</em></h1>
                    <p class="hh-description hh-entry" style="--delay:.33s">Shop healthcare essentials or book trusted diagnostic tests from the comfort of your home.</p>
                    <form class="hh-search hh-entry" style="--delay:.42s" data-kind="Diagnostics"><svg class="hh-icon">
                            <use href="#hh-i-search" />
                        </svg><input aria-label="Search tests and health packages" placeholder="Search medicines, tests, health packages…" required><label class="hh-location"><svg class="hh-icon">
                                <use href="#hh-i-pin" />
                            </svg><select aria-label="Collection location">
                                <option>Ahmedabad</option>
                                <option>Mumbai</option>
                                <option>Delhi</option>
                                <option>Surat</option>
                            </select></label><button class="hh-search-submit" aria-label="Explore health"><svg class="hh-icon">
                                <use href="#hh-i-arrow" />
                            </svg></button></form>
                    <div class="hh-stats hh-entry" style="--delay:.55s">
                        <div class="hh-stat"><svg class="hh-icon">
                                <use href="#hh-i-shield" />
                            </svg>
                            <div><strong>100% Genuine<br>Products</strong></div>
                        </div>
                        <div class="hh-stat"><svg class="hh-icon">
                                <use href="#hh-i-truck" />
                            </svg>
                            <div><strong>Fast &amp; Reliable<br>Delivery</strong></div>
                        </div>
                        <div class="hh-stat"><svg class="hh-icon">
                                <use href="#hh-i-check" />
                            </svg>
                            <div><strong>NABL Accredited</strong><small>Thyrocare Labs</small></div>
                        </div>
                    </div>
                </div>
                <!-- <div class="hh-photo hh-entry" style="--ratio:1024/682"><img src="/assets/img/hero/lab-banner.webp" width="1024" height="682" alt="Thyrocare sample collection kit, sample bag and test vials on a marble stand" loading="lazy" decoding="async"><div class="hh-arc" style="--x:41.5%;--y:6.2%;--d:55.3%;--base:1100px"><div class="hh-feature hh-f1" style="--a:230deg;--r:.5" data-feature="Book Lab Test" data-icon="test" data-sub="Choose from 1,000+ tests<br>& health packages"></div><div class="hh-feature hh-f2" style="--a:305deg;--r:.5" data-feature="Home Sample Collection" data-icon="home" data-sub="Safe, quick & convenient<br>at your doorstep"></div><div class="hh-feature hh-f3" style="--a:180deg;--r:.72" data-feature="Full Body Checkup" data-icon="shield" data-sub="✓ Basic Health Checkup<br>✓ Thyroid Profile<br>✓ Diabetes Profile<br>✓ Heart Health Checkup"></div><div class="hh-feature hh-f4" style="--a:70deg;--r:.56" data-feature="Thyrocare Partner" data-icon="check" data-sub="Trusted Diagnostics · Home Collection"></div></div></div> -->
            </div>
        </div>
    </div>

    <div class="hh-slider-footer"><button type="button" class="hh-arrow hh-previous" aria-label="Previous slide"><svg class="hh-icon" style="transform:rotate(180deg)">
                <use href="#hh-i-arrow" />
            </svg></button>
        <div class="hh-tabs" role="group" aria-label="Choose a slide"><button type="button" class="hh-tab" aria-current="true">Healthcare</button><button type="button" class="hh-tab" aria-current="false">Health Store</button><button type="button" class="hh-tab" aria-current="false">Diagnostics</button></div><button type="button" class="hh-arrow hh-next" aria-label="Next slide"><svg class="hh-icon">
                <use href="#hh-i-arrow" />
            </svg></button><button type="button" class="hh-play" aria-label="Pause slideshow">Ⅱ</button>
    </div>
    <div class="hh-timer" aria-hidden="true"><span></span></div>
    <p class="hh-sr-only hh-announce" aria-live="polite"></p>

    <dialog class="hh-modal">
        <h3></h3>
        <p></p>
        <form method="dialog"><button>Close</button></form>
    </dialog>
    <script defer src="/assets/js/hero-slider.js?v=<?= (int) $hsJsBust ?>"></script>

</section>