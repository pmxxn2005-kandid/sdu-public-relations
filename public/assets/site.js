'use strict';
(() => {
  document.querySelectorAll('input[type="password"]').forEach((input, index) => {
    if (!input.id) input.id = `password-field-${index}`;
    const wrapper = document.createElement('span');
    wrapper.className = 'password-field';
    input.before(wrapper);
    wrapper.append(input);
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'password-toggle';
    button.setAttribute('aria-controls', input.id);
    button.setAttribute('aria-pressed', 'false');
    button.setAttribute('aria-label', 'แสดงรหัสผ่าน');
    button.title = 'แสดงรหัสผ่าน';
    button.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m3 3 18 18"/></svg>';
    button.addEventListener('click', event => {
      event.preventDefault();
      const visible = input.type === 'password';
      input.type = visible ? 'text' : 'password';
      button.setAttribute('aria-pressed', String(visible));
      button.setAttribute('aria-label', visible ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน');
      button.title = visible ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน';
    });
    wrapper.append(button);
    input.form?.addEventListener('submit', () => {
      input.type = 'password';
      button.setAttribute('aria-pressed', 'false');
      button.setAttribute('aria-label', 'แสดงรหัสผ่าน');
      button.title = 'แสดงรหัสผ่าน';
    });
  });
  document.querySelectorAll('[data-post-mode]').forEach(select => {
    const form = select.closest('form');
    const link = form.querySelector('[data-post-link]');
    const input = link.querySelector('input');
    const update = () => {
      const external = select.value === 'link';
      link.hidden = !external; input.disabled = !external; input.required = external;
    };
    select.addEventListener('change', update); update();
  });
  const toggle = document.querySelector('.mobile-toggle');
  const menu = document.getElementById('mobile-nav');
  if (toggle && menu) {
    toggle.addEventListener('click', () => {
      const open = toggle.getAttribute('aria-expanded') !== 'true';
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'ปิดเมนู' : 'เปิดเมนู');
      menu.hidden = !open;
    });
    document.addEventListener('keydown', ev => {
      if (ev.key === 'Escape' && !menu.hidden) { menu.hidden = true; toggle.setAttribute('aria-expanded', 'false'); toggle.focus(); }
    });
  }
  const toast = document.getElementById('toast');
  let toastTimer;
  function notify(message) { if (!toast) return; clearTimeout(toastTimer); toast.textContent = message; toast.hidden = false; toastTimer = setTimeout(() => { toast.hidden = true; }, 3500); }
  document.querySelectorAll('[data-copy]').forEach(button => {
    button.addEventListener('click', async () => {
      try { await navigator.clipboard.writeText(location.href); notify('คัดลอกลิงก์แล้ว'); }
      catch { notify('คัดลอกไม่ได้ กรุณาคัดลอก URL จากแถบที่อยู่'); }
    });
  });
  const lightbox = document.getElementById('lightbox');
  const photos = [...document.querySelectorAll('[data-gallery]')];
  if (lightbox && photos.length) {
    let index = 0;
    const image = lightbox.querySelector('img');
    const caption = lightbox.querySelector('.lightbox-caption');
    const prev = lightbox.querySelector('.lightbox-prev');
    const next = lightbox.querySelector('.lightbox-next');
    function show(n) {
      index = (n + photos.length) % photos.length;
      image.src = photos[index].dataset.gallery;
      lightbox.querySelector('.lightbox-download').href = photos[index].dataset.gallery + '?download=1';
      image.alt = photos[index].dataset.caption;
      caption.textContent = `${image.alt} · ${index + 1}/${photos.length}`;
      prev.hidden = next.hidden = photos.length < 2;
    }
    photos.forEach((photo, n) => photo.addEventListener('click', () => { show(n); lightbox.showModal(); }));
    lightbox.querySelector('.lightbox-close').addEventListener('click', () => lightbox.close());
    prev.addEventListener('click', () => show(index - 1));
    next.addEventListener('click', () => show(index + 1));
    lightbox.addEventListener('keydown', ev => { if (ev.key === 'ArrowLeft') show(index - 1); if (ev.key === 'ArrowRight') show(index + 1); });
    lightbox.addEventListener('click', ev => { if (ev.target === lightbox) { const b = lightbox.getBoundingClientRect(); if (ev.clientX < b.left || ev.clientX > b.right || ev.clientY < b.top || ev.clientY > b.bottom) lightbox.close(); } });
    lightbox.addEventListener('close', () => photos[index].focus());
  }
  document.querySelectorAll('[data-back]').forEach(link => {
    if (document.referrer && new URL(document.referrer).origin === location.origin && /\/(news|events|media)(\?|$)/.test(new URL(document.referrer).pathname + new URL(document.referrer).search)) link.href = document.referrer;
  });
  document.querySelectorAll('[data-format]').forEach(button => button.addEventListener('click', () => {
    const editor = button.closest('.field-group')?.querySelector('textarea[name=body]');
    if (!editor) return;
    const start = editor.selectionStart, end = editor.selectionEnd;
    const selected = editor.value.slice(start, end) || 'ข้อความ';
    let replacement = selected;
    switch (button.dataset.format) {
      case 'heading': replacement = `\n\n## ${selected}\n`; break;
      case 'bold': replacement = `**${selected}**`; break;
      case 'italic': replacement = `*${selected}*`; break;
      case 'list': replacement = '\n' + selected.split('\n').map(line => '- ' + line).join('\n'); break;
      case 'link': {
        const url = prompt('ลิงก์ http:// หรือ https://');
        if (!url) return;
        try { const parsed = new URL(url); if (!['http:', 'https:'].includes(parsed.protocol)) throw new Error(); }
        catch { notify('กรุณากรอกลิงก์ http/https ที่ถูกต้อง'); return; }
        replacement = `[${selected}](${url})`; break;
      }
    }
    editor.setRangeText(replacement, start, end, 'end'); editor.focus(); editor.dispatchEvent(new Event('input', {bubbles: true}));
  }));
  document.querySelectorAll('[data-confirm]').forEach(button => button.addEventListener('click', ev => { if (!confirm(button.dataset.confirm)) ev.preventDefault(); }));
  document.querySelectorAll('[data-media-selection]').forEach(section => {
    const checkboxes = [...section.querySelectorAll('input[name="media_ids[]"]')];
    const actions = section.querySelector('.media-selection-actions');
    const count = section.querySelector('[data-selection-count]');
    const folderFilter = section.querySelector('[data-media-folder]');
    const visible = () => checkboxes.filter(input => !input.closest('.media-option').hidden);
    folderFilter?.addEventListener('change', () => {
      checkboxes.forEach(input => { const option=input.closest('.media-option'); option.hidden=folderFilter.value!=='all' && option.dataset.folder!==folderFilter.value; });
      update();
    });
    if (!actions || !count) return;
    const update = () => {
      const selected = checkboxes.filter(input => input.checked).length;
      count.textContent = `เลือกแล้ว ${selected} จาก ${checkboxes.length} รายการ`;
      section.querySelector('[data-select-media="all"]').disabled = visible().every(input => input.checked);
      section.querySelector('[data-select-media="none"]').disabled = visible().every(input => !input.checked);
    };
    actions.hidden = false;
    section.addEventListener('change', update);
    section.querySelectorAll('[data-select-media]').forEach(button => {
      button.addEventListener('click', () => {
        visible().forEach(input => { input.checked = button.dataset.selectMedia === 'all'; });
        checkboxes[0]?.dispatchEvent(new Event('change', { bubbles: true }));
        update();
      });
    });
    update();
    const editor = section.closest('form');
    const cover = editor?.querySelector('select[name=cover_id]');
    const coverFolder = editor?.querySelector('[data-cover-folder]');
    if (cover && coverFolder && folderFilter) {
      const options = [...cover.options].map(option => option.cloneNode(true));
      const preview = editor.querySelector('[data-cover-preview]');
      const note = editor.querySelector('[data-cover-folder-note]');
      const updatePreview = () => {
        if (!preview) return;
        preview.hidden = !cover.value;
        if (cover.value) preview.src = '/files/' + cover.value;
      };
      const filterCover = () => {
        const selected = cover.value;
        const folder = coverFolder.value;
        const kept = options.filter(option => !option.value || folder === 'all' || option.dataset.folder === folder || option.value === selected);
        cover.replaceChildren(...kept.map(option => option.cloneNode(true)));
        cover.value = selected;
        const current = options.find(option => option.value === selected);
        const outside = !!selected && folder !== 'all' && current?.dataset.folder !== folder;
        if (note) { note.hidden = !outside; note.textContent = 'รูปปกที่เลือกอยู่มาจากโฟลเดอร์อื่น เลือกรูปใหม่ในโฟลเดอร์ของงานได้'; }
        updatePreview();
      };
      const sync = source => {
        folderFilter.value = coverFolder.value = source.value;
        checkboxes.forEach(input => { const option = input.closest('.media-option'); option.hidden = source.value !== 'all' && option.dataset.folder !== source.value; });
        filterCover(); update();
      };
      folderFilter.addEventListener('change', () => sync(folderFilter));
      coverFolder.addEventListener('change', () => sync(coverFolder));
      cover.addEventListener('change', filterCover);
      const selectedFolders = [...new Set(checkboxes.filter(input => input.checked).map(input => input.closest('.media-option').dataset.folder))];
      const initialFolder = selectedFolders.length === 1 ? selectedFolders[0] : options.find(option => option.value && option.value === cover.value)?.dataset.folder;
      if (initialFolder !== undefined) { folderFilter.value = initialFolder; sync(folderFilter); }
      else filterCover();
    }
  });
  document.querySelectorAll('form[data-unsaved]').forEach(form => {
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('change', () => { dirty = true; });
    form.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', ev => { if (dirty) { ev.preventDefault(); ev.returnValue = ''; } });
  });
})();
