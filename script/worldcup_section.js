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
