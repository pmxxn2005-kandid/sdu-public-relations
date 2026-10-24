'use strict';
(() => {
  const form = document.getElementById('media-upload-form');
  if (!form) return;
  const files = document.getElementById('upload-files');
  const folder = document.getElementById('upload-folder');
  const modes = [...form.querySelectorAll('input[name=upload_mode]')];
  const alt = document.getElementById('upload-alt');
  const targetFolder = document.getElementById('upload-target-folder');
  const start = document.getElementById('upload-start');
  const stop = document.getElementById('upload-stop');
  const refresh = document.getElementById('upload-refresh');
  const results = document.getElementById('upload-results');
  const summary = document.getElementById('upload-summary');
  const progress = document.getElementById('upload-progress');
  const list = document.getElementById('upload-queue');
  const token = form.querySelector('input[name=_token]').value;
  const labels = {queued:'รออัปโหลด', uploading:'กำลังอัปโหลด', success:'สำเร็จ', skipped:'ข้าม', failed:'ไม่สำเร็จ'};
  let queue = [], busy = false, stopping = false, sessionExpired = false;
  files.multiple = true;
  if ('webkitdirectory' in folder) document.getElementById('folder-mode-option').hidden = false;
  else document.getElementById('folder-support-note').hidden = false;
  const mode = () => modes.find(input => input.checked).value;

  function updateItem(item) {
    item.element.dataset.state = item.state;
    item.status.textContent = labels[item.state] + (item.message ? ` · ${item.message}` : '');
  }
  function updateSummary(message = '') {
    const count = state => queue.filter(item => item.state === state).length;
    const done = count('success') + count('skipped') + count('failed');
    progress.max = Math.max(1, queue.length); progress.value = done;
    summary.textContent = `${message ? message + ' · ' : ''}ทั้งหมด ${queue.length} ไฟล์ · สำเร็จ ${count('success')} · ข้าม ${count('skipped')} · ไม่สำเร็จ ${count('failed')} · รอ ${count('queued') + count('uploading')}`;
    refresh.hidden = !count('success');
    const retry = queue.some(item => ['queued', 'failed'].includes(item.state));
    start.disabled = busy || sessionExpired || !retry;
    start.textContent = busy ? 'กำลังอัปโหลด…' : count('success') || count('failed') ? 'อัปโหลดไฟล์ที่เหลือ / ลองใหม่' : 'อัปโหลด';
  }
  function buildQueue() {
    queue = []; list.replaceChildren(); sessionExpired = false;
    const selected = [...(mode() === 'folder' ? folder.files : files.files)];
    for (const file of selected) {
      const element = document.createElement('li');
      const name = document.createElement('span'); name.className = 'upload-filename';
      name.textContent = file.webkitRelativePath || file.name;
      const status = document.createElement('span'); status.className = 'upload-file-status';
      element.append(name, status); list.append(element);
      const item = {file, state:'queued', message:'', element, status};
      const supported = mode() === 'folder' ? /\.(jpe?g|png|webp)$/i.test(file.name) : /\.(jpe?g|png|webp|pdf)$/i.test(file.name);
      if (!supported) { item.state = 'skipped'; item.message = mode() === 'folder' ? 'ไม่ใช่รูป JPG, PNG หรือ WebP' : 'ชนิดไฟล์ไม่รองรับ'; }
      else if (file.size > 10 * 1024 * 1024 || file.size === 0) { item.state = 'skipped'; item.message = file.size === 0 ? 'ไฟล์ว่าง' : 'เกิน 10 MB'; }
      queue.push(item); updateItem(item);
    }
    results.hidden = !queue.length;
    if (queue.length) updateSummary('เลือกไฟล์แล้ว'); else { start.disabled = false; start.textContent = 'อัปโหลด'; refresh.hidden = true; }
  }
  for (const input of modes) input.addEventListener('change', () => {
    const isFolder = mode() === 'folder';
    document.getElementById('files-upload-panel').hidden = isFolder;
    document.getElementById('folder-upload-panel').hidden = !isFolder;
    files.disabled = isFolder; files.required = !isFolder;
    folder.disabled = !isFolder; folder.required = isFolder;
    buildQueue();
  });
  files.addEventListener('change', buildQueue); folder.addEventListener('change', buildQueue);
  stop.addEventListener('click', () => { stopping = true; stop.disabled = true; updateSummary('จะหยุดเมื่อไฟล์ปัจจุบันเสร็จ'); });
  window.addEventListener('beforeunload', event => { if (busy) { event.preventDefault(); event.returnValue = ''; } });

  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (busy || sessionExpired) return;
    if (!queue.length) buildQueue();
    if (!queue.some(item => ['queued', 'failed'].includes(item.state))) return;
    const folderId = targetFolder.value;
    refresh.href = '/admin/media?folder=' + encodeURIComponent(folderId);
    targetFolder.disabled = true;
    busy = true; stopping = false; stop.hidden = false; stop.disabled = false;
    files.disabled = folder.disabled = alt.disabled = true; modes.forEach(input => { input.disabled = true; });
    for (const item of queue) if (item.state === 'failed') { item.state = 'queued'; item.message = ''; updateItem(item); }
    updateSummary('เริ่มอัปโหลด');
    let interruption = '';
    try {
      for (const item of queue) {
        if (stopping || sessionExpired) break;
        if (item.state !== 'queued') continue;
        item.state = 'uploading'; item.message = ''; updateItem(item); updateSummary();
        const body = new FormData(); body.append('_token', token); body.append('alt', alt.value); body.append('folder_id', folderId);
        // Only a basename is sent. Folder paths are display labels, never server paths.
        body.append('file', item.file, item.file.name);
        try {
          const response = await fetch('/admin/media/upload', {method:'POST', body, credentials:'same-origin'});
          if (response.redirected || response.status === 401 || response.status === 419) {
            sessionExpired = true; throw new Error('การเข้าสู่ระบบหมดอายุ กรุณาเปิดคลังสื่อใหม่และเข้าสู่ระบบอีกครั้ง');
          }
          if (!(response.headers.get('content-type') || '').includes('application/json')) throw new Error('เซิร์ฟเวอร์รับไฟล์ไม่สำเร็จ กรุณาตรวจขนาดไฟล์และลองใหม่');
          const data = await response.json();
          if (!response.ok || !Number.isInteger(data.id)) throw new Error(data.error || 'อัปโหลดไม่สำเร็จ');
          item.state = 'success'; item.message = data.resolution ? `ต้นฉบับ ${data.resolution.width} × ${data.resolution.height} px · ${data.resolution.is4k ? 'รองรับ 4K' : 'ต้นฉบับต่ำกว่า 4K'}` : '';
        } catch (error) {
          item.state = 'failed'; item.message = error.message || 'การเชื่อมต่อขัดข้อง';
          if (sessionExpired || error instanceof TypeError) { interruption = item.message; stopping = true; }
        }
        updateItem(item); updateSummary();
      }
    } finally {
      busy = false; stop.hidden = true; targetFolder.disabled = false;
      modes.forEach(input => { input.disabled = false; });
      files.disabled = mode() === 'folder'; folder.disabled = mode() !== 'folder'; alt.disabled = false;
      updateSummary(interruption || (stopping ? 'หยุดแล้ว สามารถอัปโหลดไฟล์ที่เหลือต่อได้' : queue.some(item => item.state === 'failed') ? 'อัปโหลดเสร็จสิ้น บางไฟล์ไม่สำเร็จ' : 'อัปโหลดไฟล์เรียบร้อย'));
    }
  });
})();
