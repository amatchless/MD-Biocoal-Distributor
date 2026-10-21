// Candidate joining form (/join/{token}).
// Sends the form in the background so the candidate sees upload progress, and blocks a second submit while it runs.
// The server answers with { redirect } (validation errors are flashed to the session first), so the page reloads as before.

const MAX_FILE_BYTES = 10 * 1024 * 1024; // matches the server's 10 MB rule

export function formatBytes(bytes) {
    if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default (initial = {}) => ({
    ...initial,
    sending: false,
    progress: 0,
    loaded: 0,
    total: 0,
    fileCount: 0,
    sendError: '',
    selected: {},

    // Lists the chosen files under each input, so the candidate can see what will be uploaded.
    pick(event) {
        const input = event.target;
        this.selected[input.name] = Array.from(input.files || []).map((f) => ({
            name: f.name,
            size: formatBytes(f.size),
            tooBig: f.size > MAX_FILE_BYTES,
        }));
    },

    get stage() {
        if (this.progress < 100) return `Uploading… ${this.progress}%`;
        return 'Upload complete. Saving your details…';
    },

    get detail() {
        if (!this.total) return '';
        const files = this.fileCount === 1 ? '1 file' : `${this.fileCount} files`;
        return `${this.fileCount ? files + ' · ' : ''}${formatBytes(this.loaded)} of ${formatBytes(this.total)}`;
    },

    send(event) {
        if (this.sending) return;
        const form = event.target;
        const submitter = event.submitter;

        const files = Array.from(form.querySelectorAll('input[type=file]')).flatMap((i) => Array.from(i.files || []));
        const tooBig = files.find((f) => f.size > MAX_FILE_BYTES);
        if (tooBig) {
            this.sendError = `"${tooBig.name}" is ${formatBytes(tooBig.size)}. Each file must be under 10 MB: take the photo again at a lower size, or upload a smaller PDF.`;
            return;
        }

        const fd = new FormData(form);
        if (submitter && submitter.name) fd.set(submitter.name, submitter.value);

        this.sending = true;
        this.sendError = '';
        this.progress = 0;
        this.loaded = 0;
        this.total = 0;
        this.fileCount = files.length;
        window.addEventListener('beforeunload', this.warnLeaving);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.getAttribute('action')); // form.action is shadowed by the buttons named "action"
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.upload.onprogress = (e) => {
            if (!e.lengthComputable) return;
            this.loaded = e.loaded;
            this.total = e.total;
            this.progress = Math.min(100, Math.round((e.loaded / e.total) * 100));
        };
        xhr.upload.onload = () => { this.progress = 100; };
        xhr.onload = () => {
            let redirect = null;
            try { redirect = JSON.parse(xhr.responseText).redirect; } catch (e) { /* not JSON */ }
            if (xhr.status >= 200 && xhr.status < 300 && redirect) {
                window.removeEventListener('beforeunload', this.warnLeaving);
                window.location.href = redirect;
                return;
            }
            this.fail(xhr.status === 413
                ? 'These files are too large to send together. Upload a few at a time and press Save for later between them.'
                : xhr.status === 419
                    ? 'This page was open too long. Reload the page and try again.'
                    : `The server could not save this (error ${xhr.status}). Please try again.`);
        };
        xhr.onerror = () => this.fail('Upload failed, probably because the internet connection dropped. Please check your connection and try again.');
        xhr.send(fd);
    },

    fail(message) {
        window.removeEventListener('beforeunload', this.warnLeaving);
        this.sending = false;
        this.sendError = message;
    },

    warnLeaving(e) {
        e.preventDefault();
        e.returnValue = '';
    },
});
