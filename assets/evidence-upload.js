(() => {
  const form = document.getElementById('complaintForm');
  if (!form) return;
  const input = document.getElementById('evidence-files');
  const zone = document.getElementById('evidence-dropzone');
  const selection = document.getElementById('evidence-selection');
  const feedback = document.getElementById('evidence-feedback');
  const error = document.getElementById('report-error');
  const submit = document.getElementById('submit-report');
  const progress = document.getElementById('upload-progress');
  const bar = document.getElementById('upload-progress-bar');
  const percent = document.getElementById('upload-percent');
  const status = document.getElementById('upload-status');
  let files = [], objectUrls = [], uploading = false, preparing = false;
  async function smallerPhoto(file) {
    if (!document.getElementById('optimize-photos')?.checked || !/^image\/(jpeg|png|webp)$/.test(file.type) || file.size < 1024 * 1024) return file;
    const url=URL.createObjectURL(file);
    try {
      const image=new Image();image.src=url;await image.decode();
      const scale=Math.min(1,2000/Math.max(image.naturalWidth,image.naturalHeight));
      const canvas=document.createElement('canvas');canvas.width=Math.max(1,Math.round(image.naturalWidth*scale));canvas.height=Math.max(1,Math.round(image.naturalHeight*scale));
      canvas.getContext('2d').drawImage(image,0,0,canvas.width,canvas.height);
      const blob=await new Promise(resolve=>canvas.toBlob(resolve,file.type,.85));
      return blob && blob.type===file.type && blob.size<file.size ? new File([blob],file.name,{type:file.type,lastModified:file.lastModified}) : file;
    } catch(_){return file;} finally{URL.revokeObjectURL(url);}
  }
  function showError(message, focus = false) {
    error.textContent = message;
    error.hidden = !message;
    if (focus) error.focus();
  }
  function validate(candidate) {
    if (candidate.length > 4) return 'Choose up to 4 evidence files.';
    if (candidate.some(file => !/\.(jpe?g|png|webp|mp4|webm|mov)$/i.test(file.name))) return 'Choose JPG, PNG, WebP, MP4, WebM, or MOV files.';
    if (candidate.some(file => file.size === 0 || file.size > 20 * 1024 * 1024)) return 'Each file must be nonempty and 20 MB or smaller.';
    if (candidate.reduce((sum, file) => sum + file.size, 0) > 50 * 1024 * 1024) return 'Your combined evidence files must be 50 MB or smaller.';
    return '';
  }
  function render() {
    objectUrls.forEach(url => URL.revokeObjectURL(url));
    objectUrls = [];
    selection.replaceChildren();
    // Keep normal multipart submission functional as well as the AJAX submission.
    try { const transfer = new DataTransfer(); files.forEach(file => transfer.items.add(file)); input.files = transfer.files; } catch (_) {}
    files.forEach((file, index) => {
      const tile = document.createElement('div');
      tile.className = 'selected-evidence';
      const url = URL.createObjectURL(file);
      objectUrls.push(url);
      if (/\.(jpe?g|png|webp)$/i.test(file.name)) {
        const image = document.createElement('img'); image.src = url; image.alt = file.name; tile.append(image);
      } else {
        const video = document.createElement('video'); video.src = url; video.controls = true; video.preload = 'metadata'; video.playsInline = true; tile.append(video);
      }
      const info = document.createElement('div'); info.className = 'selected-evidence-info';
      const name = document.createElement('strong'); name.textContent = file.name; name.title = file.name;
      const size = document.createElement('small'); size.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB`;
      const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = 'Remove'; remove.disabled = uploading;
      remove.setAttribute('aria-label', `Remove ${file.name}`);
      remove.addEventListener('click', () => { files.splice(index, 1); feedback.textContent = ''; showError(''); render(); });
      info.append(name, size, remove); tile.append(info); selection.append(tile);
    });
  }
  async function addFiles(incoming) {
    if (uploading || preparing) return;
    const additions = [...incoming].filter(file => !files.some(existing => existing.name === file.name && existing.size === file.size && existing.lastModified === file.lastModified));
    const candidate = [...files, ...additions];
    const message = validate(candidate);
    if (message) { feedback.textContent = message; showError(message); render(); return; }
    preparing=true;submit.disabled=true;input.disabled=true;feedback.textContent='Preparing evidence…';
    try {files=[...files,...await Promise.all(additions.map(smallerPhoto))];feedback.textContent='';showError('');render();}
    finally {preparing=false;submit.disabled=false;input.disabled=false;}
  }
  input.addEventListener('change', () => addFiles(input.files));
  ['dragenter', 'dragover'].forEach(type => zone.addEventListener(type, event => { event.preventDefault(); if (!uploading) zone.classList.add('is-dragging'); }));
  zone.addEventListener('dragleave', event => { if (!zone.contains(event.relatedTarget)) zone.classList.remove('is-dragging'); });
  zone.addEventListener('drop', event => { event.preventDefault(); zone.classList.remove('is-dragging'); addFiles(event.dataTransfer.files); });
  function setUploading(value) {
    uploading = value; submit.disabled = value; input.disabled = value;
    submit.innerHTML = value ? '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Sending report…' : '<i class="fas fa-paper-plane" aria-hidden="true"></i> Submit report';
    selection.querySelectorAll('button').forEach(button => { button.disabled = value; });
    form.setAttribute('aria-busy', String(value));
  }
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (uploading || preparing) return;
    const latitude = document.getElementById('latitude').value;
    const longitude = document.getElementById('longitude').value;
    if (!latitude || !longitude) { showError('Click or tap the map to pin the location before submitting.', true); return; }
    const message = validate(files);
    if (message) { showError(message, true); return; }
    showError('');
    const data = new FormData(form);
    data.delete('evidence[]');
    files.forEach(file => data.append('evidence[]', file, file.name));
    setUploading(true); progress.hidden = false; bar.style.width = '0%'; percent.textContent = '0%';
    if(form.prepareDraftSubmission)await form.prepareDraftSubmission();
    const progressbar = progress.querySelector('[role=progressbar]'); progressbar.setAttribute('aria-valuenow', '0');
    status.textContent = files.length ? 'Uploading evidence…' : 'Sending your report…';
    const request = new XMLHttpRequest();
    request.open('POST', form.action || window.location.href);
    request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    request.upload.addEventListener('progress', event => {
      if (!event.lengthComputable) return;
      const value = Math.round(event.loaded / event.total * 100);
      bar.style.width = `${value}%`; percent.textContent = `${value}%`; progressbar.setAttribute('aria-valuenow', String(value));
      if (value === 100) status.textContent = 'Saving your report…';
    });
    request.addEventListener('load', () => {
      try {
        const result = JSON.parse(request.responseText);
        if (request.status >= 200 && request.status < 300 && result.ok) { status.textContent = 'Report submitted.'; form.dispatchEvent(new Event('report-submitted')); window.location.assign(result.redirect); return; }
        showError(result.error || 'Your report could not be saved. Please try again.', true);
      } catch (_) { showError('The server could not process this upload. Try smaller files, or refresh and sign in again.', true); }
      setUploading(false); progress.hidden = true; form.dispatchEvent(new Event('report-submit-failed'));
    });
    request.addEventListener('error', () => { showError('The connection was interrupted. Your files are still selected; check your connection and try again.', true); setUploading(false); progress.hidden = true; form.dispatchEvent(new Event('report-submit-failed')); });
    request.send(data);
  });
  window.addEventListener('pagehide', () => objectUrls.forEach(url => URL.revokeObjectURL(url)));
})();
