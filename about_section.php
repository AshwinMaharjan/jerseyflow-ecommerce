<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Learn about JerseyFlow — your ultimate destination for authentic football jerseys. Passion, quality, and fan culture in every stitch.">
    <title>About Us | JerseyFlow</title>

    <!-- External CSS -->    <link rel="icon" href="/jerseyflow-ecommerce/images/logo_icon.ico?v=2">

    <link rel="stylesheet" href="style/about.css">
    <link rel="stylesheet" href="style/footer.css">
</head>
<body>

    <?php include 'homepage/navbar.php'; ?>

    <main>

        <!-- ═══════════════════════════════════════════════
             HERO SECTION
        ════════════════════════════════════════════════ -->
        <section class="hero" aria-label="Hero">
            <div class="hero__bg-overlay"></div>
            <div class="hero__noise"></div>

            <div class="hero__content">
                <span class="hero__eyebrow">Our Story</span>
                <h1 class="hero__title">About<br><span class="hero__title--accent">JerseyFlow</span></h1>
                <p class="hero__tagline">
                    Born from the stands. Driven by passion.<br>
                    Every jersey we sell carries the soul of the beautiful game.
                </p>
                <div class="hero__divider"></div>
            </div>

            <div class="hero__scroll-hint" aria-hidden="true">
                <span>Scroll</span>
                <div class="hero__scroll-line"></div>
            </div>
        </section>

        <!-- ═══════════════════════════════════════════════
             WHO WE ARE
        ════════════════════════════════════════════════ -->
        <section class="section who-we-are" aria-labelledby="who-heading">
            <div class="container who-we-are__grid">

                <div class="who-we-are__image-wrap" aria-hidden="true">
                    <div class="who-we-are__image-frame">
                        <img
                            src="images/about/team.webp"
                            alt="JerseyFlow team"
                            class="who-we-are__image"
                            loading="lazy"
                            onerror="this.style.display='none'; this.parentElement.classList.add('img-fallback')"
                        >
                        <div class="who-we-are__image-badge">
                            <span class="badge__year">EST.</span>
                            <span class="badge__num">2026</span>
                        </div>
                    </div>
                </div>

                <div class="who-we-are__text">
                    <span class="section__label">Who We Are</span>
                    <h2 id="who-heading" class="section__heading">More Than a Jersey.<br>A Statement of Identity.</h2>
                    <p class="who-we-are__body">
JerseyFlow was built on a simple idea: football fans deserve more than generic replica kits.
We’re a team of passionate supporters and designers who set out to create jerseys that balance authenticity, quality, and accessibility, so fans can represent their clubs with pride, without compromise.
                    </p>
                    <p class="who-we-are__body">
                        From iconic club jerseys to national team classics, every piece in our collection is carefully selected for its quality and authenticity. Because wearing a jersey isn’t just about style, it’s about representing the team you stand behind.
                    </p>
                    <ul class="who-we-are__tags" aria-label="Brand values">
                        <li>Authentic</li>
                        <li>Fan-First</li>
                        <li>Quality-Driven</li>
                    </ul>
                </div>

            </div>
        </section>

        <!-- ═══════════════════════════════════════════════
             OUR MISSION
        ════════════════════════════════════════════════ -->
        <section class="section mission" aria-labelledby="mission-heading">
            <div class="mission__bg-accent" aria-hidden="true"></div>
            <div class="container">
                <div class="mission__header">
                    <span class="section__label">Our Mission</span>
                    <h2 id="mission-heading" class="section__heading">What Drives<br>Every Decision We Make</h2>
                    <p class="mission__intro">
                        Our goal is simple: to make football jerseys accessible to every fan, no matter their budget, location, or the team they support.
                    </p>
                </div>

                <div class="mission__pillars">
                    <article class="mission__pillar">
                        <div class="pillar__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h3 class="pillar__title">Uncompromising Quality</h3>
                        <p class="pillar__text">Every jersey in our collection is selected for its quality and attention to detail. No cheap knock-offs, just jerseys you can wear with confidence.</p>
                    </article>

                    <article class="mission__pillar">
                        <div class="pillar__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75"/>
                            </svg>
                        </div>
                        <h3 class="pillar__title">Genuine Affordability</h3>
                        <p class="pillar__text">Football fandom shouldn’t be a luxury. We keep our prices fair so every fan, no matter their budget or location can support their club.</p>
                    </article>

                    <article class="mission__pillar">
                        <div class="pillar__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
                            </svg>
                        </div>
                        <h3 class="pillar__title">True Fan Connection</h3>
                        <p class="pillar__text">We’re not just sellers, we’re fans too. Our community is built on shared passion, matchday debates, and supporting the teams we love.</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- ═══════════════════════════════════════════════
             WHY CHOOSE US
        ════════════════════════════════════════════════ -->
        <section class="section why-us" aria-labelledby="why-heading">
            <div class="container">
                <div class="why-us__header">
                    <span class="section__label">Why Choose Us</span>
                    <h2 id="why-heading" class="section__heading">The JerseyFlow Difference</h2>
                </div>

                <div class="why-us__grid">

                    <article class="feature-card">
                        <div class="feature-card__icon-wrap" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
                            </svg>
                        </div>
                        <h3 class="feature-card__title">Authentic Designs</h3>
                        <p class="feature-card__text">Licensed and verified kits sourced directly from trusted manufacturers. What you see is exactly what you get, no surprises.</p>
                        <div class="feature-card__line" aria-hidden="true"></div>
                    </article>

                    <article class="feature-card">
                        <div class="feature-card__icon-wrap" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185z"/>
                            </svg>
                        </div>
                        <h3 class="feature-card__title">Affordable Prices</h3>
                        <p class="feature-card__text">We negotiate hard with suppliers so you don't have to break the bank. Regular deals, seasonal sales, and loyalty rewards for our members.</p>
                        <div class="feature-card__line" aria-hidden="true"></div>
                    </article>

                    <article class="feature-card">
                        <div class="feature-card__icon-wrap" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                            </svg>
                        </div>
                        <h3 class="feature-card__title">Fast Delivery</h3>
                        <p class="feature-card__text">Packed with care, dispatched within 24 hours. Nationwide delivery with real-time tracking so your jersey arrives matchday-ready.</p>
                        <div class="feature-card__line" aria-hidden="true"></div>
                    </article>

                    <article class="feature-card">
                        <div class="feature-card__icon-wrap" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M15.182 15.182a4.5 4.5 0 01-6.364 0M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75zm-.375 0h.008v.015h-.008V9.75zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75zm-.375 0h.008v.015h-.008V9.75z"/>
                            </svg>
                        </div>
                        <h3 class="feature-card__title">Customer Satisfaction</h3>
                        <p class="feature-card__text">Our 30-day hassle-free return policy and responsive support team mean you shop with complete confidence, every single time.</p>
                        <div class="feature-card__line" aria-hidden="true"></div>
                    </article>

                </div>
            </div>
        </section>

        <!-- ═══════════════════════════════════════════════
             STATS / HIGHLIGHTS
        ════════════════════════════════════════════════ -->
        <section class="section stats" aria-labelledby="stats-heading">
            <div class="stats__bg" aria-hidden="true"></div>
            <div class="container">
                <h2 id="stats-heading" class="visually-hidden">Our Numbers</h2>
                <div class="stats__grid">

                    <div class="stat-item">
                        <span class="stat-item__number" data-target="50">0</span>
                        <span class="stat-item__suffix">+</span>
                        <span class="stat-item__label">Happy Customers</span>
                    </div>

                    <div class="stat-item">
                        <span class="stat-item__number" data-target="100">0</span>
                        <span class="stat-item__suffix">+</span>
                        <span class="stat-item__label">Jerseys Sold</span>
                    </div>

                    <div class="stat-item">
                        <span class="stat-item__number" data-target="50">0</span>
                        <span class="stat-item__suffix">+</span>
                        <span class="stat-item__label">Clubs Covered</span>
                    </div>

                    <div class="stat-item">
                        <span class="stat-item__number" data-target="99">0</span>
                        <span class="stat-item__suffix">%</span>
                        <span class="stat-item__label">Satisfaction Rate</span>
                    </div>

                </div>
            </div>
        </section>

        <!-- ═══════════════════════════════════════════════
             CALL TO ACTION
        ════════════════════════════════════════════════ -->
        <section class="section cta" aria-labelledby="cta-heading">
            <div class="cta__bg-pattern" aria-hidden="true"></div>
            <div class="container cta__inner">
                <span class="section__label">Ready to Represent?</span>
                <h2 id="cta-heading" class="cta__heading">Find Your Perfect Jersey Today</h2>
                <p class="cta__sub">Hundreds of authentic kits. Your club is waiting.</p>
                <a href="product.php" class="cta__button" aria-label="Browse all jerseys in our shop">
                    <span>Shop Now</span>
                    <svg class="cta__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M17.25 8.25L21 12m0 0l-3.75 3.75M21 12H3"/>
                    </svg>
                </a>
            </div>
        </section>

    </main>

    <?php include 'footer.php'; ?>

    <!-- External JS -->
    <script src="script/about.js"></script>

</body>
</html>