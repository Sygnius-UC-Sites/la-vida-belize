const listings = {
  casa: "https://www.lavidabelize.com/index.php/la-vida-belize---casa-212730",
  casita: "https://www.lavidabelize.com/la-vida-belize-casita-212729",
  studio: "https://www.lavidabelize.com/la-vida-belize---studio-213571",
};

const sleeps = { casa: 6, casita: 3, studio: 2, estate: 11 };

function localISO(date) {
  const shifted = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
  return shifted.toISOString().slice(0, 10);
}

function addDays(iso, days) {
  const date = new Date(`${iso}T00:00:00`);
  date.setDate(date.getDate() + days);
  return localISO(date);
}

function syncDates(form) {
  const checkin = form.elements.checkin;
  const checkout = form.elements.checkout;
  const today = localISO(new Date());
  checkin.min = today;
  checkout.min = checkin.value ? addDays(checkin.value, 1) : addDays(today, 1);
  if (checkin.value && checkout.value && checkout.value <= checkin.value) {
    checkout.setCustomValidity("Check-out must be after check-in.");
  } else {
    checkout.setCustomValidity("");
  }
}

function syncGuests(form) {
  const guests = form.elements.guests;
  const home = form.elements.home.value;
  const cap = sleeps[home] || 11;
  guests.max = String(cap);
  if (home && Number(guests.value) > cap) {
    guests.setCustomValidity(
      home === "estate"
        ? "The three homes together sleep 11."
        : `This home sleeps ${cap}. Choose Entire estate for up to 11 guests.`,
    );
  } else {
    guests.setCustomValidity("");
  }
}

document.querySelectorAll(".booking-form").forEach((form) => {
  const panel = form.querySelector(".estate-panel");
  const refresh = () => {
    syncDates(form);
    syncGuests(form);
    if (form.elements.home.value !== "estate") panel.hidden = true;
  };
  form.elements.checkin.addEventListener("change", refresh);
  form.elements.checkout.addEventListener("change", refresh);
  form.elements.guests.addEventListener("input", refresh);
  form.elements.home.addEventListener("change", refresh);
  refresh();

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    refresh();
    if (!form.reportValidity()) return;
    const home = form.elements.home.value;
    if (home === "estate") {
      panel.hidden = false;
      const dates = panel.querySelector(".estate-dates");
      if (dates) {
        dates.textContent = `For ${form.elements.checkin.value} to ${form.elements.checkout.value}, ${form.elements.guests.value} guests. Enter those dates on each listing.`;
      }
      const firstLink = panel.querySelector("a");
      if (firstLink) firstLink.focus();
      return;
    }
    const url = new URL(listings[home]);
    url.searchParams.set("checkin", form.elements.checkin.value);
    url.searchParams.set("checkout", form.elements.checkout.value);
    url.searchParams.set("guests", form.elements.guests.value);
    window.location.assign(url.href);
  });
});

const inquiry = document.querySelector(".inquiry-form");
if (inquiry) {
  inquiry.addEventListener("submit", (event) => {
    event.preventDefault();
    if (!inquiry.reportValidity()) return;
    const name = inquiry.elements.name.value.trim();
    const email = inquiry.elements.email.value.trim();
    const message = inquiry.elements.message.value.trim();
    const body = `Name: ${name}\nEmail: ${email}\n\n${message}`;
    const href = `mailto:info@lavidabelize.com?subject=${encodeURIComponent("Stay inquiry")}&body=${encodeURIComponent(body)}`;
    window.location.assign(href);
  });
}

const nav = document.querySelector(".site-nav");
const navQuery = window.matchMedia("(max-width: 1099px)");

function syncNav() {
  if (!navQuery.matches) nav.open = true;
}

if (nav) {
  nav.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => {
      if (navQuery.matches) nav.open = false;
    });
  });
  navQuery.addEventListener("change", () => {
    nav.open = !navQuery.matches;
  });
  syncNav();
}

const header = document.querySelector(".site-header");
const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

function syncHeader() {
  header.classList.toggle("is-solid", window.scrollY > 12);
}

window.addEventListener("scroll", syncHeader, { passive: true });
syncHeader();

const video = document.querySelector(".hero-video");
const toggle = document.querySelector(".video-toggle");
const smallScreen = window.matchMedia("(max-width: 767px)");
let userPaused = false;

function saveDataOn() {
  return Boolean(navigator.connection && navigator.connection.saveData);
}

function videoAllowed() {
  return !smallScreen.matches && !reduceMotion.matches && !saveDataOn();
}

function stopVideo() {
  userPaused = false;
  video.pause();
  video.classList.remove("is-playing");
  video.removeAttribute("src");
  video.load();
  toggle.hidden = true;
}

function startVideo() {
  if (!videoAllowed()) {
    stopVideo();
    return;
  }
  if (userPaused) return;
  if (!video.getAttribute("src")) {
    video.src = video.dataset.src;
    video.muted = true;
    video.loop = true;
    video.playsInline = true;
  }
  const pending = video.play();
  if (!pending) return;
  pending.then(() => {
    video.classList.add("is-playing");
    toggle.hidden = false;
    toggle.textContent = "Pause pier video";
  }).catch(() => {
    stopVideo();
  });
}

toggle.addEventListener("click", () => {
  if (video.paused) {
    userPaused = false;
    startVideo();
    return;
  }
  userPaused = true;
  video.pause();
  toggle.textContent = "Play pier video";
});

smallScreen.addEventListener("change", startVideo);
reduceMotion.addEventListener("change", startVideo);
if (navigator.connection) navigator.connection.addEventListener("change", startVideo);
startVideo();

function setupFilm(wrap) {
  const filmstrip = wrap.querySelector(".filmstrip");
  const filmPrev = wrap.querySelector(".film-arrow--prev");
  const filmNext = wrap.querySelector(".film-arrow--next");
  if (!filmstrip || !filmPrev || !filmNext) return;

  function filmStep() {
    const card = filmstrip.querySelector("figure");
    const styles = getComputedStyle(filmstrip);
    const gap = Number.parseFloat(styles.columnGap || styles.gap) || 14;
    return (card ? card.getBoundingClientRect().width : 280) + gap;
  }

  function syncFilmArrows() {
    const max = Math.max(0, filmstrip.scrollWidth - filmstrip.clientWidth);
    const atStart = filmstrip.scrollLeft <= 2;
    const atEnd = filmstrip.scrollLeft >= max - 2;
    filmPrev.disabled = atStart;
    filmNext.disabled = atEnd;
    filmPrev.hidden = atStart;
    filmNext.hidden = atEnd;
  }

  function scrollFilm(direction) {
    const behavior = reduceMotion.matches ? "auto" : "smooth";
    filmstrip.scrollBy({ left: direction * filmStep(), behavior });
  }

  filmPrev.addEventListener("click", () => scrollFilm(-1));
  filmNext.addEventListener("click", () => scrollFilm(1));
  filmstrip.addEventListener("scroll", syncFilmArrows, { passive: true });
  window.addEventListener("resize", syncFilmArrows);
  filmstrip.querySelectorAll("img").forEach((img) => {
    if (!img.complete) img.addEventListener("load", syncFilmArrows, { once: true });
  });
  syncFilmArrows();
  requestAnimationFrame(syncFilmArrows);

  filmstrip.addEventListener("keydown", (event) => {
    if (event.key === "ArrowLeft") {
      event.preventDefault();
      scrollFilm(-1);
    }
    if (event.key === "ArrowRight") {
      event.preventDefault();
      scrollFilm(1);
    }
  });

  let filmDrag = null;

  filmstrip.addEventListener("pointerdown", (event) => {
    if (event.pointerType !== "mouse" || event.button !== 0) return;
    filmDrag = {
      id: event.pointerId,
      startX: event.clientX,
      startScroll: filmstrip.scrollLeft,
    };
    filmstrip.setPointerCapture(event.pointerId);
  });

  filmstrip.addEventListener("pointermove", (event) => {
    if (!filmDrag || event.pointerId !== filmDrag.id) return;
    const delta = event.clientX - filmDrag.startX;
    if (Math.abs(delta) > 4) filmstrip.classList.add("is-dragging");
    filmstrip.scrollLeft = filmDrag.startScroll - delta;
  });

  function endFilmDrag(event) {
    if (!filmDrag || event.pointerId !== filmDrag.id) return;
    filmDrag = null;
    filmstrip.classList.remove("is-dragging");
    syncFilmArrows();
  }

  filmstrip.addEventListener("pointerup", endFilmDrag);
  filmstrip.addEventListener("pointercancel", endFilmDrag);
}

document.querySelectorAll(".film-wrap").forEach(setupFilm);
