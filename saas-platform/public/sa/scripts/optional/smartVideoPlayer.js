/**
 * VideoPlayer - A vanilla JavaScript video player plugin
 * @version 1.0.0
 */
(function(window) {
    'use strict';

    function VideoPlayer(selector, options) {
        if (!(this instanceof VideoPlayer)) {
            return new VideoPlayer(selector, options);
        }

        this.options = Object.assign({
            autoplay: false,
            controls: true,
            width: '100%',
            height: 'auto',
            thumbnail: null
        }, options || {});

        this.video = typeof selector === 'string' 
            ? document.querySelector(selector) 
            : selector;

        if (!this.video || this.video.tagName !== 'VIDEO') {
            throw new Error('Invalid video element');
        }

        // Add crossorigin attribute for thumbnail generation
        this.video.setAttribute('crossorigin', 'anonymous');

        this.init();
    }

    VideoPlayer.prototype = {
        init: function() {
            // Create container
            this.container = document.createElement('div');
            this.container.className = 'video-player-container';
            this.video.parentNode.insertBefore(this.container, this.video);
            this.container.appendChild(this.video);

            // Create error display
            this.createErrorDisplay();

            // Create thumbnail overlay
            this.createThumbnailOverlay();

            // Apply styles
            this.applyStyles();

            // Create controls
            if (this.options.controls) {
                this.createControls();
            }

            // Setup event listeners
            this.setupEvents();
        },

        createErrorDisplay: function() {
            this.errorDisplay = document.createElement('div');
            this.errorDisplay.className = 'video-error-display';
            this.container.appendChild(this.errorDisplay);
        },

        createThumbnailOverlay: function() {
            this.thumbnailOverlay = document.createElement('div');
            this.thumbnailOverlay.className = 'video-thumbnail-overlay';
            this.container.appendChild(this.thumbnailOverlay);

            if (this.options.thumbnail) {
                this.setThumbnail(this.options.thumbnail);
            } else if (this.video.poster) {
                this.setThumbnail(this.video.poster);
            } else {
                this.thumbnailOverlay.classList.add('default-overlay');
            }
        },

        setThumbnail: function(url) {
            this.thumbnailOverlay.style.backgroundImage = `url(${url})`;
        },

        showError: function(message) {
            this.errorDisplay.textContent = message;
            this.errorDisplay.style.display = 'flex';
            this.thumbnailOverlay.style.display = 'none';
            if (this.playBtn) {
                this.playBtn.disabled = true;
            }
        },

        hideError: function() {
            this.errorDisplay.style.display = 'none';
            this.thumbnailOverlay.style.display = 'block';
            if (this.playBtn) {
                this.playBtn.disabled = false;
            }
        },

        applyStyles: function() {
            // Container styles
            this.container.style.cssText = `
                width: ${this.options.width};
                position: relative;
                background: #000;
                overflow: hidden;
                display: flex;
                justify-content: center;
                align-items: center;
            `;

            // Video styles
            this.video.style.cssText = `
                width: 100%;
                height: ${this.options.height};
                display: block;
                max-width: 100%;
                max-height: 100%;
            `;

            // Inject CSS
            if (!document.getElementById('video-player-styles')) {
                const style = document.createElement('style');
                style.id = 'video-player-styles';
                style.textContent = `
                    .video-player-container {
                        position: relative;
                    }
                    .video-error-display {
                        position: absolute;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0, 0, 0, 0.8);
                        color: white;
                        display: none;
                        align-items: center;
                        justify-content: center;
                        text-align: center;
                        padding: 20px;
                        box-sizing: border-box;
                        z-index: 2;
                    }
                    .video-thumbnail-overlay {
                        position: absolute;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background-size: cover;
                        background-position: center;
                        cursor: pointer;
                        transition: opacity 0.3s;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    .video-thumbnail-overlay.default-overlay {
                        background-color: rgba(0, 0, 0, 0.2);
                    }
                    .video-thumbnail-overlay::after {
                        content: '';
                        width: 60px;
                        height: 60px;
                        background: rgba(0,0,0,0.7);
                        border-radius: 50%;
                        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3E%3Cpath d='M8 5v14l11-7z'/%3E%3C/svg%3E");
                        background-repeat: no-repeat;
                        background-position: center;
                        background-size: 30px;
                    }
                    .video-thumbnail-overlay.hidden {
                        opacity: 0;
                        pointer-events: none;
                    }
                    .video-controls {
                        position: absolute;
                        bottom: 0;
                        left: 0;
                        right: 0;
                        background: rgba(0,0,0,0.7);
                        padding: 10px;
                        display: flex;
                        align-items: center;
                        gap: 10px;
                    }
                    .video-controls button {
                        background: transparent;
                        border: none;
                        color: white;
                        cursor: pointer;
                        padding: 5px 10px;
                    }
                    .video-controls button:disabled {
                        opacity: 0.5;
                        cursor: not-allowed;
                    }
                    .video-controls button:not(:disabled):hover {
                        opacity: 0.8;
                    }
                    .video-progress {
                        flex-grow: 1;
                        height: 5px;
                        background: rgba(255,255,255,0.3);
                        cursor: pointer;
                        position: relative;
                    }
                    .video-progress-bar {
                        height: 100%;
                        background: var(--bs-body-bg);
                        width: 0%;
                    }
                    .video-time {
                        color: white;
                        font-size: 14px;
                    }
                    .volume-control {
                        display: flex;
                        align-items: center;
                        gap: 5px;
                    }
                    .volume-slider {
                        width: 60px;
                        height: 5px;
                        -webkit-appearance: none;
                        background: rgba(255,255,255,0.3);
                        outline: none;
                        border-radius: 2px;
                    }
                    .volume-slider::-webkit-slider-thumb {
                        -webkit-appearance: none;
                        width: 12px;
                        height: 12px;
                        background: white;
                        border-radius: 50%;
                        cursor: pointer;
                    }
                    .volume-slider::-moz-range-thumb {
                        width: 12px;
                        height: 12px;
                        background: white;
                        border-radius: 50%;
                        cursor: pointer;
                        border: none;
                    }
                    .fullscreen-btn svg {
                        width: 20px;
                        height: 20px;
                        fill: white;
                    }
                    .video-player-container.is-fullscreen {
                        position: fixed !important;
                        top: 0 !important;
                        left: 0 !important;
                        width: 100vw !important;
                        height: 100vh !important;
                        background: #000;
                        z-index: 9999;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        padding: 0 !important;
                        margin: 0 !important;
                    }
                    .video-player-container.is-fullscreen video {
                        position: absolute;
                        top: 0;
                        left: 0;
                        width: 100% !important;
                        height: 100% !important;
                        object-fit: cover;
                        margin: 0;
                        padding: 0;
                    }

                    /* Add a class for contain mode */
                    .video-player-container.is-fullscreen.contain-mode video {
                        object-fit: contain;
                    }
                `;
                document.head.appendChild(style);
            }
        },

        createControls: function() {
            const controls = document.createElement('div');
            controls.className = 'video-controls';

            controls.innerHTML = `
                <button type="button" class="play-btn">Play</button>
                <div class="video-progress">
                    <div class="video-progress-bar"></div>
                </div>
                <div class="volume-control">
                    <button type="button" class="volume-btn">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="white">
                            <path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/>
                        </svg>
                    </button>
                    <input type="range" class="volume-slider" min="0" max="1" step="0.1" value="1">
                </div>
                <span class="video-time">0:00 / 0:00</span>
                <button type="button" class="fullscreen-btn">
                    <svg viewBox="0 0 24 24">
                        <path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/>
                    </svg>
                </button>
            `;

            this.container.appendChild(controls);

            this.playBtn = controls.querySelector('.play-btn');
            this.progress = controls.querySelector('.video-progress');
            this.progressBar = controls.querySelector('.video-progress-bar');
            this.timeDisplay = controls.querySelector('.video-time');
            this.volumeBtn = controls.querySelector('.volume-btn');
            this.volumeSlider = controls.querySelector('.volume-slider');
            this.fullscreenBtn = controls.querySelector('.fullscreen-btn');
        },

        setupEvents: function() {
            // Error handling
            this.video.addEventListener('error', () => {
                const error = this.video.error;
                let message = 'An error occurred while loading the video.';
                
                if (error) {
                    switch (error.code) {
                        case 1:
                            message = 'Video loading aborted';
                            break;
                        case 2:
                            message = 'Network error occurred while loading video';
                            break;
                        case 3:
                            message = 'Error decoding video';
                            break;
                        case 4:
                            message = 'Video not supported';
                            break;
                    }
                }
                
                this.showError(message);
            });

            this.video.addEventListener('loadeddata', () => {
                this.hideError();
            });

            if (!this.options.controls) return;

            // Thumbnail overlay click
            this.thumbnailOverlay.addEventListener('click', () => {
                this.togglePlay();
            });

            // Play/Pause
            this.playBtn.addEventListener('click', () => this.togglePlay());
            this.video.addEventListener('click', () => this.togglePlay());

            // Time update
            this.video.addEventListener('timeupdate', () => this.updateProgress());

            // Progress bar click
            this.progress.addEventListener('click', (e) => this.seek(e));

            // Volume control
            this.volumeBtn.addEventListener('click', () => this.toggleMute());
            this.volumeSlider.addEventListener('input', (e) => {
                this.video.volume = e.target.value;
                this.updateVolumeIcon();
            });

            // Fullscreen
            this.fullscreenBtn.addEventListener('click', () => this.toggleFullscreen());

            // Video events
            this.video.addEventListener('play', () => {
                this.updatePlayButton();
                this.thumbnailOverlay.classList.add('hidden');
            });
            this.video.addEventListener('pause', () => {
                this.updatePlayButton();
                this.thumbnailOverlay.classList.remove('hidden');
            });
            this.video.addEventListener('ended', () => {
                this.video.currentTime = 0;
                this.playBtn.textContent = 'Play';
                this.thumbnailOverlay.classList.remove('hidden');
            });
            this.video.addEventListener('volumechange', () => this.updateVolumeIcon());

            // Add fullscreen change event listeners
            document.addEventListener('fullscreenchange', () => this.handleFullscreenChange());
            document.addEventListener('webkitfullscreenchange', () => this.handleFullscreenChange());
            document.addEventListener('mozfullscreenchange', () => this.handleFullscreenChange());
            document.addEventListener('MSFullscreenChange', () => this.handleFullscreenChange());
        },

        togglePlay: function() {
            if (this.video.paused) {
                this.video.play().catch(error => {
                    this.showError('Failed to play video: ' + error.message);
                });
            } else {
                this.video.pause();
            }
        },

        updatePlayButton: function() {
            this.playBtn.textContent = this.video.paused ? 'Play' : 'Pause';
        },

        formatTime: function(seconds) {
            const minutes = Math.floor(seconds / 60);
            seconds = Math.floor(seconds % 60);
            return `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
        },

        updateProgress: function() {
            const percent = (this.video.currentTime / this.video.duration) * 100;
            this.progressBar.style.width = `${percent}%`;
            this.timeDisplay.textContent = `${this.formatTime(this.video.currentTime)} / ${this.formatTime(this.video.duration)}`;
        },

        seek: function(e) {
            const rect = this.progress.getBoundingClientRect();
            const pos = (e.clientX - rect.left) / rect.width;
            this.video.currentTime = pos * this.video.duration;
        },

        toggleMute: function() {
            this.video.muted = !this.video.muted;
            if (!this.video.muted && this.video.volume === 0) {
                this.video.volume = 1;
                this.volumeSlider.value = 1;
            }
            this.updateVolumeIcon();
        },

        updateVolumeIcon: function() {
            const volumeIcon = this.volumeBtn.querySelector('svg');
            if (this.video.muted || this.video.volume === 0) {
                volumeIcon.innerHTML = '<path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/>';
            } else if (this.video.volume < 0.5) {
                volumeIcon.innerHTML = '<path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02z"/>';
            } else {
                volumeIcon.innerHTML = '<path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/>';
            }
        },

        toggleFullscreen: function() {
            const elem = this.container;
            
            if (!document.fullscreenElement && 
                !document.webkitFullscreenElement && 
                !document.mozFullScreenElement && 
                !document.msFullscreenElement) {
                
                if (elem.requestFullscreen) {
                    elem.requestFullscreen();
                } else if (elem.webkitRequestFullscreen) {
                    elem.webkitRequestFullscreen();
                } else if (elem.mozRequestFullScreen) {
                    elem.mozRequestFullScreen();
                } else if (elem.msRequestFullscreen) {
                    elem.msRequestFullscreen();
                }
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
            }
        },

        handleFullscreenChange: function() {
            const isFullscreen = document.fullscreenElement || 
                document.webkitFullscreenElement || 
                document.mozFullScreenElement || 
                document.msFullscreenElement;

            this.container.classList.toggle('is-fullscreen', !!isFullscreen);
            
            // Update fullscreen button icon
            const svg = this.fullscreenBtn.querySelector('svg');
            if (isFullscreen) {
                svg.innerHTML = '<path d="M5 16h3v3h2v-5H5v2zm3-8H5v2h5V5H8v3zm6 11h2v-3h3v-2h-5v5zm2-11V5h-2v5h5V8h-3z"/>';
            } else {
                svg.innerHTML = '<path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/>';
            }
        }
    };

    // Export to window
    window.VideoPlayer = VideoPlayer;
})(window);