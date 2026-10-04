(() => {
  'use strict';
  document.querySelectorAll('.mjsp').forEach((root) => {
    if (root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    const form = root.querySelector('form');
    const photo = form.elements.photo;
    const status = root.querySelector('.mjsp__status');
    const result = root.querySelector('.mjsp__result');
    const selected = root.querySelector('.mjsp__selected');
    const submit = form.querySelector('button[type=submit]');
    let originalUrl = null, generatedUrl = null, expiry = null, busy = false;
    const say = (message, error = false) => { status.textContent = message; status.dataset.error = String(error); };
    const clearResult = () => {
      clearTimeout(expiry);
      if (generatedUrl) URL.revokeObjectURL(generatedUrl);
      generatedUrl = null;
      root.querySelector('.mjsp__generated').removeAttribute('src');
      root.querySelector('.mjsp__original').removeAttribute('src');
      result.hidden = true;
    };
    const clearAll = () => {
      clearResult();
      if (originalUrl) URL.revokeObjectURL(originalUrl);
      originalUrl = null; selected.removeAttribute('src'); selected.hidden = true;
      photo.value = ''; form.elements.consent.checked = false;
    };
    photo.addEventListener('change', () => {
      clearResult();
      if (originalUrl) URL.revokeObjectURL(originalUrl);
      originalUrl = null; selected.hidden = true; selected.removeAttribute('src');
      const file = photo.files[0];
      if (!file) return;
      if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        say('Choose a JPG, PNG or WebP photo. Export HEIC as JPG first.', true); photo.value = ''; return;
      }
      originalUrl = URL.createObjectURL(file); selected.src = originalUrl; selected.hidden = false; say('Photo selected. Describe the hair you would like to try.');
    });
    async function post(data, signal) {
      const response = await fetch(root.dataset.endpoint, { method: 'POST', credentials: 'same-origin', cache: 'no-store', body: data, signal });
      let json;
      try { json = await response.json(); } catch (_) { throw new Error('The server could not complete this request. Please try again later.'); }
      if (!response.ok || !json.success) throw new Error(json.data?.message || 'Preview is unavailable. Please try again later.');
      return json.data;
    }
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (busy || !form.reportValidity()) return;
      busy = true; clearResult();
      const data = new FormData(form);
      const controls = [...form.elements]; controls.forEach(el => { el.disabled = true; });
      form.setAttribute('aria-busy', 'true'); submit.textContent = 'Creating your preview…';
      say('Preparing your photo. Your preview may take up to a few minutes.');
      const controller = new AbortController();
      const timeout = setTimeout(() => controller.abort(), 175000);
      const progress = setTimeout(() => say('Still creating your look. Please keep this page open.'), 30000);
      try {
        const sessionData = new FormData(); sessionData.set('action', 'mjsp_session');
        const session = await post(sessionData, controller.signal);
        if (data.get('photo').size > session.maxBytes) throw new Error('Your photo is too large. Please choose a smaller image.');
        data.set('action', 'mjsp_generate'); data.set('nonce', session.nonce);
        const response = await post(data, controller.signal);
        const bytes = Uint8Array.from(atob(response.image), c => c.charCodeAt(0));
        generatedUrl = URL.createObjectURL(new Blob([bytes], { type: 'image/jpeg' }));
        root.querySelector('.mjsp__original').src = originalUrl;
        root.querySelector('.mjsp__generated').src = generatedUrl;
        root.querySelector('.mjsp__applied').textContent = `Hair details applied: ${response.applied}`;
        result.hidden = false; say('Your style preview is ready.'); result.focus();
        expiry = setTimeout(() => { clearAll(); say('Your private preview has expired. Select a photo to start again.'); }, response.lifetime * 1000);
      } catch (error) {
        say(error.name === 'AbortError' ? 'This preview took too long. Please wait a few minutes before trying again.' : error.message, true); status.focus();
      } finally {
        clearTimeout(timeout); clearTimeout(progress); busy = false;
        controls.forEach(el => { el.disabled = false; }); form.removeAttribute('aria-busy'); submit.textContent = 'Preview my look ↗';
      }
    });
    root.querySelector('.mjsp__again').addEventListener('click', () => { clearResult(); form.elements.hairstyle.focus(); say('Try a different cut, color or texture.'); });
    window.addEventListener('pagehide', clearAll);
  });
})();
