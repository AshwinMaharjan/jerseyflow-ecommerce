<?php
/**
 * JerseyFlow — Clubs Section Component
 * File: clubs_section.php
 *
 * Usage in homepage.php:
 *   <?php include("clubs_section.php"); ?>
 *
 * To add/remove clubs, edit the $clubs array below.
 * Each entry:
 *   'img'   => path to the club logo image
 *   'name'  => display name shown under the logo
 *   'page'  => URL this card links to
*/

$clubs = [
    [
        'img'  => 'images/clubs/manu.png',
        'name' => 'Man United',
        'page' => 'clubs.php?club=manchester-united',
    ],
    [
        'img'  => 'images/clubs/real_madrid.png',
        'name' => 'Real Madrid',
        'page' => 'clubs.php?club=real-madrid',
    ],
    [
        'img'  => 'images/clubs/barcelona.png',
        'name' => 'FC Barcelona',
        'page' => 'clubs.php?club=barcelona',
    ],
    [
        'img'  => 'images/clubs/arsenal.png',
        'name' => 'Arsenal',
        'page' => 'clubs.php?club=arsenal',
    ],
    [
        'img'  => 'images/clubs/juventus.png',
        'name' => 'Juventus',
        'page' => 'clubs.php?club=juventus',
    ],
    [
        'img'  => 'images/clubs/liverpool.png',
        'name' => 'Liverpool FC',
        'page' => 'clubs.php?club=liverpool',
    ],
];
?>

<!-- Clubs Section CSS -->
<link rel="stylesheet" href="style/clubs_section.css" />

<!-- ══════════════════════════ CLUBS ══════════════════════════ -->
<section class="jf-clubs" id="jfClubs" aria-label="Shop by Club">

  <!-- Heading -->
  <div class="jf-clubs__heading">
    <p class="jf-clubs__sub">Browse by</p>
    <h2>Top <span>Clubs</span></h2>
    <div class="jf-clubs__heading-line"></div>
  </div>

  <!-- Club Cards Grid -->
  <div class="jf-clubs__grid">
    <?php foreach ($clubs as $club): ?>
      <a
        href="<?= htmlspecialchars($club['page']) ?>"
        class="jf-club-card"
        aria-label="Shop <?= htmlspecialchars($club['name']) ?> jerseys"
      >
        <div class="jf-club-card__img-wrap">
          <img
            src="<?= htmlspecialchars($club['img']) ?>"
            alt="<?= htmlspecialchars($club['name']) ?> logo"
            loading="lazy"
            draggable="false"
          />
        </div>

        <span class="jf-club-card__name">
          <?= htmlspecialchars($club['name']) ?>
        </span>

        <!-- hover tag -->
        <span class="jf-club-card__tag" aria-hidden="true">
          Shop Jerseys
        </span>
      </a>
    <?php endforeach; ?>
  </div>

</section>