// Clock in / clock out: GPS fix (required), optional selfie and note, posted to the server,
// which stamps the time itself.

const GOOD_ACCURACY_M = 50;
const GPS_WAIT_MS = 15000;
const SELFIE_MAX_PX = 720;

export default (config) => ({
    nextType: config.nextType,
    notes: '',
    state: 'idle', // idle | locating | saving
    message: '',
    error: '',

    cameraOn: false,
    stream: null,
    photo: null, // Blob
    photoUrl: '',

    get actionLabel() {
        return this.nextType === 'in' ? 'Clock in' : 'Clock out';
    },

    // ---------- selfie ----------

    async openCamera() {
        this.error = '';
        if (!navigator.mediaDevices?.getUserMedia) {
            this.$refs.photoInput.click();
            return;
        }
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 } }, audio: false });
            this.cameraOn = true;
            this.$nextTick(() => {
                this.$refs.video.srcObject = this.stream;
                this.$refs.video.play().catch(() => {});
            });
        } catch (e) {
            // Camera blocked or unavailable: fall back to the phone's own camera picker.
            this.$refs.photoInput.click();
        }
    },

    closeCamera() {
        this.stream?.getTracks().forEach((t) => t.stop());
        this.stream = null;
        this.cameraOn = false;
    },

    snap() {
        const video = this.$refs.video;
        if (!video.videoWidth) return;
        const scale = Math.min(1, SELFIE_MAX_PX / Math.max(video.videoWidth, video.videoHeight));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(video.videoWidth * scale);
        canvas.height = Math.round(video.videoHeight * scale);
        const ctx = canvas.getContext('2d');
        // Mirror so the saved photo matches the preview the person saw.
        ctx.translate(canvas.width, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob((blob) => this.setPhoto(blob), 'image/jpeg', 0.82);
        this.closeCamera();
    },

    pickedFile(event) {
        const file = event.target.files[0];
        if (file) this.setPhoto(file);
        event.target.value = '';
    },

    setPhoto(blob) {
        if (this.photoUrl) URL.revokeObjectURL(this.photoUrl);
        this.photo = blob;
        this.photoUrl = blob ? URL.createObjectURL(blob) : '';
    },

    removePhoto() {
        this.setPhoto(null);
    },

    // ---------- GPS ----------

    locate() {
        return new Promise((resolve, reject) => {
            if (!('geolocation' in navigator)) {
                return reject(new Error('This browser cannot read the location. Use Chrome or Safari on your phone.'));
            }
            let best = null;
            let watch = null;
            const finish = () => {
                navigator.geolocation.clearWatch(watch);
                clearTimeout(timer);
                best ? resolve(best) : reject(new Error('Could not get a GPS fix. Turn on Location / GPS and try again.'));
            };
            const timer = setTimeout(finish, GPS_WAIT_MS);
            watch = navigator.geolocation.watchPosition(
                (pos) => {
                    if (!best || pos.coords.accuracy < best.accuracy) {
                        best = { lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy };
                    }
                    this.message = `Getting your location… ±${Math.round(best.accuracy)} m`;
                    if (best.accuracy <= GOOD_ACCURACY_M) finish();
                },
                (err) => {
                    if (best) return finish();
                    navigator.geolocation.clearWatch(watch);
                    clearTimeout(timer);
                    reject(new Error(err.code === err.PERMISSION_DENIED
                        ? 'Location permission was blocked. Allow location for this site in your browser settings, then try again.'
                        : 'Could not get a GPS fix. Turn on Location / GPS and try again.'));
                },
                { enableHighAccuracy: true, maximumAge: 0, timeout: GPS_WAIT_MS },
            );
        });
    },

    // ---------- submit ----------

    async punch() {
        if (this.state !== 'idle') return;
        this.error = '';
        this.state = 'locating';
        this.message = 'Getting your location…';

        let geo;
        try {
            geo = await this.locate();
        } catch (e) {
            this.state = 'idle';
            this.message = '';
            this.error = e.message;
            return;
        }

        this.state = 'saving';
        this.message = 'Saving…';
        const body = new FormData();
        body.append('type', this.nextType);
        body.append('latitude', geo.lat);
        body.append('longitude', geo.lng);
        if (this.notes.trim()) body.append('notes', this.notes.trim());
        if (this.photo) body.append('photo', this.photo, 'selfie.jpg');

        try {
            const res = await fetch(config.url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body,
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) {
                if (res.status === 419) {
                    this.error = 'This page had expired. Refreshing…';
                    setTimeout(() => window.location.reload(), 1200);
                    return;
                }
                const first = json.errors ? Object.values(json.errors)[0][0] : null;
                throw new Error(first || json.message || 'Could not save. Please try again.');
            }
            this.message = json.message;
            window.location.reload();
        } catch (e) {
            this.state = 'idle';
            this.message = '';
            this.error = e.message === 'Failed to fetch' ? 'No internet connection. Please try again.' : e.message;
        }
    },
});
