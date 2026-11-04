const listings = {
  casa: "https://www.lavidabelize.com/la-vida-belize---casa-212730",
  casita: "https://www.lavidabelize.com/la-vida-belize-casita-212729",
  studio: "https://www.lavidabelize.com/la-vida-belize---studio-213571",
  all: "https://www.lavidabelize.com/properties",
};

const capacity = { casa: 6, casita: 3, studio: 2 };

const gallery = {
  casa: {
    name: "The Casa",
    pill: "The spacious beachfront home",
    desc: "A larger retreat for up to six guests, with generous living spaces, the Casa pool, and easy access to the beach and pier.",
    photos: [
      "assets/image-06-d8a97bf200a4.webp",
      "assets/image-16-88a24d20c11f.webp",
      "assets/image-17-e83799aff507.webp",
      "assets/image-18-b6857a39f071.webp",
      "assets/image-19-5553374d80b4.webp",
      "assets/image-20-d499b5bb71fe.webp",
      "assets/image-09-6a1b93b083df.webp",
      "assets/image-21-7668f1c00c86.webp",
    ],
  },
  casita: {
    name: "The Casita",
    pill: "A romantic beachside hideaway",
    desc: "A cozy casita near the sea with its own plunge pool and wooden deck.",
    photos: [
      "assets/image-07-b7515f3cf8d1.webp",
      "assets/image-15-d4171de3d77a.webp",
      "assets/image-22-3518a6b58905.webp",
      "assets/image-23-2de373791055.webp",
      "assets/image-24-178291535577.webp",
      "assets/image-25-5336b3786888.webp",
      "assets/image-26-681f9132fa7a.webp",
      "assets/image-14-2c6c09ac4a38.webp",
    ],
  },
  studio: {
    name: "The Studio",
    pill: "An easygoing coastal home base",
    desc: "A compact, inviting studio for two with an open-plan living area and shaded patio.",
    photos: [
      "assets/image-08-4dfe2a0731af.webp",
      "assets/image-27-9a0127eca9aa.webp",
      "assets/image-28-2d5f2505db75.webp",
      "assets/image-29-8ad2ad659601.webp",
      "assets/image-30-77e797c1ae17.webp",
      "assets/image-31-294ac078a142.webp",
      "assets/image-32-8702574cbce1.webp",
    ],
  },
};

const reviews = [
  {
    quote: "From the moment we saw the casita, we knew we chose the right spot. Perfectly tended and welcoming… we felt as if it was our own oasis.",
    name: "Michael B.",
    meta: "La Vida Casita · November 2025",
  },
  {
    quote: "La Vida Belize is truly a home away from home. The house is beautiful and well-maintained. The views are amazing and we caught fish just off the beach via the kayak.",
    name: "Jason G.",
    meta: "La Vida Belize · November 2025",
  },
  {
    quote: "Came here for our honeymoon and it was everything we could’ve imagined. Close enough to the village, far enough away for complete privacy.",
    name: "Alyssa T.",
    meta: "La Vida Belize · October 2025",
  },
  {
    quote: "Wonderful! We loved our stay at La Vida. We wanted a week away from our cold winter and this was perfect!",
    name: "Karen N.",
    meta: "La Vida Belize · March 2024",
  },
];

const $ = (s, root = document) => root.querySelector(s);
const $$ = (s, root = document) => [...root.querySelectorAll(s)];

const year = $("#year");
if (year) year.textContent = String(new Date().getFullYear());

const header = $("#header");
const menu = $("#navLinks");
const menuBtn = $("#menuToggle");
function syncHeader() {
  if (header) header.classList.toggle("fixed", window.scrollY > 80);
}
window.addEventListener("scroll", syncHeader, { passive: true });
syncHeader();

if (menuBtn && menu) {
  menuBtn.addEventListener("click", () => {
    const open = menu.classList.toggle("show");
    menuBtn.setAttribute("aria-expanded", String(open));
    menuBtn.textContent = open ? "✕" : "☰";
    document.body.classList.toggle("menu-open", open);
  });
  $$("#navLinks a").forEach((a) =>
    a.addEventListener("click", () => {
      menu.classList.remove("show");
      document.body.classList.remove("menu-open");
      menuBtn.setAttribute("aria-expanded", "false");
      menuBtn.textContent = "☰";
    }),
  );
}

if ("IntersectionObserver" in window) {
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((e) => {
        if (e.isIntersecting) {
          e.target.classList.add("in");
          observer.unobserve(e.target);
        }
      });
    },
    { threshold: 0.09, rootMargin: "0px 0px -25px 0px" },
  );
  $$(".reveal").forEach((el) => observer.observe(el));
} else {
  $$(".reveal").forEach((el) => el.classList.add("in"));
}

const reduced = window.matchMedia("(prefers-reduced-motion: reduce)");
const smallScreen = window.matchMedia("(max-width: 767px)");
const video = $(".hero-video");
const videoToggle = $(".video-toggle");

function saveDataOn() {
  return Boolean(navigator.connection && navigator.connection.saveData);
}

function videoAllowed() {
  return !reduced.matches && !saveDataOn();
}

function stopVideo() {
  if (!video) return;
  video.pause();
  video.removeAttribute("src");
  const source = video.querySelector("source");
  if (source) source.removeAttribute("src");
  video.load();
  if (videoToggle) videoToggle.hidden = true;
}

function startVideo() {
  if (!video) return;
  if (!videoAllowed()) {
    stopVideo();
    return;
  }
  const source = video.querySelector("source");
  const src = video.dataset.src || (source && source.dataset.src);
  if (src) {
    if (source) source.src = src;
    else video.src = src;
  }
  video.muted = true;
  video.loop = true;
  video.playsInline = true;
  const pending = video.play();
  if (!pending) return;
  pending
    .then(() => {
      video.classList.add("is-playing");
      if (videoToggle) {
        videoToggle.hidden = false;
        videoToggle.textContent = "Pause pier video";
      }
    })
    .catch(stopVideo);
}

if (video) startVideo();
if (videoToggle) {
  videoToggle.addEventListener("click", () => {
    if (video.paused) startVideo();
    else video.pause();
    videoToggle.textContent = video.paused ? "Play pier video" : "Pause pier video";
  });
}
reduced.addEventListener("change", startVideo);
if (navigator.connection) navigator.connection.addEventListener("change", startVideo);

let galleryName = "casa";
let galleryIndex = 0;
let previousFocus;
function paintGallery() {
  const g = gallery[galleryName];
  if (!g || !$("#galleryModal")) return;
  $("#modalPill").textContent = g.pill;
  $("#galleryTitle").textContent = g.name;
  $("#modalDesc").textContent = g.desc;
  $("#modalImage").src = g.photos[galleryIndex];
  $("#modalImage").alt = `${g.name} photo ${galleryIndex + 1}`;
  $("#galleryCount").textContent = `${galleryIndex + 1} / ${g.photos.length}`;
}
function openGallery(name) {
  previousFocus = document.activeElement;
  galleryName = name;
  galleryIndex = 0;
  paintGallery();
  $("#galleryModal").classList.add("active");
  document.body.style.overflow = "hidden";
  $("#modalClose").focus();
}
function closeGallery() {
  const modal = $("#galleryModal");
  if (!modal) return;
  modal.classList.remove("active");
  document.body.style.overflow = "";
  previousFocus?.focus();
}
$$("[data-gallery]").forEach((b) => b.addEventListener("click", () => openGallery(b.dataset.gallery)));
$("#modalClose")?.addEventListener("click", closeGallery);
$("#galleryModal")?.addEventListener("click", (e) => {
  if (e.target.id === "galleryModal") closeGallery();
});
$("#galleryPrev")?.addEventListener("click", () => {
  const g = gallery[galleryName];
  galleryIndex = (galleryIndex - 1 + g.photos.length) % g.photos.length;
  paintGallery();
});
$("#galleryNext")?.addEventListener("click", () => {
  const g = gallery[galleryName];
  galleryIndex = (galleryIndex + 1) % g.photos.length;
  paintGallery();
});

let ri = 0;
function paintReview() {
  if (!$("#reviewText")) return;
  $("#reviewText").textContent = reviews[ri].quote;
  $("#reviewName").textContent = reviews[ri].name;
  $("#reviewMeta").textContent = reviews[ri].meta;
}
$("#reviewPrev")?.addEventListener("click", () => {
  ri = (ri - 1 + reviews.length) % reviews.length;
  paintReview();
});
$("#reviewNext")?.addEventListener("click", () => {
  ri = (ri + 1) % reviews.length;
  paintReview();
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeGallery();
});

const mobileBook = $(".mobile-book");
const booking = $("#booking");
function setMobileBook(hidden) {
  if (!mobileBook) return;
  mobileBook.classList.toggle("is-hidden", hidden);
}
if (mobileBook && window.matchMedia("(max-width: 680px)").matches) {
  document.body.classList.add("has-mobile-book");
}
if (mobileBook && booking && "IntersectionObserver" in window) {
  const barWatch = new IntersectionObserver(
    (entries) => setMobileBook(entries.some((entry) => entry.isIntersecting)),
    { threshold: 0.2 },
  );
  barWatch.observe(booking);
}

const cal = $("#datePopover");
const gp = $("#guestPopover");
const ci = $("#checkin");
const co = $("#checkout");
const gt = $("#guestTrigger");
const cl = $("#checkinTrigger");
const out = $("#checkoutTrigger");
const form = $("#bookingForm");

if (form && cal && gp && ci && co && gt && cl && out) {
  const dateToday = new Date();
  dateToday.setHours(12, 0, 0, 0);
  const todayIso = `${dateToday.getFullYear()}-${String(dateToday.getMonth() + 1).padStart(2, "0")}-${String(dateToday.getDate()).padStart(2, "0")}`;
  const pad = (n) => String(n).padStart(2, "0");
  const iso = (y, m, d) => `${y}-${pad(m + 1)}-${pad(d)}`;
  const pretty = (s) => {
    if (!s) return "Add date";
    const [y, m, d] = s.split("-").map(Number);
    return new Date(y, m - 1, d, 12).toLocaleDateString("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
    });
  };
  let month = new Date(dateToday.getFullYear(), dateToday.getMonth(), 1, 12);
  let mode = "checkin";
  let active = null;
  let hovered = null;
  const people = { adults: 1, children: 0 };
  const startOfMonth = (d) => new Date(d.getFullYear(), d.getMonth(), 1, 12);

  function setExpanded() {
    cl.setAttribute("aria-expanded", String(active === cal && mode === "checkin"));
    out.setAttribute("aria-expanded", String(active === cal && mode === "checkout"));
    gt.setAttribute("aria-expanded", String(active === gp));
  }
  function closePopup() {
    if (active) active.hidden = true;
    active = null;
    setExpanded();
    setMobileBook(false);
  }
  function place(panel, trigger) {
    const r = trigger.getBoundingClientRect();
    const margin = 12;
    const w = panel.offsetWidth;
    const h = panel.offsetHeight;
    const bar = mobileBook && !mobileBook.classList.contains("is-hidden") && getComputedStyle(mobileBook).display !== "none" ? 60 : 0;
    const vw = innerWidth;
    const vh = innerHeight - bar;
    let left = Math.min(Math.max(margin, r.left), vw - w - margin);
    let top = r.bottom + 9 + h <= vh - margin ? r.bottom + 9 : r.top - h - 11;
    top = Math.min(vh - h - margin, Math.max(margin, top));
    panel.style.left = `${Math.max(margin, left)}px`;
    panel.style.top = `${top}px`;
  }
  function showPopup(panel, trigger) {
    if (active && active !== panel) active.hidden = true;
    active = panel;
    panel.hidden = false;
    setExpanded();
    setMobileBook(true);
    requestAnimationFrame(() => place(panel, trigger));
  }
  function syncDateText() {
    cl.textContent = pretty(ci.value);
    out.textContent = pretty(co.value);
  }
  function renderCalendar() {
    const container = $("#calendarMonths");
    container.textContent = "";
    const names = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
    const monthsToShow = innerWidth < 700 ? 1 : 2;
    for (let shift = 0; shift < monthsToShow; shift += 1) {
      const m = new Date(month.getFullYear(), month.getMonth() + shift, 1, 12);
      const y = m.getFullYear();
      const mon = m.getMonth();
      const block = document.createElement("div");
      block.className = "calendar-month";
      const title = document.createElement("div");
      title.className = "calendar-month-title";
      title.textContent = m.toLocaleDateString("en-US", { month: "long", year: "numeric" });
      block.appendChild(title);
      const weekdays = document.createElement("div");
      weekdays.className = "calendar-weekdays";
      names.forEach((name) => {
        const e = document.createElement("span");
        e.textContent = name;
        weekdays.appendChild(e);
      });
      block.appendChild(weekdays);
      const grid = document.createElement("div");
      grid.className = "calendar-days";
      const leading = (m.getDay() + 6) % 7;
      const days = new Date(y, mon + 1, 0).getDate();
      const cells = Math.ceil((leading + days) / 7) * 7;
      for (let i = 0; i < cells; i += 1) {
        if (i < leading || i >= leading + days) {
          const empty = document.createElement("span");
          empty.className = "calendar-blank";
          grid.appendChild(empty);
          continue;
        }
        const day = i - leading + 1;
        const s = iso(y, mon, day);
        const b = document.createElement("button");
        b.type = "button";
        b.className = "calendar-day";
        b.textContent = String(day);
        b.dataset.date = s;
        if (s < todayIso) b.disabled = true;
        if (s === todayIso) b.classList.add("today");
        if (s === ci.value || s === co.value) b.classList.add("selected");
        const hi = co.value || (mode === "checkout" ? hovered : null);
        if (ci.value && hi && s > ci.value && s < hi) b.classList.add("in-range");
        b.addEventListener("click", () => pickDate(s));
        b.addEventListener("pointerenter", () => {
          if (mode === "checkout") {
            hovered = s;
            updateRangeHighlight();
          }
        });
        grid.appendChild(b);
      }
      block.appendChild(grid);
      container.appendChild(block);
    }
    $("#calendarPrev").disabled = month.getFullYear() === dateToday.getFullYear() && month.getMonth() === dateToday.getMonth();
    $("#calendarPrompt").textContent = mode === "checkin" ? "Choose your check-in date" : "Choose your check-out date";
  }
  function updateRangeHighlight() {
    $$(".calendar-day[data-date]").forEach((b) => {
      const s = b.dataset.date;
      const hi = co.value || (mode === "checkout" ? hovered : null);
      b.classList.toggle("in-range", Boolean(ci.value && hi && s > ci.value && s < hi));
    });
  }
  function pickDate(s) {
    hovered = null;
    if (mode === "checkin" || !ci.value) {
      ci.value = s;
      co.value = "";
      mode = "checkout";
      syncDateText();
      renderCalendar();
      setExpanded();
      return;
    }
    if (s <= ci.value) return;
    co.value = s;
    syncDateText();
    renderCalendar();
    closePopup();
  }
  function openDate(which) {
    mode = which;
    hovered = null;
    const value = which === "checkin" ? ci.value : co.value || ci.value;
    if (value) {
      const [y, m] = value.split("-").map(Number);
      month = new Date(y, m - 1, 1, 12);
      if (month < startOfMonth(dateToday)) month = startOfMonth(dateToday);
    }
    renderCalendar();
    showPopup(cal, which === "checkin" ? cl : out);
  }
  cl.addEventListener("click", () => (active === cal && mode === "checkin" ? closePopup() : openDate("checkin")));
  out.addEventListener("click", () => (active === cal && mode === "checkout" ? closePopup() : openDate("checkout")));
  $("#calendarPrev").addEventListener("click", () => {
    month = new Date(month.getFullYear(), month.getMonth() - 1, 1, 12);
    renderCalendar();
    place(cal, mode === "checkin" ? cl : out);
  });
  $("#calendarNext").addEventListener("click", () => {
    month = new Date(month.getFullYear(), month.getMonth() + 1, 1, 12);
    renderCalendar();
    place(cal, mode === "checkin" ? cl : out);
  });
  $("#clearDates").addEventListener("click", () => {
    ci.value = "";
    co.value = "";
    mode = "checkin";
    hovered = null;
    syncDateText();
    renderCalendar();
    setExpanded();
  });
  const total = () => people.adults + people.children;
  function guestsSummary() {
    const n = total();
    return `${n} guest${n === 1 ? "" : "s"}`;
  }
  function updateGuests() {
    gt.innerHTML = `${guestsSummary()} <span class="booking-chevron" aria-hidden="true">⌄</span>`;
    $("#guests").value = String(total());
    $("#adultCount").textContent = people.adults;
    $("#childCount").textContent = people.children;
    $$("[data-counter]").forEach((b) => {
      const type = b.dataset.counter;
      const step = Number(b.dataset.step);
      b.disabled = step < 0 ? people[type] <= (type === "adults" ? 1 : 0) : total() >= 11;
    });
  }
  gt.addEventListener("click", () => {
    if (active === gp) {
      closePopup();
      return;
    }
    updateGuests();
    showPopup(gp, gt);
  });
  $$("[data-counter]").forEach((b) =>
    b.addEventListener("click", () => {
      const type = b.dataset.counter;
      const step = Number(b.dataset.step);
      const low = type === "adults" ? 1 : 0;
      if (people[type] + step < low) return;
      if (step > 0 && total() >= 11) return;
      people[type] += step;
      updateGuests();
    }),
  );
  $("#guestClose")?.addEventListener("click", closePopup);
  document.addEventListener("pointerdown", (e) => {
    if (active && !active.contains(e.target) && !cl.contains(e.target) && !out.contains(e.target) && !gt.contains(e.target)) {
      closePopup();
    }
  });
  window.addEventListener("resize", () => {
    if (active) place(active, active === gp ? gt : mode === "checkin" ? cl : out);
  }, { passive: true });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && active) closePopup();
  });

  form.addEventListener("submit", (e) => {
    e.preventDefault();
    closePopup();
    if (!ci.value || !co.value) {
      openDate(ci.value ? "checkout" : "checkin");
      return;
    }
    if (co.value <= ci.value) {
      openDate("checkout");
      return;
    }
    const rental = $("#rental").value;
    if (capacity[rental] && total() > capacity[rental]) {
      alert(`This accommodation is listed for up to ${capacity[rental]} guests. Please adjust guest numbers or choose another stay.`);
      return;
    }
    if (rental === "group") {
      const body = `Hello La Vida Belize,\n\nI would like to enquire about booking the full property.\nCheck-in: ${ci.value}\nCheck-out: ${co.value}\nGuests: ${people.adults} adult(s), ${people.children} child(ren)\n`;
      window.location.assign(`mailto:info@lavidabelize.com?subject=${encodeURIComponent("La Vida Belize group stay inquiry")}&body=${encodeURIComponent(body)}`);
      return;
    }
    const url = new URL(listings[rental] || listings.all);
    url.searchParams.set("start_date", ci.value);
    url.searchParams.set("end_date", co.value);
    url.searchParams.set("adults", String(people.adults));
    url.searchParams.set("child", String(people.children));
    window.location.assign(url.href);
  });

  renderCalendar();
  syncDateText();
  updateGuests();
}

document.documentElement.classList.add("motion-enhanced");
if (!reduced.matches && window.innerWidth > 900) {
  const layers = [
    { section: $(".hero"), element: $(".hero-bg"), max: 20, kind: "hero" },
    { section: $(".feature-banner"), element: $(".feature-banner .bg"), max: 12 },
    { section: $(".day-media"), element: $(".day-media img"), max: 8 },
    { section: $(".group-image"), element: $(".group-image img"), max: 8 },
  ].filter((layer) => layer.section && layer.element);
  let frame = 0;
  function renderParallax() {
    frame = 0;
    const h = window.innerHeight;
    layers.forEach(({ section, element, max, kind }) => {
      const box = section.getBoundingClientRect();
      const relative = (box.top + box.height / 2 - h / 2) / ((box.height + h) / 2);
      const y = Math.round(Math.max(-1, Math.min(1, relative)) * max * 10) / 10;
      element.style.setProperty(kind === "hero" ? "--vida-hero-parallax" : "--vida-parallax", `${y}px`);
    });
  }
  window.addEventListener(
    "scroll",
    () => {
      if (!frame) frame = requestAnimationFrame(renderParallax);
    },
    { passive: true },
  );
}
