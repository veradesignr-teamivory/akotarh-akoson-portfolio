/* =========================================================
   AKOTARH AKOSON — Shared App JS
   Handles: navbar scroll, mobile nav, reveal animations,
            counter animations, particle canvas (home only)
   ========================================================= */

/* ---- Navbar scroll blur ---- */
(function () {
  const navbar = document.getElementById('navbar');
  if (!navbar) return;
  const onScroll = () => navbar.classList.toggle('scrolled', window.scrollY > 40);
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
})();

/* ---- Mobile nav ---- */
(function () {
  const hamburger = document.getElementById('hamburger');
  const mobileNav = document.getElementById('mobile-nav');
  const closeBtn  = document.getElementById('nav-close');
  if (!hamburger || !mobileNav) return;

  hamburger.addEventListener('click', () => mobileNav.classList.add('open'));
  if (closeBtn) closeBtn.addEventListener('click', () => mobileNav.classList.remove('open'));
  mobileNav.querySelectorAll('a').forEach(a =>
    a.addEventListener('click', () => mobileNav.classList.remove('open'))
  );
})();

/* ---- Scroll reveal ---- */
(function () {
  const els = document.querySelectorAll('.reveal');
  if (!els.length) return;
  const io = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('visible');
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
  els.forEach(el => io.observe(el));
})();

/* ---- Counter animation ---- */
(function () {
  const counters = document.querySelectorAll('[data-target]');
  if (!counters.length) return;

  function animateCounter(el) {
    const target   = +el.dataset.target;
    const suffix   = el.dataset.suffix || '';
    const duration = 1800;
    const steps    = Math.max(target, 60);
    const interval = duration / steps;
    let current    = 0;
    const timer    = setInterval(() => {
      current = Math.min(current + Math.ceil(target / steps), target);
      el.textContent = current + suffix;
      if (current >= target) clearInterval(timer);
    }, interval);
  }

  const io = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.querySelectorAll('[data-target]').forEach(animateCounter);
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.3 });

  /* observe the closest section ancestor */
  counters.forEach(el => {
    const section = el.closest('section, .stats-bar, div') || el;
    io.observe(section);
  });
})();

/* ---- Hero particle canvas (index.html only) ---- */
(function () {
  const canvas = document.getElementById('hero-canvas');
  if (!canvas) return;

  const ctx = canvas.getContext('2d');
  const PARTICLE_COUNT = 90;
  const CONNECT_DIST   = 130;
  let particles = [];
  let animId;

  function resize() {
    canvas.width  = canvas.offsetWidth;
    canvas.height = canvas.offsetHeight;
  }

  function Particle() {
    this.reset();
  }
  Particle.prototype.reset = function () {
    this.x       = Math.random() * canvas.width;
    this.y       = Math.random() * canvas.height;
    this.vx      = (Math.random() - 0.5) * 0.45;
    this.vy      = (Math.random() - 0.5) * 0.45;
    this.r       = Math.random() * 1.6 + 0.4;
    this.opacity = Math.random() * 0.55 + 0.15;
    this.color   = Math.random() > 0.55 ? '0,212,255' : '123,47,255';
  };
  Particle.prototype.update = function () {
    this.x += this.vx;
    this.y += this.vy;
    if (this.x < -10) this.x = canvas.width  + 10;
    if (this.x > canvas.width  + 10) this.x = -10;
    if (this.y < -10) this.y = canvas.height + 10;
    if (this.y > canvas.height + 10) this.y = -10;
  };
  Particle.prototype.draw = function () {
    ctx.beginPath();
    ctx.arc(this.x, this.y, this.r, 0, Math.PI * 2);
    ctx.fillStyle = `rgba(${this.color},${this.opacity})`;
    ctx.fill();
  };

  function init() {
    particles = Array.from({ length: PARTICLE_COUNT }, () => new Particle());
  }

  function draw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    /* connections */
    for (let i = 0; i < particles.length; i++) {
      for (let j = i + 1; j < particles.length; j++) {
        const dx   = particles[i].x - particles[j].x;
        const dy   = particles[i].y - particles[j].y;
        const dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < CONNECT_DIST) {
          const alpha = (1 - dist / CONNECT_DIST) * 0.2;
          ctx.beginPath();
          ctx.moveTo(particles[i].x, particles[i].y);
          ctx.lineTo(particles[j].x, particles[j].y);
          ctx.strokeStyle = `rgba(0,212,255,${alpha})`;
          ctx.lineWidth   = 0.5;
          ctx.stroke();
        }
      }
    }

    /* particles */
    particles.forEach(p => { p.update(); p.draw(); });

    animId = requestAnimationFrame(draw);
  }

  function start() {
    resize();
    init();
    if (animId) cancelAnimationFrame(animId);
    draw();
  }

  start();

  let resizeTimer;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(start, 200);
  });

  /* pause when tab hidden to save CPU */
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      cancelAnimationFrame(animId);
    } else {
      draw();
    }
  });
})();

/* ---- Smooth active nav highlight ---- */
(function () {
  const path = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav-links a').forEach(a => {
    const href = a.getAttribute('href');
    if (href === path || (path === '' && href === 'index.html')) {
      a.classList.add('active');
    } else {
      a.classList.remove('active');
    }
  });
})();
