/* ============================================================
   BASE VACATION RENTALS — frontend behaviour
   nav scroll state · mobile menu · reveal-on-scroll · hero video
   sticky availability bar
   ============================================================ */
(function () {
  "use strict";
  document.documentElement.classList.add("js");

  function init() {
    /* nav scroll state */
    var nav = document.getElementById("bvNav");
    var hero = document.querySelector(".bv-hero");
    var avail = document.getElementById("bvAvailbar");
    if (nav) {
      if (!hero) nav.classList.add("solid");
      var onScroll = function () {
        var y = window.scrollY;
        nav.classList.toggle("scrolled", y > 60);
        if (avail && hero) {
          var show = y > hero.offsetHeight - 90;
          avail.classList.toggle("show", show);
          avail.setAttribute("aria-hidden", show ? "false" : "true");
        }
      };
      window.addEventListener("scroll", onScroll, { passive: true });
      onScroll();
    }

    /* mobile menu */
    var t = document.getElementById("bvNavtoggle");
    var l = document.getElementById("bvNavlinks");
    if (t && l) {
      t.addEventListener("click", function () {
        var open = l.classList.toggle("open");
        t.classList.toggle("open", open);
        t.setAttribute("aria-expanded", open);
      });
      l.querySelectorAll("a").forEach(function (a) {
        a.addEventListener("click", function () {
          l.classList.remove("open");
          t.classList.remove("open");
          t.setAttribute("aria-expanded", "false");
        });
      });
    }

    /* reveal on scroll */
    var nodes = document.querySelectorAll(".bv-reveal:not(.in)");
    if (!("IntersectionObserver" in window)) {
      nodes.forEach(function (n) { n.classList.add("in"); });
    } else {
      var obs = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (e.isIntersecting) { e.target.classList.add("in"); obs.unobserve(e.target); }
        });
      }, { threshold: .14, rootMargin: "0px 0px -8% 0px" });
      nodes.forEach(function (n) { obs.observe(n); });
    }

    /* scene zoom-settle */
    var scenes = document.querySelectorAll(".bv-scene");
    if (scenes.length && "IntersectionObserver" in window) {
      var sobs = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (e.isIntersecting) { e.target.classList.add("in"); sobs.unobserve(e.target); }
        });
      }, { threshold: .25 });
      scenes.forEach(function (s) { sobs.observe(s); });
    }

    /* hero: photo slideshow fallback + local mp4 film */
    var slides = Array.prototype.slice.call(document.querySelectorAll(".bv-hero-media .slide"));
    slides.forEach(function (s) { if (s.dataset.img) s.style.backgroundImage = "url('" + s.dataset.img + "')"; });
    var slideStarted = false;
    function startSlideshow() {
      if (slideStarted || !slides.length) return;
      slideStarted = true;
      slides[0].classList.add("active");
      if (slides.length < 2) return;
      var i = 0;
      setInterval(function () {
        slides[i].classList.remove("active");
        i = (i + 1) % slides.length;
        slides[i].classList.add("active");
      }, 6000);
    }
    var video = document.getElementById("bvHeroVideo");
    var reduced = window.matchMedia("(prefers-reduced-motion:reduce)").matches;
    if (video && !reduced) {
      var fallback = setTimeout(startSlideshow, 6000);
      var markReady = function () {
        clearTimeout(fallback);
        video.classList.add("ready");
      };
      video.muted = true;
      video.addEventListener("playing", markReady);
      video.addEventListener("error", function () { clearTimeout(fallback); startSlideshow(); });
      // The video may already be playing before these listeners attach
      // (autoplay + fast local source) — check current state directly.
      if (!video.paused && video.readyState >= 2) {
        markReady();
      } else {
        var p = video.play();
        if (p && p.then) {
          p.then(function () { if (!video.paused) markReady(); })
           .catch(function () { clearTimeout(fallback); startSlideshow(); });
        }
      }
    } else {
      startSlideshow();
    }

    /* year stamp */
    var yr = document.getElementById("bvYr");
    if (yr) yr.textContent = new Date().getFullYear();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
