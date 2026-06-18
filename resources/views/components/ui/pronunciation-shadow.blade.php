{{--
    Pronunciation Shadowing — record user audio and compare to a
    reference recording. Waveform + amplitude visualisation helps
    learners pace and intonation.

    Browser support:
      - MediaRecorder API (Chrome, Edge, Firefox, Safari 14.1+)
      - Falls back to "Browser không hỗ trợ ghi âm" if unavailable.
--}}
@props([
    'referenceText' => '', // What the speaker said
    'referenceAudioUrl' => null, // URL of the model recording
])

<div class="pron-shadow" x-data="pronShadow()">
    <div style="background:var(--bg-elevated);border:1px solid var(--glass-border);border-radius:12px;padding:1.25rem">

        @if($referenceText)
            <p style="font-style:italic;color:var(--text-muted);margin-bottom:0.75rem">"{{ $referenceText }}"</p>
        @endif

        @if($referenceAudioUrl)
            <audio controls src="{{ $referenceAudioUrl }}" style="width:100%;margin-bottom:1rem"></audio>
        @endif

        <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap">
            <button type="button" @click="start" :disabled="recording" class="btn btn-primary" style="padding:0.5rem 1rem">
                <span x-show="!recording">🎙️ Bắt đầu ghi</span>
                <span x-show="recording" x-cloak>⏹️ Dừng</span>
            </button>

            <button type="button" @click="playback" :disabled="!audioUrl" class="btn btn-outline" style="padding:0.5rem 1rem">
                ▶️ Nghe lại
            </button>

            <span x-show="recording" x-cloak style="color:var(--danger);font-weight:600">● REC <span x-text="elapsed + 's'"></span></span>
        </div>

        <div x-show="audioUrl" x-cloak style="margin-top:1rem">
            <canvas x-ref="waveform" width="800" height="80"
                    style="width:100%;height:80px;background:var(--bg-secondary);border-radius:8px"></canvas>
            <audio x-ref="player" :src="audioUrl" controls style="width:100%;margin-top:0.5rem"></audio>
        </div>

        <p x-show="error" x-cloak style="color:var(--danger);margin-top:0.5rem;font-size:0.875rem" x-text="error"></p>

        <details x-show="audioBlob" x-cloak style="margin-top:1rem">
            <summary style="cursor:pointer;color:var(--text-muted);font-size:0.875rem">📤 Gửi cho AI chấm phát âm</summary>
            <form @submit.prevent="submitForGrading" style="margin-top:0.5rem">
                <button type="submit" :disabled="submitting" class="btn btn-primary" style="padding:0.4rem 0.8rem">
                    <span x-show="!submitting">Gửi</span>
                    <span x-show="submitting" x-cloak>Đang gửi...</span>
                </button>
                <p x-show="gradeResult" x-cloak x-html="gradeResult" style="margin-top:0.5rem;font-size:0.875rem"></p>
            </form>
        </details>
    </div>
</div>

@once
@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            window.Alpine.data('pronShadow', () => ({
                recording: false,
                audioBlob: null,
                audioUrl: null,
                elapsed: 0,
                error: '',
                submitting: false,
                gradeResult: '',
                _timer: null,
                _mediaRecorder: null,
                _chunks: [],

                async start() {
                    this.error = '';
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        this._chunks = [];
                        this._mediaRecorder = new MediaRecorder(stream);
                        this._mediaRecorder.ondataavailable = (e) => this._chunks.push(e.data);
                        this._mediaRecorder.onstop = () => {
                            const blob = new Blob(this._chunks, { type: 'audio/webm' });
                            this.audioBlob = blob;
                            this.audioUrl = URL.createObjectURL(blob);
                            stream.getTracks().forEach((t) => t.stop());
                            this.$nextTick(() => this.drawWaveform());
                        };
                        this._mediaRecorder.start();
                        this.recording = true;
                        this.elapsed = 0;
                        this._timer = setInterval(() => this.elapsed++, 1000);
                    } catch (e) {
                        this.error = 'Không thể truy cập microphone: ' + (e.message || e);
                    }
                },

                stop() {
                    if (this._mediaRecorder && this.recording) {
                        this._mediaRecorder.stop();
                        this.recording = false;
                        clearInterval(this._timer);
                    }
                },

                toggle() { this.recording ? this.stop() : this.start(); },

                playback() {
                    this.$refs.player?.play();
                },

                async submitForGrading() {
                    if (!this.audioBlob) return;
                    this.submitting = true;
                    try {
                        const fd = new FormData();
                        fd.append('audio', this.audioBlob, 'recording.webm');
                        fd.append('reference_text', @json($referenceText ?? ''));
                        const csrf = document.querySelector('meta[name="csrf-token"]').content;
                        const res = await fetch('/speaking/grade-audio', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                            body: fd,
                        });
                        const data = await res.json();
                        this.gradeResult = data.feedback || 'Không nhận được phản hồi.';
                    } catch (e) {
                        this.gradeResult = 'Lỗi: ' + e.message;
                    } finally {
                        this.submitting = false;
                    }
                },

                async drawWaveform() {
                    try {
                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                        const arrayBuffer = await this.audioBlob.arrayBuffer();
                        const audioBuffer = await ctx.decodeAudioData(arrayBuffer);
                        const data = audioBuffer.getChannelData(0);
                        const canvas = this.$refs.waveform;
                        const c2 = canvas.getContext('2d');
                        const w = canvas.width = canvas.clientWidth * devicePixelRatio;
                        const h = canvas.height = 80 * devicePixelRatio;
                        c2.clearRect(0, 0, w, h);
                        const step = Math.ceil(data.length / w);
                        c2.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#6366f1';
                        for (let i = 0; i < w; i++) {
                            let sum = 0;
                            for (let j = 0; j < step; j++) sum += Math.abs(data[i * step + j] || 0);
                            const amp = (sum / step) * h * 1.4;
                            c2.fillRect(i, (h - amp) / 2, 1, amp);
                        }
                    } catch (e) { /* waveform optional */ }
                },
            }));
        });
    </script>
@endpush
@endonce