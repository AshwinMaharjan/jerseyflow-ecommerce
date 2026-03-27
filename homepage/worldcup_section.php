<?php
/**
 * JerseyFlow — FIFA World Cup 2026 Section
 * File: worldcup_section.php
 */

$products = [
    [
        'img' => 'images/worldcup/argentina_home.png',
        'name' => 'Argentina Home Kit',
        'price' => 4500,
        'old_price' => 6000,
        'country' => 'Argentina',
        'type' => 'home',
        'tag' => 'Trending'
    ],
    [
        'img' => 'images/worldcup/france_away.png',
        'name' => 'France Away Kit',
        'price' => 4200,
        'old_price' => 5200,
        'country' => 'France',
        'type' => 'away',
        'tag' => ''
    ],
    [
        'img' => 'images/worldcup/brazil_home.png',
        'name' => 'Brazil Home Kit',
        'price' => 4700,
        'old_price' => 6200,
        'country' => 'Brazil',
        'type' => 'home',
        'tag' => 'Trending'
    ],
    [
        'img' => 'images/worldcup/england_home.png',
        'name' => 'England Home Kit',
        'price' => 4100,
        'old_price' => 5100,
        'country' => 'England',
        'type' => 'home',
        'tag' => ''
    ],
];
?>

<link rel="stylesheet" href="style/worldcup_section.css" />

<section class="jf-worldcup">

  <div class="jf-worldcup__container">

    <!-- LEFT SIDE -->
    <div class="jf-worldcup__left">
      <p class="jf-worldcup__sub">FIFA 2026</p>
      <h2>World Cup <span>Collection</span></h2>

      <p class="jf-worldcup__desc">
The FIFA World Cup 2026 Jersey is a great way to show how much you love football. Made from lightweight, breathable fabric, it offers all-day comfort and a modern sporty fit. Perfect for match-day celebrations, training sessions, casual wear, or collections, this jersey is a must-have for every football supporter. Pair it with jeans, shorts, or sportswear to complete your FIFA World Cup 2026 celebration look.
      </p>

      <a href="worldcup.php" class="jf-worldcup__cta">
        View All Kits →
      </a>

      <!-- FILTERS -->
      <div class="jf-worldcup__filters">
        <button class="active" data-filter="all">All</button>
        <button data-filter="home">Home</button>
        <button data-filter="away">Away</button>
        <button data-filter="trending">Trending</button>
      </div>
    </div>

    <!-- RIGHT SIDE -->
    <div class="jf-worldcup__grid">
      <?php foreach ($products as $p): ?>
        <div 
          class="jf-product-card"
          data-type="<?= $p['type'] ?>"
          data-tag="<?= strtolower($p['tag']) ?>"
        >

          <div class="jf-product-card__img">
            <img src="<?= $p['img'] ?>" alt="<?= $p['name'] ?>">
            
            <?php if($p['tag'] === 'Trending'): ?>
              <span class="jf-badge">Trending</span>
            <?php endif; ?>
          </div>

          <div class="jf-product-card__info">
            <h4><?= $p['name'] ?></h4>

            <div class="jf-price">
              <span class="new">Rs <?= $p['price'] ?></span>
              <span class="old">Rs <?= $p['old_price'] ?></span>
            </div>
          </div>

        </div>
      <?php endforeach; ?>
    </div>

  </div>

</section>

<!-- SIMPLE FILTER JS -->
<script>
const filterBtns = document.querySelectorAll(".jf-worldcup__filters button");
const cards = document.querySelectorAll(".jf-product-card");

filterBtns.forEach(btn => {
  btn.addEventListener("click", () => {
    document.querySelector(".jf-worldcup__filters .active").classList.remove("active");
    btn.classList.add("active");

    const filter = btn.dataset.filter;

    cards.forEach(card => {
      const type = card.dataset.type;
      const tag = card.dataset.tag;

      if (filter === "all" || type === filter || tag === filter) {
        card.style.display = "block";
      } else {
        card.style.display = "none";
      }
    });
  });
});
</script>