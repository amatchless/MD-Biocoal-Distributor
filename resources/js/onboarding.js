// Alpine component behind the dealer / distributor onboarding form.
// Handles the step-by-step wizard, GPS capture, geo-stamped photos, local draft autosave and the upload.

const MAX_PHOTO_EDGE = 1600;
const GOOD_ACCURACY_M = 25;
const GPS_WAIT_MS = 25000;

function fmtTime(d) {
    return d.toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// Downscale a photo and burn the GPS position and time into the bottom of it, so a printed or
// forwarded copy still shows where and when it was taken.
async function stampPhoto(file, geo, caption) {
    if (!file.type.startsWith('image/')) return file;
    let bitmap;
    try {
        bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch (e) {
        return file; // a format the browser cannot decode (e.g. HEIC on Android): upload as-is
    }
    const scale = Math.min(1, MAX_PHOTO_EDGE / Math.max(bitmap.width, bitmap.height));
    const w = Math.round(bitmap.width * scale);
    const h = Math.round(bitmap.height * scale);
    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(bitmap, 0, 0, w, h);

    const lines = [
        geo ? `${geo.lat.toFixed(6)}, ${geo.lng.toFixed(6)}  (±${Math.round(geo.accuracy)} m)` : 'Location not available',
        `${fmtTime(new Date())}  ·  ${caption}`,
    ];
    const fontSize = Math.max(14, Math.round(w / 45));
    const pad = Math.round(fontSize * 0.6);
    const band = lines.length * fontSize * 1.35 + pad * 2;
    ctx.fillStyle = 'rgba(0,0,0,0.55)';
    ctx.fillRect(0, h - band, w, band);
    ctx.fillStyle = '#fff';
    ctx.font = `600 ${fontSize}px system-ui, sans-serif`;
    ctx.textBaseline = 'top';
    lines.forEach((line, i) => ctx.fillText(line, pad, h - band + pad + i * fontSize * 1.35));

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.82));
    if (!blob) return file;
    const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
    return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
}

export default (config) => ({
    step: 0,
    steps: config.steps,
    isEdit: config.isEdit,
    draftKey: config.draftKey,
    employeeName: config.employeeName,
    photoCategories: config.photoCategories,
    categories: config.categories,
    existingDocs: config.existingDocs,

    gstRegistered: config.values.gst_registered,
    hasPesticide: config.values.has_pesticide_licence,
    hasFertilizer: config.values.has_fertilizer_licence,
    partnerType: config.values.partner_type,

    geo: config.geo, // {lat, lng, accuracy, captured_at} or null
    geoState: config.geo ? 'done' : 'idle', // idle | locating | done | error
    geoMessage: '',
    geoWatch: null,
    secure: window.isSecureContext,

    docs: [],
    newDocCategory: 'other',
    snapshot: {}, // plain copy of the text fields, refreshed on every step change (used in the agreement and summary)

    signature: null, // the signature doc once the signer taps "Use this signature"
    padDirty: false,
    padDrawing: false,

    errors: {},
    formError: '',
    duplicates: [],
    submitting: false,
    progress: 0,
    draftSavedAt: null,
    draftAvailable: null,

    init() {
        if (!this.isEdit) {
            try {
                const raw = localStorage.getItem(this.draftKey);
                if (raw) this.draftAvailable = JSON.parse(raw);
            } catch (e) { /* storage unavailable */ }
        }
        this.$watch('step', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            this.refreshSnapshot();
            if (this.steps[this.step].key === 'terms') this.$nextTick(() => this.padSetup());
        });
        this.refreshSnapshot();
    },

    get isDistributor() {
        return this.partnerType === 'distributor';
    },

    refreshSnapshot() {
        const form = this.$root.querySelector('form');
        if (form) this.snapshot = Object.fromEntries(new FormData(form).entries());
    },

    get agreementDateText() {
        const v = this.snapshot.agreement_date;
        if (!v) return '[date]';
        return new Date(`${v}T00:00:00`).toLocaleDateString('en-IN', { day: 'numeric', month: 'long', year: 'numeric' });
    },

    // ---------- wizard ----------

    sectionEl(i) {
        return this.$root.querySelector(`[data-step="${i}"]`);
    },

    validateStep(i, report = true) {
        const section = this.sectionEl(i);
        if (!section) return true;
        for (const el of section.querySelectorAll('input, select, textarea')) {
            if (el.disabled || el.type === 'file') continue;
            if (!el.checkValidity()) {
                if (report) {
                    this.step = i;
                    this.$nextTick(() => { el.reportValidity(); el.focus({ preventScroll: false }); });
                }
                return false;
            }
        }
        const custom = this.customCheck(i);
        if (custom) {
            if (report) {
                this.step = i;
                this.formError = custom;
            }
            return false;
        }
        return true;
    },

    customCheck(i) {
        const key = this.steps[i].key;
        if (key === 'location' && !this.geo) return 'Capture the GPS location at the premises before continuing.';
        if (key === 'terms' && !this.isEdit && !this.signature) {
            return this.padDirty ? 'Tap "Use this signature" to keep the signature.' : 'The dealer / distributor must sign before submitting.';
        }
        if (key === 'documents' && !this.isEdit && !this.docs.some((d) => d.category === 'shop_front')) {
            return 'Take at least one photo of the shop / office front.';
        }
        return '';
    },

    next() {
        this.formError = '';
        if (!this.validateStep(this.step)) return;
        if (this.step < this.steps.length - 1) this.step++;
        this.saveDraft();
    },

    back() {
        this.formError = '';
        if (this.step > 0) this.step--;
    },

    goTo(i) {
        this.formError = '';
        for (let s = 0; s < i; s++) if (!this.validateStep(s)) return;
        this.step = i;
    },

    stepHasError(i) {
        const section = this.sectionEl(i);
        if (!section) return false;
        return Object.keys(this.errors).some((k) => this.stepForField(k) === i);
    },

    stepForField(field) {
        const map = { documents: 'documents', signature: 'terms', terms_accepted: 'terms', consent: 'review' };
        if (map[field]) return this.steps.findIndex((s) => s.key === map[field]);
        if (field.startsWith('documents.')) return this.steps.findIndex((s) => s.key === 'documents');
        if (['latitude', 'longitude', 'geo_accuracy', 'geo_captured_at'].includes(field)) return this.steps.findIndex((s) => s.key === 'location');
        const parts = field.split('.');
        const name = parts.length > 1 ? `${parts[0]}[${parts.slice(1).join('][')}]` : field;
        const el = this.$root.querySelector(`[name="${name}"], [name="${name}[]"]`);
        const section = el && el.closest('[data-step]');
        return section ? Number(section.dataset.step) : this.steps.length - 1;
    },

    err(field) {
        return this.errors[field] ? this.errors[field][0] : '';
    },

    // ---------- GPS ----------

    captureLocation() {
        if (!('geolocation' in navigator)) {
            this.geoState = 'error';
            this.geoMessage = 'This browser cannot read the location. Use Chrome or Safari on your phone.';
            return;
        }
        if (this.geoWatch !== null) navigator.geolocation.clearWatch(this.geoWatch);
        this.geoState = 'locating';
        this.geoMessage = 'Getting a GPS fix… stand near the shop entrance, outdoors if possible.';
        let best = null;
        const finish = () => {
            if (this.geoWatch !== null) navigator.geolocation.clearWatch(this.geoWatch);
            this.geoWatch = null;
            clearTimeout(timer);
            if (best) {
                this.geo = best;
                this.geoState = 'done';
                this.geoMessage = '';
                this.formError = '';
                this.saveDraft();
            } else if (this.geoState === 'locating') {
                this.geoState = 'error';
                this.geoMessage = 'Could not get a GPS fix. Turn on Location / GPS and try again.';
            }
        };
        const timer = setTimeout(finish, GPS_WAIT_MS);
        this.geoWatch = navigator.geolocation.watchPosition(
            (pos) => {
                const reading = {
                    lat: pos.coords.latitude,
                    lng: pos.coords.longitude,
                    accuracy: pos.coords.accuracy,
                    captured_at: new Date(pos.timestamp).toISOString(),
                };
                if (!best || reading.accuracy < best.accuracy) best = reading;
                this.geoMessage = `Improving accuracy… currently ±${Math.round(best.accuracy)} m`;
                if (best.accuracy <= GOOD_ACCURACY_M) finish();
            },
            (err) => {
                clearTimeout(timer);
                if (this.geoWatch !== null) navigator.geolocation.clearWatch(this.geoWatch);
                this.geoWatch = null;
                if (best) return finish();
                this.geoState = 'error';
                this.geoMessage = err.code === err.PERMISSION_DENIED
                    ? 'Location permission was blocked. Allow location for this site in your browser settings, then tap Capture again.'
                    : 'Could not get a GPS fix. Turn on Location / GPS and try again.';
            },
            { enableHighAccuracy: true, maximumAge: 0, timeout: GPS_WAIT_MS },
        );
    },

    get mapEmbed() {
        if (!this.geo) return '';
        const d = 0.004;
        const { lat, lng } = this.geo;
        return `https://www.openstreetmap.org/export/embed.html?bbox=${lng - d},${lat - d},${lng + d},${lat + d}&layer=mapnik&marker=${lat},${lng}`;
    },

    get mapLink() {
        return this.geo ? `https://www.google.com/maps?q=${this.geo.lat},${this.geo.lng}` : '#';
    },

    get accuracyClass() {
        if (!this.geo) return '';
        if (this.geo.accuracy <= 30) return 'text-brand-700';
        if (this.geo.accuracy <= 100) return 'text-amber-700';
        return 'text-red-700';
    },

    // Quick position for a photo; falls back to the premises location.
    currentPosition() {
        return new Promise((resolve) => {
            if (!('geolocation' in navigator)) return resolve(this.geo);
            navigator.geolocation.getCurrentPosition(
                (pos) => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy, captured_at: new Date(pos.timestamp).toISOString() }),
                () => resolve(this.geo),
                { enableHighAccuracy: true, maximumAge: 60000, timeout: 8000 },
            );
        });
    },

    // ---------- required documents checklist ----------

    get requiredDocs() {
        return config.required[this.partnerType] || {};
    },

    haveDocs(category) {
        return this.docsIn(category) + Number(this.existingDocs[category] || 0);
    },

    get requiredDone() {
        return Object.entries(this.requiredDocs).filter(([cat, n]) => this.haveDocs(cat) >= n).length;
    },

    // ---------- signature pad ----------

    padSetup() {
        const canvas = this.$refs.signaturePad;
        if (!canvas || !canvas.clientWidth) return;
        const ratio = window.devicePixelRatio || 1;
        const w = Math.round(canvas.clientWidth * ratio);
        const h = Math.round(canvas.clientHeight * ratio);
        if (canvas.width === w && canvas.height === h) return;
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext('2d');
        ctx.scale(ratio, ratio);
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#111827';
        this.padDirty = false;
    },

    padPoint(e) {
        const r = this.$refs.signaturePad.getBoundingClientRect();
        return { x: e.clientX - r.left, y: e.clientY - r.top };
    },

    padStart(e) {
        this.padSetup();
        e.target.setPointerCapture && e.target.setPointerCapture(e.pointerId);
        const ctx = this.$refs.signaturePad.getContext('2d');
        const p = this.padPoint(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
        ctx.lineTo(p.x + 0.1, p.y + 0.1);
        ctx.stroke();
        this.padDrawing = true;
        this.padDirty = true;
    },

    padMove(e) {
        if (!this.padDrawing) return;
        const ctx = this.$refs.signaturePad.getContext('2d');
        const p = this.padPoint(e);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
    },

    padEnd() {
        this.padDrawing = false;
    },

    padClear() {
        const canvas = this.$refs.signaturePad;
        canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
        this.padDirty = false;
    },

    async saveSignature() {
        if (!this.padDirty) return;
        const blob = await new Promise((resolve) => this.$refs.signaturePad.toBlob(resolve, 'image/png'));
        if (!blob) return;
        this.clearSignature();
        const file = new File([blob], 'signature.png', { type: 'image/png', lastModified: Date.now() });
        this.signature = { key: `sig-${Date.now()}`, category: 'signature', file, preview: URL.createObjectURL(file), geo: this.geo };
        this.docs.push(this.signature);
        this.formError = '';
    },

    clearSignature() {
        if (this.signature) {
            const i = this.docs.indexOf(this.signature);
            if (i >= 0) this.docs.splice(i, 1);
            URL.revokeObjectURL(this.signature.preview);
        }
        this.signature = null;
        this.padDirty = false;
        this.$nextTick(() => { this.padSetup(); if (this.$refs.signaturePad) this.padClear(); });
    },

    // ---------- documents & photos ----------

    async addFiles(event, category) {
        const files = Array.from(event.target.files || []);
        event.target.value = '';
        if (!files.length) return;
        const isPhoto = this.photoCategories.includes(category);
        const geo = isPhoto ? await this.currentPosition() : null;
        for (const original of files) {
            if (original.size > 25 * 1024 * 1024) {
                this.formError = `${original.name} is too large.`;
                continue;
            }
            const file = isPhoto ? await stampPhoto(original, geo, `${this.categories[category]} · ${this.employeeName}`) : original;
            if (file.size > 10 * 1024 * 1024) {
                this.formError = `${original.name} is larger than 10 MB.`;
                continue;
            }
            this.docs.push({
                key: `${Date.now()}-${Math.random()}`,
                category,
                file,
                preview: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
                geo,
            });
        }
        this.formError = '';
    },

    removeDoc(i) {
        const [doc] = this.docs.splice(i, 1);
        if (doc && doc === this.signature) this.signature = null;
        if (doc && doc.preview) URL.revokeObjectURL(doc.preview);
    },

    docsIn(category) {
        return this.docs.filter((d) => d.category === category).length;
    },

    // ---------- GST / PAN helpers ----------

    onGstInput(e) {
        const v = e.target.value.toUpperCase().replace(/\s+/g, '');
        e.target.value = v;
        const pan = this.$root.querySelector('[name="pan_no"]');
        if (v.length === 15 && pan && !pan.value) pan.value = v.slice(2, 12);
    },

    async checkDuplicates() {
        const gst = (this.$root.querySelector('[name="gst_no"]') || {}).value || '';
        const pan = (this.$root.querySelector('[name="pan_no"]') || {}).value || '';
        if (gst.length < 15 && pan.length < 10) { this.duplicates = []; return; }
        try {
            const params = new URLSearchParams({ gst: gst.length === 15 ? gst : '', pan: pan.length === 10 ? pan : '', exclude: config.dealerId || '' });
            const res = await fetch(`${config.duplicateUrl}?${params}`, { headers: { Accept: 'application/json' } });
            if (res.ok) this.duplicates = (await res.json()).matches;
        } catch (e) { /* offline: the server checks again later */ }
    },

    // ---------- draft autosave (text fields only; photos cannot be stored) ----------

    saveDraft() {
        if (this.isEdit) return;
        try {
            const form = this.$root.querySelector('form');
            const data = {};
            for (const el of form.elements) {
                if (!el.name || el.type === 'file' || el.name === '_token' || el.name === '_method') continue;
                if ((el.type === 'radio' || el.type === 'checkbox') && !el.checked) continue;
                if (el.name.endsWith('[]')) (data[el.name] = data[el.name] || []).push(el.value);
                else data[el.name] = el.value;
            }
            localStorage.setItem(this.draftKey, JSON.stringify({ data, geo: this.geo, saved_at: Date.now() }));
            this.draftSavedAt = new Date();
        } catch (e) { /* storage full or blocked */ }
    },

    restoreDraft() {
        const draft = this.draftAvailable;
        this.draftAvailable = null;
        if (!draft) return;
        const form = this.$root.querySelector('form');
        for (const el of form.elements) {
            if (!el.name || el.type === 'file' || !(el.name in draft.data)) continue;
            const v = draft.data[el.name];
            if (el.type === 'radio') el.checked = el.value === v;
            else if (el.type === 'checkbox') el.checked = Array.isArray(v) ? v.includes(el.value) : el.value === v;
            else el.value = v;
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }
        this.refreshSnapshot();
        if (draft.geo) { this.geo = draft.geo; this.geoState = 'done'; }
    },

    discardDraft() {
        this.draftAvailable = null;
        try { localStorage.removeItem(this.draftKey); } catch (e) { /* ignore */ }
    },

    draftLabel(ts) {
        return fmtTime(new Date(ts));
    },

    // ---------- submit ----------

    submit() {
        this.formError = '';
        for (let i = 0; i < this.steps.length; i++) {
            if (!this.validateStep(i)) return;
        }
        if (!navigator.onLine) {
            this.formError = 'You are offline. Your entries are saved on this phone; submit again when you have signal.';
            this.saveDraft();
            return;
        }

        const form = this.$root.querySelector('form');
        const fd = new FormData(form);
        this.docs.forEach((doc, i) => {
            fd.append(`documents[${i}][file]`, doc.file, doc.file.name);
            fd.append(`documents[${i}][category]`, doc.category);
            if (doc.geo) {
                fd.append(`documents[${i}][latitude]`, doc.geo.lat);
                fd.append(`documents[${i}][longitude]`, doc.geo.lng);
                fd.append(`documents[${i}][accuracy]`, doc.geo.accuracy);
                fd.append(`documents[${i}][captured_at]`, doc.geo.captured_at);
            }
        });

        this.submitting = true;
        this.progress = 0;
        this.errors = {};
        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.action);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.upload.onprogress = (e) => { if (e.lengthComputable) this.progress = Math.round((e.loaded / e.total) * 100); };
        xhr.onload = () => {
            if (xhr.status === 422) {
                this.submitting = false;
                const body = JSON.parse(xhr.responseText || '{}');
                this.errors = body.errors || {};
                const first = Object.keys(this.errors)[0];
                this.formError = 'Some details need fixing. Fields with problems are marked in red.';
                if (first) this.step = this.stepForField(first);
                return;
            }
            if (xhr.status === 419) {
                this.submitting = false;
                this.saveDraft();
                this.formError = 'Your session expired. Your entries are saved on this phone. Log in again in a new tab, then submit.';
                return;
            }
            if (xhr.status >= 200 && xhr.status < 400) {
                try { localStorage.removeItem(this.draftKey); } catch (e) { /* ignore */ }
                let target = config.doneUrl;
                try { target = JSON.parse(xhr.responseText).redirect || target; } catch (e) { target = xhr.responseURL || target; }
                window.location.href = target;
                return;
            }
            this.submitting = false;
            this.formError = `The server could not save this (error ${xhr.status}). Your entries are saved on this phone; try again.`;
            this.saveDraft();
        };
        xhr.onerror = () => {
            this.submitting = false;
            this.saveDraft();
            this.formError = 'Upload failed, probably because the signal dropped. Your entries are saved on this phone; try again.';
        };
        xhr.send(fd);
    },
});
