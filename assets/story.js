document.addEventListener('DOMContentLoaded', () => {

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- preloader ---------- */
  const preloader = document.querySelector('.preloader');
  if (preloader) setTimeout(() => preloader.classList.add('hidden'), 1800);

  /* ---------- scroll-linked parallax variable (hero decorative shapes) ---------- */
  if (!reducedMotion) {
    document.addEventListener('scroll', () => {
      document.documentElement.style.setProperty('--scrollY', window.scrollY);
    }, { passive: true });
  }

  /* ---------- 3D tilt on hover/touch — photos and cards feel alive, not static ---------- */
  if (!reducedMotion) {
    const tiltEls = document.querySelectorAll(
      '.bio-photo, .bio-photo-inset, .gallery-item, .value-card, .tl-card, .contact-card, .hero-photo-frame'
    );
    const applyTilt = (el, clientX, clientY) => {
      const rect = el.getBoundingClientRect();
      const px = (clientX - rect.left) / rect.width;
      const py = (clientY - rect.top) / rect.height;
      const rotateY = (px - 0.5) * 12;
      const rotateX = (0.5 - py) * 12;
      el.style.transition = 'transform .08s ease';
      el.style.transform = `perspective(800px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-4px) scale(1.015)`;
    };
    const resetTilt = (el) => {
      el.style.transition = 'transform .6s cubic-bezier(.22,1,.36,1)';
      el.style.transform = '';
    };
    tiltEls.forEach(el => {
      el.classList.add('tilt-el');
      el.addEventListener('mousemove', (e) => applyTilt(el, e.clientX, e.clientY));
      el.addEventListener('mouseleave', () => resetTilt(el));
      el.addEventListener('touchstart', (e) => {
        const t = e.touches[0];
        if (t) applyTilt(el, t.clientX, t.clientY);
      }, { passive: true });
      el.addEventListener('touchmove', (e) => {
        const t = e.touches[0];
        if (t) applyTilt(el, t.clientX, t.clientY);
      }, { passive: true });
      el.addEventListener('touchend', () => resetTilt(el));
    });
  }

  /* ---------- button ripple: tactile click feedback ---------- */
  if (!reducedMotion) {
    document.querySelectorAll('.btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const rect = btn.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height) * 1.6;
        const ripple = document.createElement('span');
        ripple.className = 'btn-ripple';
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
        ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
        btn.appendChild(ripple);
        ripple.addEventListener('animationend', () => ripple.remove());
      });
    });
  }

  /* ---------- celebrate button: brief confetti flourish ---------- */
  const celebrateBtn = document.getElementById('celebrateBtn');
  if (celebrateBtn && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const petalColors = ['#c69a3e', '#e2c375', '#C41E3A', '#DC143C'];
    celebrateBtn.addEventListener('click', (e) => {
      const rect = celebrateBtn.getBoundingClientRect();
      const originX = rect.left + rect.width / 2;
      const originY = rect.top;
      const count = window.innerWidth < 700 ? 18 : 30;
      for (let i = 0; i < count; i++) {
        const petal = document.createElement('span');
        const size = 5 + Math.random() * 6;
        const drift = (Math.random() - 0.5) * 260;
        const fall = 220 + Math.random() * 200;
        const spin = Math.random() * 520 - 260;
        petal.style.cssText = [
          'position:fixed', 'z-index:6000', 'pointer-events:none',
          'left:' + originX + 'px', 'top:' + originY + 'px',
          'width:' + size + 'px', 'height:' + (size * 1.6) + 'px',
          'background:' + petalColors[i % petalColors.length],
          'border-radius:2px',
          'opacity:.95',
          'transform:translate(-50%,-50%)'
        ].join(';');
        document.body.appendChild(petal);
        const anim = petal.animate([
          { transform: 'translate(-50%,-50%) rotate(0deg)', opacity: .95 },
          { transform: 'translate(calc(-50% + ' + drift + 'px), ' + fall + 'px) rotate(' + spin + 'deg)', opacity: 0 }
        ], { duration: 1100 + Math.random() * 500, easing: 'cubic-bezier(.25,.6,.4,1)' });
        anim.onfinish = () => petal.remove();
      }
    });
  }

  /* ---------- scroll progress + header state ---------- */
  const progressBar = document.querySelector('.scroll-progress');
  const header = document.querySelector('.site-header');
  const backTop = document.querySelector('.back-top');
  const onScroll = () => {
    const scrollTop = window.scrollY;
    const docHeight = document.documentElement.scrollHeight - window.innerHeight;
    const pct = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
    if (progressBar) progressBar.style.width = pct + '%';
    if (header) header.classList.toggle('scrolled', scrollTop > 40);
    if (backTop) backTop.classList.toggle('visible', scrollTop > 600);
  };
  document.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
  if (backTop) backTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

  /* ---------- mobile nav ---------- */
  const menuToggle = document.querySelector('.menu-toggle');
  const mobileNav = document.querySelector('.mobile-nav');
  if (menuToggle && mobileNav) {
    const closeMobileNav = () => {
      mobileNav.classList.remove('open');
      document.body.classList.remove('menu-open');
      menuToggle.setAttribute('aria-expanded', 'false');
    };
    menuToggle.addEventListener('click', () => {
      const open = mobileNav.classList.toggle('open');
      document.body.classList.toggle('menu-open', open);
      menuToggle.setAttribute('aria-expanded', String(open));
      if (open) {
        const firstLink = mobileNav.querySelector('a');
        if (firstLink) firstLink.focus();
      } else {
        menuToggle.focus();
      }
    });
    mobileNav.querySelectorAll('a').forEach(a => a.addEventListener('click', closeMobileNav));
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && mobileNav.classList.contains('open')) closeMobileNav();
    });
  }

  /* ---------- reveal on scroll ---------- */
  const revealEls = document.querySelectorAll('.reveal, .reveal-left, .reveal-right');
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach(entry => entry.target.classList.toggle('visible', entry.isIntersecting));
    }, { threshold: 0.12, rootMargin: '0px 0px -60px 0px' });
    revealEls.forEach(el => io.observe(el));
  } else {
    revealEls.forEach(el => el.classList.add('visible'));
  }

  /* ---------- countdown ---------- */
  const body = document.body;
  const eventDateAttr = body.getAttribute('data-event-date');
  if (eventDateAttr) {
    const target = new Date(eventDateAttr).getTime();
    const dayEl = document.querySelector('[data-cd-days]');
    const hourEl = document.querySelector('[data-cd-hours]');
    const minEl = document.querySelector('[data-cd-minutes]');
    const secEl = document.querySelector('[data-cd-seconds]');
    const pad = n => String(n).padStart(2, '0');
    const tick = () => {
      const diff = target - Date.now();
      if (diff <= 0) {
        if (dayEl) dayEl.textContent = '00';
        if (hourEl) hourEl.textContent = '00';
        if (minEl) minEl.textContent = '00';
        if (secEl) secEl.textContent = '00';
        clearInterval(timer);
        return;
      }
      if (dayEl) dayEl.textContent = pad(Math.floor(diff / 86400000));
      if (hourEl) hourEl.textContent = pad(Math.floor((diff % 86400000) / 3600000));
      if (minEl) minEl.textContent = pad(Math.floor((diff % 3600000) / 60000));
      if (secEl) secEl.textContent = pad(Math.floor((diff % 60000) / 1000));
    };
    tick();
    var timer = setInterval(tick, 1000);
  }

  /* ---------- lightbox gallery ---------- */
  const lightbox = document.querySelector('.lightbox');
  if (lightbox) {
    const lightboxImage = lightbox.querySelector('.lightbox-image');
    const closeBtn = lightbox.querySelector('.lightbox-close');
    const prevBtn = lightbox.querySelector('.lightbox-prev');
    const nextBtn = lightbox.querySelector('.lightbox-next');
    const counter = lightbox.querySelector('.lightbox-counter');
    const items = Array.from(document.querySelectorAll('.gallery-item'));
    let currentIndex = 0;

    const openLightbox = (index) => {
      if (!items.length) return;
      currentIndex = (index + items.length) % items.length;
      const img = items[currentIndex].querySelector('img');
      if (!img) return;
      lightboxImage.src = img.src;
      lightboxImage.alt = img.alt || '';
      if (counter) counter.textContent = (currentIndex + 1) + ' / ' + items.length;
      lightbox.classList.add('open');
      document.body.classList.add('menu-open');
    };
    const closeLightbox = () => {
      lightbox.classList.remove('open');
      document.body.classList.remove('menu-open');
    };
    items.forEach((el, idx) => el.addEventListener('click', () => openLightbox(idx)));
    if (closeBtn) closeBtn.addEventListener('click', closeLightbox);
    if (prevBtn) prevBtn.addEventListener('click', () => openLightbox(currentIndex - 1));
    if (nextBtn) nextBtn.addEventListener('click', () => openLightbox(currentIndex + 1));
    lightbox.addEventListener('click', (e) => { if (e.target === lightbox) closeLightbox(); });
    document.addEventListener('keydown', (e) => {
      if (!lightbox.classList.contains('open')) return;
      if (e.key === 'Escape') closeLightbox();
      if (e.key === 'ArrowLeft') openLightbox(currentIndex - 1);
      if (e.key === 'ArrowRight') openLightbox(currentIndex + 1);
    });
  }

  /* ---------- background music toggle ---------- */
  const musicBtn = document.querySelector('.music-toggle');
  const audio = document.getElementById('backgroundMusic');
  if (musicBtn && audio) {
    const musicLabel = musicBtn.querySelector('.music-toggle-label');
    const STORAGE_KEY = 'lnStoryMusicState';

    const setButtonState = (playing) => {
      musicBtn.classList.toggle('playing', playing);
      musicBtn.setAttribute('aria-label', playing ? 'Pause background music' : 'Play background music');
      if (musicLabel) musicLabel.textContent = playing ? 'Pause Music' : 'Play Music';
    };

    const saveState = (playing) => {
      try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ playing, time: audio.currentTime || 0 }));
      } catch (e) { /* storage unavailable — music just won't persist across reloads */ }
    };

    let saved = null;
    try { saved = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || 'null'); } catch (e) {}

    if (saved && saved.playing) {
      const resume = () => {
        if (saved.time) { try { audio.currentTime = saved.time; } catch (e) {} }
        audio.play().then(() => setButtonState(true)).catch(() => setButtonState(false));
      };
      if (audio.readyState >= 1) resume();
      else { audio.addEventListener('loadedmetadata', resume, { once: true }); audio.load(); }
    }

    musicBtn.addEventListener('click', () => {
      if (audio.paused) {
        audio.play().then(() => saveState(true)).catch(() => {});
      } else {
        audio.pause();
        saveState(false);
      }
    });

    audio.addEventListener('play', () => setButtonState(true));
    audio.addEventListener('pause', () => setButtonState(false));

    let lastSaved = 0;
    audio.addEventListener('timeupdate', () => {
      const now = Date.now();
      if (now - lastSaved > 2000) { lastSaved = now; saveState(!audio.paused); }
    });
    window.addEventListener('pagehide', () => saveState(!audio.paused));
  }

  /* ---------- RSVP: attending toggle ---------- */
  const attendBtns = document.querySelectorAll('.attend-toggle button');
  const attendField = document.getElementById('attendingField');
  attendBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      attendBtns.forEach(b => { b.classList.remove('active'); b.setAttribute('aria-pressed', 'false'); });
      btn.classList.add('active');
      btn.setAttribute('aria-pressed', 'true');
      if (attendField) attendField.value = btn.dataset.value;
    });
  });

  /* ---------- RSVP: submit (Formspree, with WhatsApp fallback) ---------- */
  const form = document.getElementById('rsvpForm');
  if (form) {
    const successBox = document.getElementById('rsvpSuccess');
    const errorBox = document.getElementById('rsvpError');
    const submitBtn = form.querySelector('button[type="submit"]');
    const nameInput = document.getElementById('rsvpName');
    const phoneInput = document.getElementById('rsvpPhone');
    const phonePattern = /^[0-9+()\s-]{7,}$/;

    const setFieldError = (input, show) => {
      const field = input.closest('.field');
      if (field) field.classList.toggle('has-error', show);
    };

    const clearFieldError = (input) => setFieldError(input, false);
    [nameInput, phoneInput].forEach(input => {
      if (input) input.addEventListener('input', () => clearFieldError(input));
    });

    const validateForm = () => {
      let valid = true;
      if (nameInput && !nameInput.value.trim()) { setFieldError(nameInput, true); valid = false; }
      else if (nameInput) setFieldError(nameInput, false);

      if (phoneInput && !phonePattern.test(phoneInput.value.trim())) { setFieldError(phoneInput, true); valid = false; }
      else if (phoneInput) setFieldError(phoneInput, false);

      return valid;
    };

    var LIVE_HOSTS = ['manager.lailai.co.za'];
    var isLiveSite = LIVE_HOSTS.indexOf(window.location.hostname) !== -1;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (errorBox) errorBox.classList.remove('show');

      if (!validateForm()) {
        const firstInvalid = form.querySelector('.field.has-error input');
        if (firstInvalid) firstInvalid.focus();
        return;
      }

      if (!isLiveSite) {
        form.hidden = true;
        if (successBox) {
          successBox.classList.add('show');
          const successText = document.getElementById('rsvp-success-text');
          if (successText) successText.textContent = 'Preview mode — this RSVP was not actually sent. On the live site, this confirms your attendance right away.';
        }
        return;
      }

      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Sending…'; }

      const formData = new FormData(form);
      const name = formData.get('Name') || '';
      const attending = formData.get('Attending') || 'Yes';
      const guests = formData.get('Number of Guests') || '1';
      const dietary = formData.get('Dietary Requirements') || '';
      const message = formData.get('Congratulatory Message') || '';

      let posted = false;
      try {
        const res = await fetch(form.action, {
          method: 'POST',
          body: formData,
          headers: { 'Accept': 'application/json' }
        });
        posted = res.ok;
      } catch (err) {
        posted = false;
      }

      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Send RSVP'; }

      if (posted) {
        form.hidden = true;
        if (successBox) {
          successBox.classList.add('show');
          const successText = document.getElementById('rsvp-success-text');
          if (successText) successText.textContent = 'Your RSVP has been received successfully. We look forward to celebrating with you.';
        }
      } else {
        const lines = [
          'RSVP — Dr Lerato Ngobeni’s Doctoral Celebration, 28 November 2026',
          'Name: ' + name,
          'Attending: ' + attending,
          'Number of Guests: ' + guests
        ];
        if (dietary) lines.push('Dietary Requirements: ' + dietary);
        if (message) lines.push('Message: ' + message);
        const waText = encodeURIComponent(lines.join('\n'));
        window.open('https://wa.me/27824748827?text=' + waText, '_blank');

        form.hidden = true;
        if (successBox) {
          successBox.classList.add('show');
          const successText = document.getElementById('rsvp-success-text');
          if (successText) successText.textContent = 'We’ve opened WhatsApp with your RSVP ready to send — just tap send there to confirm with us.';
        }
      }
    });
  }
});
